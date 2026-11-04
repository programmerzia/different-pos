<?php

declare(strict_types=1);

namespace App\Enums;

/** A finished product is sold; a raw material (fabric, trims) is bought and stocked but never sold. */
enum ProductItemType: string
{
    case Finished = 'finished';
    case RawMaterial = 'raw_material';
}
