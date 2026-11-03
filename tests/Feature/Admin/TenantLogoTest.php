<?php

declare(strict_types=1);

require_once __DIR__.'/AdminHelpers.php';

use App\Models\Tenant;
use App\Support\Permissions\SystemRoles;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    Storage::fake('public');
});

it('stores an uploaded logo, records its path in branding and audits it', function (): void {
    [$owner, $tenant] = adminActor();

    $this->actingAs($owner)->post('/app/settings/business/logo', ['logo' => UploadedFile::fake()->image('logo.png', 400, 200)])
        ->assertRedirect()->assertSessionHasNoErrors();

    $path = $tenant->refresh()->logoPath();
    expect($path)->toStartWith("tenants/{$tenant->id}/logo-")->toEndWith('.png')
        ->and($tenant->logoUrl())->toBe(Storage::disk('public')->url((string) $path));
    Storage::disk('public')->assertExists((string) $path);

    $audit = Activity::query()->where('event', 'settings.logo_updated')->where('subject_id', $tenant->id)->sole();
    expect($audit->causer_id)->toBe($owner->id)->and($audit->getProperty('action'))->toBe('uploaded');

    $owner->unsetRelation('tenant'); // the acting user keeps its loaded tenant between test requests
    $this->get('/app/settings/business')->assertInertia(fn (Assert $page) => $page->where('business.logo_url', $tenant->logoUrl()));
});

it('replaces the previous logo file and keeps other branding keys', function (): void {
    [$owner, $tenant] = adminActor();
    $tenant->update(['branding' => ['brand_hex' => 'kept']]);

    $this->actingAs($owner)->post('/app/settings/business/logo', ['logo' => UploadedFile::fake()->image('a.jpg')])->assertRedirect();
    $first = (string) $tenant->refresh()->logoPath();
    $this->post('/app/settings/business/logo', ['logo' => UploadedFile::fake()->image('b.webp')])->assertRedirect();
    $second = (string) $tenant->refresh()->logoPath();

    expect($second)->not->toBe($first)->toEndWith('.webp')->and($tenant->branding['brand_hex'])->toBe('kept');
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);
});

it('rejects svg, oversized and non-image uploads', function (): void {
    [$owner, $tenant] = adminActor();
    $this->actingAs($owner);

    $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    $this->post('/app/settings/business/logo', ['logo' => $svg])->assertSessionHasErrors('logo');
    $this->post('/app/settings/business/logo', ['logo' => UploadedFile::fake()->image('big.png')->size(2049)])->assertSessionHasErrors('logo');
    $this->post('/app/settings/business/logo', ['logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')])->assertSessionHasErrors('logo');
    $this->post('/app/settings/business/logo', ['logo' => UploadedFile::fake()->createWithContent('fake.png', 'not an image')])->assertSessionHasErrors('logo');
    $this->post('/app/settings/business/logo', [])->assertSessionHasErrors('logo');

    expect($tenant->refresh()->logoPath())->toBeNull()->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('removes the logo: clears the path and deletes the file', function (): void {
    [$owner, $tenant] = adminActor();
    $this->actingAs($owner)->post('/app/settings/business/logo', ['logo' => UploadedFile::fake()->image('logo.png')])->assertRedirect();
    $path = (string) $tenant->refresh()->logoPath();

    $this->delete('/app/settings/business/logo')->assertRedirect();

    expect($tenant->refresh()->logoPath())->toBeNull()->and($tenant->logoUrl())->toBeNull();
    Storage::disk('public')->assertMissing($path);
    expect(Activity::query()->where('event', 'settings.logo_updated')->where('subject_id', $tenant->id)->count())->toBe(2);

    // Removing when there is none is a harmless no-op.
    $this->delete('/app/settings/business/logo')->assertRedirect()->assertSessionHasNoErrors();
});

it('needs settings.manage to upload or remove a logo', function (): void {
    [$manager, $tenant] = adminActor(SystemRoles::MANAGER);
    $this->actingAs($manager);

    $this->post('/app/settings/business/logo', ['logo' => UploadedFile::fake()->image('logo.png')])->assertForbidden();
    $this->delete('/app/settings/business/logo')->assertForbidden();

    expect($tenant->refresh()->logoPath())->toBeNull()->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('only changes the signed-in business logo', function (): void {
    [$owner, $tenant] = adminActor();
    $other = Tenant::factory()->create(['branding' => ['logo_path' => 'tenants/999/logo-other.png']]);

    $this->actingAs($owner)->post('/app/settings/business/logo', ['logo' => UploadedFile::fake()->image('logo.png')])->assertRedirect();
    $this->delete('/app/settings/business/logo')->assertRedirect();

    expect($other->refresh()->logoPath())->toBe('tenants/999/logo-other.png')
        ->and($tenant->refresh()->logoPath())->toBeNull();
});

it('gives the login page the remembered business logo, and nothing for a bogus code', function (): void {
    Tenant::factory()->create(['slug' => 'logo-shop', 'name' => 'Logo Shop', 'branding' => ['logo_path' => 'tenants/1/logo-x.png']]);
    Tenant::factory()->create(['slug' => 'plain-shop', 'name' => 'Plain Shop']);

    $this->withUnencryptedCookie('pos_tenant', 'logo-shop')->get('/login')->assertInertia(fn (Assert $page) => $page
        ->where('business.code', 'logo-shop')
        ->where('business.logo_url', Storage::disk('public')->url('tenants/1/logo-x.png'))
        ->where('business.tagline', null));
    $this->withUnencryptedCookie('pos_tenant', 'plain-shop')->get('/login')
        ->assertInertia(fn (Assert $page) => $page->where('business.name', 'Plain Shop')->where('business.logo_url', null));
    $this->withUnencryptedCookie('pos_tenant', "x' OR 1=1 --")->get('/login')->assertInertia(fn (Assert $page) => $page->where('business', null));
    $this->withUnencryptedCookie('pos_tenant', str_repeat('a', 500))->get('/login')->assertInertia(fn (Assert $page) => $page->where('business', null));
});

it('gives the login page no business without the cookie', function (): void {
    Tenant::factory()->create(['slug' => 'logo-shop', 'branding' => ['logo_path' => 'tenants/1/logo-x.png']]);

    $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('business', null)->where('tenant', null));
});

it('shares the tenant name and logo only inside a tenant context', function (): void {
    $this->withoutVite();
    [$owner, $tenant] = adminActor();
    $tenant->update(['branding' => ['logo_path' => "tenants/{$tenant->id}/logo-abc.png"]]);
    $owner->unsetRelation('tenant');

    $this->actingAs($owner)->get('/app/settings/business')->assertInertia(fn (Assert $page) => $page
        ->where('tenant.name', $tenant->name)
        ->where('tenant.logo_url', Storage::disk('public')->url("tenants/{$tenant->id}/logo-abc.png")));

    expect(app(TenantContext::class)->hasTenant())->toBeTrue();
});

it('shows the logo on receipts when there is one', function (): void {
    $tenant = Tenant::factory()->make(['branding' => ['logo_path' => 'tenants/7/logo-r.png']]);

    $html = view('receipts._shop', ['paper' => '80', 'r' => ['shop' => [
        'name' => 'Shop', 'logo_url' => $tenant->logoUrl(), 'legal_name' => null, 'vat_bin' => null, 'address' => null, 'phone' => null,
        'branch' => 'Main', 'branch_address' => null, 'branch_phone' => null, 'header' => null, 'footer' => null,
    ]]])->render();
    expect($html)->toContain('class="shop-logo"')->toContain('logo-r.png')->toContain('Shop');

    $plain = view('receipts._shop', ['paper' => 'a4', 'r' => ['shop' => [
        'name' => 'Shop', 'logo_url' => null, 'legal_name' => null, 'vat_bin' => null, 'address' => null, 'phone' => null,
        'branch' => 'Main', 'branch_address' => null, 'branch_phone' => null, 'header' => null, 'footer' => null,
    ]]])->render();
    expect($plain)->not->toContain('shop-logo')->toContain('Shop');
});
