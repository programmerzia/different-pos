<?php

declare(strict_types=1);

namespace App\WhatsApp\Contracts;

use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppResult;

/**
 * The only way application code sends a WhatsApp message (ADR 0007: provider-agnostic, swappable per
 * tenant). Adapters never throw: a failure is a WhatsAppResult. No provider HTTP API is called outside
 * an adapter in app/WhatsApp/Drivers.
 */
interface WhatsAppGateway
{
    public function send(WhatsAppMessage $message): WhatsAppResult;
}
