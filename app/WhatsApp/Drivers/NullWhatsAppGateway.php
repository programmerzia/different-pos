<?php

declare(strict_types=1);

namespace App\WhatsApp\Drivers;

use App\WhatsApp\Contracts\WhatsAppGateway;
use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppResult;

/** WhatsApp is off: every send fails with a clear reason (nothing leaves the server). */
final class NullWhatsAppGateway implements WhatsAppGateway
{
    public function send(WhatsAppMessage $message): WhatsAppResult
    {
        return WhatsAppResult::failed('No WhatsApp provider is configured.');
    }
}
