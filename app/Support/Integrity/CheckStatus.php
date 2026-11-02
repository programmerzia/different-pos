<?php

declare(strict_types=1);

namespace App\Support\Integrity;

enum CheckStatus: string
{
    case Ok = 'ok';
    case Warn = 'warn';
    case Fail = 'fail';
}
