<?php

declare(strict_types=1);

namespace App\Enums;

enum AdjustmentReason: string
{
    case Damage = 'damage';
    case Loss = 'loss';
    case Found = 'found';
    case Opening = 'opening';
    case Correction = 'correction';
    case Stocktake = 'stocktake';
    case ProductionUse = 'production_use';
    case Other = 'other';
}
