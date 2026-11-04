<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** A sale was voided (dispatched after the transaction). */
final readonly class SaleVoided
{
    use Dispatchable;

    public function __construct(public int $saleId) {}
}
