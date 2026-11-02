<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Laravel\Fortify\Fortify;

/** Writes one login_activities row per sign-in attempt: success, failed or throttled (registered by event discovery); the throttle limiter calls throttled(). */
final readonly class RecordLoginActivity
{
    public function __construct(private Request $request) {}

    public function handleLogin(Login $event): void
    {
        $user = $event->user;
        if (! $user instanceof User) {
            return;
        }

        $this->write('success', $this->typed() ?: ($user->email ?? $user->username ?? ''), $user);
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        $slug = $user->tenant?->slug;
        if ($slug !== null) {
            // Plain, non-secret cookie: the next visit pre-fills the business code (ADR 0006).
            Cookie::queue(Cookie::make('pos_tenant', $slug, 60 * 24 * 365, '/', null, null, false, false, 'lax'));
        }
    }

    public function handleFailed(Failed $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        $this->write('failed', $this->typed(), $user);
    }

    /** Called by the `login` rate limiter when it turns a request away. */
    public static function throttled(Request $request): void
    {
        (new self($request))->write('throttled', (string) $request->input(Fortify::username(), ''), null);
    }

    private function typed(): string
    {
        $value = $this->request->input(Fortify::username());

        return is_string($value) ? $value : '';
    }

    private function write(string $event, string $login, ?User $user): void
    {
        LoginActivity::query()->create([
            'tenant_id' => $user?->tenant_id,
            'user_id' => $user?->id,
            'login' => mb_substr($login, 0, 150),
            'event' => $event,
            'ip_address' => (string) $this->request->ip(),
            'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 500) ?: null,
        ]);
    }
}
