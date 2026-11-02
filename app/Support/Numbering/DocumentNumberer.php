<?php

declare(strict_types=1);

namespace App\Support\Numbering;

use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Allocates gap-free document numbers from document_sequences. Must run inside Transaction::run():
 * the row stays locked until commit, so a rolled-back document gives its number back.
 * POS sale numbers are not allocated here (the device numbers them).
 */
final readonly class DocumentNumberer
{
    public const string CUSTOMER = 'customer';

    /** Source of auto-generated EAN-13 barcodes: a plain, never-resetting tenant sequence. */
    public const string BARCODE = 'barcode';

    private const int CUSTOMER_START = 1_000_001;

    public function __construct(private TenantContext $context) {}

    public function next(string $type, ?int $branchId = null): string
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('DocumentNumberer::next() must be called inside Transaction::run().');
        }

        $tenantId = $this->context->tenantId();
        $year = now()->year;

        $this->ensureRow($tenantId, $type, $branchId, $year);

        $row = DB::table('document_sequences')
            ->where('tenant_id', $tenantId)
            ->where('type', $type)
            ->when(
                $branchId === null,
                fn ($query) => $query->whereNull('branch_id'),
                fn ($query) => $query->where('branch_id', $branchId),
            )
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            throw new LogicException("Document sequence [{$type}] could not be locked.");
        }

        $number = (int) $row->next_number;
        $yearly = (bool) $row->reset_yearly;

        if ($yearly && (int) $row->current_year !== $year) {
            $number = 1;
        }

        DB::table('document_sequences')->where('id', $row->id)->update([
            'next_number' => $number + 1,
            'current_year' => $year,
        ]);

        return $row->prefix
            .($yearly ? substr((string) $year, -2) : '')
            .str_pad((string) $number, (int) $row->pad, '0', STR_PAD_LEFT);
    }

    private function ensureRow(int $tenantId, string $type, ?int $branchId, int $year): void
    {
        $exists = DB::table('document_sequences')
            ->where('tenant_id', $tenantId)
            ->where('type', $type)
            ->when(
                $branchId === null,
                fn ($query) => $query->whereNull('branch_id'),
                fn ($query) => $query->where('branch_id', $branchId),
            )
            ->exists();

        if ($exists) {
            return;
        }

        $customer = $type === self::CUSTOMER;
        $plain = $customer || $type === self::BARCODE;
        $code = $branchId === null
            ? null
            : DB::table('branches')->where('tenant_id', $tenantId)->where('id', $branchId)->value('code');

        if ($branchId !== null && $code === null) {
            throw new LogicException("Branch [{$branchId}] does not belong to the current tenant.");
        }

        $prefix = $plain ? '' : ($code === null ? '' : $code.'-').strtoupper($type).'-';

        DB::table('document_sequences')->insertOrIgnore([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'type' => $type,
            'prefix' => $prefix,
            'next_number' => $customer ? self::CUSTOMER_START : 1,
            'pad' => $customer ? 7 : 6,
            'reset_yearly' => ! $plain,
            'current_year' => $year,
        ]);
    }
}
