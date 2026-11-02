<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** A user flagged must_change_password (migrated from the legacy system) can reach nothing else until they set a new one. */
final class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->must_change_password) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['code' => 'password_change_required'], 409);
        }

        return redirect()->route('password.change');
    }
}
