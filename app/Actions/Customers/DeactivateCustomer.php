<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Exceptions\Domain\InvalidPartyData;
use App\Models\Customer;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;

/** Deactivates a customer (history stays). The walk-in customer is protected. */
final readonly class DeactivateCustomer
{
    public function handle(User $actor, Customer $customer): Customer
    {
        return Transaction::run(function () use ($actor, $customer): Customer {
            $customer = Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            if ($customer->is_walk_in) {
                throw InvalidPartyData::invalid('The walk-in customer cannot be deactivated.');
            }

            if ($customer->is_active) {
                $customer->is_active = false;
                $customer->updated_by = $actor->id;
                $customer->save();
                Audit::log('customer.deactivated', $customer, [], $actor);
            }

            return $customer;
        });
    }
}
