<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductType: string
{
    case Standard = 'standard';
    case Service = 'service';
}
