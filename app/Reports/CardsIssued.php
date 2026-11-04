<?php

declare(strict_types=1);

namespace App\Reports;

use Illuminate\Support\Facades\DB;

/**
 * Membership cards issued in the period, per customer group (customers without a group in one row):
 * first cards (issue_no 1) against reissues (issue_no above 1), and how many of them are active,
 * lost, replaced or revoked today. Cards carry no branch, so the branch scope does not narrow this
 * report. Only counts: the customer PIN is never read.
 */
final class CardsIssued extends AbstractReport
{
    private const string NO_GROUP = 'No group';

    public function name(): string
    {
        return 'cards-issued';
    }

    public function group(): string
    {
        return 'customers';
    }

    public function run(ReportParameters $parameters): ReportResult
    {
        $records = DB::table('membership_cards')
            ->join('customers', 'customers.id', '=', 'membership_cards.customer_id')
            ->leftJoin('customer_groups', fn ($join) => $join->on('customer_groups.id', '=', 'customers.customer_group_id')->where('customer_groups.tenant_id', '=', $this->tenantId()))
            ->where('membership_cards.tenant_id', $this->tenantId())
            ->where('customers.tenant_id', $this->tenantId())
            ->where('membership_cards.issued_at', '>=', $parameters->from()->format('Y-m-d H:i:s'))
            ->where('membership_cards.issued_at', '<', $parameters->toExclusive()->format('Y-m-d H:i:s'))
            ->groupBy('customer_groups.id', 'customer_groups.name')
            ->select(
                DB::raw('customer_groups.name AS group_name'),
                DB::raw('COUNT(*) AS issued'),
                DB::raw('SUM(membership_cards.issue_no = 1) AS first_cards'),
                DB::raw('SUM(membership_cards.issue_no > 1) AS reissued'),
                DB::raw("SUM(membership_cards.status = 'active') AS active"),
                DB::raw("SUM(membership_cards.status = 'lost') AS lost"),
                DB::raw("SUM(membership_cards.status = 'replaced') AS replaced"),
                DB::raw("SUM(membership_cards.status = 'revoked') AS revoked"),
                DB::raw('SUM(membership_cards.print_count) AS prints'),
            )
            ->orderByRaw('COUNT(*) DESC')
            ->orderBy('customer_groups.id')
            ->get();

        $rows = [];
        foreach ($records as $r) {
            $rows[] = [
                'group' => $this->text($r->group_name) ?? self::NO_GROUP,
                'issued' => $this->intOf($r->issued),
                'first_cards' => $this->intOf($r->first_cards),
                'reissued' => $this->intOf($r->reissued),
                'active' => $this->intOf($r->active),
                'lost' => $this->intOf($r->lost),
                'replaced' => $this->intOf($r->replaced),
                'revoked' => $this->intOf($r->revoked),
                'prints' => $this->intOf($r->prints),
            ];
        }

        $columns = [
            new ReportColumn('group', 'Customer group', ColumnType::Text),
            new ReportColumn('issued', 'Cards issued', ColumnType::Int),
            new ReportColumn('first_cards', 'New cards', ColumnType::Int),
            new ReportColumn('reissued', 'Reissued', ColumnType::Int),
            new ReportColumn('active', 'Active now', ColumnType::Int),
            new ReportColumn('lost', 'Lost', ColumnType::Int),
            new ReportColumn('replaced', 'Replaced', ColumnType::Int),
            new ReportColumn('revoked', 'Revoked', ColumnType::Int),
            new ReportColumn('prints', 'Prints', ColumnType::Int),
        ];

        return new ReportResult($columns, $rows, $this->totals($columns, $rows));
    }
}
