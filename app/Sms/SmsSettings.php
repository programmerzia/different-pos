<?php

declare(strict_types=1);

namespace App\Sms;

use App\Models\Tenant;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * The tenant's SMS configuration, read from tenants.settings['sms']:
 * {driver, sender_id, sid, base_url, api_token, cost_per_segment (paisa), templates: {key: {enabled, body}}}.
 * The api_token is stored encrypted (Crypt::encryptString) inside that array and only decrypted here;
 * it never reaches a page, a log or an audit row (Audit::FORBIDDEN lists api_token).
 */
final readonly class SmsSettings
{
    public const array DRIVERS = ['null', 'log', 'ssl_wireless'];

    /** Default price of one SMS segment in paisa (৳0.35), used for the composer's cost estimate only. */
    public const int DEFAULT_COST_PER_SEGMENT = 35;

    /** @param  array<string, array{enabled: bool, body: string}>  $templates */
    public function __construct(
        public string $driver,
        public string $senderId,
        public string $sid,
        public string $baseUrl,
        public ?string $apiToken,
        public array $templates,
        public int $costPerSegment = self::DEFAULT_COST_PER_SEGMENT,
    ) {}

    public static function fromTenant(Tenant $tenant): self
    {
        $raw = $tenant->setting('sms');
        $raw = is_array($raw) ? $raw : [];

        $driver = is_string($raw['driver'] ?? null) && in_array($raw['driver'], self::DRIVERS, true) ? $raw['driver'] : 'null';

        $templates = [];
        foreach (Templates::DEFAULTS as $key => $default) {
            $stored = is_array($raw['templates'][$key] ?? null) ? $raw['templates'][$key] : [];
            $body = is_string($stored['body'] ?? null) && trim($stored['body']) !== '' ? $stored['body'] : $default;
            $templates[$key] = ['enabled' => (bool) ($stored['enabled'] ?? true), 'body' => $body];
        }

        return new self(
            $driver,
            is_string($raw['sender_id'] ?? null) ? $raw['sender_id'] : '',
            is_string($raw['sid'] ?? null) ? $raw['sid'] : '',
            is_string($raw['base_url'] ?? null) ? $raw['base_url'] : '',
            self::decrypt($raw['api_token'] ?? null),
            $templates,
            is_int($raw['cost_per_segment'] ?? null) && $raw['cost_per_segment'] >= 0 ? $raw['cost_per_segment'] : self::DEFAULT_COST_PER_SEGMENT,
        );
    }

    /** Automatic messages go out only with a real driver and an enabled template. */
    public function sends(string $template): bool
    {
        return $this->driver !== 'null' && ($this->templates[$template]['enabled'] ?? false);
    }

    public function body(string $template): string
    {
        return $this->templates[$template]['body'] ?? Templates::DEFAULTS[$template] ?? '';
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
