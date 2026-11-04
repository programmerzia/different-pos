<?php

declare(strict_types=1);

use App\Exceptions\Tenancy\CrossTenantWrite;
use App\Exceptions\Tenancy\MissingTenantContext;
use App\Models\BranchStock;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\DocumentSequence;
use App\Models\MembershipCard;
use App\Models\Message;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Purchase;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\StockAdjustment;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\TaxRate;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\VariantOption;
use App\Models\VariantOptionValue;
use App\Models\Vendor;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

dataset('tenant models', [
    Category::class, Brand::class, Unit::class, TaxRate::class, VariantOption::class, VariantOptionValue::class,
    CustomerGroup::class, Customer::class, MembershipCard::class, Vendor::class,
    ProductFamily::class, Product::class,
    BranchStock::class, StockBatch::class, StockMovement::class, StockAdjustment::class,
    Purchase::class,
    PaymentMethod::class, Courier::class, Register::class, Sale::class, Payment::class, SaleReturn::class,
    DocumentSequence::class, Message::class,
]);

beforeEach(function (): void {
    $this->ctx = app(TenantContext::class);
    $this->a = Tenant::factory()->create();
    $this->b = Tenant::factory()->create();
    $this->branchA = makeBranch($this->a);
    $this->branchB = makeBranch($this->b);

    /** @param class-string<Model> $class */
    $this->attributesFor = static function (string $class, Tenant $tenant, $branch): array {
        $attributes = ['tenant_id' => $tenant->id];
        if (in_array(BelongsToBranch::class, class_uses_recursive($class), true)) {
            $attributes['branch_id'] = $branch->id;
        }

        return $attributes;
    };

    /** @param class-string<Model> $class */
    $this->makeRow = fn (string $class, Tenant $tenant, $branch): Model => $this->ctx->withoutTenancy(
        fn (): Model => $class::factory()->create(($this->attributesFor)($class, $tenant, $branch)),
    );

    $this->inTenant = fn (Tenant $tenant, $branch, callable $callback): mixed => $this->ctx->run($tenant, $branch, $callback);
});

it('fails closed without a tenant context', function (string $class): void {
    ($this->makeRow)($class, $this->a, $this->branchA);

    expect(fn () => $class::query()->count())->toThrow(MissingTenantContext::class);
})->with('tenant models');

it('does not show another tenant rows', function (string $class): void {
    $own = ($this->makeRow)($class, $this->a, $this->branchA);
    $foreign = ($this->makeRow)($class, $this->b, $this->branchB);

    ($this->inTenant)($this->a, $this->branchA, function () use ($class, $own, $foreign): void {
        expect($class::query()->pluck('id')->all())->toBe([$own->getKey()])
            ->and($class::query()->whereKey($foreign->getKey())->exists())->toBeFalse();
    });
})->with('tenant models');

it('refuses to write across tenants', function (string $class): void {
    $foreign = ($this->makeRow)($class, $this->b, $this->branchB);
    $draft = $this->ctx->withoutTenancy(
        fn (): Model => $class::factory()->make(($this->attributesFor)($class, $this->b, $this->branchB)),
    );

    ($this->inTenant)($this->a, $this->branchA, function () use ($foreign, $draft): void {
        expect(fn () => $draft->save())->toThrow(CrossTenantWrite::class)
            ->and(fn () => $foreign->delete())->toThrow(CrossTenantWrite::class);
    });
})->with('tenant models');

it('fills the tenant on create inside a context', function (): void {
    $row = ($this->inTenant)($this->a, $this->branchA, fn () => Category::query()->create(['name' => 'Shirts']));

    expect($row->tenant_id)->toBe($this->a->id);
});
