<?php

declare(strict_types=1);

namespace App\Actions\Sales\Data;

use App\Support\Money\Money;
use App\Support\Quantity\Quantity;

/** One cart line. A null unit price means the product's sale price; another price needs pos.price_override. */
final readonly class SaleLineDraft
{
    public Discount $discount;

    public function __construct(
        public int $productId,
        public Quantity $qty,
        public ?Money $unitPrice = null,
        ?Discount $discount = null,
    ) {
        $this->discount = $discount ?? Discount::none();
    }
}
