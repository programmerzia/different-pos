<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A suspended tenant is logged out on the next request.
 * Platform admins (no tenant) pass; ResolveTenant keeps them off tenant routes.
 */
final class EnsureTenantActive
{
    public const string SUSPENDED_MESSAGE_KEY = 'auth.tenant_suspended';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->tenant_id !== null) {
            $tenant = $user->tenant;

            if (! $tenant instanceof Tenant || $tenant->status !== Tenant::STATUS_ACTIVE) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson()) {
                    return response()->json(['code' => 'tenant_suspended', 'message' => self::SUSPENDED_MESSAGE_KEY], 403);
                }

                return redirect()->route('login')->with('status_key', self::SUSPENDED_MESSAGE_KEY);
            }
        }

        return $next($request);
    }
}
