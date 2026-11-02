<?php

declare(strict_types=1);

namespace App\Enums;

enum SaleOrigin: string
{
    case Device = 'device';
    case Web = 'web';
    case Import = 'import';
}
