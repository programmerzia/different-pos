<?php

declare(strict_types=1);

namespace App\WhatsApp\Drivers;

use App\WhatsApp\Contracts\WhatsAppGateway;
use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppResult;
use Illuminate\Support\Facades\Log;

/** Development driver: writes the message to the application log. The text only goes in at debug level. */
final class LogWhatsAppGateway implements WhatsAppGateway
{
    public function send(WhatsAppMessage $message): WhatsAppResult
    {
        Log::info('WhatsApp (log driver)', ['to' => $message->phone, 'reference' => $message->reference, 'length' => mb_strlen($message->text)]);
        Log::debug('WhatsApp text (log driver)', ['text' => $message->text]);

        return WhatsAppResult::sent('log-'.$message->reference);
    }
}
