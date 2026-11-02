<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
final class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => fn (array $attributes): int => Branch::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'number' => fake()->unique()->bothify('PAY-######'),
            'party_id' => fake()->numberBetween(1, 100000),
            'payment_method_id' => fn (array $attributes): int => PaymentMethod::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'amount' => Money::ofPaisa(50000),
            'paid_at' => now(),
            'received_by' => fn (array $attributes): int => User::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
        ];
    }
}
