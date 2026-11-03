<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locale = user preference, else (guests only) the `locale` cookie set by the sign-in language switch,
 * else tenant default; only en|bn.
 */
final readonly class SetLocale
{
    public const array SUPPORTED = ['en', 'bn'];

    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $preferred = $user instanceof User ? ($user->preferences['locale'] ?? null) : $request->cookie('locale');
        $tenantLocale = $this->context->hasTenant() ? $this->context->tenant()->locale : null;

        foreach ([$preferred, $tenantLocale] as $candidate) {
            if (is_string($candidate) && in_array($candidate, self::SUPPORTED, true)) {
                app()->setLocale($candidate);

                break;
            }
        }

        return $next($request);
    }
}
