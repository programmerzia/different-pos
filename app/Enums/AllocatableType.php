<?php

declare(strict_types=1);

namespace App\Enums;

enum AllocatableType: string
{
    case Sale = 'sale';
    case Purchase = 'purchase';
    case SaleReturn = 'sale_return';
}
