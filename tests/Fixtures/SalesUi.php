<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;

/** Helpers for the sales / returns / dues page tests: a SalesWorld whose users can sign in to its branch. */
final class SalesUi
{
    public static function world(): SalesWorld
    {
        $world = SalesWorld::make();
        foreach ($world->users as $user) {
            $user->branches()->attach($world->branch);
        }

        return $world;
    }

    /** A user of the world's tenant with no role at all. */
    public static function nobody(SalesWorld $world): User
    {
        $user = app(TenantContext::class)->withoutTenancy(static fn (): User => User::factory()->create(['tenant_id' => $world->tenant->id]));
        $user->branches()->attach($world->branch);

        return $user;
    }

    /** A second branch of the same tenant (the owner can switch to it). */
    public static function otherBranch(SalesWorld $world): Branch
    {
        $branch = app(TenantContext::class)->withoutTenancy(static fn (): Branch => Branch::factory()->for($world->tenant)->create(['code' => 'DF2']));
        $world->user()->branches()->attach($branch);

        return $branch;
    }

    /** Runs $callback as if the world's context pointed at another branch, then restores it. */
    public static function inBranch(SalesWorld $world, Branch $branch, callable $callback): mixed
    {
        return app(TenantContext::class)->run($world->tenant, $branch, $callback);
    }

    /** A sale of `$units` × the product, paying `$paid` paisa cash (null = in full). */
    public static function sale(SalesWorld $world, Product $product, ?Customer $customer = null, int $units = 1, ?int $paid = null): Sale
    {
        $total = $product->sale_price->paisa * $units;
        $tenders = ($paid ?? $total) > 0 ? [$world->tender('cash', $paid ?? $total)] : [];

        return $world->sell([$world->line($product, $units)], $tenders, $customer);
    }

    public static function age(Sale $sale, int $days): Sale
    {
        Sale::query()->whereKey($sale->id)->update(['sold_at' => Carbon::now()->subDays($days)]);

        return $sale->refresh();
    }

    /** A sale in a different tenant, for isolation checks. */
    public static function foreignSale(): Sale
    {
        $context = app(TenantContext::class);
        [$tenant, $branch] = $context->withoutTenancy(static function (): array {
            $tenant = Tenant::factory()->create();

            return [$tenant, Branch::factory()->for($tenant)->create()];
        });

        return $context->withoutTenancy(static function () use ($tenant, $branch): Sale {
            $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Foreign Buyer']);

            return Sale::factory()->create([
                'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'customer_id' => $customer->id, 'number' => 'X-FOREIGN-1', 'public_token' => str_repeat('f', 32),
            ]);
        });
    }
}
