<?php

declare(strict_types=1);

namespace App\Legacy\Steps;

use App\Enums\SaleStatus;
use App\Legacy\ImportContext;
use App\Models\Customer;
use App\Support\Money\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Step 16: customers.balance = Σ due of completed sales, last_purchase_at = latest sale;
 * vendors.balance = Σ purchases.due. Customers are saved through Eloquent so their sync_version moves.
 */
final readonly class Recompute implements Step
{
    public function name(): string
    {
        return 'Recompute';
    }

    public function run(ImportContext $context): void
    {
        $tenantId = $context->tenantId();
        $totals = DB::table('sales')->where('tenant_id', $tenantId)->where('status', SaleStatus::Completed->value)
            ->groupBy('customer_id')
            ->selectRaw('customer_id, SUM(due) AS due, MAX(sold_at) AS last_sold_at')
            ->get()->keyBy('customer_id');

        Customer::query()->withTrashed()->orderBy('id')->chunkById($context->options->chunk, function ($customers) use ($totals, $context): void {
            foreach ($customers as $customer) {
                $row = $totals->get($customer->id);
                $customer->balance = Money::ofPaisa($row === null ? 0 : (int) $row->due);
                $customer->last_purchase_at = $row === null ? null : Carbon::parse((string) $row->last_sold_at, 'UTC');
                if ($customer->isDirty()) {
                    $customer->save();
                    $context->created();
                } else {
                    $context->skipped();
                }
            }
        });

        $vendorDue = DB::table('purchases')->where('tenant_id', $tenantId)->groupBy('vendor_id')->selectRaw('vendor_id, SUM(due) AS due')->pluck('due', 'vendor_id');
        DB::table('vendors')->where('tenant_id', $tenantId)->orderBy('id')->get(['id', 'balance'])->each(function (stdClass $vendor) use ($vendorDue): void {
            $due = (int) ($vendorDue[$vendor->id] ?? 0);
            if ((int) $vendor->balance !== $due) {
                DB::table('vendors')->where('id', $vendor->id)->update(['balance' => $due]);
            }
        });
    }
}
