<?php

declare(strict_types=1);

use App\Actions\Sales\CreateSaleReturn;
use App\Actions\Sales\Data\Discount;
use App\Actions\Sales\Data\ReturnDraft;
use App\Actions\Sales\Data\ReturnLineDraft;
use App\Actions\Sales\VoidSale;
use App\Enums\AllocatableType;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethodType;
use App\Enums\RefundMode;
use App\Enums\StockMovementType;
use App\Exceptions\Domain\InvalidSaleDocument;
use App\Exceptions\Domain\InvalidTender;
use App\Exceptions\Domain\ReturnQtyExceeded;
use App\Exceptions\Domain\ReturnWindowClosed;
use App\Models\BranchStock;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Sale;
use App\Models\SaleItemBatch;
use App\Models\SaleReturn;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\Fixtures\SalesWorld;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 11, 3)->setTime(8, 0));
    $this->world = SalesWorld::make();
    $this->shirt = $this->world->product(150_000, 60_000);
    $this->old = $this->world->stock($this->shirt, 2, 50_000, 120);
    $this->new = $this->world->stock($this->shirt, 5, 60_000, 30);
    $this->return = fn (Sale $sale, array $lines, RefundMode $mode = RefundMode::Refund, string $role = 'Manager', ?int $method = null): SaleReturn => app(CreateSaleReturn::class)->handle(
        $this->world->user($role),
        new ReturnDraft($sale->id, $lines, $mode, 'size', $method),
    );
    $this->line = fn (Sale $sale, int|string $units, bool $restock = true, int $index = 0): ReturnLineDraft => new ReturnLineDraft(
        $sale->items[$index]->id,
        is_int($units) ? Quantity::ofUnits($units) : Quantity::parse($units),
        $restock,
    );
    $this->onHand = fn (): string => (string) BranchStock::query()->where('product_id', $this->shirt->id)->first()?->qty_on_hand->toDecimalString();
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('returns part of a line onto its newest batch and refunds cash', function (): void {
    $sale = $this->world->sell([$this->world->line($this->shirt, 3)], [$this->world->tender('cash', 450_000)]);
    expect(($this->onHand)())->toBe('4.000');

    $return = ($this->return)($sale, [($this->line)($sale, 1)]);

    expect($return->number)->toBe('DF1-RETURN-26000001')
        ->and($return->total->paisa)->toBe(150_000)
        ->and($return->refunded->paisa)->toBe(150_000)
        ->and($return->credited->paisa)->toBe(0)
        ->and($return->items[0]->cogs_reversed->paisa)->toBe(60_000)
        ->and(($this->onHand)())->toBe('5.000')
        ->and($this->new->refresh()->qty_remaining->toDecimalString())->toBe('5.000')
        ->and($this->old->refresh()->qty_remaining->toDecimalString())->toBe('0.000')
        ->and($sale->items[0]->refresh()->qty_returned->toDecimalString())->toBe('1.000')
        ->and($sale->refresh()->returned_total->paisa)->toBe(150_000)
        ->and(StockMovement::query()->where('type', StockMovementType::SaleReturn)->where('reference_type', 'sale_return_items')->count())->toBe(1);

    $refund = Payment::query()->where('direction', PaymentDirection::Out)->sole();
    expect($refund->number)->toBe('DF1-RETURN-26000001/R')
        ->and($refund->amount->paisa)->toBe(150_000)
        ->and(PaymentAllocation::query()->where('payment_id', $refund->id)->sole()->only(['allocatable_type', 'allocatable_id']))
        ->toBe(['allocatable_type' => AllocatableType::SaleReturn, 'allocatable_id' => $return->id]);
});

it('refuses to return more than sold minus already returned', function (): void {
    $sale = $this->world->sell([$this->world->line($this->shirt, 3)], [$this->world->tender('cash', 450_000)]);
    ($this->return)($sale, [($this->line)($sale, 2)]);

    expect(fn () => ($this->return)($sale, [($this->line)($sale, 2)]))->toThrow(ReturnQtyExceeded::class, 'only 1.000 can be returned')
        ->and(fn () => ($this->return)($sale, [($this->line)($sale, '0')]))->toThrow(InvalidSaleDocument::class, 'greater than zero')
        ->and(fn () => ($this->return)($sale, [($this->line)($sale, 1), ($this->line)($sale, 1)]))->toThrow(InvalidSaleDocument::class, 'listed twice')
        ->and(fn () => ($this->return)($sale, []))->toThrow(InvalidSaleDocument::class, 'no lines');

    // The sale took 2 from the old batch and 1 from the new one; returns refill the newest first.
    ($this->return)($sale, [($this->line)($sale, 1)]);
    expect($this->new->refresh()->qty_remaining->toDecimalString())->toBe('5.000')
        ->and($this->old->refresh()->qty_remaining->toDecimalString())->toBe('2.000')
        ->and(SaleItemBatch::query()->get()->sum(fn ($l): int => $l->qty_returned->milli))->toBe(3000);
});

it('writes no stock for a line that is not restocked', function (): void {
    $sale = $this->world->sell([$this->world->line($this->shirt, 2)], [$this->world->tender('cash', 300_000)]);

    $return = ($this->return)($sale, [($this->line)($sale, 1, false)]);

    expect($return->items[0]->restock)->toBeFalse()
        ->and($return->items[0]->cogs_reversed->paisa)->toBe(0)
        ->and(($this->onHand)())->toBe('5.000')
        ->and(StockMovement::query()->where('type', StockMovementType::SaleReturn)->count())->toBe(0)
        ->and($sale->items[0]->refresh()->qty_returned->toDecimalString())->toBe('1.000');
});

it('credits the customer account instead of refunding', function (): void {
    $customer = $this->world->customer();
    $sale = $this->world->sell([$this->world->line($this->shirt, 2)], [$this->world->tender('cash', 300_000)], $customer);

    $return = ($this->return)($sale, [($this->line)($sale, 1)], RefundMode::Credit);

    expect($return->credited->paisa)->toBe(150_000)
        ->and($return->refunded->paisa)->toBe(0)
        ->and(Payment::query()->where('direction', PaymentDirection::Out)->count())->toBe(0)
        ->and($customer->refresh()->balance->paisa)->toBe(-150_000);

    $walkInSale = $this->world->sell([$this->world->line($this->shirt)], [$this->world->tender('cash', 150_000)]);
    expect(fn () => ($this->return)($walkInSale, [($this->line)($walkInSale, 1)], RefundMode::Credit))->toThrow(InvalidTender::class, 'named customer')
        ->and(fn () => ($this->return)($walkInSale, [($this->line)($walkInSale, 1)], RefundMode::Exchange))->toThrow(InvalidSaleDocument::class, 'exchange flow');
});

it('settles the open due of the sale before refunding', function (): void {
    $customer = $this->world->customer();
    $sale = $this->world->sell([$this->world->line($this->shirt, 2)], [$this->world->tender('cash', 200_000)], $customer);
    expect($sale->due->paisa)->toBe(100_000)->and($customer->refresh()->balance->paisa)->toBe(100_000);

    $return = ($this->return)($sale, [($this->line)($sale, 1)]);

    $sale->refresh();
    expect($return->credited->paisa)->toBe(100_000)
        ->and($return->refunded->paisa)->toBe(50_000)
        ->and($sale->due->paisa)->toBe(0)
        ->and($sale->paid->paisa)->toBe(300_000)
        ->and($customer->refresh()->balance->paisa)->toBe(0);

    $credit = Payment::query()->where('number', 'DF1-RETURN-26000001/C')->sole();
    expect($credit->paymentMethod->type)->toBe(PaymentMethodType::CustomerCredit)
        ->and($credit->amount->paisa)->toBe(100_000)
        ->and(Payment::query()->where('number', 'DF1-RETURN-26000001/R')->sole()->amount->paisa)->toBe(50_000);
});

it('values a return at the paid line price with the order discount prorated, exact on the last unit', function (): void {
    $sale = $this->world->sell(
        [$this->world->line($this->shirt, 3)],
        [$this->world->tender('cash', 440_000)],
        orderDiscount: Discount::amount(Money::ofPaisa(10_000)),
    );
    expect($sale->total->paisa)->toBe(440_000);

    $first = ($this->return)($sale, [($this->line)($sale, 1)]);
    $second = ($this->return)($sale, [($this->line)($sale, 1)]);
    $last = ($this->return)($sale, [($this->line)($sale, 1)]);

    expect([$first->total->paisa, $second->total->paisa, $last->total->paisa])->toBe([146_667, 146_667, 146_666]);
});

it('refuses a return after the return window or of a voided sale', function (): void {
    $sale = $this->world->sell([$this->world->line($this->shirt, 2)], [$this->world->tender('cash', 300_000)]);
    $this->world->tenant->forceFill(['settings' => [...$this->world->tenant->settings, 'return_days' => 7]])->save();

    $this->travel(7)->days();
    expect(($this->return)($sale, [($this->line)($sale, 1)])->total->paisa)->toBe(150_000);

    $this->travel(1)->days();
    expect(fn () => ($this->return)($sale, [($this->line)($sale, 1)]))->toThrow(ReturnWindowClosed::class, '7-day');

    $this->travelBack();
    $this->travelTo(now()->setDate(2026, 11, 3)->setTime(8, 0));
    $voided = $this->world->sell([$this->world->line($this->shirt)], [$this->world->tender('cash', 150_000)]);
    app(VoidSale::class)->handle($this->world->user(), $voided, 'test');
    expect(fn () => ($this->return)($voided, [($this->line)($voided, 1)]))->toThrow(InvalidSaleDocument::class, 'not a completed sale');
});

it('needs returns.create and refuses lines of another sale', function (): void {
    $sale = $this->world->sell([$this->world->line($this->shirt)], [$this->world->tender('cash', 150_000)]);
    $other = $this->world->sell([$this->world->line($this->shirt)], [$this->world->tender('cash', 150_000)]);

    expect(fn () => ($this->return)($sale, [($this->line)($sale, 1)], role: 'Cashier'))->toThrow(AuthorizationException::class)
        ->and(fn () => ($this->return)($sale, [($this->line)($other, 1)]))->toThrow(InvalidSaleDocument::class, 'does not belong');
});

it('treats another tenant\'s sale as not found', function (): void {
    $foreign = app(TenantContext::class)->withoutTenancy(fn (): Sale => Sale::factory()->create(['tenant_id' => Tenant::factory()->create()->id]));

    expect(fn () => app(CreateSaleReturn::class)->handle($this->world->user(), new ReturnDraft($foreign->id, [new ReturnLineDraft(1, Quantity::ofUnits(1))])))
        ->toThrow(ModelNotFoundException::class);
});
