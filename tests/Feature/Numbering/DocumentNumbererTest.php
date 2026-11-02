<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Support\Database\Transaction;
use App\Support\Numbering\DocumentNumberer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->branch = makeBranch($this->tenant);
    $this->numberer = app(DocumentNumberer::class);
    $this->in = fn (callable $fn) => app(TenantContext::class)->run($this->tenant, null, fn () => Transaction::run($fn));
});

it('formats branch documents as BR-TYPE-YYseq6', function (): void {
    $this->travelTo(now()->setDate(2026, 11, 3));
    $code = $this->branch->code;

    expect(($this->in)(fn () => $this->numberer->next('purchase', $this->branch->id)))->toBe("{$code}-PURCHASE-26000001")
        ->and(($this->in)(fn () => $this->numberer->next('purchase', $this->branch->id)))->toBe("{$code}-PURCHASE-26000002");
});

it('resets yearly sequences on a new year but not others', function (): void {
    $this->travelTo(now()->setDate(2026, 12, 31));
    ($this->in)(fn () => $this->numberer->next('invoice', $this->branch->id));
    ($this->in)(fn () => $this->numberer->next('invoice', $this->branch->id));

    $this->travelTo(now()->setDate(2027, 1, 1));
    expect(($this->in)(fn () => $this->numberer->next('invoice', $this->branch->id)))
        ->toEndWith('-27000001');
});

it('keeps separate counters per branch and type', function (): void {
    $other = makeBranch($this->tenant);
    ($this->in)(fn () => $this->numberer->next('purchase', $this->branch->id));

    expect(($this->in)(fn () => $this->numberer->next('purchase', $other->id)))->toEndWith('000001')
        ->and(($this->in)(fn () => $this->numberer->next('expense', $this->branch->id)))->toEndWith('000001');
});

it('allocates 20 strictly increasing unique numbers across separate transactions', function (): void {
    $numbers = [];
    for ($i = 0; $i < 20; $i++) {
        $numbers[] = ($this->in)(fn () => $this->numberer->next('purchase', $this->branch->id));
    }

    $sorted = $numbers;
    sort($sorted, SORT_STRING);

    expect(array_unique($numbers))->toHaveCount(20)->and($numbers)->toBe($sorted);
});

it('gives the number back when the transaction rolls back', function (): void {
    try {
        ($this->in)(function (): void {
            $this->numberer->next('purchase', $this->branch->id);

            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
    }

    expect(($this->in)(fn () => $this->numberer->next('purchase', $this->branch->id)))->toEndWith('000001');
});

it('refuses to run outside a transaction', function (): void {
    // RefreshDatabase keeps one outer transaction open; step out of it for this check.
    DB::rollBack();

    try {
        expect(fn () => app(TenantContext::class)->run($this->tenant, null, fn () => $this->numberer->next('purchase')))
            ->toThrow(LogicException::class);
    } finally {
        DB::beginTransaction();
    }
});

it('rejects a branch of another tenant', function (): void {
    $foreign = makeBranch(Tenant::factory()->create());

    ($this->in)(fn () => $this->numberer->next('purchase', $foreign->id));
})->throws(LogicException::class);
