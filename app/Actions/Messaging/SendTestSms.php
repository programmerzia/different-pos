<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Enums\MessageStatus;
use App\Exceptions\Domain\InvalidPartyData;
use App\Models\Message;
use App\Models\User;
use App\Sms\SmsSender;
use App\Support\Phone\BdPhone;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;

/** Sends a test SMS right away through the configured driver (no queue) and returns the logged row. */
final readonly class SendTestSms
{
    public function __construct(private SmsSender $sender, private TenantContext $context) {}

    public function handle(User $actor, string $phone): Message
    {
        Gate::forUser($actor)->authorize('send', Message::class);

        $e164 = BdPhone::toE164($phone) ?? throw InvalidPartyData::invalid('Enter a valid Bangladeshi mobile number.');

        $message = new Message([
            'phone' => $e164,
            'body' => 'Test SMS from '.$this->context->tenant()->name.'. Your SMS settings work.',
            'driver' => 'null',
            'status' => MessageStatus::Queued,
            'event' => 'test',
        ]);
        $message->save();
        $this->sender->deliver($message);

        return $message->refresh();
    }
}
