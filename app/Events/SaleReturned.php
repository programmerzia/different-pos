<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Goods were taken back from a sale (dispatched after the transaction; not for exchanges). */
final readonly class SaleReturned
{
    use Dispatchable;

    public function __construct(public int $returnId, public int $customerId) {}
}
