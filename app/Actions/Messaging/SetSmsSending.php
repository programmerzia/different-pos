<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;

/** The master SMS switch: on or off at once, leaving the provider, credentials and templates as they are. */
final readonly class SetSmsSending
{
    public function __construct(private TenantContext $context) {}

    public function handle(User $actor, bool $enabled): Tenant
    {
        Gate::forUser($actor)->authorize('configure', Message::class);

        return Transaction::run(function () use ($actor, $enabled): Tenant {
            $tenant = Tenant::query()->whereKey($this->context->tenantId())->lockForUpdate()->firstOrFail();
            $sms = is_array($tenant->settings['sms'] ?? null) ? $tenant->settings['sms'] : [];

            $tenant->settings = [...$tenant->settings, 'sms' => [...$sms, 'sending_enabled' => $enabled]];
            $tenant->save();

            Audit::log('sms.sending_switched', $tenant, ['enabled' => $enabled], $actor);

            return $tenant;
        });
    }
}
