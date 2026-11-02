<?php

declare(strict_types=1);

namespace App\Actions\Vendors;

use App\Exceptions\Domain\InvalidPartyData;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Database\Transaction;
use App\Support\Phone\BdPhone;

/** Creates or updates a vendor. A phone, when given, is normalised to E.164 and must be unique in the tenant. */
final readonly class SaveVendor
{
    /** @param array{name?: string, company?: string|null, phone?: string|null, email?: string|null, address?: string|null, notes?: string|null} $data */
    public function handle(User $actor, array $data, ?Vendor $vendor = null): Vendor
    {
        return Transaction::run(function () use ($actor, $data, $vendor): Vendor {
            $vendor = $vendor instanceof Vendor ? Vendor::query()->whereKey($vendor->id)->lockForUpdate()->firstOrFail() : null;
            $created = ! $vendor instanceof Vendor;
            $vendor ??= new Vendor;

            $name = trim((string) ($data['name'] ?? ($created ? '' : $vendor->name)));
            if ($name === '') {
                throw InvalidPartyData::required('Name');
            }
            $attributes = ['name' => $name];

            if (array_key_exists('phone', $data)) {
                $raw = trim((string) $data['phone']);
                $phone = $raw === '' ? null : (BdPhone::toE164($raw) ?? throw InvalidPartyData::invalid('Enter a valid Bangladeshi mobile number.'));
                if ($phone !== null && Vendor::withTrashed()->where('phone', $phone)->when(! $created, fn ($q) => $q->whereKeyNot($vendor->id))->exists()) {
                    throw InvalidPartyData::invalid(sprintf('Phone %s already belongs to another vendor.', $phone));
                }
                $attributes['phone'] = $phone;
            }
            foreach (['company', 'email', 'address', 'notes'] as $key) {
                if (array_key_exists($key, $data)) {
                    $value = trim((string) $data[$key]);
                    $attributes[$key] = $value === '' ? null : $value;
                }
            }
            if (isset($attributes['email']) && filter_var($attributes['email'], FILTER_VALIDATE_EMAIL) === false) {
                throw InvalidPartyData::invalid('Enter a valid email address.');
            }

            $vendor->fill($attributes);
            if ($created) {
                $vendor->created_by = $actor->id;
            }
            $vendor->updated_by = $actor->id;
            $vendor->save();
            $vendor->refresh();

            return $vendor;
        });
    }
}
