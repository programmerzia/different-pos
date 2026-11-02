<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Message;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Message> */
final class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'phone' => '+8801'.fake()->numerify('#########'),
            'body' => fake()->sentence(),
            'driver' => 'log',
            'event' => 'manual',
        ];
    }
}
