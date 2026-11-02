<?php

declare(strict_types=1);

namespace App\Enums;

enum CardPrintLayout: string
{
    case Cr80 = 'cr80';
    case A410 = 'a4_10';
    case Pdf = 'pdf';
}
