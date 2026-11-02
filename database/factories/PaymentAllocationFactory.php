<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AllocatableType;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentAllocation> */
final class PaymentAllocationFactory extends Factory
{
    protected $model = PaymentAllocation::class;

    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'allocatable_type' => AllocatableType::Sale,
            'allocatable_id' => fake()->numberBetween(1, 100000),
            'amount' => Money::ofPaisa(fake()->numberBetween(1000, 100000)),
        ];
    }
}
