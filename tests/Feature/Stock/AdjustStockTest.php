<?php

declare(strict_types=1);

use App\Actions\Stock\AdjustStock;
use App\Enums\AdjustmentReason;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Exceptions\Domain\InsufficientStock;
use App\Exceptions\Domain\InvalidStockDocument;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockBatch;
use App\Models\StockMovement;
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
    $this->product = $this->world->product(['cost_price' => Money::ofPaisa(7500)]);

    $this->adjust = fn (array $items, AdjustmentReason $reason = AdjustmentReason::Correction): StockAdjustment => app(AdjustStock::class)
        ->handle($this->world->user, ['reason' => $reason, 'items' => $items, 'note' => 'count']);

    $this->stock = fn (int $units, int $paisa, int $minutesAgo): StockBatch => Transaction::run(fn (): StockBatch => app(StockLedger::class)->receive(
        $this->product, $this->world->branch, Quantity::ofUnits($units), Money::ofPaisa($paisa), StockMovementType::Purchase, null, ['type' => 'test', 'id' => 1], now()->subMinutes($minutesAgo),
    ));

    $this->onHand = fn (): ?string => BranchStock::query()->where('product_id', $this->product->id)->first()?->qty_on_hand->toDecimalString();
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('adds found stock as an adjustment batch at the given cost', function (): void {
    $adjustment = ($this->adjust)([['product_id' => $this->product->id, 'qty' => Quantity::ofUnits(3), 'unit_cost' => Money::ofPaisa(5000)]], AdjustmentReason::Found);

    expect($adjustment->number)->toBe($this->world->branch->code.'-ADJUSTMENT-26000001')
        ->and($adjustment->reason)->toBe(AdjustmentReason::Found)
        ->and($adjustment->created_by)->toBe($this->world->user->id)
        ->and($adjustment->items)->toHaveCount(1)
        ->and($adjustment->items[0]->unit_cost->paisa)->toBe(5000)
        ->and(($this->onHand)())->toBe('3.000');

    $batch = StockBatch::query()->sole();
    $movement = StockMovement::query()->sole();
    expect($batch->source_type)->toBe(StockSourceType::Adjustment)
        ->and($batch->unit_cost->paisa)->toBe(5000)
        ->and($movement->type)->toBe(StockMovementType::AdjustmentIn)
        ->and($movement->reference_type)->toBe('stock_adjustment_items')
        ->and($movement->reference_id)->toBe($adjustment->items[0]->id)
        ->and($movement->note)->toBe('found');
});

it('defaults the cost of added stock to the product cost price', function (): void {
    $adjustment = ($this->adjust)([['product_id' => $this->product->id, 'qty' => Quantity::ofUnits(1)]]);

    expect($adjustment->items[0]->unit_cost->paisa)->toBe(7500)
        ->and(StockBatch::query()->sole()->unit_cost->paisa)->toBe(7500);
});

it('writes damaged stock off FIFO and stores the weighted cost', function (): void {
    ($this->stock)(2, 10000, 20);
    ($this->stock)(2, 20000, 10);

    $adjustment = ($this->adjust)([['product_id' => $this->product->id, 'qty' => Quantity::ofUnits(-3)]], AdjustmentReason::Damage);

    // (2 × 100.00 + 1 × 200.00) / 3 = 133.33
    expect($adjustment->items[0]->qty->toDecimalString())->toBe('-3.000')
        ->and($adjustment->items[0]->unit_cost->paisa)->toBe(13333)
        ->and(($this->onHand)())->toBe('1.000')
        ->and(StockMovement::query()->where('type', StockMovementType::AdjustmentOut)->pluck('qty')->map->toDecimalString()->all())
        ->toBe(['-2.000', '-1.000']);
});

it('writes off from a chosen batch', function (): void {
    ($this->stock)(2, 10000, 20);
    $newer = ($this->stock)(2, 20000, 10);

    $adjustment = ($this->adjust)([['product_id' => $this->product->id, 'qty' => Quantity::ofUnits(-1), 'batch_id' => $newer->id]]);

    expect($adjustment->items[0]->unit_cost->paisa)->toBe(20000)
        ->and($newer->refresh()->qty_remaining->toDecimalString())->toBe('1.000');
});

it('handles both directions in one document', function (): void {
    ($this->stock)(5, 10000, 20);
    $other = $this->world->product();

    ($this->adjust)([
        ['product_id' => $this->product->id, 'qty' => Quantity::ofUnits(-2)],
        ['product_id' => $other->id, 'qty' => Quantity::parse('0.750'), 'unit_cost' => Money::ofPaisa(100)],
    ]);

    expect(($this->onHand)())->toBe('3.000')
        ->and(BranchStock::query()->where('product_id', $other->id)->first()?->qty_on_hand->toDecimalString())->toBe('0.750');
});

it('refuses a write-off beyond stock and rolls the document back', function (): void {
    ($this->stock)(1, 10000, 20);

    expect(fn () => ($this->adjust)([['product_id' => $this->product->id, 'qty' => Quantity::ofUnits(-2)]]))->toThrow(InsufficientStock::class);

    expect(StockAdjustment::query()->count())->toBe(0)
        ->and(($this->onHand)())->toBe('1.000');
});

it('rejects empty documents, zero quantities, negative costs and services', function (): void {
    $service = $this->world->product(['track_stock' => false]);

    expect(fn () => ($this->adjust)([]))->toThrow(InvalidStockDocument::class, 'no lines')
        ->and(fn () => ($this->adjust)([['product_id' => $this->product->id, 'qty' => Quantity::zero()]]))->toThrow(InvalidStockDocument::class, 'must not be zero')
        ->and(fn () => ($this->adjust)([['product_id' => $this->product->id, 'qty' => Quantity::ofUnits(1), 'unit_cost' => Money::ofPaisa(-1)]]))->toThrow(InvalidStockDocument::class, 'negative')
        ->and(fn () => ($this->adjust)([['product_id' => $service->id, 'qty' => Quantity::ofUnits(1)]]))->toThrow(InvalidStockDocument::class, 'does not track stock');
});

it('rejects a product of another tenant', function (): void {
    $foreign = app(TenantContext::class)->withoutTenancy(fn (): Product => Product::factory()->create());

    expect(fn () => ($this->adjust)([['product_id' => $foreign->id, 'qty' => Quantity::ofUnits(1)]]))->toThrow(ModelNotFoundException::class);
});
