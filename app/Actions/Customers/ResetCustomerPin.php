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
 * Clears a customer's PIN and lockout (customers.reset_pin + a reason, audited). With $newPin the
 * customer chose a replacement at the counter and it is set right away; without one the PIN is
 * pending until they set it again. The reason is audited; the PIN never is.
 */
final readonly class ResetCustomerPin
{
    public function handle(User $actor, Customer $customer, string $reason, ?string $newPin = null, ?string $confirmation = null): Customer
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw InvalidCustomerPin::reasonRequired();
        }
        if ($newPin !== null) {
            if (! CustomerPin::wellFormed($newPin)) {
                throw InvalidCustomerPin::format();
            }
            if (! hash_equals($newPin, (string) $confirmation)) {
                throw InvalidCustomerPin::mismatch();
            }
        }

        return Transaction::run(function () use ($actor, $customer, $reason, $newPin): Customer {
            $customer = Customer::query()->where('is_walk_in', false)->lockForUpdate()->findOrFail($customer->id);
            Gate::forUser($actor)->authorize('resetPin', $customer);

            if ($newPin !== null && CustomerPin::isWeak($newPin, $customer->phone)) {
                throw InvalidCustomerPin::weak();
            }

            $customer->forceFill([
                'pin_hash' => $newPin === null ? null : Hash::make($newPin),
                'pin_set_at' => $newPin === null ? null : now(),
                'pin_failed_attempts' => 0,
                'pin_locked_until' => null,
            ])->save();

            Audit::log('pin.reset', $customer, ['scope' => 'customer', 'reason' => $reason, 'new_pin_set' => $newPin !== null], $actor);

            return $customer;
        });
    }
}
