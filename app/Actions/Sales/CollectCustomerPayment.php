<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Actions\Sales\Data\CustomerPaymentDraft;
use App\Enums\AllocatableType;
use App\Enums\PaymentDirection;
use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Exceptions\Domain\InvalidSaleDocument;
use App\Exceptions\Domain\InvalidTender;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;
use App\Support\Money\Money;
use App\Support\Numbering\DocumentNumberer;
use App\Support\Sales\CustomerAccounts;
use App\Support\Sales\SaleGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;

/**
 * Collects money from a customer in the current branch: one `payments` row (direction in, number
 * type `payment`) allocated to the given sales, or to the oldest open dues first. What is left is an
 * advance (the balance goes below the open dues, or negative). customers.balance −= amount.
 * Dues are collected in the branch that made the sale (sales are branch-scoped). Needs payments.collect.
 * Locking order: document_sequences → customers → sales.
 */
final readonly class CollectCustomerPayment
{
    public function __construct(
        private DocumentNumberer $numberer,
        private TenantContext $context,
        private SaleGate $gate,
        private CustomerAccounts $accounts,
    ) {}

    public function handle(User $actor, CustomerPaymentDraft $draft): Payment
    {
        $this->gate->authorize($actor, 'collect', Payment::class);

        if ($draft->amount->paisa <= 0) {
            throw InvalidSaleDocument::nonPositivePayment();
        }

        return Transaction::run(function () use ($actor, $draft): Payment {
            $method = PaymentMethod::query()->findOrFail($draft->paymentMethodId);
            if (! $method->is_active) {
                throw InvalidTender::inactive($method->name);
            }
            if (CustomerAccounts::isCredit($method)) {
                throw InvalidTender::notMoney($method->name);
            }
            if ($method->requires_reference && trim((string) $draft->reference) === '') {
                throw InvalidTender::referenceRequired($method->name);
            }

            $customer = Customer::query()->findOrFail($draft->customerId);
            if ($customer->is_walk_in) {
                throw InvalidSaleDocument::walkInAccount();
            }

            $number = $this->numberer->next('payment', $this->context->branchId());
            $customer = $this->accounts->lock($customer) ?? throw InvalidSaleDocument::walkInAccount();

            $plan = $draft->allocations === null
                ? $this->planOldestFirst($customer, $draft->amount)
                : $this->planGiven($customer, $draft->amount, $draft->allocations);

            $payment = $this->accounts->recordPayment(
                $actor,
                $number,
                PaymentDirection::In,
                $customer,
                $method,
                $draft->amount,
                $draft->paidAt ?? Carbon::now(),
                array_map(static fn (array $step): array => [AllocatableType::Sale, $step[0]->id, $step[1]], $plan),
                $draft->reference,
                null,
                $draft->note,
            );

            foreach ($plan as [$sale, $share]) {
                $sale->paid = $sale->paid->plus($share);
                $sale->due = $sale->due->minus($share);
                $sale->payment_status = $sale->due->isZero() ? PaymentStatus::Paid : PaymentStatus::Partial;
                $sale->save();
            }

            $customer->balance = $customer->balance->minus($draft->amount);
            $customer->save();

            $allocated = array_reduce($plan, static fn (Money $sum, array $step): Money => $sum->plus($step[1]), Money::zero());
            Audit::log('payment.collected', $payment, [
                'number' => $number,
                'customer_id' => $customer->id,
                'amount' => $draft->amount->paisa,
                'allocated' => $allocated->paisa,
                'advance' => $draft->amount->minus($allocated)->paisa,
            ], $actor);

            return $payment->load('allocations');
        });
    }

    /** @return list<array{Sale, Money}> */
    private function planOldestFirst(Customer $customer, Money $amount): array
    {
        $sales = Sale::query()
            ->where('customer_id', $customer->id)
            ->where('status', SaleStatus::Completed)
            ->where('due', '>', 0)
            ->orderBy('sold_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $left = $amount;
        $plan = [];
        foreach ($sales as $sale) {
            if ($left->isZero()) {
                break;
            }
            $share = $sale->due->compareTo($left) < 0 ? $sale->due : $left;
            $plan[] = [$sale, $share];
            $left = $left->minus($share);
        }

        return $plan;
    }

    /**
     * @param  array<int, Money>  $allocations  sale id => amount
     * @return list<array{Sale, Money}>
     */
    private function planGiven(Customer $customer, Money $amount, array $allocations): array
    {
        $ids = array_keys($allocations);
        sort($ids);
        $sales = Sale::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        $sum = Money::zero();
        $plan = [];
        foreach ($allocations as $saleId => $share) {
            $sale = $sales->get($saleId) ?? throw (new ModelNotFoundException)->setModel(Sale::class, [$saleId]);
            $number = (string) $sale->number;
            if ($sale->customer_id !== $customer->id) {
                throw InvalidSaleDocument::wrongCustomer($number);
            }
            if ($sale->status !== SaleStatus::Completed) {
                throw InvalidSaleDocument::notCompleted($number);
            }
            if ($share->paisa <= 0) {
                throw InvalidSaleDocument::nonPositivePayment();
            }
            if ($share->compareTo($sale->due) > 0) {
                throw InvalidSaleDocument::overpaid($number);
            }
            $sum = $sum->plus($share);
            $plan[] = [$sale, $share];
        }

        if ($sum->compareTo($amount) > 0) {
            throw InvalidSaleDocument::allocationsExceedPayment();
        }

        return $plan;
    }
}
