<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Enums\MessageChannel;
use App\Models\Message;
use App\Sms\SmsSender;
use App\WhatsApp\WhatsAppSender;

/** Routes one logged message to its channel's sender (SMS gateway or WhatsApp gateway). */
final readonly class MessageDispatcher
{
    public function __construct(private SmsSender $sms, private WhatsAppSender $whatsApp) {}

    /** @return object{ok: bool, retryable: bool} */
    public function deliver(Message $message, bool $final = true): object
    {
        return $message->channel === MessageChannel::WhatsApp
            ? $this->whatsApp->deliver($message, $final)
            : $this->sms->deliver($message, $final);
    }
}
