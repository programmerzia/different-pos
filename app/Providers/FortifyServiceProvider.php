<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\AuthenticateLogin;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\LoginResponse;
use App\Listeners\RecordLoginActivity;
use App\Models\Tenant;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

final class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Platform admins land on /platform after signing in (also after the 2FA challenge).
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(TwoFactorLoginResponseContract::class, LoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::authenticateUsing(fn (Request $request) => app(AuthenticateLogin::class)($request));
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
            'business' => $this->rememberedBusiness($request),
            'statusKey' => $request->session()->get('status_key'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey)->response(function (Request $request, array $headers): never {
                RecordLoginActivity::throttled($request);

                if ($request->expectsJson()) {
                    throw new ThrottleRequestsException('Too Many Attempts.', null, $headers);
                }

                // The sign-in form gets a field error it can count down from (an Inertia 429 would be a raw error page).
                $retryAfter = $headers['Retry-After'] ?? 60;
                $seconds = max(1, is_numeric($retryAfter) ? (int) $retryAfter : 60);

                throw ValidationException::withMessages(['login' => __('auth.throttle', ['seconds' => $seconds])]);
            });
        });

        RateLimiter::for('passkeys', fn (Request $request) => Limit::perMinute(10)->by(
            ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
        ));
    }

    /**
     * The business code remembered by the `pos_tenant` cookie, shown with its name and logo for confirmation.
     * The cookie only names a business by code: an unknown or malformed code yields null (no hint either way).
     *
     * @return array{code: string, name: string, logo_url: string|null, tagline: string|null}|null
     */
    private function rememberedBusiness(Request $request): ?array
    {
        $code = $request->cookie('pos_tenant');
        if (! is_string($code) || $code === '' || mb_strlen($code) > 100) {
            return null;
        }

        $tenant = Tenant::query()->whereRaw('LOWER(slug) = ?', [mb_strtolower($code)])->first();

        return $tenant instanceof Tenant
            ? ['code' => $tenant->slug, 'name' => $tenant->name, 'logo_url' => $tenant->logoUrl(), 'tagline' => null]
            : null;
    }
}
