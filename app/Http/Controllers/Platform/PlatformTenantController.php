<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\ImpersonateTenant;
use App\Actions\Platform\ProvisionTenant;
use App\Actions\Platform\SetTenantStatus;
use App\Http\Controllers\Concerns\TranslatesDomainErrors;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Platform\ProvisionedTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/** /platform/tenants: provision, suspend, resume and sign in as a business. */
final class PlatformTenantController extends Controller
{
    use TranslatesDomainErrors;

    public function store(Request $request, ProvisionTenant $provision): RedirectResponse
    {
        Gate::authorize('platform.admin');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/'],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email', 'max:150'],
            'owner_phone' => ['nullable', 'string', 'max:20'],
            'timezone' => ['nullable', 'string', 'timezone:all'],
            'locale' => ['nullable', 'in:en,bn'],
        ]);

        $result = $this->guarded(fn (): ProvisionedTenant => $provision->handle($this->admin($request), $data));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.tenant_provisioned', ['name' => $result->tenant->name, 'email' => $result->owner->email])]);

        return back();
    }

    public function suspend(Request $request, int $tenant, SetTenantStatus $set): RedirectResponse
    {
        Gate::authorize('platform.admin');
        $tenant = Tenant::query()->findOrFail($tenant);
        $this->guarded(fn (): Tenant => $set->handle($this->admin($request), $tenant, Tenant::STATUS_SUSPENDED));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.tenant_suspended', ['name' => $tenant->name])]);

        return back();
    }

    public function resume(Request $request, int $tenant, SetTenantStatus $set): RedirectResponse
    {
        Gate::authorize('platform.admin');
        $tenant = Tenant::query()->findOrFail($tenant);
        $this->guarded(fn (): Tenant => $set->handle($this->admin($request), $tenant, Tenant::STATUS_ACTIVE));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.tenant_resumed', ['name' => $tenant->name])]);

        return back();
    }

    public function impersonate(Request $request, int $tenant, ImpersonateTenant $impersonate): RedirectResponse
    {
        Gate::authorize('platform.admin');
        $tenant = Tenant::query()->findOrFail($tenant);
        $owner = $this->guarded(fn (): User => $impersonate->handle($this->admin($request), $tenant));
        $request->session()->regenerate();
        Inertia::flash('toast', ['type' => 'info', 'message' => __('app.impersonation_started', ['name' => $owner->name])]);

        return redirect('/app');
    }

    private function admin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
