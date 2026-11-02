<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\VariantOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VariantOption> */
final class VariantOptionFactory extends Factory
{
    protected $model = VariantOption::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->word(),
        ];
    }
}
