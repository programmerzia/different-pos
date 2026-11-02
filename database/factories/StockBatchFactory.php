<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StockSourceType;
use App\Models\Branch;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\Tenant;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockBatch> */
final class StockBatchFactory extends Factory
{
    protected $model = StockBatch::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => fn (array $attributes): int => Branch::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'product_id' => fn (array $attributes): int => Product::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'source_type' => StockSourceType::Purchase,
            'unit_cost' => Money::ofPaisa(fake()->numberBetween(1000, 100000)),
            'qty_received' => Quantity::ofUnits(fake()->numberBetween(1, 10)),
            'qty_remaining' => Quantity::ofUnits(1),
            'received_at' => now(),
        ];
    }
}
