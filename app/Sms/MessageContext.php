<?php

declare(strict_types=1);

namespace App\Sms;

use App\Models\Customer;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;

/** Shared facts for rendering a template: shop name, customer, and the customer's open balance. */
final readonly class MessageContext
{
    public function __construct(private TenantContext $context) {}

    /** True when the customer can receive an SMS about their account at all. */
    public function reachable(Customer $customer): bool
    {
        return ! $customer->is_walk_in && $customer->phone !== null && $customer->phone !== '';
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    public function values(Customer $customer, array $extra = []): array
    {
        $balance = $customer->balance;
        $due = $balance->isNegative() ? Money::zero() : $balance;

        return [
            'customer' => $customer->name,
            'shop' => $this->context->tenant()->name,
            'due' => $due->format(symbol: false),
            'balance' => $balance->format(symbol: false),
            ...$extra,
        ];
    }
}
