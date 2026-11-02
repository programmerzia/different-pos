<?php

declare(strict_types=1);

namespace App\Enums;

enum CardStatus: string
{
    case Active = 'active';
    case Lost = 'lost';
    case Replaced = 'replaced';
    case Revoked = 'revoked';
}
