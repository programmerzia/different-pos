<?php

declare(strict_types=1);

namespace App\Services\Crypto;

use SensitiveParameter;

/**
 * The opened contents of an offline customer-PIN box (04_TRD §3.9.5).
 * The PIN is hidden from var_dump/print_r/logs; never serialise this object.
 */
final readonly class SealedPin
{
    public function __construct(
        public int $customerId,
        #[SensitiveParameter] public string $pin,
        public string $clientUuid,
        public int $timestamp,
    ) {}

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return ['customerId' => $this->customerId, 'pin' => '[hidden]', 'clientUuid' => $this->clientUuid, 'timestamp' => $this->timestamp];
    }
}
