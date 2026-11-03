<?php

declare(strict_types=1);

namespace App\WhatsApp\Drivers;

use App\WhatsApp\Contracts\WhatsAppGateway;
use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppResult;

/**
 * No account needed: builds the official click-to-chat deep link https://wa.me/<E.164 digits>?text=<url-encoded>
 * that the UI opens in a new tab, so the staff member sends the pre-filled text from their own WhatsApp.
 * Nothing is sent by the server, so the result is a `handoff`, never `sent`. One chat at a time: not for bulk.
 */
final class ClickToChatGateway implements WhatsAppGateway
{
    public const string BASE_URL = 'https://wa.me/';

    public function send(WhatsAppMessage $message): WhatsAppResult
    {
        if (! preg_match('/^[1-9][0-9]{7,14}$/', $message->digits())) {
            return WhatsAppResult::failed('The phone number is not a valid international number.');
        }

        return WhatsAppResult::handoff(self::link($message->digits(), $message->text));
    }

    public static function link(string $digits, string $text): string
    {
        return self::BASE_URL.$digits.'?text='.rawurlencode($text);
    }
}
