<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Exceptions\Domain\InvalidCustomerPin;
use App\Models\Customer;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Customers\CustomerPin;
use App\Support\Database\Transaction;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

/**
 * The customer chooses their membership PIN (twice, 4–6 digits, not on the weak-PIN denylist and not
 * the tail of their phone number). Staff-assisted from the profile (customers.update) or at the
 * register for a first PIN (pos.sell; an existing PIN can only be replaced by ResetCustomerPin).
 * The PIN is hashed (argon2id) and never logged: the audit row only says a PIN was set.
 */
final readonly class SetCustomerPin
{
    public function handle(User $actor, Customer $customer, string $pin, string $confirmation, bool $firstPinOnly = false): Customer
    {
        if (! CustomerPin::wellFormed($pin)) {
            throw InvalidCustomerPin::format();
        }
        if (! hash_equals($pin, $confirmation)) {
            throw InvalidCustomerPin::mismatch();
        }

        return Transaction::run(function () use ($actor, $customer, $pin, $firstPinOnly): Customer {
            $customer = Customer::query()->where('is_walk_in', false)->lockForUpdate()->findOrFail($customer->id);

            if ($firstPinOnly) {
                // Register path: a cashier may help a member choose their first PIN, never overwrite one.
                Gate::forUser($actor)->authorize('pos.sell');
                if ($customer->pin_hash !== null && ! $actor->can('update', $customer)) {
                    throw InvalidCustomerPin::alreadySet();
                }
            } else {
                Gate::forUser($actor)->authorize('update', $customer);
            }

            if (CustomerPin::isWeak($pin, $customer->phone)) {
                throw InvalidCustomerPin::weak();
            }

            $customer->forceFill([
                'pin_hash' => Hash::make($pin),
                'pin_set_at' => now(),
                'pin_failed_attempts' => 0,
                'pin_locked_until' => null,
            ])->save();

            Audit::log('pin.set', $customer, ['scope' => 'customer'], $actor);

            return $customer;
        });
    }
}
