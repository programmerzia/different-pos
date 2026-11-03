<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Enums\MessageChannel;
use App\Enums\SmsBroadcastStatus;
use App\Exceptions\Domain\InvalidAdminData;
use App\Exceptions\Domain\SmsNotConfigured;
use App\Exceptions\Domain\WhatsAppNotConfigured;
use App\Models\Message;
use App\Models\SmsBroadcast;
use App\Models\User;
use App\Sms\BroadcastAudience;
use App\Sms\FanOutSmsBroadcast;
use App\Sms\SmsSegments;
use App\Sms\SmsSettings;
use App\Sms\Templates;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use App\WhatsApp\WhatsAppSettings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;

/**
 * The messaging hub's composer (/app/messages, "Send"): one free-text SMS or WhatsApp message to one customer, a customer
 * group, every customer with a due, or every customer. Records one `sms_broadcasts` row (audience, text,
 * reachable recipient count and cost estimate) and its audit row in one transaction, then queues ONE
 * FanOutSmsBroadcast job (after commit) that writes the per-recipient `messages` rows and SendSms jobs in
 * chunks, so the request never loops over thousands of customers.
 *
 * Idempotent per tenant on the composer's key: a double submit, or a retry after a timeout, returns the
 * existing broadcast (`created` false) and sends nothing again. WhatsApp needs a gateway that can send unattended
 * (log or the Cloud API; click-to-chat opens one chat at a time, so bulk is refused) and has no per-part cost. Needs settings.manage (MessagePolicy::send).
 * The audit row records the audience and counts only: never the text or a phone number.
 */
final readonly class SendSmsBroadcast
{
    public function __construct(private TenantContext $context, private BroadcastAudience $audience) {}

    /**
     * @param  array{target: string, customer?: string|null, group_id?: int|null}  $audience  `customer` is the customer's uuid
     * @return array{broadcast: SmsBroadcast, created: bool}
     */
    public function handle(User $actor, array $audience, string $body, string $idempotencyKey, ?string $template = null, MessageChannel $channel = MessageChannel::Sms): array
    {
        Gate::forUser($actor)->authorize('send', Message::class);

        $existing = $this->existing($idempotencyKey);
        if ($existing instanceof SmsBroadcast) {
            return ['broadcast' => $existing, 'created' => false];
        }

        $body = trim($body);
        if ($body === '') {
            throw InvalidAdminData::invalid((string) __('sms.broadcast.body_required'));
        }
        if (mb_strlen($body) > BroadcastAudience::MAX_BODY) {
            throw InvalidAdminData::invalid((string) __('sms.broadcast.body_too_long', ['max' => BroadcastAudience::MAX_BODY]));
        }
        if ($template !== null && ! in_array($template, Templates::keys(), true)) {
            $template = null;
        }

        $settings = SmsSettings::fromTenant($this->context->tenant());
        if ($channel === MessageChannel::WhatsApp) {
            $whatsApp = WhatsAppSettings::fromTenant($this->context->tenant());
            if (! $whatsApp->configured()) {
                throw WhatsAppNotConfigured::make();
            }
            if (! $whatsApp->bulk()) {
                throw WhatsAppNotConfigured::bulk();
            }
        } elseif ($settings->driver === 'null') {
            throw SmsNotConfigured::make();
        }

        $audience = [
            'target' => $audience['target'],
            'customer' => $audience['target'] === 'customer' ? ($audience['customer'] ?? null) : null,
            'group_id' => $audience['target'] === 'group' ? ($audience['group_id'] ?? null) : null,
        ];
        $recipients = $this->audience->recipients($audience)->count();
        if ($recipients === 0) {
            throw InvalidAdminData::invalid((string) __('sms.ad_hoc_none'));
        }

        try {
            return Transaction::run(function () use ($actor, $audience, $body, $idempotencyKey, $template, $recipients, $settings, $channel): array {
                $broadcast = new SmsBroadcast([
                    'user_id' => $actor->id,
                    'channel' => $channel,
                    'audience' => $audience,
                    'template' => $template,
                    'body' => $body,
                    'recipient_count' => $recipients,
                    // An estimate on the unfilled text (each customer's values change the length a little).
                    'cost_paisa' => Money::ofPaisa($channel === MessageChannel::Sms ? SmsSegments::count($body) * $recipients * $settings->costPerSegment : 0),
                    'idempotency_key' => $idempotencyKey,
                    'status' => SmsBroadcastStatus::Queued,
                ]);
                $broadcast->save();

                Audit::log('sms.broadcast_queued', $broadcast, [
                    'target' => $audience['target'],
                    'channel' => $channel->value,
                    'group_id' => $audience['group_id'],
                    'recipients' => $recipients,
                    'cost_paisa' => $broadcast->cost_paisa->paisa,
                ], $actor);

                FanOutSmsBroadcast::dispatch($broadcast->id)->afterCommit();

                return ['broadcast' => $broadcast, 'created' => true];
            });
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent request with the same key won the race: return its broadcast.
            $winner = $this->existing($idempotencyKey);
            if ($winner instanceof SmsBroadcast) {
                return ['broadcast' => $winner, 'created' => false];
            }

            throw $e;
        }
    }

    private function existing(string $idempotencyKey): ?SmsBroadcast
    {
        return SmsBroadcast::query()->where('idempotency_key', $idempotencyKey)->first();
    }
}
