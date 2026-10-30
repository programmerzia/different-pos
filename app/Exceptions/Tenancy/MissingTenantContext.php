<?php

declare(strict_types=1);

namespace App\Exceptions\Tenancy;

use LogicException;

/** A tenant-scoped query or write ran without a tenant (or branch) context. Always a programming error. */
final class MissingTenantContext extends LogicException
{
    public static function tenant(): self
    {
        return new self('No tenant context. Set it via TenantContext::run() or use TenantContext::withoutTenancy() for platform code.');
    }

    public static function branch(): self
    {
        return new self('No branch context for a branch-scoped model. Set it via TenantContext::run() or call withoutBranchScope().');
    }
}
