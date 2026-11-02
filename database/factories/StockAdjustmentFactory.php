<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AdjustmentReason;
use App\Models\Branch;
use App\Models\StockAdjustment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockAdjustment> */
final class StockAdjustmentFactory extends Factory
{
    protected $model = StockAdjustment::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => fn (array $attributes): int => Branch::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'number' => fake()->unique()->bothify('ADJ-######'),
            'reason' => AdjustmentReason::Damage,
            'adjusted_at' => now(),
        ];
    }
}
