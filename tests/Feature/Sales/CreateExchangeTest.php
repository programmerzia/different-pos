<?php

declare(strict_types=1);

use App\Actions\Sales\CreateExchange;
use App\Actions\Sales\Data\ExchangeDraft;
use App\Actions\Sales\Data\ExchangeResult;
use App\Actions\Sales\Data\ReturnLineDraft;
use App\Actions\Sales\Data\SaleDraft;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethodType;
use App\Enums\PaymentStatus;
use App\Enums\RefundMode;
use App\Exceptions\Domain\CreditLimitExceeded;
use App\Exceptions\Domain\InvalidSaleDocument;
use App\Exceptions\Domain\InvalidTender;
use App\Exceptions\Domain\ReturnQtyExceeded;
use App\Models\BranchStock;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Support\Quantity\Quantity;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Fixtures\SalesWorld;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 11, 3)->setTime(8, 0));
    $this->world = SalesWorld::make();
    $this->m = $this->world->product(150_000, 60_000);
    $this->l = $this->world->product(150_000, 60_000);
    $this->xl = $this->world->product(200_000, 70_000);
    $this->kids = $this->world->product(100_000, 40_000);
    foreach ([$this->m, $this->l, $this->xl, $this->kids] as $product) {
        $this->world->stock($product, 5, $product->cost_price->paisa);
    }
    $this->onHand = fn (Product $product): string => (string) BranchStock::query()->where('product_id', $product->id)->first()?->qty_on_hand->toDecimalString();
    $this->exchange = (fn (Sale $sale, Product $replacement, array $tenders = [], RefundMode $surplus = RefundMode::Refund, string $role = 'Manager', int|string $qty = 1, ?int $customerId = null): ExchangeResult => app(CreateExchange::class)->handle($this->world->user($role), new ExchangeDraft(
        $sale->id,
        [new ReturnLineDraft($sale->items[0]->id, is_int($qty) ? Quantity::ofUnits($qty) : Quantity::parse($qty))],
        new SaleDraft([$this->world->line($replacement)], $tenders, $customerId),
        $surplus,
    )));
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('swaps a size for an even price with an exchange credit tender', function (): void {
    $customer = $this->world->customer();
    $sale = $this->world->sell([$this->world->line($this->m)], [$this->world->tender('cash', 150_000)], $customer);

    $result = ($this->exchange)($sale, $this->l);

    expect($result->difference->paisa)->toBe(0)
        ->and($result->return->refund_mode)->toBe(RefundMode::Exchange)
        ->and($result->return->exchange_sale_id)->toBe($result->sale->id)
        ->and($result->sale->exchange_return_id)->toBe($result->return->id)
        ->and($result->sale->customer_id)->toBe($customer->id)
        ->and($result->sale->paid->paisa)->toBe(150_000)
        ->and($result->sale->due->paisa)->toBe(0)
        ->and($result->return->credited->paisa)->toBe(150_000)
        ->and($result->return->refunded->paisa)->toBe(0)
        ->and(($this->onHand)($this->m))->toBe('5.000')
        ->and(($this->onHand)($this->l))->toBe('4.000')
        ->and($customer->refresh()->balance->paisa)->toBe(0);

    $credit = Payment::query()->where('number', $result->sale->number.'/1')->sole();
    expect($credit->paymentMethod->type)->toBe(PaymentMethodType::ExchangeCredit)
        ->and($credit->amount->paisa)->toBe(150_000);
});

it('provisions a missing exchange credit tender instead of failing', function (): void {
    PaymentMethod::query()->where('type', PaymentMethodType::ExchangeCredit)->delete();
    $customer = $this->world->customer();
    $sale = $this->world->sell([$this->world->line($this->m)], [$this->world->tender('cash', 150_000)], $customer);

    $result = ($this->exchange)($sale, $this->l);

    $credit = Payment::query()->where('number', $result->sale->number.'/1')->sole();
    expect($credit->paymentMethod->type)->toBe(PaymentMethodType::ExchangeCredit)
        ->and($credit->paymentMethod->is_active)->toBeTrue();
});

it('takes the difference when the replacement costs more', function (): void {
    $sale = $this->world->sell([$this->world->line($this->m)], [$this->world->tender('cash', 150_000)]);

    $result = ($this->exchange)($sale, $this->xl, [$this->world->tender('cash', 100_000)]);

    expect($result->difference->paisa)->toBe(50_000)
        ->and($result->sale->paid->paisa)->toBe(200_000)
        ->and($result->sale->change_given->paisa)->toBe(50_000)
        ->and($result->sale->payment_status)->toBe(PaymentStatus::Paid)
        ->and(Payment::query()->where('number', $result->sale->number.'/2')->sole()->amount->paisa)->toBe(50_000);
});

it('refunds the surplus when the replacement costs less', function (): void {
    $sale = $this->world->sell([$this->world->line($this->m)], [$this->world->tender('cash', 150_000)]);

    $result = ($this->exchange)($sale, $this->kids);

    $refund = Payment::query()->where('direction', PaymentDirection::Out)->sole();
    expect($result->difference->paisa)->toBe(-50_000)
        ->and($result->sale->paid->paisa)->toBe(100_000)
        ->and($result->return->credited->paisa)->toBe(100_000)
        ->and($result->return->refunded->paisa)->toBe(50_000)
        ->and($refund->number)->toBe($result->return->number.'/R')
        ->and($refund->amount->paisa)->toBe(50_000);
});

it('credits the surplus to a member, never to the walk-in', function (): void {
    $customer = $this->world->customer();
    $sale = $this->world->sell([$this->world->line($this->m)], [$this->world->tender('cash', 150_000)], $customer);

    $result = ($this->exchange)($sale, $this->kids, surplus: RefundMode::Credit);

    expect($result->return->credited->paisa)->toBe(150_000)
        ->and($result->return->refunded->paisa)->toBe(0)
        ->and(Payment::query()->where('direction', PaymentDirection::Out)->count())->toBe(0)
        ->and($customer->refresh()->balance->paisa)->toBe(-50_000);

    $walkIn = $this->world->sell([$this->world->line($this->m)], [$this->world->tender('cash', 150_000)]);
    expect(fn () => ($this->exchange)($walkIn, $this->kids, surplus: RefundMode::Credit))->toThrow(InvalidTender::class, 'named customer');
});

it('lets a member put the difference on account within the rules', function (): void {
    $customer = $this->world->customer();
    $sale = $this->world->sell([$this->world->line($this->m)], [$this->world->tender('cash', 150_000)], $customer);

    $result = ($this->exchange)($sale, $this->xl);

    expect($result->sale->due->paisa)->toBe(50_000)
        ->and($customer->refresh()->balance->paisa)->toBe(50_000);
});

it('applies the return rules and needs returns.create + pos.sell', function (): void {
    $sale = $this->world->sell([$this->world->line($this->m)], [$this->world->tender('cash', 150_000)]);
    $other = $this->world->customer();

    expect(fn () => ($this->exchange)($sale, $this->l, role: 'Cashier'))->toThrow(AuthorizationException::class)
        ->and(fn () => ($this->exchange)($sale, $this->l, qty: 2))->toThrow(ReturnQtyExceeded::class)
        ->and(fn () => ($this->exchange)($sale, $this->l, customerId: $other->id))->toThrow(InvalidSaleDocument::class, 'customer of the original sale')
        ->and(fn () => ($this->exchange)($sale, $this->xl))->toThrow(CreditLimitExceeded::class, 'named customer');

    expect(SaleReturn::query()->count())->toBe(0)
        ->and(Sale::query()->count())->toBe(1)
        ->and(($this->onHand)($this->m))->toBe('4.000')
        ->and(($this->onHand)($this->l))->toBe('5.000');
});

it('exchanges the same product (a swap for a fresh piece)', function (): void {
    $sale = $this->world->sell([$this->world->line($this->m)], [$this->world->tender('cash', 150_000)]);

    $result = ($this->exchange)($sale, $this->m);

    expect($result->difference->paisa)->toBe(0)->and(($this->onHand)($this->m))->toBe('4.000');
    expect(Customer::query()->where('is_walk_in', true)->sole()->balance->paisa)->toBe(0);
});

it('clears the unpaid invoice with the surplus instead of refunding unpaid money', function (): void {
    $customer = $this->world->customer();
    $sale = $this->world->sell([$this->world->line($this->m)], [], $customer);

    $result = ($this->exchange)($sale, $this->kids);

    expect(Payment::query()->where('direction', PaymentDirection::Out)->count())->toBe(0)
        ->and($result->return->refunded->paisa)->toBe(0)
        ->and($result->return->credited->paisa)->toBe(150_000)
        ->and($sale->refresh()->due->paisa)->toBe(100_000)
        ->and($result->sale->due->paisa)->toBe(0)
        ->and($customer->refresh()->balance->paisa)->toBe(100_000);
});

it('refunds only what is left after the invoice due is cleared', function (): void {
    $customer = $this->world->customer();
    $cheap = $this->world->product(60_000, 20_000);
    $this->world->stock($cheap, 5, 20_000);
    $sale = $this->world->sell([$this->world->line($this->m)], [$this->world->tender('cash', 100_000)], $customer);

    $result = ($this->exchange)($sale, $cheap);

    expect($sale->refresh()->due->paisa)->toBe(0)
        ->and($result->return->refunded->paisa)->toBe(40_000)
        ->and($result->return->credited->paisa)->toBe(110_000)
        ->and($customer->refresh()->balance->paisa)->toBe(0);
});
