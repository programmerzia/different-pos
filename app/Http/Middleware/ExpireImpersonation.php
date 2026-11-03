<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Platform\EndImpersonation;
use App\Support\Platform\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Ends an impersonation that passed its 60-minute limit (05_Schema §1) and sends the admin back to the console. */
final readonly class ExpireImpersonation
{
    public function __construct(private Impersonation $impersonation, private EndImpersonation $end) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! $this->impersonation->isActive() || ! $this->impersonation->isExpired()) {
            return $next($request);
        }

        $this->end->handle('expired');
        $request->session()->regenerate();

        if ($request->expectsJson()) {
            return response()->json(['code' => 'impersonation_expired', 'message' => __('app.impersonation_expired')], 409);
        }

        return redirect('/platform')->with('status_key', 'app.impersonation_expired');
    }
}
