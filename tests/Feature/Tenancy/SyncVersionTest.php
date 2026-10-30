<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Support\Database\Transaction;
use App\Support\Tenancy\TenantContext;
use Tests\Fixtures\SyncTestItem;

beforeEach(function (): void {
    $this->ctx = app(TenantContext::class);
    $this->tenant = Tenant::factory()->create();
    $this->counter = fn (?Tenant $t = null): int => (int) Tenant::query()->whereKey(($t ?? $this->tenant)->id)->value('sync_counter');
});

it('assigns consecutive versions with one counter bump per transaction', function (): void {
    [$x, $y] = $this->ctx->run($this->tenant, null, fn () => Transaction::run(fn (): array => [
        SyncTestItem::query()->create(['name' => 'x']),
        SyncTestItem::query()->create(['name' => 'y']),
    ]));

    expect([$x->sync_version, $y->sync_version])->toBe([1, 2])
        ->and(($this->counter)())->toBe(2)
        ->and(SyncTestItem::withoutGlobalScopes()->whereKey($x->id)->value('sync_version'))->toBe(1);
});

it('gives a row written twice in one transaction a single version', function (): void {
    $item = $this->ctx->run($this->tenant, null, fn () => Transaction::run(function (): SyncTestItem {
        $item = SyncTestItem::query()->create(['name' => 'first']);
        $item->update(['name' => 'second']);

        return $item;
    }));

    expect($item->sync_version)->toBe(1)->and(($this->counter)())->toBe(1);
});

it('bumps the version on update, soft delete and restore', function (): void {
    $this->ctx->run($this->tenant, null, function (): void {
        $item = Transaction::run(fn () => SyncTestItem::query()->create(['name' => 'x']));
        Transaction::run(fn () => $item->update(['name' => 'y']));
        expect($item->sync_version)->toBe(2);

        Transaction::run(fn () => $item->delete());
        expect(SyncTestItem::withTrashed()->findOrFail($item->id)->sync_version)->toBe(3);

        Transaction::run(fn () => $item->restore());
        expect(SyncTestItem::query()->findOrFail($item->id)->sync_version)->toBe(4);
    });
});

it('assigns versions only at the end of the outermost transaction', function (): void {
    $this->ctx->run($this->tenant, null, function (): void {
        Transaction::run(function (): void {
            $inner = Transaction::run(fn () => SyncTestItem::query()->create(['name' => 'inner']));
            expect($inner->sync_version)->toBeNull()
                ->and(($this->counter)())->toBe(0);
        });
    });

    expect(($this->counter)())->toBe(1);
});

it('leaves the counter untouched when the transaction rolls back', function (): void {
    $this->ctx->run($this->tenant, null, function (): void {
        expect(fn () => Transaction::run(function (): void {
            SyncTestItem::query()->create(['name' => 'doomed']);
            throw new RuntimeException('boom');
        }))->toThrow(RuntimeException::class);
    });

    expect(($this->counter)())->toBe(0)
        ->and(SyncTestItem::withoutGlobalScopes()->count())->toBe(0);
});

it('versions writes made outside the runner (factories, seeders)', function (): void {
    $item = $this->ctx->run($this->tenant, null, fn () => SyncTestItem::query()->create(['name' => 'seeded']));

    expect($item->sync_version)->toBe(1)->and(($this->counter)())->toBe(1);
});

it('keeps separate counters per tenant', function (): void {
    $other = Tenant::factory()->create();

    $this->ctx->run($this->tenant, null, fn () => Transaction::run(fn () => SyncTestItem::query()->create(['name' => 'a'])));
    $this->ctx->run($other, null, fn () => Transaction::run(function (): void {
        SyncTestItem::query()->create(['name' => 'b1']);
        SyncTestItem::query()->create(['name' => 'b2']);
    }));

    expect(($this->counter)())->toBe(1)->and(($this->counter)($other))->toBe(2);
});
