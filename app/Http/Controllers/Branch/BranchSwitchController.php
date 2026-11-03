<?php

declare(strict_types=1);

namespace App\Http\Controllers\Branch;

use App\Actions\Users\SwitchBranch;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureBranchSelected;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** POST /app/branch/{branch}/switch: only to a branch the user is assigned to and that is active; anything else is a 404. Needs branches.switch (Owner only by default); others stay in their own branch. */
final class BranchSwitchController extends Controller
{
    public function __invoke(Request $request, int $branch, SwitchBranch $switch): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        Gate::authorize('branches.switch');

        $target = $user->branches()->where('branches.is_active', true)->whereKey($branch)->first();
        abort_if($target === null, 404);
        Gate::authorize('view', $target);

        $switch->handle($user, $target);
        $request->session()->put(EnsureBranchSelected::SESSION_KEY, $target->id);

        return redirect()->intended(route('dashboard'));
    }
}
