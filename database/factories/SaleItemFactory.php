<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SaleItem> */
final class SaleItemFactory extends Factory
{
    protected $model = SaleItem::class;

    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'product_id' => Product::factory(),
            'product_name' => fake()->words(3, true),
            'sku' => fake()->bothify('SKU-#####'),
            'qty' => Quantity::ofUnits(fake()->numberBetween(1, 10)),
            'unit_price' => Money::ofPaisa(fake()->numberBetween(1000, 100000)),
            'line_total' => Money::ofPaisa(fake()->numberBetween(1000, 100000)),
        ];
    }
}
