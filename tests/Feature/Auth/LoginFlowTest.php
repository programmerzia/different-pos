<?php

declare(strict_types=1);

require_once __DIR__.'/../Admin/AdminHelpers.php';

use App\Models\LoginActivity;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

function loginTenant(string $slug = 'different-fashion'): Tenant
{
    return Tenant::factory()->create(['slug' => $slug, 'name' => 'Different Fashion']);
}

it('logs in with an email and remembers the business in the pos_tenant cookie', function (): void {
    $tenant = loginTenant();
    $user = User::factory()->for($tenant)->create(['email' => 'owner@shop.test']);

    $this->post('/login', ['login' => 'Owner@Shop.test', 'password' => 'password'])->assertRedirect('/app')
        ->assertCookie('pos_tenant', 'different-fashion', false);

    $this->assertAuthenticatedAs($user);
    expect(LoginActivity::query()->where('user_id', $user->id)->where('event', 'success')->exists())->toBeTrue()
        ->and($user->refresh()->last_login_at)->not->toBeNull();
});

it('logs in with a username or a phone number plus the business code', function (): void {
    $tenant = loginTenant();
    $user = User::factory()->for($tenant)->create(['email' => null, 'username' => 'cashier1', 'phone' => '+8801712345678']);

    $this->post('/login', ['login' => 'cashier1', 'business' => 'Different-Fashion', 'password' => 'password'])->assertRedirect('/app');
    $this->assertAuthenticatedAs($user);
    $this->post('/logout');

    $this->post('/login', ['login' => '01712-345678', 'business' => 'different-fashion', 'password' => 'password'])->assertRedirect('/app');
    $this->assertAuthenticatedAs($user);
});

it('uses the remembered business cookie when the form sends no business code', function (): void {
    $tenant = loginTenant();
    $user = User::factory()->for($tenant)->create(['username' => 'cashier1']);

    $this->withUnencryptedCookie('pos_tenant', 'different-fashion')
        ->post('/login', ['login' => 'cashier1', 'password' => 'password'])->assertRedirect('/app');
    $this->assertAuthenticatedAs($user);
});

it('picks the right tenant when two shops share a username', function (): void {
    $a = loginTenant('shop-a');
    $b = loginTenant('shop-b');
    User::factory()->for($a)->create(['username' => 'cashier1', 'password' => Hash::make('secret-a')]);
    $userB = User::factory()->for($b)->create(['username' => 'cashier1', 'password' => Hash::make('secret-b')]);

    $this->post('/login', ['login' => 'cashier1', 'business' => 'shop-b', 'password' => 'secret-b'])->assertRedirect('/app');
    $this->assertAuthenticatedAs($userB);
});

it('gives the same error for an unknown business, an unknown user and a wrong password', function (): void {
    loginTenant();
    User::factory()->for(Tenant::query()->first())->create(['username' => 'cashier1']);

    $errors = [];
    foreach ([
        ['login' => 'cashier1', 'business' => 'nope', 'password' => 'password'],
        ['login' => 'ghost', 'business' => 'different-fashion', 'password' => 'password'],
        ['login' => 'cashier1', 'business' => 'different-fashion', 'password' => 'wrong'],
        ['login' => 'cashier1', 'password' => 'password'], // username without any business code
    ] as $payload) {
        $response = $this->post('/login', $payload);
        $response->assertSessionHasErrors('login');
        $errors[] = session('errors')->get('login')[0];
    }

    $this->assertGuest();
    expect(array_unique($errors))->toHaveCount(1);
});

it('throttles after five failures per login and ip and records every attempt', function (): void {
    $tenant = loginTenant();
    $user = User::factory()->for($tenant)->create(['email' => 'owner@shop.test']);

    foreach (range(1, 5) as $_) {
        $this->post('/login', ['login' => 'owner@shop.test', 'password' => 'wrong'])->assertSessionHasErrors('login');
    }
    $this->post('/login', ['login' => 'owner@shop.test', 'password' => 'password'])->assertTooManyRequests();

    $this->assertGuest();
    expect(LoginActivity::query()->where('event', 'failed')->count())->toBe(5)
        ->and(LoginActivity::query()->where('event', 'throttled')->count())->toBe(1);
    RateLimiter::clear(md5('login'.'owner@shop.test|127.0.0.1'));
    expect($user->exists)->toBeTrue();
});

it('refuses a deactivated user with a clear message and records it', function (): void {
    $tenant = loginTenant();
    $user = User::factory()->for($tenant)->create(['email' => 'gone@shop.test', 'is_active' => false]);

    $this->post('/login', ['login' => 'gone@shop.test', 'password' => 'password'])
        ->assertSessionHasErrors(['login' => __('auth.deactivated')]);

    $this->assertGuest();
    expect(LoginActivity::query()->where('user_id', $user->id)->where('event', 'deactivated')->exists())->toBeTrue();
});

it('does not reveal a deactivated account to a wrong password', function (): void {
    $tenant = loginTenant();
    User::factory()->for($tenant)->create(['email' => 'gone@shop.test', 'is_active' => false]);

    $this->post('/login', ['login' => 'gone@shop.test', 'password' => 'wrong'])
        ->assertSessionHasErrors(['login' => __('auth.failed')]);
});

it('logs a user out on the next request once they are deactivated', function (): void {
    [$user] = adminActor();

    $this->actingAs($user)->get('/app')->assertOk();
    $user->forceFill(['is_active' => false])->save();

    $this->get('/app')->assertRedirect('/login');
    $this->assertGuest();
});

it('answers a deactivated session on the JSON api with 403', function (): void {
    [$user] = adminActor();
    $this->actingAs($user);
    $user->forceFill(['is_active' => false])->save();

    $this->getJson('/app/api/search?q=shirt')->assertForbidden()->assertJsonPath('code', 'user_deactivated');
});

it('upgrades a legacy md5 password on first login and forces a password change', function (): void {
    $tenant = loginTenant();
    $user = User::factory()->for($tenant)->create([
        'email' => 'old@shop.test',
        'password' => Hash::make('unknown-random'),
        'legacy_password_md5' => md5('legacy-pass'),
    ]);

    $this->post('/login', ['login' => 'old@shop.test', 'password' => 'legacy-pass'])->assertRedirect('/app');

    $user->refresh();
    expect($user->legacy_password_md5)->toBeNull()
        ->and($user->must_change_password)->toBeTrue()
        ->and(Hash::check('legacy-pass', $user->password))->toBeTrue();
    $this->assertAuthenticatedAs($user);
});

it('rejects a wrong password for a legacy user and keeps the digest', function (): void {
    $tenant = loginTenant();
    $user = User::factory()->for($tenant)->create(['email' => 'old@shop.test', 'legacy_password_md5' => md5('legacy-pass')]);

    $this->post('/login', ['login' => 'old@shop.test', 'password' => 'password'])->assertSessionHasErrors('login');

    expect($user->refresh()->legacy_password_md5)->toBe(md5('legacy-pass'));
    $this->assertGuest();
});

it('keeps a user who must change the password on the change page until they do', function (): void {
    [$user] = adminActor();
    $user->forceFill(['must_change_password' => true])->save();
    $this->actingAs($user);

    $this->get('/app')->assertRedirect('/app/password/change');
    $this->get('/app/settings/users')->assertRedirect('/app/password/change');
    $this->getJson('/app/api/search?q=shirt')->assertStatus(409)->assertJsonPath('code', 'password_change_required');
    $this->get('/app/password/change')->assertOk();

    $this->put('/app/password/change', ['password' => 'a-new-password-1', 'password_confirmation' => 'different'])->assertSessionHasErrors('password');
    $this->put('/app/password/change', ['password' => 'a-new-password-1', 'password_confirmation' => 'a-new-password-1'])->assertRedirect('/app');

    $user->refresh();
    expect($user->must_change_password)->toBeFalse()->and(Hash::check('a-new-password-1', $user->password))->toBeTrue();
    $this->get('/app')->assertOk();
});

it('sends a user without the forced flag away from the change page', function (): void {
    [$user] = adminActor();

    $this->actingAs($user)->get('/app/password/change')->assertRedirect('/app');
});

it('shows the remembered business on the login page', function (): void {
    loginTenant();

    $this->withUnencryptedCookie('pos_tenant', 'different-fashion')->get('/login')
        ->assertInertia(fn ($page) => $page->where('business.code', 'different-fashion')->where('business.name', 'Different Fashion'));
    $this->withUnencryptedCookie('pos_tenant', 'unknown')->get('/login')->assertInertia(fn ($page) => $page->where('business', null));
});
