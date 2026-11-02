<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\LoginActivity;
use App\Models\User;
use App\Support\Auth\LegacyMd5Hasher;
use App\Support\Auth\LoginResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

/**
 * Fortify's authenticateUsing callback. Returns the user for a correct identifier + password, or
 * null for every other case (unknown business, unknown user, wrong password) so the form cannot
 * tell them apart. A user migrated from the legacy system may still carry only an MD5 digest: a
 * match logs them in, stores an argon2id hash instead and forces a password change.
 */
final readonly class AuthenticateLogin
{
    public function __construct(
        private LoginResolver $resolver,
        private LegacyMd5Hasher $legacy,
    ) {}

    public function __invoke(Request $request): ?User
    {
        $login = $request->input(Fortify::username());
        $password = $request->input('password');
        if (! is_string($login) || ! is_string($password) || $password === '') {
            return null;
        }

        $business = $request->input('business', $request->cookie('pos_tenant'));
        $user = $this->resolver->resolve($login, is_string($business) ? $business : null);

        // Spend the same hashing time whether or not the user exists.
        if (! $user instanceof User) {
            Hash::make($password);

            return null;
        }

        if (! $this->passwordMatches($user, $password)) {
            return null;
        }

        if (! $user->is_active) {
            LoginActivity::query()->create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'login' => mb_substr($login, 0, 150),
                'event' => 'deactivated',
                'ip_address' => (string) $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500) ?: null,
            ]);

            throw ValidationException::withMessages([Fortify::username() => [trans('auth.deactivated')]]);
        }

        return $user;
    }

    private function passwordMatches(User $user, string $password): bool
    {
        if ($user->legacy_password_md5 !== null) {
            if (! $this->legacy->check($password, $user->legacy_password_md5)) {
                return false;
            }

            $user->forceFill([
                'password' => $password,
                'legacy_password_md5' => null,
                'must_change_password' => true,
            ])->save();

            return true;
        }

        return Hash::check($password, $user->password);
    }
}
