<?php

declare(strict_types=1);

namespace App\Support\Platform;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;

/** What ProvisionTenant created. The temporary password is only ever handed to the invitation mail. */
final readonly class ProvisionedTenant
{
    public function __construct(
        public Tenant $tenant,
        public Branch $branch,
        public User $owner,
        public string $temporaryPassword,
    ) {}
}
