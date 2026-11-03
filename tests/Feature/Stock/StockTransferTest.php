<?php

declare(strict_types=1);

use App\Actions\Purchasing\ReceivePurchase;
use App\Actions\Stock\CreateStockTransfer;
use App\Actions\Stock\ReceiveStockTransfer;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockTransferStatus;
use App\Exceptions\Domain\InsufficientStock;
use App\Exceptions\Domain\InvalidStockTransfer;
use App\Models\Branch;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\CatalogWorld;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 11, 4));
    $this->world = CatalogWorld::make();
    $this->second = app(TenantContext::class)->withoutTenancy(fn () => Branch::factory()->for($this->world->tenant)->create(['name' => 'Branch 2', 'code' => 'B2', 'is_active' => true]));
    $this->world->user->branches()->attach($this->second);
    $this->world->enter();
    $this->product = $this->world->product(['cost_price' => Money::ofPaisa(7500), 'name' => 'Panjabi']);

    $this->stock = function (int $units, int $paisa): void {
        app(ReceivePurchase::class)->handle($this->world->user, [
            'vendor_id' => $this->world->vendor()->id,
            'items' => [['product_id' => $this->product->id, 'qty' => Quantity::ofUnits($units), 'unit_cost' => Money::ofPaisa($paisa)]],
        ]);
    };
    $this->send = fn (int $units, ?int $to = null): StockTransfer => app(CreateStockTransfer::class)->handle($this->world->user, [
        'to_branch_id' => $to ?? $this->second->id,
        'items' => [['product_id' => $this->product->id, 'qty' => Quantity::ofUnits($units)]],
        'note' => 'Weekend stock replenishment',
    ]);
    $this->at = fn (Branch $branch) => app(TenantContext::class)->set($this->world->tenant, $branch);
    $this->onHand = fn (Branch $branch): string => (string) DB::table('branch_stocks')->where('branch_id', $branch->id)->where('product_id', $this->product->id)->value('qty_on_hand');
    $this->inTransit = fn (Branch $branch): ?string => DB::table('branch_stocks')->where('branch_id', $branch->id)->where('product_id', $this->product->id)->value('qty_in_transit');
});

afterEach(fn () => app(TenantContext::class)->forget());

it('dispatches FIFO out of the current branch at the weighted cost and counts it in transit at the destination', function (): void {
    ($this->stock)(2, 10000);
    ($this->stock)(2, 20000);

    $transfer = ($this->send)(3);

    expect($transfer->number)->toBe($this->world->branch->code.'-TRANSFER-26000001')
        ->and($transfer->status)->toBe(StockTransferStatus::InTransit)
        ->and($transfer->from_branch_id)->toBe($this->world->branch->id)
        ->and($transfer->to_branch_id)->toBe($this->second->id)
        ->and($transfer->sent_by)->toBe($this->world->user->id)
        ->and($transfer->items)->toHaveCount(1)
        ->and($transfer->items[0]->qty_sent->toDecimalString())->toBe('3.000')
        ->and($transfer->items[0]->qty_received)->toBeNull()
        // (2 × 100.00 + 1 × 200.00) / 3 = 133.33
        ->and($transfer->items[0]->unit_cost->paisa)->toBe(13333)
        ->and(($this->onHand)($this->world->branch))->toBe('1.000')
        ->and(($this->inTransit)($this->second))->toBe('3.000')
        ->and(($this->onHand)($this->second))->toBe('0.000');

    $out = StockMovement::query()->where('type', StockMovementType::TransferOut)->get();
    expect($out)->toHaveCount(2)
        ->and($out->pluck('qty')->map->toDecimalString()->all())->toBe(['-2.000', '-1.000'])
        ->and($out[0]->reference_type)->toBe('stock_transfer_items')
        ->and($out[0]->reference_id)->toBe($transfer->items[0]->id)
        ->and($out[0]->note)->toBe($transfer->number);
});

it('receives in full at the destination as a transfer_in batch at the travelling cost', function (): void {
    ($this->stock)(5, 12000);
    $transfer = ($this->send)(4);

    ($this->at)($this->second);
    $received = app(ReceiveStockTransfer::class)->handle($this->world->user, $transfer, ['items' => []]);

    expect($received->status)->toBe(StockTransferStatus::Received)
        ->and($received->received_by)->toBe($this->world->user->id)
        ->and($received->received_at)->not->toBeNull()
        ->and($received->items[0]->qty_received->toDecimalString())->toBe('4.000')
        ->and($received->items[0]->short_reason)->toBeNull()
        ->and(($this->onHand)($this->second))->toBe('4.000')
        ->and(($this->inTransit)($this->second))->toBe('0.000')
        ->and(($this->onHand)($this->world->branch))->toBe('1.000');

    $batch = StockBatch::query()->withoutGlobalScopes()->where('branch_id', $this->second->id)->sole();
    expect($batch->source_type)->toBe(StockSourceType::TransferIn)
        ->and($batch->source_id)->toBe($received->items[0]->id)
        ->and($batch->unit_cost->paisa)->toBe(12000)
        ->and($batch->qty_remaining->toDecimalString())->toBe('4.000')
        ->and(StockMovement::query()->where('type', StockMovementType::TransferIn)->sole()->qty->toDecimalString())->toBe('4.000');
});

it('records a short receipt with its reason', function (): void {
    ($this->stock)(5, 12000);
    $transfer = ($this->send)(4);

    ($this->at)($this->second);
    $received = app(ReceiveStockTransfer::class)->handle($this->world->user, $transfer, ['items' => [
        ['item_id' => $transfer->items[0]->id, 'qty_received' => Quantity::ofUnits(3), 'short_reason' => 'One carton damaged in transit'],
    ]]);

    expect($received->items[0]->qty_received->toDecimalString())->toBe('3.000')
        ->and($received->items[0]->short_reason)->toBe('One carton damaged in transit')
        ->and(($this->onHand)($this->second))->toBe('3.000')
        ->and(($this->inTransit)($this->second))->toBe('0.000');
});

it('refuses a short receipt without a reason, an over-receipt and an unknown line', function (): void {
    ($this->stock)(5, 12000);
    $transfer = ($this->send)(4);
    $id = $transfer->items[0]->id;
    ($this->at)($this->second);

    expect(fn () => app(ReceiveStockTransfer::class)->handle($this->world->user, $transfer, ['items' => [['item_id' => $id, 'qty_received' => Quantity::ofUnits(1)]]]))
        ->toThrow(InvalidStockTransfer::class, 'reason')
        ->and(fn () => app(ReceiveStockTransfer::class)->handle($this->world->user, $transfer, ['items' => [['item_id' => $id, 'qty_received' => Quantity::ofUnits(5)]]]))
        ->toThrow(InvalidStockTransfer::class, 'more than')
        ->and(fn () => app(ReceiveStockTransfer::class)->handle($this->world->user, $transfer, ['items' => [['item_id' => $id + 99, 'qty_received' => Quantity::ofUnits(1)]]]))
        ->toThrow(InvalidStockTransfer::class, 'does not belong')
        ->and(($this->onHand)($this->second))->toBe('0.000')
        ->and($transfer->fresh()?->status)->toBe(StockTransferStatus::InTransit);
});

it('only receives at the destination branch, and only once', function (): void {
    ($this->stock)(5, 12000);
    $transfer = ($this->send)(2);

    expect(fn () => app(ReceiveStockTransfer::class)->handle($this->world->user, $transfer, ['items' => []]))
        ->toThrow(InvalidStockTransfer::class, 'destination');

    ($this->at)($this->second);
    app(ReceiveStockTransfer::class)->handle($this->world->user, $transfer, ['items' => []]);
    expect(fn () => app(ReceiveStockTransfer::class)->handle($this->world->user, $transfer, ['items' => []]))
        ->toThrow(InvalidStockTransfer::class, 'not in transit')
        ->and(($this->onHand)($this->second))->toBe('2.000');
});

it('refuses the same branch, no lines, zero quantity, an untracked product and more than is on hand', function (): void {
    ($this->stock)(1, 12000);
    $service = $this->world->product(['track_stock' => false]);

    expect(fn () => ($this->send)(1, $this->world->branch->id))->toThrow(InvalidStockTransfer::class, 'same')
        ->and(fn () => app(CreateStockTransfer::class)->handle($this->world->user, ['to_branch_id' => $this->second->id, 'items' => []]))->toThrow(InvalidStockTransfer::class, 'no lines')
        ->and(fn () => ($this->send)(0))->toThrow(InvalidStockTransfer::class, 'greater than zero')
        ->and(fn () => app(CreateStockTransfer::class)->handle($this->world->user, ['to_branch_id' => $this->second->id, 'items' => [['product_id' => $service->id, 'qty' => Quantity::ofUnits(1)]]]))->toThrow(InvalidStockTransfer::class, 'track')
        ->and(fn () => ($this->send)(2))->toThrow(InsufficientStock::class)
        ->and(StockTransfer::query()->count())->toBe(0)
        ->and(($this->onHand)($this->world->branch))->toBe('1.000')
        ->and(($this->inTransit)($this->second))->toBeNull();
});

it('never sends to an inactive branch or to a branch of another tenant', function (): void {
    ($this->stock)(5, 12000);
    $inactive = app(TenantContext::class)->withoutTenancy(fn () => Branch::factory()->for($this->world->tenant)->create(['is_active' => false]));
    $foreign = app(TenantContext::class)->withoutTenancy(fn () => Branch::factory()->create());

    expect(fn () => ($this->send)(1, $inactive->id))->toThrow(InvalidStockTransfer::class, 'not active')
        ->and(fn () => ($this->send)(1, $foreign->id))->toThrow(ModelNotFoundException::class);
});

it('serves the pages, dispatches and receives over HTTP', function (): void {
    ($this->stock)(5, 12000);
    app(TenantContext::class)->forget();
    $main = $this->world->branch;
    $as = fn (Branch $branch) => $this->actingAs($this->world->user)->withSession(['branch_id' => $branch->id]);

    $as($main)->get('/app/transfers/create?product='.$this->product->uuid)
        ->assertInertia(fn (Assert $page) => $page->component('transfers/create')
            ->where('from.id', $main->id)->has('branches', 1)->where('branches.0.id', $this->second->id)->where('prefill.sku', $this->product->sku));

    $as($main)->post('/app/transfers', ['to_branch_id' => $this->second->id, 'note' => 'van', 'items' => [['product_id' => $this->product->id, 'qty' => 3000]]])
        ->assertRedirect();
    $transfer = StockTransfer::query()->withoutGlobalScopes()->sole();

    $as($main)->get('/app/transfers')
        ->assertInertia(fn (Assert $page) => $page->component('transfers/index')->has('rows', 1)->where('rows.0.number', $transfer->number)->where('rows.0.incoming', false)->where('incomingCount', 0));
    $as($this->second)->get('/app/transfers?filter[direction]=incoming')
        ->assertInertia(fn (Assert $page) => $page->has('rows', 1)->where('rows.0.incoming', true)->where('incomingCount', 1));
    $as($main)->get('/app/transfers?filter[direction]=incoming')->assertInertia(fn (Assert $page) => $page->has('rows', 0));

    $as($main)->get("/app/transfers/{$transfer->uuid}")
        ->assertInertia(fn (Assert $page) => $page->component('transfers/show')->where('canReceive', false)->where('items.0.qty_sent', 3000)->where('items.0.unit_cost', 12000));
    $as($main)->post("/app/transfers/{$transfer->uuid}/receive", ['items' => []])->assertForbidden();

    $as($this->second)->get("/app/transfers/{$transfer->uuid}")->assertInertia(fn (Assert $page) => $page->where('canReceive', true));
    $as($this->second)->post("/app/transfers/{$transfer->uuid}/receive", ['items' => [['item_id' => $transfer->items()->sole()->id, 'qty_received' => 2000, 'short_reason' => 'short']]])
        ->assertRedirect("/app/transfers/{$transfer->uuid}");
    expect($transfer->fresh()?->status)->toBe(StockTransferStatus::Received)
        ->and(($this->onHand)($this->second))->toBe('2.000');
    $as($this->second)->get("/app/transfers/{$transfer->uuid}")->assertInertia(fn (Assert $page) => $page->where('canReceive', false)->where('items.0.short_reason', 'short'));
});

it('isolates transfers by tenant and permission', function (): void {
    ($this->stock)(5, 12000);
    $transfer = ($this->send)(1);
    app(TenantContext::class)->forget();

    $theirs = CatalogWorld::make();
    $this->actingAs($theirs->user)->withSession(['branch_id' => $theirs->branch->id])->get("/app/transfers/{$transfer->uuid}")->assertNotFound();
    $this->actingAs($theirs->user)->withSession(['branch_id' => $theirs->branch->id])->post("/app/transfers/{$transfer->uuid}/receive", ['items' => []])->assertNotFound();
    $this->actingAs($theirs->user)->withSession(['branch_id' => $theirs->branch->id])->get('/app/transfers')->assertInertia(fn (Assert $page) => $page->has('rows', 0));

    $cashier = CatalogWorld::make(role: null);
    $this->actingAs($cashier->user)->withSession(['branch_id' => $cashier->branch->id])->get('/app/transfers')->assertForbidden();
    $this->actingAs($cashier->user)->withSession(['branch_id' => $cashier->branch->id])->get('/app/transfers/create')->assertForbidden();
    $this->actingAs($cashier->user)->withSession(['branch_id' => $cashier->branch->id])->post('/app/transfers', ['to_branch_id' => 1, 'items' => []])->assertForbidden();
});
