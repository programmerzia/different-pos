<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Users\DeleteRole;
use App\Actions\Users\SaveRole;
use App\Http\Controllers\Concerns\TranslatesDomainErrors;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Permissions\Permission;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/** /app/settings/roles: the permission matrix (users.manage via route middleware). */
final class RoleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:users.manage')];
    }

    use TranslatesDomainErrors;

    public function index(): Response
    {
        $roles = $this->roles()->with('permissions:id,name')->withCount('users')->orderBy('id')->get();

        return Inertia::render('settings/roles', [
            'permissions' => Permission::ALL,
            'roles' => $roles->map(static fn (Role $r): array => [
                'id' => (int) $r->id,
                'name' => $r->name,
                'is_system' => (bool) $r->getAttribute('is_system'),
                'max_discount_pct_bp' => (int) $r->getAttribute('max_discount_pct_bp'),
                'permissions' => $r->permissions->pluck('name')->values()->all(),
                'users' => (int) $r->getAttribute('users_count'),
            ])->values()->all(),
        ]);
    }

    public function store(Request $request, SaveRole $save): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $this->guarded(fn (): Role => $save->handle($this->actor($request), $data));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.created')]);

        return back();
    }

    public function update(Request $request, int $role, SaveRole $save): RedirectResponse
    {
        $model = $this->roles()->findOrFail($role);
        $data = $request->validate($this->rules());
        $this->guarded(fn (): Role => $save->handle($this->actor($request), $data, $model));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.updated')]);

        return back();
    }

    public function destroy(Request $request, int $role, DeleteRole $delete): RedirectResponse
    {
        $model = $this->roles()->findOrFail($role);
        $this->guarded(fn () => $delete->handle($this->actor($request), $model));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.deleted')]);

        return back();
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:60'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(Permission::ALL)],
            'max_discount_pct_bp' => ['sometimes', 'integer', 'between:0,10000'],
        ];
    }

    /** @return Builder<Role> */
    private function roles(): Builder
    {
        return Role::query()->where('tenant_id', app(TenantContext::class)->tenantId());
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
