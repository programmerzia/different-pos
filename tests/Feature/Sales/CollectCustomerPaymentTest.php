<?php

declare(strict_types=1);

use App\Actions\Sales\CollectCustomerPayment;
use App\Actions\Sales\Data\CustomerPaymentDraft;
use App\Enums\PaymentDirection;
use App\Enums\PaymentStatus;
use App\Exceptions\Domain\InvalidSaleDocument;
use App\Exceptions\Domain\InvalidTender;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Tenant;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\Fixtures\SalesWorld;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 11, 3)->setTime(8, 0));
    $this->world = SalesWorld::make();
    $this->customer = $this->world->customer();
    $this->sales = [];
    foreach ([100_000, 200_000, 300_000] as $i => $price) {
        $product = $this->world->product($price);
        $this->world->stock($product, 1, 0);
        // Oldest first by sold_at, not by id: the first sale is back-dated the most.
        $this->sales[] = $this->world->sell([$this->world->line($product)], [], $this->customer, soldAt: now()->subDays(3 - $i));
    }
    $this->collect = fn (int $amount, ?array $allocations = null, string $method = 'cash', string $role = 'Cashier', ?Customer $customer = null): Payment => app(CollectCustomerPayment::class)->handle(
        $this->world->user($role),
        new CustomerPaymentDraft(($customer ?? $this->customer)->id, Money::ofPaisa($amount), $this->world->methods[$method]->id, $allocations, $method === 'bkash' ? 'TRX9' : null),
    );
    $this->due = fn (int $i): int => $this->sales[$i]->refresh()->due->paisa;
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('settles three invoices oldest first and keeps the rest as an advance', function (): void {
    expect($this->customer->refresh()->balance->paisa)->toBe(600_000);

    $payment = ($this->collect)(700_000);

    expect($payment->number)->toBe('DF1-PAYMENT-26000001')
        ->and($payment->direction)->toBe(PaymentDirection::In)
        ->and($payment->allocations->map(fn ($a): array => [$a->allocatable_id, $a->amount->paisa])->all())
        ->toBe([[$this->sales[0]->id, 100_000], [$this->sales[1]->id, 200_000], [$this->sales[2]->id, 300_000]])
        ->and([($this->due)(0), ($this->due)(1), ($this->due)(2)])->toBe([0, 0, 0])
        ->and($this->sales[2]->payment_status)->toBe(PaymentStatus::Paid)
        ->and($this->customer->refresh()->balance->paisa)->toBe(-100_000);
});

it('pays part of the next invoice when the money runs out', function (): void {
    ($this->collect)(250_000);

    expect([($this->due)(0), ($this->due)(1), ($this->due)(2)])->toBe([0, 50_000, 300_000])
        ->and($this->sales[1]->payment_status)->toBe(PaymentStatus::Partial)
        ->and($this->customer->refresh()->balance->paisa)->toBe(350_000);
});

it('allocates explicitly and refuses bad allocations', function (): void {
    ($this->collect)(250_000, [$this->sales[2]->id => Money::ofPaisa(200_000)], 'bkash');
    expect([($this->due)(0), ($this->due)(1), ($this->due)(2)])->toBe([100_000, 200_000, 100_000])
        ->and($this->customer->refresh()->balance->paisa)->toBe(350_000);

    $other = $this->world->customer();
    expect(fn () => ($this->collect)(100_000, [$this->sales[0]->id => Money::ofPaisa(150_000)]))->toThrow(InvalidSaleDocument::class, 'larger than the due')
        ->and(fn () => ($this->collect)(50_000, [$this->sales[0]->id => Money::ofPaisa(60_000)]))->toThrow(InvalidSaleDocument::class, 'more than the payment')
        ->and(fn () => ($this->collect)(50_000, [$this->sales[0]->id => Money::ofPaisa(50_000)], customer: $other))->toThrow(InvalidSaleDocument::class, 'another customer')
        ->and(fn () => ($this->collect)(0))->toThrow(InvalidSaleDocument::class, 'greater than zero')
        ->and(fn () => ($this->collect)(50_000, method: 'credit'))->toThrow(InvalidTender::class)
        ->and(fn () => ($this->collect)(50_000, method: 'exchange'))->toThrow(InvalidTender::class)
        ->and(fn () => ($this->collect)(50_000, customer: $this->world->walkIn))->toThrow(InvalidSaleDocument::class, 'walk-in');
});

it('needs payments.collect', function (): void {
    expect(($this->collect)(10_000, role: 'Accountant')->amount->paisa)->toBe(10_000)
        ->and(fn () => ($this->collect)(10_000, role: 'Stock Keeper'))->toThrow(AuthorizationException::class);
});

it('treats another tenant\'s customer or sale as not found', function (): void {
    [$customer, $sale] = app(TenantContext::class)->withoutTenancy(function (): array {
        $sale = Sale::factory()->create(['tenant_id' => Tenant::factory()->create()->id, 'due' => Money::ofPaisa(100_000)]);

        return [Customer::query()->findOrFail($sale->customer_id), $sale];
    });

    expect(fn () => ($this->collect)(10_000, customer: $customer))->toThrow(ModelNotFoundException::class)
        ->and(fn () => ($this->collect)(10_000, [$sale->id => Money::ofPaisa(10_000)]))->toThrow(ModelNotFoundException::class)
        ->and(Payment::query()->where('number', 'like', '%PAYMENT%')->count())->toBe(0);
});
