<?php

declare(strict_types=1);

namespace App\Actions\Sales\Data;

use App\Models\Sale;
use App\Models\SaleReturn;
use App\Support\Money\Money;

/** Both halves of an exchange. `difference` > 0: the customer paid more; < 0: the surplus refunded or credited. */
final readonly class ExchangeResult
{
    public function __construct(
        public SaleReturn $return,
        public Sale $sale,
        public Money $difference,
    ) {}
}
