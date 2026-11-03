<?php

declare(strict_types=1);

namespace App\Enums;

/** The delivery channel of a `messages` row (and of a composer broadcast). */
enum MessageChannel: string
{
    case Sms = 'sms';
    case WhatsApp = 'whatsapp';
}
