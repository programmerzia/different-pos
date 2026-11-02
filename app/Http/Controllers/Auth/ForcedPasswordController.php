<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Users\SetNewPassword;
use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/** GET/PUT /app/password/change: the page a migrated legacy user is sent to until they choose a new password. */
final class ForcedPasswordController extends Controller
{
    use PasswordValidationRules;

    public function edit(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        if (! $user->must_change_password) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('auth/change-password', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(Request $request, SetNewPassword $action): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $data = Validator::make($request->all(), ['password' => $this->passwordRules()])->validate();
        $action->handle($user, (string) $data['password']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.password_changed')]);

        return redirect()->route('dashboard');
    }
}
