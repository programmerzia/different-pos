<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LegacyIdMap;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LegacyIdMap> */
final class LegacyIdMapFactory extends Factory
{
    protected $model = LegacyIdMap::class;

    public function definition(): array
    {
        return [
            'entity' => 'products',
            'legacy_store_id' => 7,
            'legacy_id' => fake()->unique()->numberBetween(1, 1000000),
            'new_id' => fake()->numberBetween(1, 1000000),
        ];
    }
}
