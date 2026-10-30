<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Exceptions\Tenancy\CrossTenantWrite;
use App\Models\Branch;
use App\Support\Tenancy\BranchScope;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Branch isolation, layered on top of BelongsToTenant (use both).
 * Reports with reports.all_branches call withoutBranchScope() explicitly; the tenant scope still applies.
 *
 * @property int $branch_id
 *
 * @mixin Model
 */
trait BelongsToBranch
{
    public function initializeBelongsToBranch(): void
    {
        $this->mergeCasts(['branch_id' => 'integer']);
    }

    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope(new BranchScope);

        static::creating(static function (Model $model): void {
            $context = app(TenantContext::class);
            if ($context->isBypassed()) {
                return;
            }
            $current = $context->branchId();
            if ($model->getAttribute('branch_id') === null) {
                $model->setAttribute('branch_id', $current);
            }
            if ($model->getAttribute('branch_id') !== $current) {
                throw CrossTenantWrite::for($model::class, 'branch_id', $current, $model->getAttribute('branch_id'));
            }
        });

        $guard = static function (Model $model): void {
            $context = app(TenantContext::class);
            if ($context->isBypassed()) {
                return;
            }
            $current = $context->branchId();
            if ($model->isDirty('branch_id') || $model->getOriginal('branch_id') !== $current) {
                throw CrossTenantWrite::for($model::class, 'branch_id', $current, $model->getOriginal('branch_id'));
            }
        };

        static::updating($guard);
        static::deleting($guard);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithoutBranchScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(BranchScope::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
