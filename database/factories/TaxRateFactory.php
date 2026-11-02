<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TaxRate;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TaxRate> */
final class TaxRateFactory extends Factory
{
    protected $model = TaxRate::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->word(),
            'rate_bp' => 1500,
        ];
    }
}
