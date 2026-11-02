<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Purchase;
use App\Models\Tenant;
use App\Models\Vendor;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Purchase> */
final class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => fn (array $attributes): int => Branch::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'vendor_id' => fn (array $attributes): int => Vendor::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'number' => fake()->unique()->bothify('PUR-######'),
            'purchased_at' => now(),
            'subtotal' => Money::ofPaisa(100000),
            'total' => Money::ofPaisa(100000),
        ];
    }
}
