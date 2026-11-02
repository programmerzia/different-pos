<?php

declare(strict_types=1);

use App\Actions\Sales\CollectCustomerPayment;
use App\Actions\Sales\CompleteSale;
use App\Actions\Sales\CreateExchange;
use App\Actions\Sales\CreateSaleReturn;
use App\Actions\Sales\Data\CustomerPaymentDraft;
use App\Actions\Sales\Data\Discount;
use App\Actions\Sales\Data\ExchangeDraft;
use App\Actions\Sales\Data\ReturnDraft;
use App\Actions\Sales\Data\ReturnLineDraft;
use App\Actions\Sales\Data\SaleDraft;
use App\Actions\Sales\VoidSale;
use App\Enums\PaymentDirection;
use App\Enums\RefundMode;
use App\Enums\SaleOrigin;
use App\Enums\SaleStatus;
use App\Exceptions\Domain\DomainException;
use App\Models\BranchStock;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use App\Support\Sales\CustomerAccounts;
use App\Support\Tenancy\TenantContext;
use Tests\Fixtures\SalesWorld;

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('keeps stock, sales, payments and customer balances consistent over 100 random operations', function (): void {
    $this->travelTo(now()->setTimezone('Asia/Dhaka')->setDate(2026, 11, 3)->setTime(9, 0)->utc());
    mt_srand(20261103);
    $world = SalesWorld::make(branchSettings: ['cash_rounding' => 100]);
    $owner = $world->user();

    $products = [];
    foreach ([[120_000, 50_000], [99_950, 40_000], [250_000, 90_000], [45_500, 20_000]] as [$price, $cost]) {
        $product = $world->product($price, $cost);
        $world->stock($product, mt_rand(5, 9), $cost - 2_000, 300);
        $world->stock($product, mt_rand(5, 9), $cost, 200);
        $world->stock($product, mt_rand(2, 5), $cost + 3_000, 100);
        $products[] = $product;
    }
    $customers = [$world->customer(), $world->customer(), $world->customer()];
    $pick = static fn (array $items): mixed => $items[mt_rand(0, count($items) - 1)];
    $done = ['sell' => 0, 'void' => 0, 'collect' => 0, 'return' => 0, 'exchange' => 0, 'refused' => 0];
    $accounts = app(CustomerAccounts::class);

    $tendersFor = function (int $toPay, ?Customer $customer) use ($world): array {
        if ($toPay <= 0) {
            return [];
        }
        if (! $customer instanceof Customer) {
            return mt_rand(0, 1) === 0
                ? [$world->tender('cash', $toPay + mt_rand(0, 3) * 10_000)]
                : [$world->tender('card', intdiv($toPay, 2)), $world->tender('cash', $toPay - intdiv($toPay, 2))];
        }

        return match (mt_rand(0, 2)) {
            0 => [],
            1 => [$world->tender('cash', intdiv($toPay, mt_rand(2, 4)))],
            default => [$world->tender('bkash', $toPay, 'TRX'.mt_rand(1000, 9999))],
        };
    };

    for ($op = 0; $op < 100; $op++) {
        $this->travel(mt_rand(1, 5))->minutes();
        $kind = mt_rand(0, 9);
        $completed = Sale::query()->where('status', SaleStatus::Completed)->with('items')->get()->all();
        $returnable = array_values(array_filter($completed, static fn (Sale $sale): bool => $sale->items->contains(static fn (SaleItem $item): bool => $item->qty_returned->compareTo($item->qty) < 0)));
        $voidable = array_values(array_filter($completed, static fn (Sale $sale): bool => $sale->returned_total->isZero() && $sale->exchange_return_id === null));

        try {
            if ($kind < 4 || $returnable === []) {
                $customer = mt_rand(0, 9) < 3 ? null : $pick($customers);
                $lines = [];
                foreach (array_slice($products, mt_rand(0, 2), mt_rand(1, 2)) as $product) {
                    $discount = mt_rand(0, 3) === 0 ? Discount::percentBp(mt_rand(1, 15) * 100) : null;
                    $lines[] = $world->line($product, mt_rand(1, 2), discount: $discount);
                }
                $order = mt_rand(0, 4) === 0 ? Discount::amount(Money::ofPaisa(mt_rand(1, 50) * 100)) : null;
                $preview = app(CompleteSale::class)->prepare($owner, new SaleDraft($lines, [], $customers[0]->id, orderDiscount: $order), SaleOrigin::Web);
                $tenders = $tendersFor($preview->total()->paisa, $customer);
                if ($customer instanceof Customer && mt_rand(0, 2) === 0) {
                    $advance = $accounts->advance($customer->refresh());
                    if ($advance->paisa > 0 && $tenders === []) {
                        $tenders = [$world->tender('credit', min($advance->paisa, $preview->total()->paisa))];
                    }
                }
                $world->sell($lines, $tenders, $customer, orderDiscount: $order);
                $done['sell']++;
            } elseif ($kind === 4 && $voidable !== []) {
                $sale = $pick($voidable);
                app(VoidSale::class)->handle($owner, $sale, 'random');
                $done['void']++;
            } elseif ($kind === 5) {
                app(CollectCustomerPayment::class)->handle($owner, new CustomerPaymentDraft($pick($customers)->id, Money::ofPaisa(mt_rand(1, 40) * 10_000), $world->methods['cash']->id));
                $done['collect']++;
            } elseif ($kind < 8) {
                $sale = $pick($returnable);
                $item = $pick($sale->items->filter(static fn (SaleItem $i): bool => $i->qty_returned->compareTo($i->qty) < 0)->values()->all());
                $left = $item->qty->minus($item->qty_returned);
                $qty = Quantity::ofMilli(max(1000, intdiv($left->milli, 1000) * 1000 - mt_rand(0, 1) * 1000));
                $mode = mt_rand(0, 1) === 0 ? RefundMode::Refund : RefundMode::Credit;
                app(CreateSaleReturn::class)->handle($owner, new ReturnDraft($sale->id, [new ReturnLineDraft($item->id, $qty, mt_rand(0, 3) > 0)], $mode));
                $done['return']++;
            } else {
                $sale = $pick($returnable);
                $item = $pick($sale->items->filter(static fn (SaleItem $i): bool => $i->qty_returned->compareTo($i->qty) < 0)->values()->all());
                $customer = Customer::query()->findOrFail($sale->customer_id);
                $replacement = new SaleDraft([$world->line($pick($products))], [], $customer->is_walk_in ? null : $customer->id);
                $quote = app(CompleteSale::class)->prepare($owner, new SaleDraft($replacement->lines, [], $customers[0]->id), SaleOrigin::Web);
                $tenders = $customer->is_walk_in ? [$world->tender('cash', $quote->total()->paisa)] : [];
                app(CreateExchange::class)->handle($owner, new ExchangeDraft(
                    $sale->id,
                    [new ReturnLineDraft($item->id, Quantity::ofUnits(1))],
                    new SaleDraft($replacement->lines, $tenders, $replacement->customerId),
                    $customer->is_walk_in || mt_rand(0, 1) === 0 ? RefundMode::Refund : RefundMode::Credit,
                ));
                $done['exchange']++;
            }
        } catch (DomainException) {
            $done['refused']++;
        }
    }

    expect($done['sell'])->toBeGreaterThan(25)
        ->and($done['void'])->toBeGreaterThan(3)
        ->and($done['return'])->toBeGreaterThan(10)
        ->and($done['exchange'])->toBeGreaterThan(5)
        ->and($done['collect'])->toBeGreaterThan(5)
        ->and($done['refused'])->toBeLessThan(15);

    // Stock: on hand = Σ batches remaining = Σ movements (no backorders online).
    foreach ($products as $product) {
        $onHand = BranchStock::query()->where('product_id', $product->id)->sole()->qty_on_hand->milli;
        $batches = StockBatch::query()->where('product_id', $product->id)->get()->sum(fn (StockBatch $b): int => $b->qty_remaining->milli);
        $movements = StockMovement::query()->where('product_id', $product->id)->get()->sum(fn (StockMovement $m): int => $m->qty->milli);
        expect($batches)->toBe($onHand)->and($movements)->toBe($onHand);
    }

    // Sales: totals, payments and COGS.
    $livePayments = Payment::query()->whereNull('voided_at')->pluck('id')->all();
    foreach (Sale::query()->where('status', SaleStatus::Completed)->with('items.batches')->get() as $sale) {
        $lines = $sale->items->sum(fn (SaleItem $i): int => $i->line_total->paisa);
        $allocated = (int) PaymentAllocation::query()->where('allocatable_type', 'sale')->where('allocatable_id', $sale->id)->whereIn('payment_id', $livePayments)->sum('amount');
        expect($sale->total->paisa)->toBe($lines - $sale->order_discount->paisa + $sale->rounding->paisa)
            ->and($sale->paid->paisa)->toBe($allocated)
            ->and($sale->due->paisa)->toBe($sale->total->paisa - $sale->paid->paisa)
            ->and($sale->due->paisa)->toBeGreaterThanOrEqual(0)
            ->and($sale->cogs_total->paisa)->toBe($sale->items->sum(fn (SaleItem $i): int => $i->cogs->paisa));
        foreach ($sale->items as $item) {
            expect($item->cogs->paisa)->toBe($item->batches->sum(fn ($l): int => $l->unit_cost->times($l->qty)->paisa))
                ->and($item->qty_returned->compareTo($item->qty))->toBeLessThanOrEqual(0);
        }
    }

    foreach (SaleReturn::query()->get() as $return) {
        expect($return->refunded->plus($return->credited)->paisa)->toBe($return->total->paisa);
    }

    // Customers: balance = Σ open dues − advance pool, and the ledger form.
    foreach ($customers as $customer) {
        $customer->refresh();
        $dues = (int) Sale::query()->where('customer_id', $customer->id)->where('status', SaleStatus::Completed)->sum('due');
        $payments = Payment::query()->where('party_id', $customer->id)->whereNull('voided_at')->with('paymentMethod', 'allocations')->get();
        $unallocated = 0;
        $creditSpent = 0;
        $moneyIn = 0;
        $refunds = 0;
        foreach ($payments as $payment) {
            if ($payment->direction === PaymentDirection::Out) {
                $refunds += $payment->amount->paisa;
            } elseif (CustomerAccounts::isCredit($payment->paymentMethod)) {
                $creditSpent += $payment->amount->paisa;
            } else {
                $moneyIn += $payment->amount->paisa;
                $unallocated += $payment->amount->paisa - $payment->allocations->sum(fn ($a): int => $a->amount->paisa);
            }
        }
        $returns = SaleReturn::query()->where('customer_id', $customer->id)->get();
        $credited = $returns->sum(fn ($r): int => $r->credited->paisa);
        $pool = $unallocated + $credited - $creditSpent;
        $soldTotal = (int) Sale::query()->where('customer_id', $customer->id)->where('status', SaleStatus::Completed)->sum('total');
        $returnedTotal = $returns->sum(fn ($r): int => $r->total->paisa);

        expect($customer->balance->paisa)->toBe($dues - $pool)
            ->and($pool)->toBeGreaterThanOrEqual(0)
            ->and($customer->balance->paisa)->toBe($soldTotal - $returnedTotal + $refunds - $moneyIn);
    }

    expect(Customer::query()->where('is_walk_in', true)->sole()->balance->paisa)->toBe(0);
});
