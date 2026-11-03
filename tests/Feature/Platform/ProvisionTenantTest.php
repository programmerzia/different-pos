<?php

declare(strict_types=1);

use App\Actions\Platform\ProvisionTenant;
use App\Exceptions\Domain\InvalidAdminData;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Register;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\TenantOwnerInvitation;
use App\Support\Customers\CustomerNumber;
use App\Support\Permissions\SystemRoles;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    Notification::fake();
    $this->admin = User::factory()->platformAdmin()->create();
    $this->input = [
        'name' => 'Sailor Lifestyle', 'slug' => '', 'owner_name' => 'Rahim Uddin', 'owner_email' => 'Rahim@Sailor.test',
        'owner_phone' => '01712-345678', 'timezone' => 'Asia/Dhaka', 'locale' => 'bn',
    ];
});

it('provisions a business with everything a first sale needs and invites the Owner', function (): void {
    $result = app(ProvisionTenant::class)->handle($this->admin, $this->input);
    $tenant = $result->tenant;

    expect($tenant->slug)->toBe('sailor-lifestyle')->and($tenant->locale)->toBe('bn')->and($tenant->status)->toBe(Tenant::STATUS_ACTIVE)
        ->and($tenant->phone)->toBe('+8801712345678')->and($tenant->pin_box_public_key)->not->toBe('');

    app(TenantContext::class)->run($tenant, null, function () use ($tenant, $result): void {
        expect(Branch::query()->pluck('code')->all())->toBe(['MAIN'])
            ->and(Register::query()->withoutBranchScope()->where('branch_id', $result->branch->id)->pluck('code')->all())->toBe(['R1'])
            ->and(PaymentMethod::query()->pluck('type')->map(fn ($t) => $t->value)->sort()->values()->all())->toBe(['cash', 'customer_credit', 'exchange_credit'])
            ->and(Unit::query()->pluck('short_name')->all())->toBe(['pcs'])
            ->and(Role::query()->where('tenant_id', $tenant->id)->where('is_system', true)->count())->toBe(5);

        $walkIn = Customer::query()->where('is_walk_in', true)->firstOrFail();
        expect(CustomerNumber::isValid((string) $walkIn->customer_no))->toBeTrue();
    });

    $owner = $result->owner->refresh();
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    expect($owner->email)->toBe('rahim@sailor.test')->and($owner->tenant_id)->toBe($tenant->id)->and($owner->must_change_password)->toBeTrue()
        ->and($owner->is_active)->toBeTrue()->and($owner->hasRole(SystemRoles::OWNER))->toBeTrue()
        ->and(app(TenantContext::class)->withoutTenancy(fn () => $owner->branches()->wherePivot('is_default', true)->pluck('branches.id')->all()))->toBe([$result->branch->id])
        ->and(Hash::check($result->temporaryPassword, $owner->password))->toBeTrue();

    Notification::assertSentTo($owner, TenantOwnerInvitation::class);
    expect(Activity::query()->where('event', 'tenant.provisioned')->where('tenant_id', $tenant->id)->where('causer_id', $this->admin->id)->exists())->toBeTrue();
});

it('lets the new Owner sign in and forces a password change first', function (): void {
    $result = app(ProvisionTenant::class)->handle($this->admin, $this->input);

    $this->post('/login', ['login' => 'rahim@sailor.test', 'password' => $result->temporaryPassword])->assertRedirect('/app');
    $this->assertAuthenticatedAs($result->owner);
    $this->get('/app')->assertRedirect(route('password.change'));
});

it('derives the slug from the name and refuses a duplicate slug without leaving partial rows', function (): void {
    app(ProvisionTenant::class)->handle($this->admin, $this->input);
    $tenants = Tenant::query()->count();
    $users = User::query()->count();

    expect(fn () => app(ProvisionTenant::class)->handle($this->admin, ['owner_email' => 'other@sailor.test'] + $this->input))
        ->toThrow(InvalidAdminData::class, 'already exists');
    expect(fn () => app(ProvisionTenant::class)->handle($this->admin, ['slug' => 'Sailor Lifestyle', 'owner_email' => 'other@sailor.test'] + $this->input))
        ->toThrow(InvalidAdminData::class, 'already exists');

    expect(Tenant::query()->count())->toBe($tenants)->and(User::query()->count())->toBe($users);
});

it('rejects bad input', function (): void {
    $try = fn (array $overrides) => fn () => app(ProvisionTenant::class)->handle($this->admin, $overrides + $this->input);

    expect($try(['name' => '  ']))->toThrow(InvalidAdminData::class, 'Business name is required')
        ->and($try(['owner_name' => '']))->toThrow(InvalidAdminData::class, 'Owner name')
        ->and($try(['owner_email' => 'not-an-email']))->toThrow(InvalidAdminData::class, 'valid email')
        ->and($try(['owner_email' => $this->admin->email]))->toThrow(InvalidAdminData::class, 'already exists')
        ->and($try(['owner_phone' => '12345']))->toThrow(InvalidAdminData::class, 'Bangladeshi')
        ->and($try(['timezone' => 'Mars/Base']))->toThrow(InvalidAdminData::class, 'timezone');
    expect(Tenant::query()->count())->toBe(0);
});
