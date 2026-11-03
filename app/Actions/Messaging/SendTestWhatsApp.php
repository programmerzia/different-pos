<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Exceptions\Domain\InvalidPartyData;
use App\Models\Message;
use App\Models\User;
use App\Support\Phone\BdPhone;
use App\Support\Tenancy\TenantContext;
use App\WhatsApp\WhatsAppSender;
use App\WhatsApp\WhatsAppSettings;
use Illuminate\Support\Facades\Gate;

/**
 * Sends a test WhatsApp message right away through the configured gateway (no queue) and returns the
 * logged row plus, for click-to-chat, the wa.me link to open.
 */
final readonly class SendTestWhatsApp
{
    public function __construct(private WhatsAppSender $sender, private TenantContext $context) {}

    /** @return array{message: Message, link: string|null} */
    public function handle(User $actor, string $phone): array
    {
        Gate::forUser($actor)->authorize('configure', Message::class);
        Gate::forUser($actor)->authorize('send', Message::class);

        $e164 = BdPhone::toE164($phone) ?? throw InvalidPartyData::invalid('Enter a valid Bangladeshi mobile number.');

        $message = new Message([
            'channel' => MessageChannel::WhatsApp,
            'phone' => $e164,
            'body' => 'Test WhatsApp message from '.$this->context->tenant()->name.'. Your WhatsApp settings work.',
            'driver' => WhatsAppSettings::fromTenant($this->context->tenant())->driver,
            'status' => MessageStatus::Queued,
            'event' => 'test',
        ]);
        $message->save();
        $result = $this->sender->deliver($message);

        return ['message' => $message->refresh(), 'link' => $result->link];
    }
}
