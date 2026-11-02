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
 * A suspended tenant or a deactivated user is logged out on the next request.
 * Platform admins (no tenant) pass; ResolveTenant keeps them off tenant routes.
 */
final class EnsureTenantActive
{
    public const string SUSPENDED_MESSAGE_KEY = 'auth.tenant_suspended';

    public const string DEACTIVATED_MESSAGE_KEY = 'auth.deactivated';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->is_active) {
            return $this->reject($request, 'user_deactivated', self::DEACTIVATED_MESSAGE_KEY, 403);
        }

        if ($user instanceof User && $user->tenant_id !== null) {
            $tenant = $user->tenant;

            if (! $tenant instanceof Tenant || $tenant->status !== Tenant::STATUS_ACTIVE) {
                return $this->reject($request, 'tenant_suspended', self::SUSPENDED_MESSAGE_KEY, 403);
            }
        }

        return $next($request);
    }

    private function reject(Request $request, string $code, string $messageKey, int $status): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['code' => $code, 'message' => $messageKey], $status);
        }

        return redirect()->route('login')->with('status_key', $messageKey);
    }
}
