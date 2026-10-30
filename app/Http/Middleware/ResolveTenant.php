<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/** Sets the tenant context and the spatie permission team from the authenticated user. */
final readonly class ResolveTenant
{
    public function __construct(
        private TenantContext $context,
        private PermissionRegistrar $permissions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        // Platform admins (tenant_id NULL) belong on /platform only.
        abort_if($user->tenant_id === null, 403);

        $tenant = $user->tenant;
        abort_unless($tenant instanceof Tenant, 403);

        $this->context->set($tenant);
        $this->permissions->setPermissionsTeamId($tenant->id);

        return $next($request);
    }
}
