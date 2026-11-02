<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Models\Branch;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Tenancy\TenantContext;

/** A tenant + branch + user with the tenant/branch context set, plus factories for stock tests. */
final readonly class StockWorld
{
    private function __construct(
        public Tenant $tenant,
        public Branch $branch,
        public User $user,
    ) {}

    /** @param  array<string, mixed>  $branchSettings */
    public static function make(array $branchSettings = []): self
    {
        $context = app(TenantContext::class);
        [$tenant, $branch, $user] = $context->withoutTenancy(static function () use ($branchSettings): array {
            $tenant = Tenant::factory()->create();

            return [
                $tenant,
                Branch::factory()->for($tenant)->create(['settings' => $branchSettings]),
                User::factory()->create(['tenant_id' => $tenant->id]),
            ];
        });
        $context->set($tenant, $branch);

        return new self($tenant, $branch, $user);
    }

    /** @param  array<string, mixed>  $attributes */
    public function product(array $attributes = []): Product
    {
        return app(TenantContext::class)->withoutTenancy(
            fn (): Product => Product::factory()->create(['tenant_id' => $this->tenant->id, ...$attributes]),
        );
    }

    public function vendor(): Vendor
    {
        return app(TenantContext::class)->withoutTenancy(
            fn (): Vendor => Vendor::factory()->create(['tenant_id' => $this->tenant->id]),
        );
    }

    public function paymentMethod(): PaymentMethod
    {
        return app(TenantContext::class)->withoutTenancy(
            fn (): PaymentMethod => PaymentMethod::factory()->create(['tenant_id' => $this->tenant->id]),
        );
    }
}
