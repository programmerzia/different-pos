<?php

declare(strict_types=1);

namespace App\Enums;

enum SaleStatus: string
{
    case Held = 'held';
    case Completed = 'completed';
    case Voided = 'voided';
}
