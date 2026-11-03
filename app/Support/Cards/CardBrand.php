<?php

declare(strict_types=1);

namespace App\Support\Cards;

use App\Support\Tenancy\TenantContext;

/**
 * What the membership card shows besides the member: business name, logo, helpline (branch phone,
 * else the business phone), branch name and BIN. One source for the studio, the profile and print.
 */
final readonly class CardBrand
{
    public function __construct(private TenantContext $context) {}

    /** @return array{name: string, logo_url: string|null, phone: string|null, branch: string|null, bin: string|null} */
    public function toArray(): array
    {
        $tenant = $this->context->tenant();
        $branch = $this->context->hasBranch() ? $this->context->branch() : null;

        return [
            'name' => $tenant->name,
            'logo_url' => $tenant->logoUrl(),
            'phone' => $branch?->phone !== null && $branch->phone !== '' ? $branch->phone : $tenant->phone,
            'branch' => $branch?->name,
            'bin' => $tenant->vat_bin,
        ];
    }
}
