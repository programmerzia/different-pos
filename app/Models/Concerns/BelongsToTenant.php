<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Exceptions\Tenancy\CrossTenantWrite;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant isolation for a model with a tenant_id column (04_TRD §3.3, 05_Schema §11):
 * - global scope tenant_id = current (fails closed without a context);
 * - tenant_id auto-filled on create;
 * - any create/update/delete of a row of another tenant throws CrossTenantWrite.
 *
 * @property int $tenant_id
 *
 * @mixin Model
 */
trait BelongsToTenant
{
    public function initializeBelongsToTenant(): void
    {
        $this->mergeCasts(['tenant_id' => 'integer']);
    }

    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(static function (Model $model): void {
            $context = app(TenantContext::class);
            if ($context->isBypassed()) {
                return;
            }
            $current = $context->tenantId();
            if ($model->getAttribute('tenant_id') === null) {
                $model->setAttribute('tenant_id', $current);
            }
            if ($model->getAttribute('tenant_id') !== $current) {
                throw CrossTenantWrite::for($model::class, 'tenant_id', $current, $model->getAttribute('tenant_id'));
            }
        });

        $guard = static function (Model $model): void {
            $context = app(TenantContext::class);
            if ($context->isBypassed()) {
                return;
            }
            $current = $context->tenantId();
            if ($model->isDirty('tenant_id') || $model->getOriginal('tenant_id') !== $current) {
                throw CrossTenantWrite::for($model::class, 'tenant_id', $current, $model->getOriginal('tenant_id'));
            }
        };

        static::updating($guard);
        static::deleting($guard);
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
