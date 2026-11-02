<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SaleItem;
use App\Models\SaleItemBatch;
use App\Models\StockBatch;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SaleItemBatch> */
final class SaleItemBatchFactory extends Factory
{
    protected $model = SaleItemBatch::class;

    public function definition(): array
    {
        return [
            'sale_item_id' => SaleItem::factory(),
            'stock_batch_id' => StockBatch::factory(),
            'qty' => Quantity::ofUnits(fake()->numberBetween(1, 10)),
            'unit_cost' => Money::ofPaisa(fake()->numberBetween(1000, 100000)),
        ];
    }
}
