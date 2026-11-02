<?php

declare(strict_types=1);

use App\Actions\Sales\CompleteSale;
use App\Actions\Sales\Data\SaleDraft;
use App\Actions\Sales\Data\SaleLineDraft;
use App\Actions\Sales\Data\TenderDraft;
use App\Actions\Tenancy\SeedTenantRoles;
use App\Enums\PaymentMethodType;
use App\Enums\StockMovementType;
use App\Exceptions\Domain\InsufficientStock;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Database\Transaction;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use App\Support\Stock\StockLedger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

it('sells the last unit once when two real connections race for it', function (): void {
    // Step out of the RefreshDatabase transaction: everything here is committed and removed by hand.
    DB::rollBack();
    DB::beginTransaction();
    $default = DB::getDefaultConnection();
    $base = config('database.connections.'.$default);
    config(['database.connections.race_a' => $base, 'database.connections.race_b' => $base]);
    $context = app(TenantContext::class);
    $context->forget();
    $activityFloor = (int) DB::connection('race_a')->table('activity_log')->max('id');
    DB::setDefaultConnection('race_a');
    $tenantId = null;

    try {
        [$tenant, $branch, $user, $product, $cash] = $context->withoutTenancy(function (): array {
            $tenant = Tenant::factory()->create();
            $branch = Branch::factory()->for($tenant)->create(['code' => 'RC1']);

            return [
                $tenant,
                $branch,
                User::factory()->create(['tenant_id' => $tenant->id]),
                Product::factory()->create(['tenant_id' => $tenant->id, 'sale_price' => Money::ofPaisa(50_000)]),
                PaymentMethod::factory()->create(['tenant_id' => $tenant->id, 'type' => PaymentMethodType::Cash]),
            ];
        });
        $tenantId = $tenant->id;
        $context->set($tenant, $branch);
        app(SeedTenantRoles::class)->handle($tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->assignRole('Cashier');
        Customer::factory()->create(['tenant_id' => $tenant->id, 'phone' => null, 'is_walk_in' => true]);
        Transaction::run(fn () => app(StockLedger::class)->receive($product, $branch, Quantity::ofUnits(1), Money::ofPaisa(20_000), StockMovementType::Purchase, null, ['type' => 'test', 'id' => 1]));

        $draft = new SaleDraft([new SaleLineDraft($product->id, Quantity::ofUnits(1))], [new TenderDraft($cash->id, Money::ofPaisa(50_000))]);
        DB::connection('race_b')->statement('SET SESSION innodb_lock_wait_timeout = 1');

        $blocked = null;
        DB::transaction(function () use ($user, $draft, &$blocked): void {
            // Connection A completes the sale and holds its locks until the outer commit...
            app(CompleteSale::class)->handle($user, $draft);

            // ...so connection B cannot even take the sale number meanwhile.
            DB::setDefaultConnection('race_b');
            try {
                app(CompleteSale::class)->handle($user, $draft);
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
        expect(fn () => app(CompleteSale::class)->handle($user, $draft))->toThrow(InsufficientStock::class);
        DB::setDefaultConnection('race_a');

        expect(Sale::query()->count())->toBe(1)
            ->and(BranchStock::query()->where('product_id', $product->id)->sole()->qty_on_hand->toDecimalString())->toBe('0.000');
    } finally {
        DB::setDefaultConnection('race_a');
        $context->forget();
        $raw = DB::connection('race_a');
        $raw->statement('SET FOREIGN_KEY_CHECKS = 0');
        if ($tenantId !== null) {
            $saleIds = $raw->table('sales')->where('tenant_id', $tenantId)->pluck('id')->all();
            $itemIds = $raw->table('sale_items')->whereIn('sale_id', $saleIds)->pluck('id')->all();
            $raw->table('sale_item_batches')->whereIn('sale_item_id', $itemIds)->delete();
            $raw->table('sale_items')->whereIn('sale_id', $saleIds)->delete();
            $paymentIds = $raw->table('payments')->where('tenant_id', $tenantId)->pluck('id')->all();
            $raw->table('payment_allocations')->whereIn('payment_id', $paymentIds)->delete();
            $roleIds = $raw->table('roles')->where('tenant_id', $tenantId)->pluck('id')->all();
            $raw->table('role_has_permissions')->whereIn('role_id', $roleIds)->delete();
            $tables = $raw->table('information_schema.columns')
                ->where('table_schema', $raw->getDatabaseName())->where('column_name', 'tenant_id')
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
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
});
