<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockMovement> */
final class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => fn (array $attributes): int => Branch::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'product_id' => fn (array $attributes): int => Product::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'type' => StockMovementType::Opening,
            'qty' => Quantity::ofUnits(fake()->numberBetween(1, 10)),
            'unit_cost' => Money::ofPaisa(fake()->numberBetween(1000, 100000)),
            'reference_type' => 'sale_items',
            'reference_id' => fake()->numberBetween(1, 100000),
        ];
    }
}
