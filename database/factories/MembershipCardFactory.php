<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\MembershipCard;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MembershipCard> */
final class MembershipCardFactory extends Factory
{
    protected $model = MembershipCard::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => fn (array $attributes): int => Customer::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'issue_no' => 1,
            'card_code' => 'MC'.fake()->unique()->numerify('##########'),
            'issued_at' => now(),
            'issued_by' => fn (array $attributes): int => User::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
        ];
    }
}
