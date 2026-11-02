<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
final class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(3, true),
            'sku' => fake()->unique()->bothify('SKU-#####'),
            'unit_id' => fn (array $attributes): int => Unit::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'sale_price' => Money::ofPaisa(fake()->numberBetween(10000, 500000)),
        ];
    }
}
