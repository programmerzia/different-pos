<?php

declare(strict_types=1);

namespace App\Support\Sales;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permission checks for the sales Actions. Pins the permission team to the current tenant, so the
 * checks also hold outside an HTTP request (queue, console, tests). An approver is a manager who
 * authorised an override at the counter: their permissions count next to the actor's, but only if
 * they belong to the same tenant.
 */
final readonly class SaleGate
{
    public function __construct(private TenantContext $context, private PermissionRegistrar $registrar) {}

    /**
     * @param  Model|class-string<Model>  $subject
     *
     * @throws AuthorizationException
     */
    public function authorize(User $actor, string $ability, Model|string $subject): void
    {
        $this->pinTeam();
        Gate::forUser($actor)->authorize($ability, $subject);
    }

    public function allows(User $actor, ?User $approver, string $permission): bool
    {
        $this->pinTeam();

        return array_any([$actor, $approver], fn ($user): bool => $user instanceof User && $user->tenant_id === $this->context->tenantId() && $user->checkPermissionTo($permission));
    }

    /** The highest roles.max_discount_pct_bp among the actor's (and approver's) roles in this tenant. */
    public function discountCapBp(User $actor, ?User $approver): int
    {
        $cap = 0;
        foreach ([$actor, $approver] as $user) {
            if (! $user instanceof User || $user->tenant_id !== $this->context->tenantId()) {
                continue;
            }
            $max = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_type', $user->getMorphClass())
                ->where('model_has_roles.model_id', $user->id)
                ->where('model_has_roles.tenant_id', $this->context->tenantId())
                ->max('roles.max_discount_pct_bp');
            $cap = max($cap, (int) $max);
        }

        return min($cap, 10_000);
    }

    private function pinTeam(): void
    {
        $this->registrar->setPermissionsTeamId($this->context->tenantId());
    }
}
