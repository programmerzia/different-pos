<?php

declare(strict_types=1);

namespace App\Support\Sales;

use App\Enums\AllocatableType;
use App\Enums\PartyType;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethodType;
use App\Enums\SaleStatus;
use App\Exceptions\Domain\InvalidSaleDocument;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\User;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonInterface;

/**
 * The customer side of sales: walk-in lookup, the party row lock, the advance pool, credit limits
 * and payment rows with their allocations.
 *
 * Account model (05_Schema §10 inv. 2 + 6): customers.balance = Σ completed sales.due − advance pool,
 * where the pool = unallocated money received + return credits − customer_credit / exchange_credit
 * payments (credit spent on sales). Every credit that settles a sale is a payment row of a credit
 * method allocated to that sale, so sales.paid is always Σ allocations of non-void payments.
 */
final readonly class CustomerAccounts
{
    public function __construct(private TenantContext $context) {}

    public function walkIn(): Customer
    {
        return Customer::query()->where('is_walk_in', true)->orderBy('id')->first()
            ?? throw InvalidSaleDocument::noWalkInCustomer();
    }

    /** Locks the customer row (party lock, after stock in the locking order). The walk-in is never locked. */
    public function lock(Customer $customer): ?Customer
    {
        if ($customer->is_walk_in) {
            return null;
        }

        return Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Credit the customer can spend: Σ open dues (all branches) − balance. Call with the row locked,
     * before the balance includes $exceptSale (the sale being settled).
     */
    public function advance(Customer $customer, ?Sale $exceptSale = null): Money
    {
        $dues = (int) Sale::query()->withoutBranchScope()
            ->where('customer_id', $customer->id)
            ->where('status', SaleStatus::Completed)
            ->when($exceptSale instanceof Sale, static fn ($query) => $query->whereKeyNot($exceptSale?->id))
            ->sum('due');

        $advance = Money::ofPaisa($dues)->minus($customer->balance);

        return $advance->isNegative() ? Money::zero() : $advance;
    }

    /** The customer's limit, else the tenant default (setting default_credit_limit, paisa); null = no limit. */
    public function creditLimit(Customer $customer): ?Money
    {
        if ($customer->credit_limit instanceof Money) {
            return $customer->credit_limit;
        }

        $default = $this->context->tenant()->setting('default_credit_limit');

        return is_int($default) ? Money::ofPaisa($default) : null;
    }

    public function systemMethod(PaymentMethodType $type): PaymentMethod
    {
        return PaymentMethod::query()->where('type', $type)->where('is_active', true)->orderBy('sort_order')->orderBy('id')->first()
            ?? throw InvalidSaleDocument::missingSystemMethod($type->value);
    }

    /** @param  list<array{AllocatableType, int, Money}>  $allocations */
    public function recordPayment(
        User $actor,
        string $number,
        PaymentDirection $direction,
        Customer $customer,
        PaymentMethod $method,
        Money $amount,
        CarbonInterface $paidAt,
        array $allocations = [],
        ?string $reference = null,
        ?int $registerId = null,
        ?string $note = null,
    ): Payment {
        $payment = Payment::query()->create([
            'number' => $number,
            'direction' => $direction,
            'party_type' => PartyType::Customer,
            'party_id' => $customer->id,
            'payment_method_id' => $method->id,
            'account_id' => $method->account_id,
            'amount' => $amount,
            'reference' => $reference,
            'register_id' => $registerId,
            'paid_at' => $paidAt,
            'received_by' => $actor->id,
            'note' => $note,
        ]);

        foreach ($allocations as [$type, $id, $share]) {
            PaymentAllocation::query()->create([
                'payment_id' => $payment->id,
                'allocatable_type' => $type,
                'allocatable_id' => $id,
                'amount' => $share,
            ]);
        }

        return $payment;
    }

    public static function isCredit(PaymentMethod $method): bool
    {
        return in_array($method->type, [PaymentMethodType::CustomerCredit, PaymentMethodType::ExchangeCredit], true);
    }
}
