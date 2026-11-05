<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Exceptions\Domain\InvalidPartyData;
use App\Models\Message;
use App\Models\User;
use App\Sms\SendSms;
use App\Sms\SmsSettings;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;

/** Puts a failed message back in the queue (same row, so the log keeps one line per SMS). */
final readonly class ResendMessage
{
    public function __construct(private TenantContext $context) {}

    public function handle(User $actor, Message $message): Message
    {
        Gate::forUser($actor)->authorize('send', Message::class);

        if ($message->status !== MessageStatus::Failed) {
            throw InvalidPartyData::invalid('Only a failed message can be sent again.');
        }

        if ($message->channel === MessageChannel::Sms) {
            SmsSettings::fromTenant($this->context->tenant())->assertCanSend();
        }

        $message->status = MessageStatus::Queued;
        $message->error = null;
        $message->save();
        SendSms::dispatch($message->id);

        return $message;
    }
}
