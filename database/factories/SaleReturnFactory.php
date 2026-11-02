<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Tenant;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SaleReturn> */
final class SaleReturnFactory extends Factory
{
    protected $model = SaleReturn::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => fn (array $attributes): int => Branch::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'number' => fake()->unique()->bothify('RET-######'),
            'sale_id' => fn (array $attributes): int => Sale::factory()->create(['tenant_id' => $attributes['tenant_id'], 'branch_id' => $attributes['branch_id']])->id,
            'customer_id' => fn (array $attributes): int => Customer::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'returned_at' => now(),
            'subtotal' => Money::ofPaisa(fake()->numberBetween(1000, 100000)),
            'total' => Money::ofPaisa(fake()->numberBetween(1000, 100000)),
            'reason' => 'other',
        ];
    }
}
