<?php

declare(strict_types=1);

namespace App\Enums;

enum RefundMode: string
{
    case Refund = 'refund';
    case Credit = 'credit';
    case Exchange = 'exchange';
}
