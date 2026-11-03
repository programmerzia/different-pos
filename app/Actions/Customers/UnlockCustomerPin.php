<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Exceptions\Domain\InvalidCustomerPin;
use App\Models\Customer;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;
use Illuminate\Support\Facades\Gate;

/** Lifts a PIN lockout before the 30 minutes pass (customers.reset_pin + a reason, audited). The PIN stays. */
final readonly class UnlockCustomerPin
{
    public function handle(User $actor, Customer $customer, string $reason): Customer
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw InvalidCustomerPin::reasonRequired();
        }

        return Transaction::run(function () use ($actor, $customer, $reason): Customer {
            $customer = Customer::query()->where('is_walk_in', false)->lockForUpdate()->findOrFail($customer->id);
            Gate::forUser($actor)->authorize('resetPin', $customer);

            $customer->forceFill(['pin_failed_attempts' => 0, 'pin_locked_until' => null])->save();

            Audit::log('pin.unlocked', $customer, ['scope' => 'customer', 'reason' => $reason], $actor);

            return $customer;
        });
    }
}
