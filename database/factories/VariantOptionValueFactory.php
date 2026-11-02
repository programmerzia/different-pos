<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\VariantOption;
use App\Models\VariantOptionValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VariantOptionValue> */
final class VariantOptionValueFactory extends Factory
{
    protected $model = VariantOptionValue::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'variant_option_id' => fn (array $attributes): int => VariantOption::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'value' => fake()->unique()->word(),
            'code' => strtoupper(fake()->unique()->lexify('???')),
        ];
    }
}
