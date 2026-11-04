<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Exceptions\Domain\InvalidAdminData;
use App\Models\Courier;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;

/** Makes one active courier the tenant's default; the previous default is cleared. */
final readonly class SetDefaultCourier
{
    public function handle(User $actor, Courier $courier): Courier
    {
        return Transaction::run(function () use ($actor, $courier): Courier {
            $courier = Courier::query()->lockForUpdate()->findOrFail($courier->id);
            if (! $courier->is_active) {
                throw InvalidAdminData::invalid('An inactive courier cannot be the default.');
            }

            Courier::query()->where('is_default', true)->whereKeyNot($courier->id)->lockForUpdate()->update(['is_default' => false]);
            $courier->forceFill(['is_default' => true])->save();

            Audit::log('courier.default_set', $courier, ['name' => $courier->name], $actor);

            return $courier;
        });
    }
}
