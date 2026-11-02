<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProductFamily;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductFamily> */
final class ProductFamilyFactory extends Factory
{
    protected $model = ProductFamily::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'code' => fake()->unique()->bothify('FAM-####'),
            'name' => fake()->words(3, true),
            'unit_id' => fn (array $attributes): int => Unit::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
        ];
    }
}
