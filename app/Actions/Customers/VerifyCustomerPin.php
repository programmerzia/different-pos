<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Customers\CustomerPin;
use App\Support\Customers\PinVerification;
use App\Support\Database\Transaction;
use Illuminate\Support\Facades\Hash;

/**
 * Online PIN check at the register. Five wrong tries lock the PIN for 30 minutes (04_TRD §5.3); the
 * lock expiring resets the counter. The attempt itself is never audited with the PIN; only the lock is.
 * Rate limits (10/min per operator, 5/min per customer) sit on the route.
 */
final readonly class VerifyCustomerPin
{
    public function handle(User $actor, Customer $customer, string $pin): PinVerification
    {
        return Transaction::run(function () use ($actor, $customer, $pin): PinVerification {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $now = now();

            if (CustomerPin::isLocked($customer, $now)) {
                return new PinVerification(PinVerification::LOCKED, 0, $customer->pin_locked_until);
            }
            if ($customer->pin_hash === null || $customer->pin_hash === '') {
                return new PinVerification(PinVerification::NOT_SET, CustomerPin::MAX_FAILURES);
            }

            // An expired lock starts a fresh count.
            $failures = $customer->pin_locked_until === null ? $customer->pin_failed_attempts : 0;

            if (CustomerPin::wellFormed($pin) && Hash::check($pin, $customer->pin_hash)) {
                $customer->forceFill(['pin_failed_attempts' => 0, 'pin_locked_until' => null])->save();

                return new PinVerification(PinVerification::OK, CustomerPin::MAX_FAILURES);
            }

            $failures++;
            if ($failures >= CustomerPin::MAX_FAILURES) {
                $until = $now->copy()->addMinutes(CustomerPin::LOCK_MINUTES);
                $customer->forceFill(['pin_failed_attempts' => $failures, 'pin_locked_until' => $until])->save();
                Audit::log('pin.locked', $customer, ['scope' => 'customer', 'failures' => $failures], $actor);

                return new PinVerification(PinVerification::LOCKED, 0, $until);
            }

            $customer->forceFill(['pin_failed_attempts' => $failures, 'pin_locked_until' => null])->save();

            return new PinVerification(PinVerification::WRONG, CustomerPin::MAX_FAILURES - $failures);
        });
    }
}
