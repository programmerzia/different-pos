<?php

declare(strict_types=1);

namespace App\Enums;

enum StockSourceType: string
{
    case Purchase = 'purchase';
    case Opening = 'opening';
    case TransferIn = 'transfer_in';
    case Return = 'return';
    case Adjustment = 'adjustment';
}
