<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Register;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Register> */
final class RegisterFactory extends Factory
{
    protected $model = Register::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => fn (array $attributes): int => Branch::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'name' => fake()->unique()->bothify('Register ##'),
            'code' => fake()->unique()->bothify('R###'),
            'number_year' => (int) now()->format('Y'),
        ];
    }
}
