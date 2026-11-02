<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Requests\Api\UpdatePreferencesRequest;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/** Shares theme, colour scheme and density with the root view so the first paint has no flash. */
final class HandleAppearance
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $preferences = $user instanceof User ? ($user->preferences ?? []) : [];

        $scheme = $request->cookie('scheme') ?? $preferences['scheme'] ?? 'teal';
        $density = $request->cookie('density') ?? $preferences['density'] ?? 'comfortable';

        View::share('appearance', $request->cookie('appearance') ?? $preferences['theme'] ?? 'system');
        View::share('scheme', in_array($scheme, UpdatePreferencesRequest::SCHEMES, true) ? $scheme : 'teal');
        View::share('density', $density === 'compact' ? 'compact' : 'comfortable');

        return $next($request);
    }
}
