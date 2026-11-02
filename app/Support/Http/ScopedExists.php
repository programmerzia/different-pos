<?php

declare(strict_types=1);

namespace App\Support\Http;

use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/** `exists` rules limited to the current tenant (and to live rows when the table soft-deletes). */
final class ScopedExists
{
    public static function in(string $table, string $column = 'id', bool $softDeletes = false): Exists
    {
        $rule = Rule::exists($table, $column)->where('tenant_id', app(TenantContext::class)->tenantId());

        return $softDeletes ? $rule->whereNull('deleted_at') : $rule;
    }
}
