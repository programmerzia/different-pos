<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** A sale was committed (dispatched after the transaction). */
final readonly class SaleCompleted
{
    use Dispatchable;

    public function __construct(public int $saleId) {}
}
