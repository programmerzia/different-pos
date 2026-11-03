<?php

declare(strict_types=1);

namespace App\Sms;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Jobs\Middleware\WithTenantContext;
use App\Messaging\MessageDispatcher;
use App\Models\Customer;
use App\Models\Message;
use App\Support\Phone\BdPhone;
use App\Support\Tenancy\TenantContext;
use App\WhatsApp\WhatsAppSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends one logged message (SMS, or WhatsApp through the tenant's WhatsApp gateway: the row's `channel`
 * decides). The `messages` row is written (queued) before the job is dispatched, so the
 * log shows every attempt; the job moves it to sent or failed. The body is never logged at info level.
 */
final class SendSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $messageId) {}

    /** @return list<object> */
    public function middleware(): array
    {
        return [new WithTenantContext];
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(MessageDispatcher $sender): void
    {
        $message = Message::query()->findOrFail($this->messageId);
        if ($message->status === MessageStatus::Sent || $message->status === MessageStatus::Handoff) {
            return;
        }

        $final = $this->job === null || $this->attempts() >= $this->tries;
        $result = $sender->deliver($message, $final);

        if (! $final && ! $result->ok && $result->retryable) {
            $this->release($this->backoff()[min($this->attempts() - 1, 1)]);
        }
    }

    /**
     * Writes the log row and queues the job. Returns null when the phone is not a valid BD mobile
     * number (nothing to send to).
     */
    public static function enqueue(string $event, ?string $phone, string $body, ?Customer $customer = null, ?Model $related = null, MessageChannel $channel = MessageChannel::Sms): ?Message
    {
        $message = self::log($event, $phone, $body, $customer, $related, $channel);
        if (! $message instanceof Message) {
            return null;
        }

        // Inside a transaction (FanOutSmsBroadcast) the job must not run before its row is committed.
        self::dispatch($message->id)->afterCommit();

        return $message;
    }

    /**
     * Writes the queued log row only (no job): for a synchronous delivery such as WhatsApp click-to-chat,
     * whose result (the wa.me link) the request itself needs. Null without a valid BD mobile number.
     */
    public static function log(string $event, ?string $phone, string $body, ?Customer $customer = null, ?Model $related = null, MessageChannel $channel = MessageChannel::Sms): ?Message
    {
        $e164 = BdPhone::toE164($phone);
        if ($e164 === null) {
            return null;
        }

        $tenant = app(TenantContext::class)->tenant();
        $message = new Message([
            'customer_id' => $customer?->id,
            'channel' => $channel,
            'phone' => $e164,
            'body' => $body,
            'driver' => $channel === MessageChannel::WhatsApp ? WhatsAppSettings::fromTenant($tenant)->driver : SmsSettings::fromTenant($tenant)->driver,
            'status' => MessageStatus::Queued,
            'event' => $event,
            'related_type' => $related?->getMorphClass(),
            'related_id' => $related?->getKey(),
        ]);
        $message->save();

        return $message;
    }

    /** Queues a customer statement summary (the statement screen calls this). Null without a valid phone. */
    public static function statement(Customer $customer, string $summary): ?Message
    {
        $shop = app(TenantContext::class)->tenant()->name;
        $body = Templates::render('Dear {customer}, your account statement: {summary} — {shop}', [
            'customer' => $customer->name,
            'summary' => $summary,
            'shop' => $shop,
        ]);

        return self::enqueue('statement', $customer->phone, $body, $customer, $customer);
    }
}
