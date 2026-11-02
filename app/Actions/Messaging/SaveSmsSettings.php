<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use App\Sms\SmsSettings;
use App\Sms\Templates;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;

/**
 * Saves tenants.settings['sms']. A blank api_token keeps the stored one (it is never shown back);
 * a new one is stored encrypted. Needs settings.manage.
 */
final readonly class SaveSmsSettings
{
    public function __construct(private TenantContext $context) {}

    /**
     * @param  array{driver: string, sender_id?: string|null, sid?: string|null, base_url?: string|null, api_token?: string|null, clear_token?: bool, templates?: array<string, array{enabled?: bool, body?: string|null}>}  $data
     */
    public function handle(User $actor, array $data): Tenant
    {
        Gate::forUser($actor)->authorize('configure', Message::class);

        return Transaction::run(function () use ($actor, $data): Tenant {
            $tenant = Tenant::query()->whereKey($this->context->tenantId())->lockForUpdate()->firstOrFail();
            $current = is_array($tenant->settings['sms'] ?? null) ? $tenant->settings['sms'] : [];

            $token = $current['api_token'] ?? null;
            if (($data['clear_token'] ?? false) === true) {
                $token = null;
            } elseif (is_string($data['api_token'] ?? null) && trim($data['api_token']) !== '') {
                $token = Crypt::encryptString(trim($data['api_token']));
            }

            $templates = [];
            foreach (Templates::keys() as $key) {
                $given = $data['templates'][$key] ?? [];
                $body = trim((string) ($given['body'] ?? ''));
                $templates[$key] = [
                    'enabled' => (bool) ($given['enabled'] ?? false),
                    'body' => $body !== '' ? $body : Templates::DEFAULTS[$key],
                ];
            }

            $sms = [
                'driver' => in_array($data['driver'], SmsSettings::DRIVERS, true) ? $data['driver'] : 'null',
                'sender_id' => trim((string) ($data['sender_id'] ?? '')),
                'sid' => trim((string) ($data['sid'] ?? '')),
                'base_url' => trim((string) ($data['base_url'] ?? '')),
                'api_token' => $token,
                'templates' => $templates,
            ];

            $tenant->settings = [...$tenant->settings, 'sms' => $sms];
            $tenant->save();

            Audit::log('sms.settings_updated', $tenant, [
                'driver' => $sms['driver'],
                'token_changed' => $token !== ($current['api_token'] ?? null),
                'enabled' => array_keys(array_filter($templates, static fn (array $t): bool => $t['enabled'])),
            ], $actor);

            return $tenant;
        });
    }
}
