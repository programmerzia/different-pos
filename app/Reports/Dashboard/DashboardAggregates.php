<?php

declare(strict_types=1);

namespace App\Reports\Dashboard;

use App\Reports\ReportParameters;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The dashboard's own grouped queries (the report classes cover the rest). Query builder, so every
 * query filters by tenant_id explicitly and by the branches of the parameters (null = all).
 * Money is integer paisa.
 */
final readonly class DashboardAggregates
{
    /** Tenders that move no money (credit notes), left out of the payment mix and collections. */
    private const array NOT_MONEY = ['customer_credit', 'exchange_credit'];

    public function __construct(private TenantContext $context) {}

    /**
     * Completed sales of the period: invoices, net sales (after returns), gross profit (invoiced
     * minus tax minus cost, before returns, as Sales by day) and revenue without tax.
     *
     * @return array{invoices: int, sales: int, profit: int, revenue: int}
     */
    public function sales(ReportParameters $p): array
    {
        $row = $this->completedSales($p)
            ->selectRaw('COUNT(*) AS invoices, COALESCE(SUM(sales.total), 0) AS total, COALESCE(SUM(sales.returned_total), 0) AS returned, COALESCE(SUM(sales.tax_total), 0) AS tax, COALESCE(SUM(sales.cogs_total), 0) AS cogs')
            ->first();

        $total = $this->int($row?->total);
        $tax = $this->int($row?->tax);

        return [
            'invoices' => $this->int($row?->invoices),
            'sales' => $total - $this->int($row?->returned),
            'profit' => $total - $tax - $this->int($row?->cogs),
            'revenue' => $total - $tax,
        ];
    }

    /**
     * Returns booked in the period (by return date).
     *
     * @return array{amount: int, count: int}
     */
    public function returns(ReportParameters $p): array
    {
        $query = DB::table('sale_returns')
            ->where('sale_returns.tenant_id', $this->tenantId())
            ->where('sale_returns.returned_at', '>=', $p->from()->format('Y-m-d H:i:s'))
            ->where('sale_returns.returned_at', '<', $p->toExclusive()->format('Y-m-d H:i:s'));
        $this->branches($query, $p, 'sale_returns.branch_id');
        $row = $query->selectRaw('COUNT(*) AS n, COALESCE(SUM(sale_returns.total), 0) AS amount')->first();

        return ['amount' => $this->int($row?->amount), 'count' => $this->int($row?->n)];
    }

    /**
     * Due collections: money received from customers in the period that did not tender a sale at
     * its checkout (a tender's paid_at equals its sale's sold_at).
     */
    public function collections(ReportParameters $p): int
    {
        $query = $this->moneyIn($p)
            ->whereNotExists(static function (Builder $q): void {
                $q->selectRaw('1')
                    ->from('payment_allocations')
                    ->join('sales', 'sales.id', '=', 'payment_allocations.allocatable_id')
                    ->whereColumn('payment_allocations.payment_id', 'payments.id')
                    ->where('payment_allocations.allocatable_type', 'sale')
                    ->whereColumn('sales.sold_at', 'payments.paid_at');
            });

        return $this->int($query->sum('payments.amount'));
    }

    /**
     * Money received per payment method in the period, largest first.
     *
     * @return list<array{method: string, type: string, amount: int}>
     */
    public function paymentMix(ReportParameters $p): array
    {
        $rows = $this->moneyIn($p)
            ->groupBy('payment_methods.id', 'payment_methods.name', 'payment_methods.type')
            ->select('payment_methods.name', 'payment_methods.type', DB::raw('SUM(payments.amount) AS amount'))
            ->orderByDesc('amount')
            ->orderBy('payment_methods.id')
            ->get();

        $mix = [];
        foreach ($rows as $r) {
            $mix[] = ['method' => $this->text($r->name) ?? '', 'type' => $this->text($r->type) ?? '', 'amount' => $this->int($r->amount)];
        }

        return $mix;
    }

    /**
     * Net sales per local hour (0-23) of the period.
     *
     * @return array<int, int>
     */
    public function salesByHour(ReportParameters $p): array
    {
        $rows = $this->completedSales($p)
            ->selectRaw("HOUR(CONVERT_TZ(sales.sold_at, '+00:00', ?)) AS h", [$p->offset()])
            ->addSelect(DB::raw('SUM(sales.total - sales.returned_total) AS net'))
            ->groupBy('h')
            ->get();

        $hours = array_fill(0, 24, 0);
        foreach ($rows as $r) {
            $hours[$this->int($r->h)] = $this->int($r->net);
        }

        return $hours;
    }

    /**
     * Number of sales per local weekday (0 = Sunday … 6 = Saturday) and hour.
     *
     * @return list<array{dow: int, hour: int, count: int}>
     */
    public function heatmap(ReportParameters $p): array
    {
        $rows = $this->completedSales($p)
            ->selectRaw("DAYOFWEEK(CONVERT_TZ(sales.sold_at, '+00:00', ?)) - 1 AS dow", [$p->offset()])
            ->selectRaw("HOUR(CONVERT_TZ(sales.sold_at, '+00:00', ?)) AS h", [$p->offset()])
            ->addSelect(DB::raw('COUNT(*) AS n'))
            ->groupBy('dow', 'h')
            ->orderBy('dow')
            ->orderBy('h')
            ->get();

        $cells = [];
        foreach ($rows as $r) {
            $cells[] = ['dow' => $this->int($r->dow), 'hour' => $this->int($r->h), 'count' => $this->int($r->n)];
        }

        return $cells;
    }

    /**
     * Net sales and gross profit per branch for the period (every branch, whatever the parameters
     * say), keyed by branch id.
     *
     * @return array<int, array{sales: int, profit: int}>
     */
    public function salesByBranch(string $from, string $to, string $timezone): array
    {
        $p = new ReportParameters($from, $to, $timezone, null);
        $rows = $this->completedSales($p)
            ->groupBy('sales.branch_id')
            ->select('sales.branch_id', DB::raw('SUM(sales.total - sales.returned_total) AS net'), DB::raw('SUM(sales.total - sales.tax_total - sales.cogs_total) AS profit'))
            ->get();

        $byBranch = [];
        foreach ($rows as $r) {
            $byBranch[$this->int($r->branch_id)] = ['sales' => $this->int($r->net), 'profit' => $this->int($r->profit)];
        }

        return $byBranch;
    }

    /**
     * Net sales (after returns, the Net Sales KPI's definition) per district of the sale's customer:
     * the `limit` largest districts, the rest as `others`, and `unset` for sales whose customer has
     * no district (walk-in, or a customer with no location). `total` = rows + others + unset.
     *
     * @return array{rows: list<array{id: int, name_en: string, name_bn: string, sales: int, invoices: int}>, others: array{districts: int, sales: int, invoices: int}, unset: array{sales: int, invoices: int}, total: int}
     */
    public function salesByDistrict(ReportParameters $p, int $limit = 6): array
    {
        $empty = ['rows' => [], 'others' => ['districts' => 0, 'sales' => 0, 'invoices' => 0], 'unset' => ['sales' => 0, 'invoices' => 0], 'total' => 0];
        if (! Schema::hasTable('geo_districts')) {
            return $empty;
        }

        $tenantId = $this->tenantId();
        $records = $this->completedSales($p)
            ->leftJoin('customers', static function ($join) use ($tenantId): void {
                $join->on('customers.id', '=', 'sales.customer_id')->where('customers.tenant_id', '=', $tenantId);
            })
            ->leftJoin('geo_districts AS g', 'g.id', '=', 'customers.geo_district_id')
            ->groupBy('g.id', 'g.name_en', 'g.name_bn')
            ->select(
                DB::raw('g.id AS district_id'),
                DB::raw('g.name_en AS name_en'),
                DB::raw('g.name_bn AS name_bn'),
                DB::raw('COUNT(*) AS invoices'),
                DB::raw('COALESCE(SUM(sales.total - sales.returned_total), 0) AS net'),
            )
            ->get();

        $districts = [];
        $result = $empty;
        foreach ($records as $r) {
            $net = $this->int($r->net);
            $invoices = $this->int($r->invoices);
            $result['total'] += $net;
            if ($r->district_id === null) {
                $result['unset'] = ['sales' => $net, 'invoices' => $invoices];

                continue;
            }
            $districts[] = [
                'id' => $this->int($r->district_id),
                'name_en' => $this->text($r->name_en) ?? '',
                'name_bn' => $this->text($r->name_bn) ?? '',
                'sales' => $net,
                'invoices' => $invoices,
            ];
        }

        usort($districts, static fn (array $a, array $b): int => [$b['sales'], $a['id']] <=> [$a['sales'], $b['id']]);
        $result['rows'] = array_slice($districts, 0, $limit);
        foreach (array_slice($districts, $limit) as $d) {
            $result['others']['districts']++;
            $result['others']['sales'] += $d['sales'];
            $result['others']['invoices'] += $d['invoices'];
        }

        return $result;
    }

    /**
     * The latest completed sales up to the end of the period.
     *
     * @return list<array{id: int, number: string|null, customer: string|null, walk_in: bool, sold_at: string, total: int, payment_status: string|null}>
     */
    public function recentSales(ReportParameters $p, int $limit = 5): array
    {
        $query = DB::table('sales')
            ->join('customers', 'customers.id', '=', 'sales.customer_id')
            ->where('sales.tenant_id', $this->tenantId())
            ->where('customers.tenant_id', $this->tenantId())
            ->where('sales.status', 'completed')
            ->where('sales.sold_at', '<', $p->toExclusive()->format('Y-m-d H:i:s'));
        $this->branches($query, $p, 'sales.branch_id');
        $rows = $query
            ->orderByDesc('sales.sold_at')
            ->orderByDesc('sales.id')
            ->limit($limit)
            ->get(['sales.id', 'sales.number', 'sales.sold_at', 'sales.total', 'sales.payment_status', 'customers.name', 'customers.is_walk_in']);

        $recent = [];
        foreach ($rows as $r) {
            $recent[] = [
                'id' => $this->int($r->id),
                'number' => $this->text($r->number),
                'customer' => $this->text($r->name),
                'walk_in' => (bool) $r->is_walk_in,
                'sold_at' => $this->text($r->sold_at) ?? '',
                'total' => $this->int($r->total),
                'payment_status' => $this->text($r->payment_status),
            ];
        }

        return $recent;
    }

    /**
     * Customers with the largest open dues, with the age in days of their oldest unpaid invoice.
     *
     * @return list<array{id: int, uuid: string|null, customer: string|null, customer_no: string|null, phone: string|null, due: int, days: int}>
     */
    public function debtors(ReportParameters $p, int $limit = 5): array
    {
        $query = DB::table('sales')
            ->join('customers', 'customers.id', '=', 'sales.customer_id')
            ->where('sales.tenant_id', $this->tenantId())
            ->where('customers.tenant_id', $this->tenantId())
            ->where('sales.status', 'completed')
            ->where('sales.due', '>', 0);
        $this->branches($query, $p, 'sales.branch_id');
        $rows = $query
            ->groupBy('customers.id', 'customers.uuid', 'customers.name', 'customers.customer_no', 'customers.phone')
            ->select('customers.id', 'customers.uuid', 'customers.name', 'customers.customer_no', 'customers.phone', DB::raw('SUM(sales.due) AS due'), DB::raw('MIN(sales.sold_at) AS oldest'))
            ->orderByDesc('due')
            ->orderBy('customers.id')
            ->limit($limit)
            ->get();

        $today = $p->today();
        $debtors = [];
        foreach ($rows as $r) {
            $oldest = $this->text($r->oldest);
            $debtors[] = [
                'id' => $this->int($r->id),
                'uuid' => $this->text($r->uuid),
                'customer' => $this->text($r->name),
                'customer_no' => $this->text($r->customer_no),
                'phone' => $this->text($r->phone),
                'due' => $this->int($r->due),
                'days' => $oldest === null ? 0 : max(0, (int) CarbonImmutable::parse($oldest, 'UTC')->setTimezone($p->timezone)->startOfDay()->diffInDays($today)),
            ];
        }

        return $debtors;
    }

    /**
     * First-run checklist state of the tenant.
     *
     * @return array{logo: bool, payment_methods: bool, products: bool, customers: bool, first_sale: bool}
     */
    public function setup(): array
    {
        $tenant = $this->context->tenant();
        $tenantId = $this->tenantId();

        return [
            'logo' => $tenant->logoPath() !== null,
            'payment_methods' => DB::table('payment_methods')->where('tenant_id', $tenantId)->whereNotIn('type', self::NOT_MONEY)->exists(),
            'products' => DB::table('products')->where('tenant_id', $tenantId)->exists(),
            'customers' => DB::table('customers')->where('tenant_id', $tenantId)->where('is_walk_in', false)->exists(),
            'first_sale' => DB::table('sales')->where('tenant_id', $tenantId)->exists(),
        ];
    }

    private function completedSales(ReportParameters $p): Builder
    {
        $query = DB::table('sales')
            ->where('sales.tenant_id', $this->tenantId())
            ->where('sales.status', 'completed')
            ->where('sales.sold_at', '>=', $p->from()->format('Y-m-d H:i:s'))
            ->where('sales.sold_at', '<', $p->toExclusive()->format('Y-m-d H:i:s'));
        $this->branches($query, $p, 'sales.branch_id');

        return $query;
    }

    /** Money (not credit notes) received from customers in the period, not voided. */
    private function moneyIn(ReportParameters $p): Builder
    {
        $query = DB::table('payments')
            ->join('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->where('payments.tenant_id', $this->tenantId())
            ->where('payment_methods.tenant_id', $this->tenantId())
            ->where('payments.party_type', 'customer')
            ->where('payments.direction', 'in')
            ->whereNull('payments.voided_at')
            ->whereNotIn('payment_methods.type', self::NOT_MONEY)
            ->where('payments.paid_at', '>=', $p->from()->format('Y-m-d H:i:s'))
            ->where('payments.paid_at', '<', $p->toExclusive()->format('Y-m-d H:i:s'));
        $this->branches($query, $p, 'payments.branch_id');

        return $query;
    }

    private function branches(Builder $query, ReportParameters $p, string $column): void
    {
        if ($p->branchIds !== null) {
            $query->whereIn($column, $p->branchIds);
        }
    }

    private function tenantId(): int
    {
        return $this->context->tenantId();
    }

    private function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function text(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }
}
