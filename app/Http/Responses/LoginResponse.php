<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Fortify;

/** Platform admins (no tenant) land on /platform; everyone else keeps Fortify's intended/home redirect. */
final class LoginResponse implements LoginResponseContract, TwoFactorLoginResponseContract
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if ($user instanceof User && $user->is_platform_admin && $user->tenant_id === null) {
            return $request->wantsJson() ? new JsonResponse(['two_factor' => false], 200) : redirect('/platform');
        }

        return $request->wantsJson() ? new JsonResponse(['two_factor' => false], 200) : redirect()->intended(Fortify::redirects('login'));
    }
}
