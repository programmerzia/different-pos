<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Cards\IssueCard;
use App\Actions\Customers\SaveCustomer;
use App\Enums\PaymentMethodType;
use App\Enums\ProductItemType;
use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Register;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Database\Transaction;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use App\Support\Stock\StockLedger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Small fixed data set for the keyboard-only browser tests (e2e/). Run after DatabaseSeeder, on the
 * throwaway pos_e2e database only: a second branch, a walk-in, a cash method, a register, three stocked products and two
 * customers (one with an active membership card).
 */
final class E2eSeeder extends Seeder
{
    public function run(): void
    {
        $context = app(TenantContext::class);
        $tenant = Tenant::query()->where('slug', 'demo-store')->firstOrFail();
        $branch = $context->withoutTenancy(fn (): Branch => Branch::query()->where('tenant_id', $tenant->id)->where('code', 'MAIN')->firstOrFail());
        $owner = User::query()->where('email', 'owner@demo.test')->firstOrFail();

        // A second branch makes the branch picker appear after login (a single branch is selected for you).
        $warehouse = $context->withoutTenancy(fn (): Branch => Branch::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'WH'],
            ['name' => 'Warehouse', 'is_active' => true],
        ));
        $owner->branches()->syncWithoutDetaching([$warehouse->id => ['is_default' => false]]);

        $context->run($tenant, $branch, function () use ($tenant, $branch, $owner): void {
            Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Walk-in', 'phone' => null, 'is_walk_in' => true]);
            PaymentMethod::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Cash', 'type' => PaymentMethodType::Cash]);
            Register::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'name' => 'Counter 1', 'code' => 'R01']);
            $unit = Unit::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Piece', 'short_name' => 'pc']);
            Unit::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Metre', 'short_name' => 'm', 'allows_decimal' => true]);

            $ledger = app(StockLedger::class);
            foreach ([['E2E-TEE', 'E2E Tee', 45_000], ['E2E-CAP', 'E2E Cap', 30_000], ['E2E-BAG', 'E2E Bag', 120_000]] as [$sku, $name, $price]) {
                $product = Product::factory()->create([
                    'tenant_id' => $tenant->id,
                    'unit_id' => $unit->id,
                    'sku' => $sku,
                    'name' => $name,
                    'sale_price' => Money::ofPaisa($price),
                ]);
                Transaction::run(fn () => $ledger->receive(
                    $product,
                    $branch,
                    Quantity::ofUnits(50),
                    Money::ofPaisa((int) ($price / 2)),
                    StockMovementType::Purchase,
                    null,
                    ['type' => 'e2e', 'id' => 1],
                ));
            }

            // A raw material (never offered for sale) and a finished product with no stock, for the exchange screen.
            $linen = Product::factory()->create([
                'tenant_id' => $tenant->id,
                'unit_id' => $unit->id,
                'sku' => 'E2E-RAW',
                'name' => 'Seeded Linen Roll',
                'item_type' => ProductItemType::RawMaterial,
                'sale_price' => Money::ofPaisa(0),
            ]);
            Transaction::run(fn () => $ledger->receive($linen, $branch, Quantity::ofUnits(20), Money::ofPaisa(20_000), StockMovementType::Purchase, null, ['type' => 'e2e', 'id' => 1]));
            Product::factory()->create([
                'tenant_id' => $tenant->id,
                'unit_id' => $unit->id,
                'sku' => 'E2E-OUT',
                'name' => 'Seeded Sold-Out Tee',
                'sale_price' => Money::ofPaisa(35_000),
            ]);

            $save = app(SaveCustomer::class);
            $member = $save->handle($owner, ['name' => 'Rina Member', 'phone' => '01711000001']);
            $save->handle($owner, ['name' => 'Karim Regular', 'phone' => '01711000002']);
            app(IssueCard::class)->handle($owner, $member);
        });
    }
}
