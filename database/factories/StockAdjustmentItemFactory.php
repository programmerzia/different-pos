<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockAdjustmentItem> */
final class StockAdjustmentItemFactory extends Factory
{
    protected $model = StockAdjustmentItem::class;

    public function definition(): array
    {
        return [
            'stock_adjustment_id' => StockAdjustment::factory(),
            'product_id' => Product::factory(),
            'qty' => Quantity::ofUnits(fake()->numberBetween(1, 10)),
            'unit_cost' => Money::ofPaisa(fake()->numberBetween(1000, 100000)),
        ];
    }
}
