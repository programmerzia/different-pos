<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StockTransferStatus;
use App\Models\StockTransfer;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantPermissions;
use App\Support\Tenancy\TenantContext;

final class StockTransferPolicy
{
    use ChecksTenantPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'stock.view');
    }

    public function view(User $user, StockTransfer $model): bool
    {
        return $this->allows($user, 'stock.view', $model);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'stock.transfer');
    }

    /** Only the destination branch receives, and only while the goods are in transit. */
    public function receive(User $user, StockTransfer $model): bool
    {
        return $this->allows($user, 'stock.transfer', $model)
            && $model->status === StockTransferStatus::InTransit
            && app(TenantContext::class)->branchId() === $model->to_branch_id;
    }
}
