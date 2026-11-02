<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SaleReturnItem> */
final class SaleReturnItemFactory extends Factory
{
    protected $model = SaleReturnItem::class;

    public function definition(): array
    {
        return [
            'sale_return_id' => SaleReturn::factory(),
            'sale_item_id' => SaleItem::factory(),
            'product_id' => Product::factory(),
            'qty' => Quantity::ofUnits(fake()->numberBetween(1, 10)),
            'unit_price' => Money::ofPaisa(fake()->numberBetween(1000, 100000)),
            'line_total' => Money::ofPaisa(fake()->numberBetween(1000, 100000)),
        ];
    }
}
