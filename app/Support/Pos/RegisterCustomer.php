<?php

declare(strict_types=1);

namespace App\Support\Pos;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Support\Customers\CustomerNumber;
use App\Support\Customers\CustomerPin;
use App\Support\Sales\CustomerAccounts;

/**
 * The customer card the register shows once a customer is attached: what pricing needs (group
 * discount), what the tender panel needs (balance, credit limit, spendable advance) and the PIN
 * state (set / pending / locked) for the member flow. Never the PIN or its hash.
 */
final readonly class RegisterCustomer
{
    public function __construct(private CustomerAccounts $accounts) {}

    /** @return array<string, mixed> */
    public function card(Customer $customer): array
    {
        $group = $customer->customer_group_id === null ? null : CustomerGroup::query()->find($customer->customer_group_id);
        $active = $group instanceof CustomerGroup && $group->is_active;
        $limit = $this->accounts->creditLimit($customer);

        return [
            'id' => $customer->id,
            'uuid' => $customer->uuid,
            'number' => $customer->customer_no === null ? null : CustomerNumber::format((string) $customer->customer_no),
            'name' => $customer->name,
            'name_bn' => $customer->name_bn,
            'shop_name' => $customer->shop_name,
            'phone' => $customer->phone,
            'group' => $group instanceof CustomerGroup ? ['name' => $group->name, 'discount_bp' => $active ? $group->discount_bp : 0] : null,
            'balance' => $customer->balance->paisa,
            'credit_limit' => $limit?->paisa,
            'advance' => $this->accounts->advance($customer)->paisa,
            'is_active' => $customer->is_active,
            // PIN state only (04_TRD §3.9 customers-min): never the hash or the digits.
            'pin_status' => CustomerPin::status($customer),
        ];
    }
}
