<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Exceptions\Domain\InsufficientStock;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Support\Database\Transaction;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use App\Support\Stock\StockLedger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\StockWorld;

beforeEach(function (): void {
    $this->world = StockWorld::make();
    $this->branch = $this->world->branch;
    $this->ledger = app(StockLedger::class);
    $this->product = $this->world->product(['cost_price' => Money::ofPaisa(9900)]);
    $this->ref = ['type' => 'test', 'id' => 1];

    /** Receives $units at $paisa each, received $minutesAgo minutes ago, in its own transaction. */
    $this->receive = fn (int|string $units, int $paisa, int $minutesAgo = 0, ?Product $product = null): StockBatch => Transaction::run(
        fn (): StockBatch => $this->ledger->receive(
            $product ?? $this->product,
            $this->branch,
            is_int($units) ? Quantity::ofUnits($units) : Quantity::parse($units),
            Money::ofPaisa($paisa),
            StockMovementType::Purchase,
            null,
            $this->ref,
            now()->subMinutes($minutesAgo),
        ),
    );

    $this->issue = fn (int|string $units, ?StockBatch $batch = null, ?bool $allowNegative = null, ?Product $product = null): array => Transaction::run(
        fn (): array => $this->ledger->issue(
            $product ?? $this->product,
            $this->branch,
            is_int($units) ? Quantity::ofUnits($units) : Quantity::parse($units),
            StockMovementType::Sale,
            $this->ref,
            $batch,
            $allowNegative,
        ),
    );

    $this->onHand = fn (?Product $product = null): string => (string) BranchStock::query()
        ->where('product_id', ($product ?? $this->product)->id)->first()?->qty_on_hand->toDecimalString();

    $this->remaining = fn (StockBatch $batch): string => $batch->refresh()->qty_remaining->toDecimalString();

    /** @return list<array{batch: int|null, qty: string, cost: int, backorder: bool}> */
    $this->summary = fn (array $allocations): array => array_map(static fn (array $a): array => [
        'batch' => $a['batch']?->id, 'qty' => $a['qty']->toDecimalString(), 'cost' => $a['unit_cost']->paisa, 'backorder' => $a['is_backorder'],
    ], $allocations);
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('receives a batch, writes a movement and creates the branch stock row', function (): void {
    $batch = ($this->receive)('2.500', 12050);

    expect($batch->source_type)->toBe(StockSourceType::Purchase)
        ->and($batch->source_id)->toBe(1)
        ->and($batch->qty_received->toDecimalString())->toBe('2.500')
        ->and($batch->qty_remaining->toDecimalString())->toBe('2.500')
        ->and(($this->onHand)())->toBe('2.500');

    $movement = StockMovement::query()->sole();
    expect($movement->type)->toBe(StockMovementType::Purchase)
        ->and($movement->qty->toDecimalString())->toBe('2.500')
        ->and($movement->unit_cost->paisa)->toBe(12050)
        ->and($movement->stock_batch_id)->toBe($batch->id)
        ->and($movement->reference_type)->toBe('test')
        ->and($movement->reference_id)->toBe(1)
        ->and($movement->is_backorder)->toBeFalse();
});

it('issues FIFO across three batches with a split and exact cost allocation', function (): void {
    $oldest = ($this->receive)(5, 10000, 30);
    $middle = ($this->receive)(3, 12000, 20);
    $newest = ($this->receive)(4, 15000, 10);

    expect(($this->summary)(($this->issue)(7)))->toBe([
        ['batch' => $oldest->id, 'qty' => '5.000', 'cost' => 10000, 'backorder' => false],
        ['batch' => $middle->id, 'qty' => '2.000', 'cost' => 12000, 'backorder' => false],
    ])->and(($this->summary)(($this->issue)(4)))->toBe([
        ['batch' => $middle->id, 'qty' => '1.000', 'cost' => 12000, 'backorder' => false],
        ['batch' => $newest->id, 'qty' => '3.000', 'cost' => 15000, 'backorder' => false],
    ]);

    expect(($this->remaining)($oldest))->toBe('0.000')
        ->and(($this->remaining)($middle))->toBe('0.000')
        ->and(($this->remaining)($newest))->toBe('1.000')
        ->and(($this->onHand)())->toBe('1.000');

    $sales = StockMovement::query()->where('type', StockMovementType::Sale)->orderBy('id')->get();
    expect($sales)->toHaveCount(4)
        ->and($sales->map(fn (StockMovement $m): string => $m->qty->toDecimalString())->all())->toBe(['-5.000', '-2.000', '-1.000', '-3.000'])
        ->and($sales->sum(fn (StockMovement $m): int => $m->unit_cost->times($m->qty->negated())->paisa))->toBe(50000 + 24000 + 12000 + 45000);
});

it('orders FIFO by received_at, not by id', function (): void {
    $later = ($this->receive)(1, 20000, 5);
    $earlier = ($this->receive)(1, 10000, 60);

    expect(($this->summary)(($this->issue)(1))[0]['batch'])->toBe($earlier->id)
        ->and(($this->remaining)($later))->toBe('1.000');
});

it('splits fractional quantities in milli-units', function (): void {
    $a = ($this->receive)('0.333', 30000, 2);
    $b = ($this->receive)('1.000', 31000, 1);

    expect(($this->summary)(($this->issue)('0.500')))->toBe([
        ['batch' => $a->id, 'qty' => '0.333', 'cost' => 30000, 'backorder' => false],
        ['batch' => $b->id, 'qty' => '0.167', 'cost' => 31000, 'backorder' => false],
    ])->and(($this->onHand)())->toBe('0.833');
});

it('refuses an issue beyond stock and writes nothing', function (): void {
    $batch = ($this->receive)(2, 10000);
    $movements = StockMovement::query()->count();

    expect(fn () => ($this->issue)(3))->toThrow(InsufficientStock::class, '3.000 requested, 2.000 available');

    expect(($this->remaining)($batch))->toBe('2.000')
        ->and(($this->onHand)())->toBe('2.000')
        ->and(StockMovement::query()->count())->toBe($movements);
});

it('refuses an issue when there is no stock row at all', function (): void {
    expect(fn () => ($this->issue)(1))->toThrow(InsufficientStock::class, '0.000 available');
});

it('backorders the shortfall at cost_price when the branch allows negative stock', function (): void {
    $this->branch->settings = [...$this->branch->settings, 'allow_negative_stock' => true];
    $this->branch->save();
    $batch = ($this->receive)(4, 10000);

    expect(($this->summary)(($this->issue)(10)))->toBe([
        ['batch' => $batch->id, 'qty' => '4.000', 'cost' => 10000, 'backorder' => false],
        ['batch' => null, 'qty' => '6.000', 'cost' => 9900, 'backorder' => true],
    ])->and(($this->onHand)())->toBe('-6.000')
        ->and(($this->remaining)($batch))->toBe('0.000');

    $backorder = StockMovement::query()->where('is_backorder', true)->sole();
    expect($backorder->stock_batch_id)->toBeNull()
        ->and($backorder->qty->toDecimalString())->toBe('-6.000');

    // The next receipt brings the cache back up; the ledger still sums to it.
    ($this->receive)(10, 10000);
    expect(($this->onHand)())->toBe('4.000')
        ->and((string) StockMovement::query()->sum('qty'))->toBe('4.000');
});

it('lets the caller force the backorder path (synced device sales) or forbid it', function (): void {
    ($this->receive)(1, 10000);

    expect(($this->summary)(($this->issue)(2, allowNegative: true))[1]['backorder'])->toBeTrue()
        ->and(($this->onHand)())->toBe('-1.000');

    $this->branch->settings = [...$this->branch->settings, 'allow_negative_stock' => true];
    $this->branch->save();
    expect(fn () => ($this->issue)(1, allowNegative: false))->toThrow(InsufficientStock::class);
});

it('reverses allocations back onto the same batches', function (): void {
    $a = ($this->receive)(2, 10000, 2);
    $b = ($this->receive)(3, 12000, 1);
    $allocations = ($this->issue)(4);

    Transaction::run(fn () => $this->ledger->reverse($this->product, $this->branch, $allocations, StockMovementType::SaleVoid, ['type' => 'sales', 'id' => 9]));

    expect(($this->remaining)($a))->toBe('2.000')
        ->and(($this->remaining)($b))->toBe('3.000')
        ->and(($this->onHand)())->toBe('5.000');

    $voids = StockMovement::query()->where('type', StockMovementType::SaleVoid)->orderBy('id')->get();
    expect($voids->map(fn (StockMovement $m): array => [$m->stock_batch_id, $m->qty->toDecimalString(), $m->unit_cost->paisa])->all())
        ->toBe([[$a->id, '2.000', 10000], [$b->id, '2.000', 12000]]);
});

it('reverses a backorder allocation onto branch stock only', function (): void {
    $allocations = ($this->issue)(2, allowNegative: true);

    Transaction::run(fn () => $this->ledger->reverse($this->product, $this->branch, $allocations, StockMovementType::SaleReturn, $this->ref));

    expect(($this->onHand)())->toBe('0.000')
        ->and(StockBatch::query()->count())->toBe(0)
        ->and(StockMovement::query()->where('is_backorder', true)->count())->toBe(2);
});

it('puts only the headroom back on a batch and the rest into a new return batch', function (): void {
    $batch = ($this->receive)(2, 10000, 5);
    $allocations = ($this->issue)(1);
    $allocations[0]['qty'] = Quantity::ofUnits(2); // a legacy batch that sold more than it received

    Transaction::run(fn () => $this->ledger->reverse($this->product, $this->branch, $allocations, StockMovementType::SaleReturn, ['type' => 'sale_returns', 'id' => 42]));

    $fresh = StockBatch::query()->whereKeyNot($batch->id)->sole();
    expect(($this->remaining)($batch))->toBe('2.000')
        ->and($fresh->source_type)->toBe(StockSourceType::Return)
        ->and($fresh->source_id)->toBe(42)
        ->and($fresh->unit_cost->paisa)->toBe(10000)
        ->and($fresh->qty_received->toDecimalString())->toBe('1.000')
        ->and($fresh->qty_remaining->toDecimalString())->toBe('1.000')
        ->and(($this->onHand)())->toBe('3.000');

    $returns = StockMovement::query()->where('type', StockMovementType::SaleReturn)->orderBy('id')->get();
    expect($returns->map(fn (StockMovement $m): array => [$m->stock_batch_id, $m->qty->toDecimalString(), $m->unit_cost->paisa])->all())
        ->toBe([[$batch->id, '1.000', 10000], [$fresh->id, '1.000', 10000]]);
});

it('creates a new return batch when the original batch has no headroom at all', function (): void {
    $batch = ($this->receive)(2, 10000, 5);
    $allocations = ($this->issue)(1);
    // Simulate imported data: the batch is already full although a unit was sold from it.
    DB::table('stock_batches')->where('id', $batch->id)->update(['qty_remaining' => '2.000']);

    Transaction::run(fn () => $this->ledger->reverse($this->product, $this->branch, $allocations, StockMovementType::SaleReturn, $this->ref));

    $fresh = StockBatch::query()->whereKeyNot($batch->id)->sole();
    expect(($this->remaining)($batch))->toBe('2.000')
        ->and($fresh->source_type)->toBe(StockSourceType::Return)
        ->and($fresh->qty_remaining->toDecimalString())->toBe('1.000')
        ->and(StockMovement::query()->where('type', StockMovementType::SaleReturn)->sole()->stock_batch_id)->toBe($fresh->id);
});

it('sells an overflow return batch only after the older batches (FIFO)', function (): void {
    $old = ($this->receive)(1, 10000, 10);
    $allocations = ($this->issue)(1);
    DB::table('stock_batches')->where('id', $old->id)->update(['qty_remaining' => '1.000']);
    Transaction::run(fn () => $this->ledger->reverse($this->product, $this->branch, $allocations, StockMovementType::SaleReturn, $this->ref));
    $returned = StockBatch::query()->whereKeyNot($old->id)->sole();

    expect(($this->summary)(($this->issue)(2)))->toBe([
        ['batch' => $old->id, 'qty' => '1.000', 'cost' => 10000, 'backorder' => false],
        ['batch' => $returned->id, 'qty' => '1.000', 'cost' => 10000, 'backorder' => false],
    ]);
});

it('issues only from the chosen batch with manual batch selection', function (): void {
    $old = ($this->receive)(5, 10000, 2);
    $chosen = ($this->receive)(2, 15000, 1);

    expect(($this->summary)(($this->issue)(2, $chosen)))->toBe([
        ['batch' => $chosen->id, 'qty' => '2.000', 'cost' => 15000, 'backorder' => false],
    ])->and(($this->remaining)($old))->toBe('5.000');

    // An exhausted chosen batch does not fall back to FIFO.
    expect(fn () => ($this->issue)(1, $chosen))->toThrow(InsufficientStock::class, 'batch '.$chosen->id);
});

it('rejects a chosen batch of another product', function (): void {
    $other = $this->world->product();
    $batch = ($this->receive)(1, 100, product: $other);

    expect(fn () => ($this->issue)(1, $batch))->toThrow(LogicException::class, 'does not hold product');
});

it('issues several lines at once, including the same product twice', function (): void {
    $second = $this->world->product();
    $a = ($this->receive)(3, 10000);
    $b = ($this->receive)(1, 5000, product: $second);

    $result = Transaction::run(fn (): array => $this->ledger->issueMany($this->branch, StockMovementType::Sale, [
        ['product' => $this->product, 'qty' => Quantity::ofUnits(2), 'ref' => ['type' => 'sale_items', 'id' => 1]],
        ['product' => $second, 'qty' => Quantity::ofUnits(1), 'ref' => ['type' => 'sale_items', 'id' => 2]],
        ['product' => $this->product, 'qty' => Quantity::ofUnits(1), 'ref' => ['type' => 'sale_items', 'id' => 3]],
    ]));

    expect(array_map($this->summary, $result))->toBe([
        [['batch' => $a->id, 'qty' => '2.000', 'cost' => 10000, 'backorder' => false]],
        [['batch' => $b->id, 'qty' => '1.000', 'cost' => 5000, 'backorder' => false]],
        [['batch' => $a->id, 'qty' => '1.000', 'cost' => 10000, 'backorder' => false]],
    ])->and(($this->remaining)($a))->toBe('0.000')
        ->and(($this->onHand)())->toBe('0.000')
        ->and(($this->onHand)($second))->toBe('0.000')
        ->and(StockMovement::query()->where('reference_type', 'sale_items')->pluck('reference_id')->sort()->values()->all())->toBe([1, 2, 3]);
});

it('rolls back every line of a multi-line issue when one line is short', function (): void {
    $second = $this->world->product();
    ($this->receive)(3, 10000);

    expect(fn () => Transaction::run(fn (): array => $this->ledger->issueMany($this->branch, StockMovementType::Sale, [
        ['product' => $this->product, 'qty' => Quantity::ofUnits(2), 'ref' => $this->ref],
        ['product' => $second, 'qty' => Quantity::ofUnits(1), 'ref' => $this->ref],
    ])))->toThrow(InsufficientStock::class);

    expect(($this->onHand)())->toBe('3.000');
});

it('skips products that do not track stock', function (): void {
    $service = $this->world->product(['track_stock' => false]);

    expect(($this->issue)(5, product: $service))->toBe([])
        ->and(BranchStock::query()->count())->toBe(0)
        ->and(StockMovement::query()->count())->toBe(0);
});

it('refuses to run outside a transaction', function (): void {
    // RefreshDatabase keeps one outer transaction open; step out of it for this check.
    DB::rollBack();
    try {
        expect(fn () => $this->ledger->issue($this->product, $this->branch, Quantity::ofUnits(1), StockMovementType::Sale, $this->ref))
            ->toThrow(LogicException::class, 'inside Transaction::run()');
    } finally {
        DB::beginTransaction();
    }
});

it('refuses to run outside its branch context', function (): void {
    $otherBranch = makeBranch($this->world->tenant);
    expect(fn () => Transaction::run(fn () => $this->ledger->receive($this->product, $otherBranch, Quantity::ofUnits(1), Money::zero(), StockMovementType::Purchase, null, $this->ref)))
        ->toThrow(LogicException::class, 'outside its tenant/branch context');
});

it('refuses another tenant\'s product', function (): void {
    $foreign = app(TenantContext::class)->withoutTenancy(fn (): Product => Product::factory()->create());

    expect(fn () => ($this->receive)(1, 100, product: $foreign))->toThrow(LogicException::class, 'does not belong to the tenant');
});

it('refuses non-positive quantities and outbound types for receipts', function (): void {
    expect(fn () => ($this->receive)(0, 100))->toThrow(LogicException::class, 'positive quantity')
        ->and(fn () => ($this->issue)('-1'))->toThrow(LogicException::class, 'positive quantity')
        ->and(fn () => Transaction::run(fn () => $this->ledger->receive($this->product, $this->branch, Quantity::ofUnits(1), Money::zero(), StockMovementType::Sale, null, $this->ref)))
        ->toThrow(LogicException::class, 'cannot create a batch');
});

it('lets only one of two sequential issues take the last unit', function (): void {
    ($this->receive)(1, 10000);

    ($this->issue)(1);

    expect(fn () => ($this->issue)(1))->toThrow(InsufficientStock::class)
        ->and(($this->onHand)())->toBe('0.000');
});

it('serialises two real connections racing for the last unit', function (): void {
    // Drop the beforeEach rows of the RefreshDatabase transaction so it holds no row locks
    // that the cleanup below would wait for. Everything here is committed and removed by hand.
    DB::rollBack();
    DB::beginTransaction();
    $default = DB::getDefaultConnection();
    $base = config('database.connections.'.$default);
    config(['database.connections.race_a' => $base, 'database.connections.race_b' => $base]);
    $context = app(TenantContext::class);
    $context->forget();
    $activityFloor = Schema::hasTable('activity_log') ? (int) DB::connection('race_a')->table('activity_log')->max('id') : 0;

    DB::setDefaultConnection('race_a');
    $tenantId = null;

    try {
        // Committed fixtures, visible to both connections.
        [$tenant, $branch, $product] = $context->withoutTenancy(function (): array {
            $tenant = Tenant::factory()->create();

            return [
                $tenant,
                Branch::factory()->for($tenant)->create(),
                Product::factory()->create(['tenant_id' => $tenant->id]),
            ];
        });
        $tenantId = $tenant->id;
        $context->set($tenant, $branch);
        $one = Quantity::ofUnits(1);

        Transaction::run(fn () => $this->ledger->receive($product, $branch, $one, Money::ofPaisa(500), StockMovementType::Purchase, null, $this->ref));
        DB::connection('race_b')->statement('SET SESSION innodb_lock_wait_timeout = 1');

        $blocked = null;
        Transaction::run(function () use ($product, $branch, $one, &$blocked): void {
            // Connection A takes the last unit and keeps its locks until commit...
            $this->ledger->issue($product, $branch, $one, StockMovementType::Sale, $this->ref);

            // ...so connection B cannot even lock the branch_stocks row meanwhile.
            DB::setDefaultConnection('race_b');
            try {
                DB::transaction(fn () => $this->ledger->issue($product, $branch, $one, StockMovementType::Sale, $this->ref), 1);
            } catch (QueryException $e) {
                $blocked = $e;
            } finally {
                DB::setDefaultConnection('race_a');
            }
        });

        expect($blocked)->toBeInstanceOf(QueryException::class)
            ->and($blocked?->getMessage())->toContain('Lock wait timeout');

        // After A committed, B sees the empty shelf and is refused.
        DB::setDefaultConnection('race_b');
        expect(fn () => DB::transaction(fn () => $this->ledger->issue($product, $branch, $one, StockMovementType::Sale, $this->ref), 1))
            ->toThrow(InsufficientStock::class);
        DB::setDefaultConnection('race_a');

        expect(BranchStock::query()->where('product_id', $product->id)->first()?->qty_on_hand->toDecimalString())->toBe('0.000')
            ->and(StockMovement::query()->where('type', StockMovementType::Sale)->count())->toBe(1);
    } finally {
        DB::setDefaultConnection('race_a');
        $context->forget();
        $raw = DB::connection('race_a');
        $raw->statement('SET FOREIGN_KEY_CHECKS = 0');
        if ($tenantId !== null) {
            $database = $raw->getDatabaseName();
            $tables = $raw->table('information_schema.columns')
                ->where('table_schema', $database)->where('column_name', 'tenant_id')
                ->pluck(DB::raw('TABLE_NAME AS table_name'));
            foreach ($tables as $table) {
                $raw->table((string) $table)->where('tenant_id', $tenantId)->delete();
            }
            $raw->table('tenants')->where('id', $tenantId)->delete();
        }
        if (Schema::connection('race_a')->hasTable('activity_log')) {
            $raw->table('activity_log')->where('id', '>', $activityFloor)->delete();
        }
        $raw->statement('SET FOREIGN_KEY_CHECKS = 1');
        DB::setDefaultConnection($default);
        DB::purge('race_a');
        DB::purge('race_b');
    }
});

it('keeps batches, branch stock and the ledger in agreement over 200 random operations', function (): void {
    mt_srand(20261103);
    $products = [$this->product, $this->world->product(), $this->world->product()];
    /** @var list<array{product: Product, allocations: list<array<string, mixed>>}> $issued */
    $issued = [];
    $refused = 0;

    for ($i = 0; $i < 200; $i++) {
        $product = $products[mt_rand(0, 2)];
        $qty = Quantity::ofMilli(mt_rand(1, 6) * 500);
        $op = mt_rand(0, 9);

        try {
            if ($op < 4) {
                ($this->receive)($qty->toDecimalString(), mt_rand(100, 50000), mt_rand(0, 600), $product);
            } elseif ($op < 8) {
                $issued[] = ['product' => $product, 'allocations' => ($this->issue)($qty->toDecimalString(), product: $product)];
            } elseif ($issued !== []) {
                $entry = array_splice($issued, mt_rand(0, count($issued) - 1), 1)[0];
                Transaction::run(fn () => $this->ledger->reverse($entry['product'], $this->branch, $entry['allocations'], StockMovementType::SaleReturn, $this->ref));
            }
        } catch (InsufficientStock) {
            $refused++;
        }
    }

    expect($refused)->toBeGreaterThan(0);

    foreach ($products as $product) {
        $remaining = (string) (StockBatch::query()->where('product_id', $product->id)->sum('qty_remaining') ?: '0.000');
        $ledger = (string) (StockMovement::query()->where('product_id', $product->id)->sum('qty') ?: '0.000');
        $onHand = ($this->onHand)($product) ?: '0.000';

        expect(Quantity::parse($remaining)->equals(Quantity::parse($onHand)))->toBeTrue("batches {$remaining} vs on hand {$onHand}")
            ->and(Quantity::parse($ledger)->equals(Quantity::parse($onHand)))->toBeTrue("ledger {$ledger} vs on hand {$onHand}")
            ->and(StockBatch::query()->where('product_id', $product->id)->where('qty_remaining', '<', 0)->exists())->toBeFalse();
    }
});
