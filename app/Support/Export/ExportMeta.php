<?php

declare(strict_types=1);

namespace App\Support\Export;

use App\Support\Sales\BusinessDay;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/**
 * What a printed export says about itself: the business (name, logo), the branch, the document title,
 * the period or filter line and when it was generated (tenant wall-clock, Asia/Dhaka by default).
 */
final readonly class ExportMeta
{
    public function __construct(
        public string $title,
        public string $tenantName,
        public ?string $tenantLogoPath,
        public ?string $branchName,
        public ?string $period,
        public CarbonImmutable $generatedAt,
        public string $timezone,
    ) {}

    /** The current tenant and branch, generated now in the tenant's timezone. */
    public static function for(TenantContext $context, string $title, ?string $period = null): self
    {
        $tenant = $context->tenant();
        $timezone = BusinessDay::timezone($tenant);
        $logo = $tenant->logoPath();

        return new self(
            title: $title,
            tenantName: $tenant->name,
            tenantLogoPath: $logo !== null && Storage::disk('public')->exists($logo) ? Storage::disk('public')->path($logo) : null,
            branchName: $context->hasBranch() ? $context->branch()->name : null,
            period: $period,
            generatedAt: CarbonImmutable::now($timezone),
            timezone: $timezone,
        );
    }

    /** `from – to` for a period report, or the single date of a point-in-time one. */
    public static function periodLabel(string $from, ?string $to): string
    {
        return $to === null || $to === $from ? $from : "{$from} – {$to}";
    }
}
