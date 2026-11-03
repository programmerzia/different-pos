<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Enums\MessageChannel;
use App\Enums\SaleStatus;
use App\Exceptions\Domain\InvalidAdminData;
use App\Exceptions\Domain\SmsNotConfigured;
use App\Exceptions\Domain\WhatsAppNotConfigured;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use App\Sms\BroadcastAudience;
use App\Sms\MessageContext;
use App\Sms\SendSms;
use App\Sms\SmsSettings;
use App\Sms\Templates;
use App\Support\Tenancy\TenantContext;
use App\WhatsApp\WhatsAppSender;
use App\WhatsApp\WhatsAppSettings;
use Illuminate\Support\Facades\Gate;

/**
 * A message to a short, explicit list of customers (one debtor, the selected debtors, the customer whose
 * payment was just collected) on either channel: the dues list's "Send WhatsApp Reminder", its bulk
 * reminder, the receipt's "Send WhatsApp Thanks" and the SMS due reminder (SendDueReminder). Each
 * customer gets one logged `messages` row; {name} {due} {shop} {shop_phone} {last_invoice} (and any extra
 * values, such as {amount}) are filled per customer. Walk-in, phoneless and, for a due reminder, settled
 * customers are skipped.
 *
 * SMS and unattended WhatsApp (log, Cloud API) are queued through SendSms. WhatsApp click-to-chat has no
 * server-side delivery: the row is written as `handoff` and the wa.me link is returned for the UI to open
 * in a new tab, one customer per request (bulk is refused truthfully). Needs sms.send.
 */
final readonly class SendDirectMessage
{
    public const int MAX_RECIPIENTS = 200;

    /** @var list<string> */
    public const array EVENTS = [Templates::DUE_REMINDER, Templates::PAYMENT_RECEIVED, BroadcastAudience::EVENT];

    public function __construct(private TenantContext $context, private MessageContext $messages, private WhatsAppSender $whatsApp) {}

    /**
     * @param  iterable<Customer>  $customers
     * @param  string|null  $body  the text with placeholders; null = the channel's default for the event
     * @param  array<string, string>  $values  extra placeholder values (for example {amount} on a thank-you)
     * @return array{queued: int, skipped: int, handoff: list<array{customer: string, name: string, phone: string, url: string}>}
     */
    public function handle(User $actor, iterable $customers, MessageChannel $channel = MessageChannel::Sms, string $event = Templates::DUE_REMINDER, ?string $body = null, array $values = []): array
    {
        Gate::forUser($actor)->authorize('send', Message::class);

        if (! in_array($event, self::EVENTS, true)) {
            throw InvalidAdminData::invalid((string) __('sms.broadcast.no_target'));
        }
        $customers = is_array($customers) ? $customers : iterator_to_array($customers, false);
        if (count($customers) > self::MAX_RECIPIENTS) {
            throw InvalidAdminData::invalid((string) __('sms.direct.too_many', ['max' => self::MAX_RECIPIENTS]));
        }

        $tenant = $this->context->tenant();
        $handoff = false;
        if ($channel === MessageChannel::WhatsApp) {
            $settings = WhatsAppSettings::fromTenant($tenant);
            if (! $settings->configured()) {
                throw WhatsAppNotConfigured::make();
            }
            $handoff = ! $settings->bulk();
            if ($handoff && count($customers) > 1) {
                throw WhatsAppNotConfigured::bulk();
            }
        } elseif (SmsSettings::fromTenant($tenant)->driver === 'null') {
            throw SmsNotConfigured::make();
        }

        $body = $this->template($channel, $event, $body, $tenant);

        $queued = $skipped = 0;
        $links = [];
        foreach ($customers as $customer) {
            if (! $this->messages->reachable($customer) || ($event === Templates::DUE_REMINDER && $customer->balance->paisa <= 0)) {
                $skipped++;

                continue;
            }

            $text = Templates::render($body, $this->messages->values($customer, [...$values, 'last_invoice' => $this->lastInvoice($customer)]));
            $message = $handoff
                ? SendSms::log($event, $customer->phone, $text, $customer, $customer, $channel)
                : SendSms::enqueue($event, $customer->phone, $text, $customer, $customer, $channel);
            if (! $message instanceof Message) {
                $skipped++;

                continue;
            }

            if ($handoff) {
                $result = $this->whatsApp->deliver($message);
                if ($result->link === null) {
                    throw InvalidAdminData::invalid((string) ($result->error ?? __('sms.whatsapp.not_configured')));
                }
                $links[] = ['customer' => $customer->uuid, 'name' => $customer->name, 'phone' => $message->phone, 'url' => $result->link];
            }
            $queued++;
        }

        return ['queued' => $queued, 'skipped' => $skipped, 'handoff' => $links];
    }

    private function template(MessageChannel $channel, string $event, ?string $body, Tenant $tenant): string
    {
        $body = trim((string) $body);
        if ($body !== '') {
            if (mb_strlen($body) > BroadcastAudience::MAX_BODY) {
                throw InvalidAdminData::invalid((string) __('sms.broadcast.body_too_long', ['max' => BroadcastAudience::MAX_BODY]));
            }

            return $body;
        }

        $default = $channel === MessageChannel::WhatsApp
            ? (Templates::WHATSAPP_DEFAULTS[$event] ?? null)
            : (in_array($event, Templates::keys(), true) ? SmsSettings::fromTenant($tenant)->body($event) : null);

        return $default ?? throw InvalidAdminData::invalid((string) __('sms.broadcast.body_required'));
    }

    private function lastInvoice(Customer $customer): string
    {
        $sale = Sale::query()->where('customer_id', $customer->id)->where('status', SaleStatus::Completed)
            ->orderByDesc('sold_at')->orderByDesc('id')->first(['id', 'number']);

        return $sale instanceof Sale ? (string) $sale->number : '';
    }
}
