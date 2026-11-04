<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Exceptions\Domain\InvalidAdminData;
use App\Models\Courier;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;

/** Creates or updates a courier. Making one the default clears the flag on the tenant's others. */
final readonly class SaveCourier
{
    /** @param array{name?: string, phone?: string|null, is_default?: bool, is_active?: bool} $data */
    public function handle(User $actor, array $data, ?Courier $courier = null): Courier
    {
        return Transaction::run(function () use ($actor, $data, $courier): Courier {
            $courier = $courier instanceof Courier ? Courier::query()->lockForUpdate()->findOrFail($courier->id) : null;
            $created = ! $courier instanceof Courier;
            $courier ??= new Courier;

            $name = trim((string) ($data['name'] ?? $courier->name ?? ''));
            if ($name === '') {
                throw InvalidAdminData::required('Name');
            }
            if (Courier::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->when(! $created, fn ($q) => $q->whereKeyNot($courier->id))->exists()) {
                throw InvalidAdminData::duplicate('courier', $name);
            }

            $phone = array_key_exists('phone', $data) ? trim((string) $data['phone']) : (string) $courier->phone;
            $default = (bool) ($data['is_default'] ?? ($created ? false : $courier->is_default));
            $active = (bool) ($data['is_active'] ?? ($created ? true : $courier->is_active));
            if ($default && ! $active) {
                if (($data['is_default'] ?? false) === true) {
                    throw InvalidAdminData::invalid('An inactive courier cannot be the default.');
                }
                $default = false; // deactivating the default leaves the tenant without one
            }

            if ($default) {
                Courier::query()->where('is_default', true)->when(! $created, fn ($q) => $q->whereKeyNot($courier->id))->lockForUpdate()->update(['is_default' => false]);
            }

            $courier->fill(['name' => $name, 'phone' => $phone !== '' ? $phone : null, 'is_default' => $default, 'is_active' => $active])->save();

            Audit::log($created ? 'courier.created' : 'courier.updated', $courier, ['name' => $name, 'is_default' => $default], $actor);

            return $courier;
        });
    }
}
