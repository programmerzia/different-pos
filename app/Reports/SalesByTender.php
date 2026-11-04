<?php

declare(strict_types=1);

namespace App\Reports;

use Illuminate\Support\Facades\DB;

/** Money in and out by payment method (customer payments, not vendor payments). */
final class SalesByTender extends AbstractReport
{
    public function name(): string
    {
        return 'sales-by-tender';
    }

    public function group(): string
    {
        return 'sales';
    }

    public function filters(): array
    {
        return ['cashier'];
    }

    public function run(ReportParameters $parameters): ReportResult
    {
        $query = DB::table('payments')
            ->join('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->where('payments.tenant_id', $this->tenantId())
            ->where('payment_methods.tenant_id', $this->tenantId())
            ->where('payments.party_type', 'customer')
            ->whereNull('payments.voided_at')
            ->where('payments.paid_at', '>=', $parameters->from()->format('Y-m-d H:i:s'))
            ->where('payments.paid_at', '<', $parameters->toExclusive()->format('Y-m-d H:i:s'));
        $this->branches($query, $parameters, 'payments.branch_id');
        if (($cashier = $this->cashierId($parameters)) !== null) {
            $query->where('payments.received_by', $cashier);
        }

        $records = $query
            ->groupBy('payment_methods.id', 'payment_methods.name', 'payment_methods.type', 'payment_methods.sort_order')
            ->select(
                'payment_methods.name',
                'payment_methods.type',
                DB::raw("SUM(payments.direction = 'in') AS payments_in"),
                DB::raw("COALESCE(SUM(CASE WHEN payments.direction = 'in' THEN payments.amount END), 0) AS received"),
                DB::raw("COALESCE(SUM(CASE WHEN payments.direction = 'out' THEN payments.amount END), 0) AS refunded"),
            )
            ->orderBy('payment_methods.sort_order')
            ->orderBy('payment_methods.id')
            ->get();

        $rows = [];
        foreach ($records as $r) {
            $received = $this->intOf($r->received);
            $refunded = $this->intOf($r->refunded);
            $rows[] = [
                'method' => $this->text($r->name),
                'type' => $this->text($r->type),
                'payments' => $this->intOf($r->payments_in),
                'received' => $received,
                'refunded' => $refunded,
                'net' => $received - $refunded,
            ];
        }

        $columns = [
            new ReportColumn('method', 'Method', ColumnType::Text),
            new ReportColumn('type', 'Type', ColumnType::Text, valuePrefix: 'reports.tender.'),
            new ReportColumn('payments', 'Payments', ColumnType::Int),
            new ReportColumn('received', 'Received', ColumnType::Money),
            new ReportColumn('refunded', 'Refunded', ColumnType::Money),
            new ReportColumn('net', 'Net', ColumnType::Money),
        ];

        return new ReportResult($columns, $rows, $this->totals($columns, $rows));
    }
}
