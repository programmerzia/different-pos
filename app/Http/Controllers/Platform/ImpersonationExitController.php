<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\EndImpersonation;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Platform\Impersonation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/** POST /app/impersonation/exit: the banner's Exit. Only a session that is impersonating may call it. */
final class ImpersonationExitController extends Controller
{
    public function __invoke(Request $request, Impersonation $impersonation, EndImpersonation $end): RedirectResponse
    {
        Gate::authorize('impersonating');
        abort_unless($request->user() instanceof User, 401);

        $end->handle();
        $request->session()->regenerate();
        Inertia::flash('toast', ['type' => 'info', 'message' => __('app.impersonation_ended')]);

        return redirect('/platform');
    }
}
