<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BranchStock> */
final class BranchStockFactory extends Factory
{
    protected $model = BranchStock::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => fn (array $attributes): int => Branch::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'product_id' => fn (array $attributes): int => Product::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
        ];
    }
}
