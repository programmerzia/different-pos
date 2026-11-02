<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseItem> */
final class PurchaseItemFactory extends Factory
{
    protected $model = PurchaseItem::class;

    public function definition(): array
    {
        return [
            'purchase_id' => Purchase::factory(),
            'product_id' => Product::factory(),
            'qty' => Quantity::ofUnits(fake()->numberBetween(1, 10)),
            'unit_cost' => Money::ofPaisa(fake()->numberBetween(1000, 100000)),
            'line_total' => Money::ofPaisa(fake()->numberBetween(1000, 100000)),
        ];
    }
}
