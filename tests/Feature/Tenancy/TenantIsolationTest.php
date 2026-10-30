<?php

declare(strict_types=1);

use App\Exceptions\Tenancy\CrossTenantWrite;
use App\Exceptions\Tenancy\MissingTenantContext;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\Fixtures\BranchTestItem;

beforeEach(function (): void {
    $this->ctx = app(TenantContext::class);
    $this->a = Tenant::factory()->create();
    $this->b = Tenant::factory()->create();
    $this->ctx->withoutTenancy(function (): void {
        $this->a1 = Branch::factory()->for($this->a)->create(['code' => 'A1']);
        $this->a2 = Branch::factory()->for($this->a)->create(['code' => 'A2']);
        $this->b1 = Branch::factory()->for($this->b)->create(['code' => 'B1']);
    });
});

it('fails closed when there is no tenant context', function (): void {
    expect(fn () => Branch::query()->count())->toThrow(MissingTenantContext::class);
});

it('only sees the current tenant rows', function (): void {
    $codes = $this->ctx->run($this->a, null, fn () => Branch::query()->orderBy('code')->pluck('code')->all());

    expect($codes)->toBe(['A1', 'A2']);
});

it('returns not-found for another tenant id or uuid', function (): void {
    $this->ctx->run($this->a, null, function (): void {
        expect(fn () => Branch::query()->findOrFail($this->b1->id))->toThrow(ModelNotFoundException::class)
            ->and(Branch::query()->where('uuid', $this->b1->uuid)->first())->toBeNull();
    });
});

it('auto-fills tenant_id on create', function (): void {
    $branch = $this->ctx->run($this->a, null, fn () => Branch::query()->create(['code' => 'A3', 'name' => 'Third']));

    expect($branch->tenant_id)->toBe($this->a->id)
        ->and($branch->uuid)->toMatch('/^[0-9a-f-]{36}$/')
        ->and($branch->setting('batch_selection'))->toBe('auto');
});

it('refuses to create a row for another tenant', function (): void {
    $this->ctx->run($this->a, null, function (): void {
        $branch = new Branch(['code' => 'X1', 'name' => 'x']);
        $branch->tenant_id = $this->b->id;

        expect(fn () => $branch->save())->toThrow(CrossTenantWrite::class);
    });
});

it('refuses to update or delete another tenant row', function (): void {
    $foreign = $this->ctx->withoutTenancy(fn () => Branch::query()->findOrFail($this->b1->id));

    $this->ctx->run($this->a, null, function () use ($foreign): void {
        $foreign->name = 'hijacked';
        expect(fn () => $foreign->save())->toThrow(CrossTenantWrite::class)
            ->and(fn () => $foreign->delete())->toThrow(CrossTenantWrite::class);
    });

    expect($this->ctx->withoutTenancy(fn () => Branch::query()->findOrFail($this->b1->id)->name))->not->toBe('hijacked');
});

it('refuses to move a row to another tenant', function (): void {
    $this->ctx->run($this->a, null, function (): void {
        $own = Branch::query()->findOrFail($this->a1->id);
        $own->tenant_id = $this->b->id;

        expect(fn () => $own->save())->toThrow(CrossTenantWrite::class);
    });
});

it('restores the previous context after run()', function (): void {
    $this->ctx->run($this->a, $this->a1, function (): void {
        $this->ctx->run($this->b, null, fn () => expect($this->ctx->tenantId())->toBe($this->b->id));
        expect($this->ctx->tenantId())->toBe($this->a->id)
            ->and($this->ctx->branchId())->toBe($this->a1->id);
    });

    expect($this->ctx->hasTenant())->toBeFalse();
});

it('rejects a branch of another tenant as context', function (): void {
    expect(fn () => $this->ctx->set($this->a, $this->b1))->toThrow(MissingTenantContext::class);
});

it('scopes branch models to the current branch', function (): void {
    $this->ctx->run($this->a, $this->a1, fn () => BranchTestItem::query()->create(['name' => 'in A1']));
    $this->ctx->run($this->a, $this->a2, fn () => BranchTestItem::query()->create(['name' => 'in A2']));

    $this->ctx->run($this->a, $this->a1, function (): void {
        expect(BranchTestItem::query()->pluck('name')->all())->toBe(['in A1'])
            ->and(BranchTestItem::query()->withoutBranchScope()->count())->toBe(2);
    });
    $this->ctx->run($this->b, $this->b1, fn () => expect(BranchTestItem::query()->withoutBranchScope()->count())->toBe(0));
});

it('requires a branch for branch models', function (): void {
    $this->ctx->run($this->a, null, function (): void {
        expect(fn () => BranchTestItem::query()->count())->toThrow(MissingTenantContext::class);
    });
});

it('refuses a branch write into another branch', function (): void {
    $this->ctx->run($this->a, $this->a1, function (): void {
        $item = new BranchTestItem(['name' => 'x']);
        $item->branch_id = $this->a2->id;

        expect(fn () => $item->save())->toThrow(CrossTenantWrite::class);
    });
});

it('lets auth load users without a context but guards tenant writes', function (): void {
    $userB = User::factory()->for($this->b)->create();

    expect(User::query()->find($userB->id))->not->toBeNull();

    $this->ctx->run($this->a, null, function () use ($userB): void {
        expect(User::query()->inCurrentTenant()->find($userB->id))->toBeNull();

        $userB->name = 'hijacked';
        expect(fn () => $userB->save())->toThrow(CrossTenantWrite::class);
    });
});
