<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\Courier;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;

/** Deletes a courier. Sales keep the courier as text, so nothing breaks; deleting the default leaves no default. */
final readonly class DeleteCourier
{
    public function handle(User $actor, Courier $courier): void
    {
        Transaction::run(function () use ($actor, $courier): void {
            $courier = Courier::query()->lockForUpdate()->findOrFail($courier->id);
            Audit::log('courier.deleted', $courier, ['name' => $courier->name, 'was_default' => $courier->is_default], $actor);
            $courier->delete();
        });
    }
}
