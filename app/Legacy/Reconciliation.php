<?php

declare(strict_types=1);

namespace App\Legacy;

use App\Enums\AllocatableType;
use App\Enums\SaleOrigin;
use App\Legacy\Steps\CustomerGroupsAndCustomers;
use App\Legacy\Steps\Payments;
use App\Legacy\Steps\Products;
use App\Legacy\Steps\Returns;
use App\Legacy\Steps\Sales;
use App\Legacy\Steps\StockInvoicesToPurchasesAndBatches;
use App\Legacy\Steps\Users;
use App\Legacy\Steps\Vendors;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * reconciliation.csv (ADR 0002 M4): per store, legacy vs new. A row is either a count row
 * (legacy_count/new_count, diff = new − legacy count) or a sum row (legacy_sum/new_sum in taka or
 * units, diff = new − legacy sum). Every non-zero diff must be explained by migration_report.csv.
 */
final readonly class Reconciliation
{
    /** @return list<array<string, string|int>> */
    public function build(ImportContext $context): array
    {
        $rows = [];
        foreach ($context->stores() as $store) {
            $rows = [...$rows, ...$this->store($context, $store)];
        }

        return $rows;
    }

    /** @return list<array<string, string|int>> */
    private function store(ImportContext $context, int $store): array
    {
        $legacy = $context->legacy();
        $tenantId = $context->tenantId();
        $branchId = $context->branchId($store);
        $rows = [];

        $count = function (string $entity, int $legacyCount, int $newCount) use ($store, &$rows): void {
            $rows[] = ['store' => $store, 'entity' => $entity, 'legacy_count' => $legacyCount, 'new_count' => $newCount, 'legacy_sum' => '', 'new_sum' => '', 'diff' => $newCount - $legacyCount];
        };
        $money = function (string $entity, Money $legacySum, Money $newSum) use ($store, &$rows): void {
            $rows[] = ['store' => $store, 'entity' => $entity, 'legacy_count' => '', 'new_count' => '', 'legacy_sum' => $legacySum->toDecimalString(), 'new_sum' => $newSum->toDecimalString(), 'diff' => $newSum->minus($legacySum)->toDecimalString()];
        };
        $units = function (string $entity, Quantity $legacySum, Quantity $newSum) use ($store, &$rows): void {
            $rows[] = ['store' => $store, 'entity' => $entity, 'legacy_count' => '', 'new_count' => '', 'legacy_sum' => $legacySum->format(), 'new_sum' => $newSum->format(), 'diff' => $newSum->minus($legacySum)->format()];
        };

        $ids = $this->ids(...);

        // Master data: new rows carrying the store's legacy ids (deduplicated names map to one row).
        $legacyUsers = $ids($legacy->table(Users::TABLE)->where('store_id', $store));
        $count('users', count($legacyUsers), $this->countLegacy('users', $tenantId, $legacyUsers));
        $legacyVendors = $ids($legacy->table(Vendors::TABLE)->where('store_id', $store));
        $count('vendors', count($legacyVendors), $this->countLegacy('vendors', $tenantId, $legacyVendors));
        $legacyProducts = $ids($legacy->table(Products::TABLE)->where('store_id', $store));
        // Cross-store duplicates merge, so count the products the store's legacy ids resolve to.
        $count('products', count($legacyProducts), $this->sumInt($this->chunked($legacyProducts, fn (array $chunk): array => [
            'n' => DB::table('legacy_id_maps')->join('products', 'products.id', '=', 'legacy_id_maps.new_id')
                ->where('legacy_id_maps.entity', Products::TABLE)->where('products.tenant_id', $tenantId)->whereIn('legacy_id_maps.legacy_id', $chunk)->count(),
        ]), 'n'));
        $legacyCustomers = $ids($legacy->table(CustomerGroupsAndCustomers::TABLE)->where('store_id', $store));
        $count('customers', count($legacyCustomers), $this->countLegacy('customers', $tenantId, $legacyCustomers));
        // Addresses that named a known district/upazila got geo ids; the diff is the "unparsed locations" (kept as street text).
        $withAddress = $this->ids($legacy->table(CustomerGroupsAndCustomers::TABLE)->where('store_id', $store)->whereNotNull('address')->where('address', '!=', ''));
        $count('customer_locations', count($withAddress), $this->sumInt($this->chunked($withAddress, fn (array $chunk): array => [
            'n' => DB::table('customers')->where('tenant_id', $tenantId)->whereIn('legacy_id', $chunk)->whereNotNull('geo_district_id')->count(),
        ]), 'n'));

        // Purchases and stock.
        $legacyInvoices = $ids($legacy->table(StockInvoicesToPurchasesAndBatches::TABLE)->where('store_id', $store));
        $purchases = DB::table('purchases')->where('tenant_id', $tenantId)->where('branch_id', $branchId)->whereNotNull('legacy_id');
        $count('purchases', count($legacyInvoices), (clone $purchases)->count());
        $legacyLines = $this->chunked($legacyInvoices, fn (array $chunk): array => (array) $legacy->table(StockInvoicesToPurchasesAndBatches::DETAILS)->whereIn('invoice_id', $chunk)
            ->selectRaw('COUNT(*) AS n, COALESCE(SUM(quantity * ROUND(CAST(buy_price AS DECIMAL(20,4)), 2)), 0) AS received_value, COALESCE(SUM(GREATEST(available_stock, 0)), 0) AS available, COALESCE(SUM(GREATEST(available_stock, 0) * ROUND(CAST(buy_price AS DECIMAL(20,4)), 2)), 0) AS available_value')
            ->first());
        $money('purchases.total', $this->sumMoney($legacyLines, 'received_value'), Money::ofPaisa((int) (clone $purchases)->sum('total')));
        $batches = DB::table('stock_batches')->where('tenant_id', $tenantId)->where('branch_id', $branchId)->whereNotNull('legacy_id');
        $count('stock_batches', $this->sumInt($legacyLines, 'n'), (clone $batches)->count());
        $units('stock_units.batches', Quantity::ofUnits($this->sumInt($legacyLines, 'available')), Quantity::parse((string) ((clone $batches)->sum('qty_remaining') ?: '0')));
        $money('stock_value_at_cost', $this->sumMoney($legacyLines, 'available_value'), Money::ofPaisa((int) (clone $batches)->selectRaw('COALESCE(SUM(ROUND(qty_remaining * unit_cost)), 0) AS v')->value('v')));
        $units(
            'stock_units.products_vs_on_hand',
            Quantity::ofUnits((int) $legacy->table(Products::TABLE)->where('store_id', $store)->sum('quantity')),
            Quantity::parse((string) (DB::table('branch_stocks')->where('tenant_id', $tenantId)->where('branch_id', $branchId)->sum('qty_on_hand') ?: '0')),
        );

        // Sales.
        $legacySales = $legacy->table(Sales::TABLE)->where('store_id', $store);
        $sales = DB::table('sales')->where('tenant_id', $tenantId)->where('branch_id', $branchId)->where('origin', SaleOrigin::Import->value);
        $count('sales', (clone $legacySales)->count(), (clone $sales)->count());
        $legacySaleIds = $ids(clone $legacySales);
        $legacyItems = $this->chunked($legacySaleIds, fn (array $chunk): array => ['n' => $legacy->table(Sales::DETAILS)->whereIn('invoice_id', $chunk)->count()]);
        $count('sale_items', $this->sumInt($legacyItems, 'n'), DB::table('sale_items')->whereIn('sale_id', (clone $sales)->select('id'))->count());
        $sumLegacy = fn (string $column): Money => $this->legacySum(clone $legacySales, $column);
        $money('sales.subtotal', $sumLegacy('total_amount'), Money::ofPaisa((int) (clone $sales)->sum('subtotal')));
        $money('sales.discount', $sumLegacy('total_discount'), Money::ofPaisa((int) (clone $sales)->sum(DB::raw('item_discount_total + order_discount'))));
        $money('sales.total', $sumLegacy('total_amount')->minus($sumLegacy('total_discount')), Money::ofPaisa((int) (clone $sales)->sum('total')));
        $count('sales.open_dues', (clone $legacySales)->where('total_due', '>', 0)->count(), (clone $sales)->where('due', '>', 0)->count());
        $money('sales.due', $sumLegacy('total_due'), Money::ofPaisa((int) (clone $sales)->sum('due')));
        $money('sales.paid', $sumLegacy('total_amount')->minus($sumLegacy('total_discount'))->minus($sumLegacy('total_due')), Money::ofPaisa((int) DB::table('payment_allocations')
            ->where('allocatable_type', AllocatableType::Sale->value)->whereIn('allocatable_id', (clone $sales)->select('id'))->sum('amount')));

        // Legacy payment rows vs imported (non-synthesised) payments.
        $legacyPayments = $this->chunked($legacySaleIds, fn (array $chunk): array => (array) $legacy->table(Payments::TABLE)->whereIn('invoice_id', $chunk)->where('paid_amount', '>', 0)
            ->selectRaw('COUNT(*) AS n, COALESCE(SUM(ROUND(CAST(paid_amount AS DECIMAL(20,4)), 2)), 0) AS amount')->first());
        $payments = DB::table('payments')->where('tenant_id', $tenantId)->where('branch_id', $branchId)->where('number', 'like', 'L-P%');
        $count('payments', $this->sumInt($legacyPayments, 'n'), (clone $payments)->count());
        $money('payments.amount', $this->sumMoney($legacyPayments, 'amount'), Money::ofPaisa((int) (clone $payments)->sum('amount')));
        $count('payments.synthesised', $this->expectedSynthetic($context, $legacySaleIds), DB::table('payments')->where('tenant_id', $tenantId)->where('branch_id', $branchId)->where('number', 'like', 'L-S%')->count());

        // Returns.
        $legacyReturns = $legacy->table(Returns::TABLE)->where('store_id', $store);
        $count('sale_returns', (clone $legacyReturns)->count(), DB::table('sale_returns')->where('tenant_id', $tenantId)->where('branch_id', $branchId)->whereNotNull('legacy_id')->count());

        return $rows;
    }

    /**
     * Legacy sales whose positive payment rows fall short of total − due: each needs one synthesised
     * payment (Payments step).
     *
     * @param  list<int>  $legacySaleIds
     */
    private function expectedSynthetic(ImportContext $context, array $legacySaleIds): int
    {
        $expected = 0;
        foreach (array_chunk($legacySaleIds, 1000) as $chunk) {
            $paid = $context->legacy()->table(Payments::TABLE)->whereIn('invoice_id', $chunk)->where('paid_amount', '>', 0)
                ->groupBy('invoice_id')->selectRaw('invoice_id, SUM(ROUND(CAST(paid_amount AS DECIMAL(20,4)), 2)) AS paid')->pluck('paid', 'invoice_id');
            $sales = $context->select(Sales::TABLE, ['id'], [], ['total_amount', 'total_discount', 'total_due'])->whereIn('id', $chunk)->get();
            foreach ($sales as $sale) {
                $owed = Money::parseRounded((string) $sale->total_amount)->minus(Money::parseRounded((string) $sale->total_discount))->minus(Money::parseRounded((string) $sale->total_due));
                if ($owed->compareTo(Money::parseRounded((string) ($paid[$sale->id] ?? '0'))) > 0) {
                    $expected++;
                }
            }
        }

        return $expected;
    }

    /** @return list<int> */
    private function ids(Builder $query): array
    {
        return array_values($query->orderBy('id')->pluck('id')->map(fn (mixed $id): int => (int) $id)->all());
    }

    /** Σ of a legacy FLOAT money column, each value rounded half-up to the paisa first (like the import). */
    private function legacySum(Builder $query, string $column): Money
    {
        $expression = match ($column) {
            'total_amount' => 'COALESCE(SUM(ROUND(CAST(total_amount AS DECIMAL(20,4)), 2)), 0) AS v',
            'total_discount' => 'COALESCE(SUM(ROUND(CAST(total_discount AS DECIMAL(20,4)), 2)), 0) AS v',
            'total_due' => 'COALESCE(SUM(ROUND(CAST(total_due AS DECIMAL(20,4)), 2)), 0) AS v',
            default => throw new InvalidArgumentException("No legacy sum for [{$column}]."),
        };

        return Money::parseRounded((string) ($query->selectRaw($expression)->value('v') ?? '0'));
    }

    /** @param list<int> $legacyIds */
    private function countLegacy(string $table, int $tenantId, array $legacyIds): int
    {
        return $this->sumInt($this->chunked($legacyIds, fn (array $chunk): array => [
            'n' => DB::table($table)->where('tenant_id', $tenantId)->whereIn('legacy_id', $chunk)->count(),
        ]), 'n');
    }

    /**
     * @param  list<int>  $ids
     * @param  callable(list<int>): array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function chunked(array $ids, callable $query): array
    {
        $results = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            $results[] = $query($chunk);
        }

        return $results;
    }

    /** @param list<array<string, mixed>> $parts */
    private function sumInt(array $parts, string $key): int
    {
        $sum = 0;
        foreach ($parts as $part) {
            $sum += (int) ($part[$key] ?? 0);
        }

        return $sum;
    }

    /** @param list<array<string, mixed>> $parts */
    private function sumMoney(array $parts, string $key): Money
    {
        $sum = Money::zero();
        foreach ($parts as $part) {
            $sum = $sum->plus(Money::parseRounded((string) ($part[$key] ?? '0')));
        }

        return $sum;
    }
}
