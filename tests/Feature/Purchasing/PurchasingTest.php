<?php

declare(strict_types=1);

use App\Actions\Purchasing\CancelPurchase;
use App\Actions\Purchasing\PayVendor;
use App\Actions\Purchasing\ReceivePurchase;
use App\Enums\AllocatableType;
use App\Enums\PartyType;
use App\Enums\PaymentDirection;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Exceptions\Domain\InvalidStockDocument;
use App\Models\BranchStock;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Vendor;
use App\Support\Database\Transaction;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use App\Support\Stock\StockLedger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\Fixtures\StockWorld;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 11, 3));
    $this->world = StockWorld::make();
    $this->user = $this->world->user;
    $this->vendor = $this->world->vendor();
    $this->method = $this->world->paymentMethod();
    $this->shirt = $this->world->product();
    $this->jeans = $this->world->product();

    $this->receive = fn (array $overrides = []): Purchase => app(ReceivePurchase::class)->handle($this->user, [
        'vendor_id' => $this->vendor->id,
        'items' => [
            ['product_id' => $this->shirt->id, 'qty' => Quantity::ofUnits(10), 'unit_cost' => Money::ofPaisa(20000)],
            ['product_id' => $this->jeans->id, 'qty' => Quantity::ofUnits(5), 'unit_cost' => Money::ofPaisa(60000), 'batch_no' => 'LOT-7'],
        ],
        ...$overrides,
    ]);

    $this->vendorBalance = fn (): int => Vendor::query()->findOrFail($this->vendor->id)->balance->paisa;
    $this->onHand = fn (Product $product): ?string => BranchStock::query()->where('product_id', $product->id)->first()?->qty_on_hand->toDecimalString();
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('receives a purchase into stock and the vendor balance', function (): void {
    $purchase = ($this->receive)();
    $code = $this->world->branch->code;

    expect($purchase->number)->toBe("{$code}-PURCHASE-26000001")
        ->and($purchase->status)->toBe(PurchaseStatus::Received)
        ->and($purchase->subtotal->paisa)->toBe(500000)
        ->and($purchase->total->paisa)->toBe(500000)
        ->and($purchase->due->paisa)->toBe(500000)
        ->and($purchase->payment_status)->toBe(PaymentStatus::Unpaid)
        ->and($purchase->created_by)->toBe($this->user->id)
        ->and(($this->onHand)($this->shirt))->toBe('10.000')
        ->and(($this->onHand)($this->jeans))->toBe('5.000')
        ->and(($this->vendorBalance)())->toBe(500000);

    $items = $purchase->items()->orderBy('id')->get();
    $batches = StockBatch::query()->orderBy('id')->get();
    expect($batches)->toHaveCount(2)
        ->and($batches[0]->source_type)->toBe(StockSourceType::Purchase)
        ->and($batches[0]->source_id)->toBe($items[0]->id)
        ->and($batches[0]->unit_cost->paisa)->toBe(20000)
        ->and($batches[1]->batch_no)->toBe('LOT-7')
        ->and(StockMovement::query()->where('type', StockMovementType::Purchase)->where('reference_type', 'purchase_items')->count())->toBe(2)
        ->and(StockMovement::query()->value('user_id'))->toBe($this->user->id)
        ->and($this->shirt->refresh()->cost_price->paisa)->toBe(20000);
});

it('prorates shipping, order discount and tax into the landed batch cost', function (): void {
    $purchase = ($this->receive)([
        'items' => [
            ['product_id' => $this->shirt->id, 'qty' => Quantity::ofUnits(3), 'unit_cost' => Money::ofPaisa(10000), 'discount' => Money::ofPaisa(1000), 'tax_amount' => Money::ofPaisa(1450)],
            ['product_id' => $this->jeans->id, 'qty' => Quantity::parse('1.5'), 'unit_cost' => Money::ofPaisa(20000)],
        ],
        'shipping' => Money::ofPaisa(5000),
        'discount' => Money::ofPaisa(2000),
    ]);

    // nets 29000 + 30000 = 59000; total = 59000 − 2000 + 1450 + 5000 = 63450.
    expect($purchase->subtotal->paisa)->toBe(59000)
        ->and($purchase->tax_total->paisa)->toBe(1450)
        ->and($purchase->total->paisa)->toBe(63450);

    $items = $purchase->items()->orderBy('id')->get();
    // shipping − discount = 3000 split 29000 : 30000 → 1475 + 1525.
    expect($items->map(fn ($item): int => $item->line_total->paisa)->all())->toBe([29000 + 1450 + 1475, 30000 + 1525])
        ->and($items->sum(fn ($item): int => $item->line_total->paisa))->toBe(63450);

    $batches = StockBatch::query()->orderBy('id')->get();
    expect($batches[0]->unit_cost->paisa)->toBe(10642)   // 31925 / 3 = 10641.67
        ->and($batches[1]->unit_cost->paisa)->toBe(21017); // 31525 / 1.5 = 21016.67
});

it('records an immediate partial payment', function (): void {
    $purchase = ($this->receive)(['payment' => ['amount' => Money::ofPaisa(200000), 'payment_method_id' => $this->method->id, 'reference' => 'TRX1']]);

    $payment = Payment::query()->sole();
    expect($payment->direction)->toBe(PaymentDirection::Out)
        ->and($payment->party_type)->toBe(PartyType::Vendor)
        ->and($payment->party_id)->toBe($this->vendor->id)
        ->and($payment->amount->paisa)->toBe(200000)
        ->and($payment->reference)->toBe('TRX1')
        ->and($payment->number)->toEndWith('-PAYMENT-26000001')
        ->and($payment->allocations()->sole()->allocatable_type)->toBe(AllocatableType::Purchase)
        ->and($purchase->paid->paisa)->toBe(200000)
        ->and($purchase->due->paisa)->toBe(300000)
        ->and($purchase->payment_status)->toBe(PaymentStatus::Partial)
        ->and(($this->vendorBalance)())->toBe(300000);
});

it('rejects a zero quantity and writes nothing, not even a number', function (): void {
    expect(fn () => ($this->receive)(['items' => [
        ['product_id' => $this->shirt->id, 'qty' => Quantity::zero(), 'unit_cost' => Money::ofPaisa(100)],
    ]]))->toThrow(InvalidStockDocument::class, 'Line 1');

    expect(Purchase::query()->count())->toBe(0)
        ->and(StockBatch::query()->count())->toBe(0)
        ->and(($this->receive)()->number)->toEndWith('26000001');
});

it('rejects an empty purchase, negative amounts and a discount above the total', function (): void {
    expect(fn () => ($this->receive)(['items' => []]))->toThrow(InvalidStockDocument::class, 'no lines')
        ->and(fn () => ($this->receive)(['shipping' => Money::ofPaisa(-1)]))->toThrow(InvalidStockDocument::class, 'shipping')
        ->and(fn () => ($this->receive)(['discount' => Money::ofPaisa(500001)]))->toThrow(InvalidStockDocument::class, 'discount is larger')
        ->and(fn () => ($this->receive)(['payment' => ['amount' => Money::ofPaisa(500001), 'payment_method_id' => $this->method->id]]))
        ->toThrow(InvalidStockDocument::class, 'more than the amount due');
});

it('rejects a vendor, product or payment method of another tenant', function (): void {
    [$vendor, $product] = app(TenantContext::class)->withoutTenancy(fn (): array => [Vendor::factory()->create(), Product::factory()->create()]);

    expect(fn () => ($this->receive)(['vendor_id' => $vendor->id]))->toThrow(ModelNotFoundException::class)
        ->and(fn () => ($this->receive)(['items' => [['product_id' => $product->id, 'qty' => Quantity::ofUnits(1), 'unit_cost' => Money::zero()]]]))
        ->toThrow(ModelNotFoundException::class);

    expect(Purchase::query()->count())->toBe(0)
        ->and(app(TenantContext::class)->withoutTenancy(fn (): int => $vendor->refresh()->balance->paisa))->toBe(0);
});

it('cancels an untouched purchase and takes its stock back out', function (): void {
    $purchase = ($this->receive)();

    $cancelled = app(CancelPurchase::class)->handle($this->user, $purchase);

    expect($cancelled->status)->toBe(PurchaseStatus::Cancelled)
        ->and($cancelled->due->paisa)->toBe(0)
        ->and(($this->onHand)($this->shirt))->toBe('0.000')
        ->and(($this->onHand)($this->jeans))->toBe('0.000')
        ->and(StockBatch::query()->where('qty_remaining', '>', 0)->count())->toBe(0)
        ->and(($this->vendorBalance)())->toBe(0)
        ->and(StockMovement::query()->where('type', StockMovementType::Purchase)->where('qty', '<', 0)->count())->toBe(2);

    expect(fn () => app(CancelPurchase::class)->handle($this->user, $purchase))->toThrow(InvalidStockDocument::class, 'not in the received state');
});

it('refuses to cancel once a sale consumed part of a batch', function (): void {
    $purchase = ($this->receive)();
    Transaction::run(fn () => app(StockLedger::class)->issue(
        $this->shirt, $this->world->branch, Quantity::ofUnits(1), StockMovementType::Sale, ['type' => 'sale_items', 'id' => 1],
    ));

    expect(fn () => app(CancelPurchase::class)->handle($this->user, $purchase))->toThrow(InvalidStockDocument::class, 'already been sold');

    expect($purchase->refresh()->status)->toBe(PurchaseStatus::Received)
        ->and(($this->onHand)($this->shirt))->toBe('9.000')
        ->and(($this->onHand)($this->jeans))->toBe('5.000')
        ->and(($this->vendorBalance)())->toBe(500000);
});

it('refuses to cancel a purchase with payments', function (): void {
    $purchase = ($this->receive)(['payment' => ['amount' => Money::ofPaisa(100), 'payment_method_id' => $this->method->id]]);

    expect(fn () => app(CancelPurchase::class)->handle($this->user, $purchase))->toThrow(InvalidStockDocument::class, 'payments');
});

it('pays a vendor oldest due first and keeps the remainder as an advance', function (): void {
    $this->travelTo(now()->subDay());
    $first = ($this->receive)();
    $this->travelBack();
    $this->travelTo(now()->setDate(2026, 11, 3));
    $second = ($this->receive)(['items' => [['product_id' => $this->shirt->id, 'qty' => Quantity::ofUnits(1), 'unit_cost' => Money::ofPaisa(30000)]]]);

    $payment = app(PayVendor::class)->handle($this->user, [
        'vendor_id' => $this->vendor->id, 'amount' => Money::ofPaisa(600000), 'payment_method_id' => $this->method->id,
    ]);

    expect($payment->allocations()->orderBy('id')->get()->map(fn ($a): array => [$a->allocatable_id, $a->amount->paisa])->all())
        ->toBe([[$first->id, 500000], [$second->id, 30000]])
        ->and($first->refresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($second->refresh()->due->paisa)->toBe(0)
        ->and(($this->vendorBalance)())->toBe(530000 - 600000);
});

it('pays explicit allocations and rejects bad ones', function (): void {
    $purchase = ($this->receive)();
    $pay = fn (array $allocations, int $amount = 100000): Payment => app(PayVendor::class)->handle($this->user, [
        'vendor_id' => $this->vendor->id, 'amount' => Money::ofPaisa($amount), 'payment_method_id' => $this->method->id, 'allocations' => $allocations,
    ]);

    $pay([['purchase_id' => $purchase->id, 'amount' => Money::ofPaisa(100000)]]);
    expect($purchase->refresh()->paid->paisa)->toBe(100000)
        ->and($purchase->payment_status)->toBe(PaymentStatus::Partial)
        ->and(($this->vendorBalance)())->toBe(400000);

    $other = app(TenantContext::class)->withoutTenancy(fn (): Vendor => Vendor::factory()->create(['tenant_id' => $this->world->tenant->id]));
    $foreignPurchase = app(ReceivePurchase::class)->handle($this->user, [
        'vendor_id' => $other->id,
        'items' => [['product_id' => $this->shirt->id, 'qty' => Quantity::ofUnits(1), 'unit_cost' => Money::ofPaisa(100)]],
    ]);

    expect(fn () => $pay([['purchase_id' => $purchase->id, 'amount' => Money::ofPaisa(400001)]], 400001))->toThrow(InvalidStockDocument::class, 'more than the amount due')
        ->and(fn () => $pay([['purchase_id' => $purchase->id, 'amount' => Money::ofPaisa(2000)]], 1000))->toThrow(InvalidStockDocument::class, 'add up to more')
        ->and(fn () => $pay([['purchase_id' => $foreignPurchase->id, 'amount' => Money::ofPaisa(100)]]))->toThrow(InvalidStockDocument::class, 'another vendor')
        ->and(fn () => $pay([['purchase_id' => 999999, 'amount' => Money::ofPaisa(100)]]))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $pay([], 0))->toThrow(InvalidStockDocument::class, 'greater than zero');

    expect(Payment::query()->count())->toBe(1);
});

it('rejects paying a vendor of another tenant', function (): void {
    $foreign = app(TenantContext::class)->withoutTenancy(fn (): Vendor => Vendor::factory()->create());

    expect(fn () => app(PayVendor::class)->handle($this->user, [
        'vendor_id' => $foreign->id, 'amount' => Money::ofPaisa(100), 'payment_method_id' => $this->method->id,
    ]))->toThrow(ModelNotFoundException::class);
});
