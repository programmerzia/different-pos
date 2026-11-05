<?php

declare(strict_types=1);

namespace App\Sms;

use App\Exceptions\Domain\SmsNotConfigured;
use App\Models\Tenant;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * The tenant's SMS configuration, read from tenants.settings['sms']:
 * {sending_enabled, driver, sender_id, sid, base_url, api_token, cost_per_segment (paisa), templates: {key: {enabled, body}}}.
 * `sending_enabled` is the master switch: off stops every customer SMS without touching the credentials.
 * The api_token is stored encrypted (Crypt::encryptString) inside that array and only decrypted here;
 * it never reaches a page, a log or an audit row (Audit::FORBIDDEN lists api_token).
 */
final readonly class SmsSettings
{
    public const array DRIVERS = ['null', 'log', 'ssl_wireless', 'onecodesoft'];

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
        public bool $sendingEnabled = true,
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
            if (in_array($body, Templates::SUPERSEDED_DEFAULTS[$key], true)) {
                $body = $default;
            }
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
            (bool) ($raw['sending_enabled'] ?? true),
        );
    }

    /**
     * True when the gateway can actually deliver: `log` always; a real provider only with its credentials
     * (an api token that decrypted, plus the SID/sender for ssl_wireless or the base URL for onecodesoft).
     */
    public function isConfigured(): bool
    {
        return match ($this->driver) {
            'log' => true,
            'ssl_wireless' => $this->apiToken !== null && ($this->sid !== '' || $this->senderId !== ''),
            'onecodesoft' => $this->apiToken !== null && trim($this->baseUrl) !== '',
            default => false,
        };
    }

    /** Refuses a hand-sent message (broadcast, direct, statement, resend) with the reason the user can act on. */
    public function assertCanSend(): void
    {
        if (! $this->isConfigured()) {
            throw SmsNotConfigured::make();
        }
        if (! $this->sendingEnabled) {
            throw SmsNotConfigured::switchedOff();
        }
    }

    /** True when the template is switched on (independent of the gateway). */
    public function enabled(string $template): bool
    {
        return (bool) ($this->templates[$template]['enabled'] ?? false);
    }

    /** Automatic messages go out only with a working gateway and an enabled template. */
    public function sends(string $template): bool
    {
        return $this->isConfigured() && $this->sendingEnabled && $this->enabled($template);
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
