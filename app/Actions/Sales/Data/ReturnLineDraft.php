<?php

declare(strict_types=1);

namespace App\Actions\Sales\Data;

use App\Support\Quantity\Quantity;

/** One returned sale line. Restock puts the goods back onto the batches they were sold from. */
final readonly class ReturnLineDraft
{
    public function __construct(
        public int $saleItemId,
        public Quantity $qty,
        public bool $restock = true,
    ) {}
}
