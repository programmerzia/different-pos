<?php

declare(strict_types=1);

use App\Actions\Sales\CollectCustomerPayment;
use App\Actions\Sales\CreateSaleReturn;
use App\Actions\Sales\Data\CustomerPaymentDraft;
use App\Actions\Sales\Data\ReturnDraft;
use App\Actions\Sales\Data\ReturnLineDraft;
use App\Actions\Sales\VoidSale;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Exceptions\Domain\InvalidSaleDocument;
use App\Exceptions\Domain\VoidWindowClosed;
use App\Models\Activity;
use App\Models\BranchStock;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\Fixtures\SalesWorld;

beforeEach(function (): void {
    // 22:00 in Dhaka = 16:00 UTC.
    $this->travelTo(now()->setTimezone('Asia/Dhaka')->setDate(2026, 11, 3)->setTime(22, 0)->utc());
    $this->world = SalesWorld::make();
    $this->shirt = $this->world->product(150_000, 60_000);
    $this->old = $this->world->stock($this->shirt, 2, 50_000, 120);
    $this->new = $this->world->stock($this->shirt, 5, 60_000, 30);
    $this->void = fn (Sale $sale, string $role = 'Manager'): Sale => app(VoidSale::class)->handle($this->world->user($role), $sale, 'wrong item');
    $this->onHand = fn (): string => (string) BranchStock::query()->where('product_id', $this->shirt->id)->first()?->qty_on_hand->toDecimalString();
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('voids a same-day sale: stock back onto its batches, payments voided, balance restored', function (): void {
    $customer = $this->world->customer();
    $sale = $this->world->sell([$this->world->line($this->shirt, 3)], [$this->world->tender('cash', 200_000)], $customer);
    expect($customer->refresh()->balance->paisa)->toBe(250_000)->and(($this->onHand)())->toBe('4.000');

    $voided = ($this->void)($sale);

    expect($voided->status)->toBe(SaleStatus::Voided)
        ->and($voided->voided_by)->toBe($this->world->user('Manager')->id)
        ->and($voided->void_reason)->toBe('wrong item')
        ->and(($this->onHand)())->toBe('7.000')
        ->and($this->old->refresh()->qty_remaining->toDecimalString())->toBe('2.000')
        ->and($this->new->refresh()->qty_remaining->toDecimalString())->toBe('5.000')
        ->and(StockMovement::query()->where('type', StockMovementType::SaleVoid)->count())->toBe(2)
        ->and(Payment::query()->sole()->voided_at)->not->toBeNull()
        ->and($customer->refresh()->balance->paisa)->toBe(0)
        ->and(Activity::query()->where('event', 'sale.voided')->count())->toBe(1);
});

it('refuses a void after the business day closed in the tenant timezone', function (): void {
    $sale = $this->world->sell([$this->world->line($this->shirt)], [$this->world->tender('cash', 150_000)]);

    // 00:30 Dhaka the next day is still 18:30 UTC the same day.
    $this->travel(150)->minutes();
    expect(fn () => ($this->void)($sale))->toThrow(VoidWindowClosed::class)
        ->and(($this->onHand)())->toBe('6.000');
});

it('allows a void late on the same business day', function (): void {
    $sale = $this->world->sell([$this->world->line($this->shirt)], [$this->world->tender('cash', 150_000)]);
    $this->travel(119)->minutes(); // 23:59 Dhaka

    expect(($this->void)($sale)->status)->toBe(SaleStatus::Voided);
});

it('needs sales.void, a completed sale and no returns', function (): void {
    $sale = $this->world->sell([$this->world->line($this->shirt, 2)], [$this->world->tender('cash', 300_000)]);

    expect(fn () => ($this->void)($sale, 'Cashier'))->toThrow(AuthorizationException::class);

    app(CreateSaleReturn::class)->handle($this->world->user(), new ReturnDraft($sale->id, [new ReturnLineDraft($sale->items[0]->id, Quantity::ofUnits(1))]));
    expect(fn () => ($this->void)($sale))->toThrow(InvalidSaleDocument::class, 'it has returns');

    $other = $this->world->sell([$this->world->line($this->shirt)], [$this->world->tender('cash', 150_000)]);
    ($this->void)($other);
    expect(fn () => ($this->void)($other))->toThrow(InvalidSaleDocument::class, 'not a completed sale');
});

it('keeps a due collection that also paid other sales as an advance', function (): void {
    $customer = $this->world->customer();
    $first = $this->world->sell([$this->world->line($this->shirt)], [], $customer);
    $second = $this->world->sell([$this->world->line($this->shirt)], [], $customer);
    $payment = app(CollectCustomerPayment::class)->handle($this->world->user(), new CustomerPaymentDraft($customer->id, Money::ofPaisa(300_000), $this->world->methods['cash']->id));
    expect($customer->refresh()->balance->paisa)->toBe(0);

    ($this->void)($second);

    expect($payment->refresh()->voided_at)->toBeNull()
        ->and(PaymentAllocation::query()->where('payment_id', $payment->id)->count())->toBe(1)
        ->and($customer->refresh()->balance->paisa)->toBe(-150_000)
        ->and($first->refresh()->due->paisa)->toBe(0);
});

it('cannot void another tenant\'s sale', function (): void {
    $foreign = app(TenantContext::class)->withoutTenancy(fn (): Sale => Sale::factory()->create(['tenant_id' => Tenant::factory()->create()->id]));

    expect(fn () => ($this->void)($foreign))->toThrow(ModelNotFoundException::class);
});
