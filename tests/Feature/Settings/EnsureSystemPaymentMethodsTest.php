<?php

declare(strict_types=1);

use App\Actions\Settings\EnsureSystemPaymentMethods;
use App\Enums\PaymentMethodType;
use App\Models\Branch;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantScope;

beforeEach(function (): void {
    $this->context = app(TenantContext::class);
    [$this->tenant, $this->branch] = $this->context->withoutTenancy(static function (): array {
        $tenant = Tenant::factory()->create();

        return [$tenant, Branch::factory()->for($tenant)->create()];
    });
    $this->context->set($this->tenant, $this->branch);
    $this->types = fn (?Tenant $tenant = null): array => PaymentMethod::query()->withoutGlobalScope(TenantScope::class)
        ->where('tenant_id', ($tenant ?? $this->tenant)->id)->where('is_active', true)
        ->orderBy('sort_order')->get()->map(fn (PaymentMethod $m): string => $m->type->value.':'.$m->name)->all();
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('creates the store credit and exchange credit tenders once', function (): void {
    expect(app(EnsureSystemPaymentMethods::class)->handle())->toBe(2)
        ->and(($this->types)())->toBe(['customer_credit:Store credit', 'exchange_credit:Exchange credit'])
        ->and(PaymentMethod::query()->whereNotNull('sync_version')->count())->toBe(2);

    expect(app(EnsureSystemPaymentMethods::class)->handle())->toBe(0)
        ->and(PaymentMethod::query()->count())->toBe(2);
});

it('keeps an existing tender and switches an inactive one back on', function (): void {
    PaymentMethod::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Due credit', 'type' => PaymentMethodType::CustomerCredit, 'is_active' => false]);

    expect(app(EnsureSystemPaymentMethods::class)->handle())->toBe(2)
        ->and(PaymentMethod::query()->where('type', PaymentMethodType::CustomerCredit)->sole()->is_active)->toBeTrue()
        ->and(PaymentMethod::query()->count())->toBe(2);
});

it('picks a free name when a business method already uses the default one', function (): void {
    PaymentMethod::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'store CREDIT', 'type' => PaymentMethodType::Cash]);

    app(EnsureSystemPaymentMethods::class)->handle();

    expect(PaymentMethod::query()->where('type', PaymentMethodType::CustomerCredit)->sole()->name)->toBe('Store credit (2)');
});

it('works for a given tenant without a tenant context (importer, seeders) and touches no other tenant', function (): void {
    $other = $this->context->withoutTenancy(static fn (): Tenant => Tenant::factory()->create());
    $this->context->forget();

    $this->context->withoutTenancy(fn (): int => app(EnsureSystemPaymentMethods::class)->handle($other));

    expect(($this->types)($other))->toHaveCount(2)
        ->and(($this->types)())->toBe([]);
});

it('refuses to provision another tenant from inside a tenant context', function (): void {
    $other = $this->context->withoutTenancy(static fn (): Tenant => Tenant::factory()->create());

    expect(fn () => app(EnsureSystemPaymentMethods::class)->handle($other))->toThrow(LogicException::class);
});
