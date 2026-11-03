<?php

declare(strict_types=1);

namespace App\WhatsApp;

use App\Models\Tenant;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * The tenant's WhatsApp configuration, read from tenants.settings['whatsapp']:
 * {driver, phone_number_id, access_token, template_name, template_language}.
 * The access_token is stored encrypted (Crypt::encryptString) inside that array and only decrypted here;
 * it never reaches a page, a log or an audit row (Audit::FORBIDDEN lists access_token).
 *
 * Drivers: `null` (off), `click_to_chat` (wa.me deep links opened in the user's own WhatsApp: works
 * without any account), `log` (development), `meta_cloud` (WhatsApp Business Cloud API).
 */
final readonly class WhatsAppSettings
{
    public const array DRIVERS = ['null', 'click_to_chat', 'log', 'meta_cloud'];

    public function __construct(
        public string $driver,
        public string $phoneNumberId,
        public ?string $accessToken,
        public string $templateName,
        public string $templateLanguage,
    ) {}

    public static function fromTenant(Tenant $tenant): self
    {
        $raw = $tenant->setting('whatsapp');
        $raw = is_array($raw) ? $raw : [];

        $driver = is_string($raw['driver'] ?? null) && in_array($raw['driver'], self::DRIVERS, true) ? $raw['driver'] : 'null';

        return new self(
            $driver,
            is_string($raw['phone_number_id'] ?? null) ? $raw['phone_number_id'] : '',
            self::decrypt($raw['access_token'] ?? null),
            is_string($raw['template_name'] ?? null) ? $raw['template_name'] : '',
            is_string($raw['template_language'] ?? null) && $raw['template_language'] !== '' ? $raw['template_language'] : 'bn',
        );
    }

    /** Anything can be sent (queued or handed off). */
    public function configured(): bool
    {
        return $this->driver !== 'null';
    }

    /** Can messages go out unattended (bulk, automatic)? Click-to-chat cannot: it opens one chat at a time. */
    public function bulk(): bool
    {
        return in_array($this->driver, ['log', 'meta_cloud'], true);
    }

    /**
     * The truthful status pill: `connected` only when the Cloud API has both credentials, else
     * `click_to_chat`, `log`, `incomplete` (Cloud API chosen but credentials missing) or `off`.
     */
    public function status(): string
    {
        return match ($this->driver) {
            'meta_cloud' => $this->phoneNumberId !== '' && $this->accessToken !== null ? 'connected' : 'incomplete',
            'click_to_chat' => 'click_to_chat',
            'log' => 'log',
            default => 'off',
        };
    }

    private static function decrypt(mixed $stored): ?string
    {
        if (! is_string($stored) || $stored === '') {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            return null;
        }
    }
}
