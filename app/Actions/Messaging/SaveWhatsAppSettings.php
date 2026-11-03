<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;
use App\Support\Tenancy\TenantContext;
use App\WhatsApp\WhatsAppSettings;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;

/**
 * Saves tenants.settings['whatsapp']. A blank access_token keeps the stored one (it is never shown back);
 * a new one is stored encrypted. Needs settings.manage.
 */
final readonly class SaveWhatsAppSettings
{
    public function __construct(private TenantContext $context) {}

    /**
     * @param  array{driver: string, phone_number_id?: string|null, access_token?: string|null, clear_token?: bool, template_name?: string|null, template_language?: string|null}  $data
     */
    public function handle(User $actor, array $data): Tenant
    {
        Gate::forUser($actor)->authorize('configure', Message::class);

        return Transaction::run(function () use ($actor, $data): Tenant {
            $tenant = Tenant::query()->whereKey($this->context->tenantId())->lockForUpdate()->firstOrFail();
            $current = is_array($tenant->settings['whatsapp'] ?? null) ? $tenant->settings['whatsapp'] : [];

            $token = $current['access_token'] ?? null;
            if (($data['clear_token'] ?? false) === true) {
                $token = null;
            } elseif (is_string($data['access_token'] ?? null) && trim($data['access_token']) !== '') {
                $token = Crypt::encryptString(trim($data['access_token']));
            }

            $language = strtolower(trim((string) ($data['template_language'] ?? '')));
            $whatsapp = [
                'driver' => in_array($data['driver'], WhatsAppSettings::DRIVERS, true) ? $data['driver'] : 'null',
                'phone_number_id' => trim((string) ($data['phone_number_id'] ?? '')),
                'access_token' => $token,
                'template_name' => trim((string) ($data['template_name'] ?? '')),
                'template_language' => $language !== '' ? $language : 'bn',
            ];

            $tenant->settings = [...$tenant->settings, 'whatsapp' => $whatsapp];
            $tenant->save();

            Audit::log('whatsapp.settings_updated', $tenant, [
                'driver' => $whatsapp['driver'],
                'token_changed' => $token !== ($current['access_token'] ?? null),
                'template' => $whatsapp['template_name'] !== '',
            ], $actor);

            return $tenant;
        });
    }
}
