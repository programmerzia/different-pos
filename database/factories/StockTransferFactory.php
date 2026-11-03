<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StockTransferStatus;
use App\Models\Branch;
use App\Models\StockTransfer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockTransfer> */
final class StockTransferFactory extends Factory
{
    protected $model = StockTransfer::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'from_branch_id' => fn (array $attributes): int => Branch::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'to_branch_id' => fn (array $attributes): int => Branch::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'number' => fake()->unique()->bothify('TRANSFER-######'),
            'status' => StockTransferStatus::InTransit,
            'sent_at' => now(),
        ];
    }
}
