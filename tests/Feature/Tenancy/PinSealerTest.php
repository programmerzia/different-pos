<?php

declare(strict_types=1);

use App\Exceptions\Domain\InvalidSealedPin;
use App\Models\Tenant;
use App\Services\Crypto\PinSealer;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->sealer = new PinSealer;
    $this->tenant = Tenant::factory()->create();
    $this->payload = ['customer_id' => 42, 'pin' => '4821', 'client_uuid' => '0192f0c4-7e6a-7000-8000-000000000001', 'ts' => 1_761_900_000];
});

it('gives every tenant its own keypair on create', function (): void {
    $other = Tenant::factory()->create();

    expect(strlen((string) base64_decode($this->tenant->pin_box_public_key, true)))->toBe(SODIUM_CRYPTO_BOX_PUBLICKEYBYTES)
        ->and($this->tenant->pin_box_public_key)->not->toBe($other->pin_box_public_key);
});

it('stores the secret key encrypted and never serialises it', function (): void {
    $raw = DB::table('tenants')->where('id', $this->tenant->id)->value('pin_box_secret_key');

    expect($raw)->not->toBe($this->tenant->pin_box_secret_key)
        ->and(decrypt($raw, false))->toBe($this->tenant->pin_box_secret_key)
        ->and($this->tenant->toArray())->not->toHaveKey('pin_box_secret_key')
        ->and($this->tenant->toJson())->not->toContain($this->tenant->pin_box_secret_key);
});

it('opens a box sealed with the tenant public key', function (): void {
    $opened = $this->sealer->open($this->tenant->fresh(), $this->sealer->seal($this->tenant, $this->payload));

    expect($opened->customerId)->toBe(42)
        ->and($opened->pin)->toBe('4821')
        ->and($opened->clientUuid)->toBe($this->payload['client_uuid'])
        ->and($opened->timestamp)->toBe($this->payload['ts']);
});

it('cannot be opened by another tenant', function (): void {
    $sealed = $this->sealer->seal($this->tenant, $this->payload);

    expect(fn () => $this->sealer->open(Tenant::factory()->create(), $sealed))->toThrow(InvalidSealedPin::class);
});

it('rejects tampered or garbage ciphertext', function (string $mutation): void {
    $sealed = $this->sealer->seal($this->tenant, $this->payload);
    $bytes = (string) base64_decode($sealed, true);
    $bad = match ($mutation) {
        'flip' => base64_encode(substr($bytes, 0, -1).chr(ord($bytes[-1]) ^ 1)),
        'truncate' => base64_encode(substr($bytes, 0, 20)),
        'not-base64' => '***',
    };

    expect(fn () => $this->sealer->open($this->tenant, $bad))->toThrow(InvalidSealedPin::class);
})->with(['flip', 'truncate', 'not-base64']);

it('rejects malformed payloads', function (array $override): void {
    $sealed = $this->sealer->seal($this->tenant, array_replace($this->payload, $override));

    expect(fn () => $this->sealer->open($this->tenant, $sealed))->toThrow(InvalidSealedPin::class);
})->with([
    'letters' => [['pin' => '12ab']],
    'too short' => [['pin' => '123']],
    'too long' => [['pin' => '1234567']],
    'customer as string' => [['customer_id' => '42']],
    'empty client uuid' => [['client_uuid' => '']],
]);

it('never shows the PIN in dumps or exception messages', function (): void {
    $opened = $this->sealer->open($this->tenant, $this->sealer->seal($this->tenant, $this->payload));

    expect(print_r($opened, true))->not->toContain('4821');

    try {
        $this->sealer->open($this->tenant, $this->sealer->seal($this->tenant, array_replace($this->payload, ['pin' => '98x7'])));
    } catch (InvalidSealedPin $e) {
        expect($e->getMessage())->not->toContain('98x7');
    }
});
