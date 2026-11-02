<?php

declare(strict_types=1);

use App\Actions\Vendors\DeactivateVendor;
use App\Actions\Vendors\SaveVendor;
use App\Exceptions\Domain\InvalidPartyData;
use App\Models\Tenant;
use App\Models\Vendor;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\Fixtures\StockWorld;

beforeEach(function (): void {
    $this->world = StockWorld::make();
    $this->user = $this->world->user;
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('creates and updates a vendor with a normalised phone', function (): void {
    $vendor = app(SaveVendor::class)->handle($this->user, ['name' => 'Dhaka Fabrics', 'company' => 'DF Ltd', 'phone' => '01712345678', 'email' => 'a@b.com']);
    $updated = app(SaveVendor::class)->handle($this->user, ['address' => 'Islampur', 'phone' => ''], $vendor);

    expect($vendor->phone)->toBe('+8801712345678')
        ->and($vendor->balance->paisa)->toBe(0)
        ->and($vendor->created_by)->toBe($this->user->id)
        ->and($updated->id)->toBe($vendor->id)
        ->and($updated->phone)->toBeNull()
        ->and($updated->address)->toBe('Islampur')
        ->and($updated->name)->toBe('Dhaka Fabrics');
});

it('validates the vendor', function (array $data): void {
    app(SaveVendor::class)->handle($this->user, $data);
})->with([
    'no name' => [['name' => '']],
    'bad phone' => [['name' => 'X', 'phone' => '123']],
    'bad email' => [['name' => 'X', 'email' => 'nope']],
])->throws(InvalidPartyData::class);

it('refuses a duplicate vendor phone but lets the vendor keep its own', function (): void {
    $a = app(SaveVendor::class)->handle($this->user, ['name' => 'A', 'phone' => '01712345678']);

    expect(fn () => app(SaveVendor::class)->handle($this->user, ['name' => 'B', 'phone' => '+8801712345678']))->toThrow(InvalidPartyData::class, 'already');
    expect(app(SaveVendor::class)->handle($this->user, ['name' => 'A2', 'phone' => '01712345678'], $a)->name)->toBe('A2');
});

it('deactivates a vendor idempotently and keeps its balance', function (): void {
    $vendor = $this->world->vendor();
    app(TenantContext::class)->withoutTenancy(fn () => $vendor->forceFill(['balance' => Money::ofPaisa(5000)])->save());

    app(DeactivateVendor::class)->handle($this->user, $vendor);
    $again = app(DeactivateVendor::class)->handle($this->user, $vendor);

    expect($again->is_active)->toBeFalse()->and($again->balance->paisa)->toBe(5000);
});

it('isolates vendors per tenant', function (): void {
    $foreign = app(TenantContext::class)->withoutTenancy(function (): Vendor {
        $tenant = Tenant::factory()->create();

        return Vendor::factory()->create(['tenant_id' => $tenant->id, 'phone' => '+8801712345678']);
    });

    expect(app(SaveVendor::class)->handle($this->user, ['name' => 'Mine', 'phone' => '01712345678'])->id)->not->toBe($foreign->id);
    expect(fn () => app(SaveVendor::class)->handle($this->user, ['name' => 'Hijack'], $foreign))->toThrow(ModelNotFoundException::class);
    expect(fn () => app(DeactivateVendor::class)->handle($this->user, $foreign))->toThrow(ModelNotFoundException::class);
});
