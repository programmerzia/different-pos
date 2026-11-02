<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DocumentSequence;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DocumentSequence> */
final class DocumentSequenceFactory extends Factory
{
    protected $model = DocumentSequence::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'type' => fake()->unique()->word(),
        ];
    }
}
