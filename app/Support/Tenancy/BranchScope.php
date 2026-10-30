<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * where branch_id = current. Fails closed. Cross-branch reads must call withoutBranchScope() explicitly.
 *
 * @template TModel of Model
 *
 * @implements Scope<TModel>
 */
final class BranchScope implements Scope
{
    /**
     * @param  Builder<covariant TModel>  $builder
     * @param  TModel  $model
     */
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->isBypassed()) {
            return;
        }

        $builder->where($model->qualifyColumn('branch_id'), $context->branchId());
    }
}
