<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Sale> */
final class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => fn (array $attributes): int => Branch::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'client_uuid' => fake()->uuid(),
            'customer_id' => fn (array $attributes): int => Customer::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'sold_at' => now(),
            'subtotal' => Money::ofPaisa(100000),
            'total' => Money::ofPaisa(100000),
            'sold_by' => fn (array $attributes): int => User::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
        ];
    }
}
