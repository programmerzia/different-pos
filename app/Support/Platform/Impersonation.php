<?php

declare(strict_types=1);

namespace App\Support\Platform;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Carbon;

/**
 * The session state of a platform admin signed in as a tenant Owner (05_Schema §1: audited, time-boxed 60 min,
 * visible banner). The original admin id lives only in the session; ExpireImpersonation ends it after the limit.
 */
final readonly class Impersonation
{
    public const int MAX_MINUTES = 60;

    private const string ADMIN = 'impersonation.admin_id';

    private const string TENANT = 'impersonation.tenant_id';

    private const string STARTED = 'impersonation.started_at';

    public function __construct(private Session $session) {}

    public function start(User $admin, Tenant $tenant): void
    {
        $this->session->put([self::ADMIN => $admin->id, self::TENANT => $tenant->id, self::STARTED => now()->toIso8601String()]);
    }

    public function isActive(): bool
    {
        return is_int($this->session->get(self::ADMIN));
    }

    public function adminId(): ?int
    {
        $id = $this->session->get(self::ADMIN);

        return is_int($id) ? $id : null;
    }

    public function tenantId(): ?int
    {
        $id = $this->session->get(self::TENANT);

        return is_int($id) ? $id : null;
    }

    public function startedAt(): ?Carbon
    {
        $raw = $this->session->get(self::STARTED);

        return is_string($raw) ? Carbon::parse($raw) : null;
    }

    public function expiresAt(): ?Carbon
    {
        return $this->startedAt()?->addMinutes(self::MAX_MINUTES);
    }

    public function isExpired(): bool
    {
        $expires = $this->expiresAt();

        return $expires instanceof Carbon && $expires->isPast();
    }

    public function forget(): void
    {
        $this->session->forget([self::ADMIN, self::TENANT, self::STARTED]);
    }
}
