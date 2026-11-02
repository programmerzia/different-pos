<?php

declare(strict_types=1);

namespace App\Services\Crypto;

use App\Exceptions\Domain\InvalidSealedPin;
use App\Models\Tenant;
use JsonException;
use SensitiveParameter;
use SodiumException;

/**
 * Sealed boxes (libsodium crypto_box_seal) for customer PINs entered offline.
 * Devices seal with the tenant's public key; only the server can open the box.
 */
final class PinSealer
{
    /**
     * @return array{public: string, secret: string} base64; "secret" is the full keypair needed by seal_open
     */
    public static function generateKeypair(): array
    {
        $keypair = sodium_crypto_box_keypair();
        $keys = [
            'public' => base64_encode(sodium_crypto_box_publickey($keypair)),
            'secret' => base64_encode($keypair),
        ];
        sodium_memzero($keypair);

        return $keys;
    }

    /**
     * Seals a payload for a tenant. Devices do this in TypeScript; the server uses it for tests and tooling.
     *
     * @param  array{customer_id: int, pin: string, client_uuid: string, ts: int}  $payload
     */
    public function seal(Tenant $tenant, #[SensitiveParameter] array $payload): string
    {
        $publicKey = base64_decode($tenant->pin_box_public_key, true);
        if ($publicKey === false || strlen($publicKey) !== SODIUM_CRYPTO_BOX_PUBLICKEYBYTES) {
            throw InvalidSealedPin::malformed('tenant public key');
        }

        return base64_encode(sodium_crypto_box_seal(json_encode($payload, JSON_THROW_ON_ERROR), $publicKey));
    }

    public function open(Tenant $tenant, #[SensitiveParameter] string $sealed): SealedPin
    {
        $ciphertext = base64_decode($sealed, true);
        $keypair = base64_decode($tenant->pin_box_secret_key, true);

        if ($ciphertext === false || $keypair === false || strlen($keypair) !== SODIUM_CRYPTO_BOX_KEYPAIRBYTES) {
            throw InvalidSealedPin::cannotOpen();
        }

        try {
            $plain = sodium_crypto_box_seal_open($ciphertext, $keypair);
        } catch (SodiumException) {
            $plain = false;
        } finally {
            sodium_memzero($keypair);
        }

        if ($plain === false) {
            throw InvalidSealedPin::cannotOpen();
        }

        try {
            $data = json_decode($plain, true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidSealedPin::malformed('not JSON');
        } finally {
            sodium_memzero($plain);
        }

        if (! is_array($data)
            || ! is_int($data['customer_id'] ?? null)
            || ! is_string($data['pin'] ?? null) || preg_match('/^\d{4,6}$/', $data['pin']) !== 1
            || ! is_string($data['client_uuid'] ?? null) || $data['client_uuid'] === ''
            || ! is_int($data['ts'] ?? null)) {
            throw InvalidSealedPin::malformed('expected customer_id, pin (4-6 digits), client_uuid, ts');
        }

        return new SealedPin($data['customer_id'], $data['pin'], $data['client_uuid'], $data['ts']);
    }
}
