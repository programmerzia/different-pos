<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Actions\Tenancy\SeedTenantRoles;
use App\Models\Branch;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Permissions\SystemRoles;
use App\Support\Tenancy\TenantContext;
use Spatie\Permission\PermissionRegistrar;

/** A tenant + branch + role-holding user for HTTP tests; rows are created with tenancy bypassed. */
final readonly class CatalogWorld
{
    private function __construct(public Tenant $tenant, public Branch $branch, public User $user) {}

    public static function make(?string $role = SystemRoles::OWNER): self
    {
        $context = app(TenantContext::class);
        [$tenant, $branch, $user] = $context->withoutTenancy(static function (): array {
            $tenant = Tenant::factory()->create();
            $branch = Branch::factory()->for($tenant)->create();
            $user = User::factory()->create(['tenant_id' => $tenant->id]);
            $user->branches()->attach($branch);

            return [$tenant, $branch, $user];
        });

        app(SeedTenantRoles::class)->handle($tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        if ($role !== null) {
            $user->assignRole($role);
        }

        return new self($tenant, $branch, $user);
    }

    /** Context for calling Actions directly in a test (an HTTP request sets its own). */
    public function enter(): self
    {
        app(TenantContext::class)->set($this->tenant, $this->branch);

        return $this;
    }

    /** @param  array<string, mixed>  $attributes */
    public function product(array $attributes = []): Product
    {
        return app(TenantContext::class)->withoutTenancy(
            fn (): Product => Product::factory()->create(['tenant_id' => $this->tenant->id, ...$attributes]),
        );
    }

    public function unit(): Unit
    {
        return app(TenantContext::class)->withoutTenancy(
            fn (): Unit => Unit::factory()->create(['tenant_id' => $this->tenant->id]),
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
