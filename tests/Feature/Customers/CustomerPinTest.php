<?php

declare(strict_types=1);

use App\Actions\Customers\ResetCustomerPin;
use App\Actions\Customers\SetCustomerPin;
use App\Actions\Customers\UnlockCustomerPin;
use App\Actions\Customers\VerifyCustomerPin;
use App\Exceptions\Domain\InvalidCustomerPin;
use App\Models\Customer;
use App\Models\Tenant;
use App\Support\Customers\CustomerPin;
use App\Support\Customers\PinVerification;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;
use Tests\Fixtures\SalesWorld;

beforeEach(function (): void {
    $this->world = SalesWorld::make();
    foreach ($this->world->users as $user) {
        $user->branches()->attach($this->world->branch);
    }
    $this->customer = $this->world->customer(['name' => 'Rahim Uddin', 'phone' => '+8801711223344', 'customer_no' => 10000017]);
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

function setPin(SalesWorld $world, Customer $customer, string $pin = '4827', string $role = 'Owner'): Customer
{
    return app(SetCustomerPin::class)->handle($world->user($role), $customer, $pin, $pin);
}

describe('SetCustomerPin', function (): void {
    it('hashes the PIN, stamps pin_set_at, clears any lockout and audits without the PIN', function (): void {
        $this->customer->forceFill(['pin_failed_attempts' => 3, 'pin_locked_until' => now()->addMinutes(5)])->save();

        $customer = setPin($this->world, $this->customer, '4827');

        expect($customer->pin_hash)->not->toBeNull()->not->toContain('4827')
            ->and(Hash::check('4827', (string) $customer->pin_hash))->toBeTrue()
            ->and($customer->pin_set_at)->not->toBeNull()
            ->and($customer->pin_failed_attempts)->toBe(0)
            ->and($customer->pin_locked_until)->toBeNull()
            ->and(CustomerPin::status($customer))->toBe('set');

        $audit = Activity::query()->where('event', 'pin.set')->where('subject_id', $customer->id)->sole();
        $dump = json_encode($audit->getAttributes());
        expect($dump)->not->toContain('4827')->not->toContain('pin_hash')->not->toContain((string) $customer->pin_hash);

        // The model's own update entry carries no hash either (Audit::FORBIDDEN).
        foreach (Activity::query()->where('subject_id', $customer->id)->get() as $row) {
            expect(json_encode($row->getAttributes()))->not->toContain($customer->pin_hash);
        }
    });

    it('refuses a malformed PIN or two entries that differ', function (): void {
        expect(fn () => app(SetCustomerPin::class)->handle($this->world->user(), $this->customer, '123', '123'))->toThrow(InvalidCustomerPin::class, '4 to 6 digits')
            ->and(fn () => app(SetCustomerPin::class)->handle($this->world->user(), $this->customer, '1234567', '1234567'))->toThrow(InvalidCustomerPin::class)
            ->and(fn () => app(SetCustomerPin::class)->handle($this->world->user(), $this->customer, '48a7', '48a7'))->toThrow(InvalidCustomerPin::class)
            ->and(fn () => app(SetCustomerPin::class)->handle($this->world->user(), $this->customer, '4827', '4828'))->toThrow(InvalidCustomerPin::class, 'do not match');
        expect($this->customer->refresh()->pin_hash)->toBeNull();
    });

    it('rejects weak PINs: repeated, sequential, repeated blocks, the denylist and the phone tail', function (string $pin): void {
        expect(fn () => setPin($this->world, $this->customer, $pin))->toThrow(InvalidCustomerPin::class, 'too easy');
    })->with(['1111', '000000', '1234', '4321', '7890', '0987', '123456', '121212', '4848', '2580', '3344', '223344']);

    it('accepts an ordinary PIN and one that merely contains part of the phone', function (): void {
        expect(setPin($this->world, $this->customer, '4827')->pin_hash)->not->toBeNull();
        expect(setPin($this->world, $this->customer, '34417')->pin_hash)->not->toBeNull();
    });

    it('needs customers.update: a cashier cannot set it from the profile, and the walk-in has no PIN', function (): void {
        expect(fn () => setPin($this->world, $this->customer, '4827', 'Cashier'))->toThrow(AuthorizationException::class);
        expect(fn () => setPin($this->world, $this->world->walkIn, '4827'))->toThrow(ModelNotFoundException::class);
    });

    it('lets a cashier help with a first PIN at the register but never replace one', function (): void {
        $cashier = $this->world->user('Cashier');
        $customer = app(SetCustomerPin::class)->handle($cashier, $this->customer, '4827', '4827', firstPinOnly: true);
        expect(Hash::check('4827', (string) $customer->pin_hash))->toBeTrue();

        expect(fn () => app(SetCustomerPin::class)->handle($cashier, $customer, '9316', '9316', firstPinOnly: true))
            ->toThrow(InvalidCustomerPin::class, 'already has a PIN');
        expect(Hash::check('4827', (string) $customer->refresh()->pin_hash))->toBeTrue();

        // A manager (customers.update) may replace it through the same path.
        app(SetCustomerPin::class)->handle($this->world->user('Manager'), $customer, '9316', '9316', firstPinOnly: true);
        expect(Hash::check('9316', (string) $customer->refresh()->pin_hash))->toBeTrue();
    });
});

describe('VerifyCustomerPin', function (): void {
    it('answers ok and resets the counter on the right PIN', function (): void {
        $customer = setPin($this->world, $this->customer);
        $customer->forceFill(['pin_failed_attempts' => 2])->save();

        $result = app(VerifyCustomerPin::class)->handle($this->world->user('Cashier'), $customer, '4827');

        expect($result->ok())->toBeTrue()->and($result->attemptsLeft)->toBe(5)
            ->and($customer->refresh()->pin_failed_attempts)->toBe(0)
            ->and(json_encode($result->toArray()))->not->toContain('4827')->not->toContain('hash');
    });

    it('counts wrong tries and locks for 30 minutes after the fifth, audited', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $customer = setPin($this->world, $this->customer);
        $verify = app(VerifyCustomerPin::class);
        $cashier = $this->world->user('Cashier');

        foreach ([4, 3, 2, 1] as $left) {
            $result = $verify->handle($cashier, $customer, '0000');
            expect($result->status)->toBe(PinVerification::WRONG)->and($result->attemptsLeft)->toBe($left);
        }
        $locked = $verify->handle($cashier, $customer, '0000');
        expect($locked->status)->toBe(PinVerification::LOCKED)
            ->and($locked->lockedUntil?->toDateTimeString())->toBe('2026-11-03 10:30:00')
            ->and(CustomerPin::status($customer->refresh()))->toBe('locked');
        expect(Activity::query()->where('event', 'pin.locked')->where('subject_id', $customer->id)->count())->toBe(1);

        // While locked even the right PIN is refused; the right PIN still works once the lock lapses.
        expect($verify->handle($cashier, $customer, '4827')->status)->toBe(PinVerification::LOCKED);
        Carbon::setTestNow('2026-11-03 10:31:00');
        expect($verify->handle($cashier, $customer, '0000')->attemptsLeft)->toBe(4);
        expect($verify->handle($cashier, $customer, '4827')->ok())->toBeTrue();
        Carbon::setTestNow();
    });

    it('reports not_set for a customer without a PIN', function (): void {
        expect(app(VerifyCustomerPin::class)->handle($this->world->user('Cashier'), $this->customer, '4827')->status)->toBe(PinVerification::NOT_SET);
    });
});

describe('ResetCustomerPin and UnlockCustomerPin', function (): void {
    it('clears the PIN and the lock with a reason, audited without the PIN', function (): void {
        $customer = setPin($this->world, $this->customer);
        $customer->forceFill(['pin_failed_attempts' => 5, 'pin_locked_until' => now()->addMinutes(20)])->save();

        $customer = app(ResetCustomerPin::class)->handle($this->world->user('Manager'), $customer, 'Customer forgot the PIN');

        expect($customer->pin_hash)->toBeNull()->and($customer->pin_set_at)->toBeNull()
            ->and($customer->pin_locked_until)->toBeNull()->and(CustomerPin::status($customer))->toBe('pending');
        $audit = Activity::query()->where('event', 'pin.reset')->where('subject_id', $customer->id)->sole();
        expect($audit->properties['reason'])->toBe('Customer forgot the PIN')->and($audit->properties['new_pin_set'])->toBeFalse();
    });

    it('sets the replacement the customer chose at the counter, still checking the denylist', function (): void {
        $customer = setPin($this->world, $this->customer);
        expect(fn () => app(ResetCustomerPin::class)->handle($this->world->user(), $customer, 'Forgot', '1234', '1234'))->toThrow(InvalidCustomerPin::class, 'too easy');
        expect(fn () => app(ResetCustomerPin::class)->handle($this->world->user(), $customer, 'Forgot', '9316', '9317'))->toThrow(InvalidCustomerPin::class, 'do not match');

        $customer = app(ResetCustomerPin::class)->handle($this->world->user(), $customer, 'Forgot', '9316', '9316');
        expect(Hash::check('9316', (string) $customer->pin_hash))->toBeTrue();
        expect(json_encode(Activity::query()->where('event', 'pin.reset')->get()->toArray()))->not->toContain('9316');
    });

    it('needs a reason and customers.reset_pin', function (): void {
        $customer = setPin($this->world, $this->customer);
        expect(fn () => app(ResetCustomerPin::class)->handle($this->world->user(), $customer, '  '))->toThrow(InvalidCustomerPin::class, 'reason');
        expect(fn () => app(UnlockCustomerPin::class)->handle($this->world->user(), $customer, ''))->toThrow(InvalidCustomerPin::class, 'reason');
        expect(fn () => app(ResetCustomerPin::class)->handle($this->world->user('Cashier'), $customer, 'Forgot'))->toThrow(AuthorizationException::class);
        expect(fn () => app(UnlockCustomerPin::class)->handle($this->world->user('Cashier'), $customer, 'Locked'))->toThrow(AuthorizationException::class);
        expect($customer->refresh()->pin_hash)->not->toBeNull();
    });

    it('unlocks early and keeps the PIN', function (): void {
        $customer = setPin($this->world, $this->customer);
        $customer->forceFill(['pin_failed_attempts' => 5, 'pin_locked_until' => now()->addMinutes(20)])->save();

        $customer = app(UnlockCustomerPin::class)->handle($this->world->user('Manager'), $customer, 'Verified by NID');

        expect(CustomerPin::status($customer))->toBe('set')->and($customer->pin_failed_attempts)->toBe(0)
            ->and(Hash::check('4827', (string) $customer->pin_hash))->toBeTrue();
        expect(Activity::query()->where('event', 'pin.unlocked')->where('subject_id', $customer->id)->sole()->properties['reason'])->toBe('Verified by NID');
    });
});

describe('profile routes', function (): void {
    it('sets the PIN from the profile (customers.update) and flashes without the PIN', function (): void {
        $this->actingAs($this->world->user('Manager'))
            ->from('/app/customers/'.$this->customer->uuid)
            ->post('/app/customers/'.$this->customer->uuid.'/pin', ['pin' => '4827', 'pin_confirmation' => '4827'])
            ->assertRedirect('/app/customers/'.$this->customer->uuid);
        expect(Hash::check('4827', (string) $this->customer->refresh()->pin_hash))->toBeTrue();

        $this->post('/app/customers/'.$this->customer->uuid.'/pin', ['pin' => '1234', 'pin_confirmation' => '1234'])
            ->assertSessionHasErrors('pin');
        $this->postJson('/app/customers/'.$this->customer->uuid.'/pin', ['pin' => '4827', 'pin_confirmation' => '4820'])
            ->assertStatus(422)->assertJsonPath('code', 'mismatch');
    });

    it('resets and unlocks with a reason behind customers.reset_pin', function (): void {
        setPin($this->world, $this->customer);
        $url = '/app/customers/'.$this->customer->uuid;

        $this->actingAs($this->world->user('Cashier'))->post($url.'/pin/reset', ['reason' => 'Forgot'])->assertForbidden();
        $this->post($url.'/pin/unlock', ['reason' => 'Forgot'])->assertForbidden();
        $this->post($url.'/pin', ['pin' => '9316', 'pin_confirmation' => '9316'])->assertForbidden();

        $this->actingAs($this->world->user('Manager'))->from($url)->post($url.'/pin/reset', [])->assertSessionHasErrors('reason');
        $this->from($url)->post($url.'/pin/unlock', ['reason' => 'Verified by NID'])->assertRedirect($url);
        $this->from($url)->post($url.'/pin/reset', ['reason' => 'Forgot the PIN'])->assertRedirect($url);
        expect($this->customer->refresh()->pin_hash)->toBeNull();

        $this->from($url)->post($url.'/pin/reset', ['reason' => 'Chose a new one', 'pin' => '9316', 'pin_confirmation' => '9316'])->assertRedirect($url);
        expect(Hash::check('9316', (string) $this->customer->refresh()->pin_hash))->toBeTrue();
    });

    it('answers 404 for another tenant\'s customer and for the walk-in', function (): void {
        $foreign = app(TenantContext::class)->withoutTenancy(function (): Customer {
            $tenant = Tenant::factory()->create();

            return Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Secret Buyer']);
        });

        $this->actingAs($this->world->user('Owner'));
        foreach (['/pin' => ['pin' => '4827', 'pin_confirmation' => '4827'], '/pin/reset' => ['reason' => 'x y z'], '/pin/unlock' => ['reason' => 'x y z']] as $path => $body) {
            $this->postJson('/app/customers/'.$foreign->uuid.$path, $body)->assertNotFound();
            $this->postJson('/app/customers/'.$this->world->walkIn->uuid.$path, $body)->assertNotFound();
        }
        expect($foreign->refresh()->pin_hash)->toBeNull();
    });

    it('shows the PIN state on the list and the profile, filters by it, and never the hash', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $set = setPin($this->world, $this->customer);
        $pending = $this->world->customer(['name' => 'Pending Person']);
        $locked = $this->world->customer(['name' => 'Locked Person']);
        $locked->forceFill(['pin_hash' => Hash::make('9316'), 'pin_set_at' => now(), 'pin_failed_attempts' => 5, 'pin_locked_until' => now()->addMinutes(20)])->save();
        $lapsed = $this->world->customer(['name' => 'Lapsed Lock']);
        $lapsed->forceFill(['pin_hash' => Hash::make('9316'), 'pin_set_at' => now(), 'pin_locked_until' => now()->subMinute()])->save();

        $this->actingAs($this->world->user('Manager'));
        $response = $this->get('/app/customers?sort=name');
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('customers/index', false)
            ->where('can.reset_pin', true)
            ->where('customers.0.name', 'Lapsed Lock')->where('customers.0.pin_status', 'set')
            ->where('customers.1.name', 'Locked Person')->where('customers.1.pin_status', 'locked')
            ->where('customers.2.name', 'Pending Person')->where('customers.2.pin_status', 'pending')
            ->where('customers.3.name', 'Rahim Uddin')->where('customers.3.pin_status', 'set'));
        $dump = json_encode($response->viewData('page')['props']);
        expect($dump)->not->toContain('pin_hash')->not->toContain((string) $set->pin_hash)->not->toContain('4827');

        $this->get('/app/customers?filter[pin]=set')->assertInertia(fn (AssertableInertia $page) => $page->has('customers', 2));
        $this->get('/app/customers?filter[pin]=pending')->assertInertia(fn (AssertableInertia $page) => $page->has('customers', 1)->where('customers.0.name', 'Pending Person'));
        $this->get('/app/customers?filter[pin]=locked')->assertInertia(fn (AssertableInertia $page) => $page->has('customers', 1)->where('customers.0.name', 'Locked Person'));

        $profile = $this->get('/app/customers/'.$locked->uuid);
        $profile->assertInertia(fn (AssertableInertia $page) => $page->component('customers/show', false)
            ->where('customer.pin_status', 'locked')->where('customer.pin_locked_until', '2026-11-03T10:20:00+00:00'));
        expect(json_encode($profile->viewData('page')['props']))->not->toContain('pin_hash')->not->toContain((string) $locked->pin_hash);

        $csv = $this->get('/app/customers/export.csv')->streamedContent();
        expect($csv)->not->toContain('pin_hash')->not->toContain((string) $set->pin_hash)->not->toContain('4827');
        Carbon::setTestNow();
    });
});
