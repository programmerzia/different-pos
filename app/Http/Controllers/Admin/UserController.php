<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Users\DeactivateUser;
use App\Actions\Users\SaveUser;
use App\Http\Controllers\Concerns\TranslatesDomainErrors;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveUserRequest;
use App\Models\Branch;
use App\Models\User;
use App\Support\Http\TableQuery;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/** /app/settings/users: staff of the current business, their roles and branches (users.manage via route middleware). */
final class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:users.manage')];
    }

    use TranslatesDomainErrors;

    public function index(Request $request): Response
    {
        $table = TableQuery::fromRequest($request, ['name', 'email', 'last_login_at'], ['status', 'role'], 'name');

        $query = User::query()->inCurrentTenant()->with(['roles:id,name', 'branches:branches.id,branches.code'])
            ->when($table->search !== '', function (Builder $q) use ($table): void {
                $like = '%'.addcslashes($table->search, '\\%_').'%';
                $q->where(fn (Builder $w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)
                    ->orWhere('username', 'like', $like)->orWhere('phone', 'like', $like));
            })
            ->when(($table->filters['status'] ?? '') === 'active', fn (Builder $q) => $q->where('is_active', true))
            ->when(($table->filters['status'] ?? '') === 'inactive', fn (Builder $q) => $q->where('is_active', false))
            ->when(isset($table->filters['role']), fn (Builder $q) => $q->whereHas('roles', fn (Builder $r) => $r->where('roles.id', (int) $table->filters['role'])));

        $page = $table->applySort($query)->paginate($table->perPage, ['*'], 'page', $table->page);

        return Inertia::render('settings/users/index', [
            'users' => $this->rows($page->items()),
            'meta' => ['page' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => $page->lastPage()],
            'roles' => $this->roleOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('settings/users/form', [
            'user' => null,
            'roles' => $this->roleOptions(),
            'branches' => $this->branchOptions(),
        ]);
    }

    public function edit(int $user): Response
    {
        $model = User::query()->inCurrentTenant()->with(['roles:id', 'branches:branches.id'])->findOrFail($user);

        return Inertia::render('settings/users/form', [
            'user' => [
                'id' => $model->id,
                'name' => $model->name,
                'email' => $model->email,
                'username' => $model->username,
                'phone' => $model->phone,
                'is_active' => $model->is_active,
                'must_change_password' => $model->must_change_password,
                'role_ids' => $model->roles->pluck('id')->map(intval(...))->values()->all(),
                'branch_ids' => $model->branches->pluck('id')->map(intval(...))->values()->all(),
            ],
            'roles' => $this->roleOptions(),
            'branches' => $this->branchOptions(),
        ]);
    }

    public function store(SaveUserRequest $request, SaveUser $save): RedirectResponse
    {
        $this->guarded(fn (): User => $save->handle($this->actor($request), $this->data($request)));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.created')]);

        return redirect()->route('users.index');
    }

    public function update(SaveUserRequest $request, int $user, SaveUser $save): RedirectResponse
    {
        $model = User::query()->inCurrentTenant()->findOrFail($user);
        $this->guarded(fn (): User => $save->handle($this->actor($request), $this->data($request), $model));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.updated')]);

        return redirect()->route('users.index');
    }

    public function deactivate(Request $request, int $user, DeactivateUser $deactivate): RedirectResponse
    {
        $model = User::query()->inCurrentTenant()->findOrFail($user);
        $this->guarded(fn (): User => $deactivate->handle($this->actor($request), $model));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.updated')]);

        return back();
    }

    /** @return array<string, mixed> */
    private function data(SaveUserRequest $request): array
    {
        $data = $request->validated();
        if (($data['password'] ?? null) === null) {
            unset($data['password']);
        }

        return $data;
    }

    /**
     * @param  array<array-key, mixed>  $items
     * @return list<array<string, mixed>>
     */
    private function rows(array $items): array
    {
        $rows = [];
        foreach ($items as $u) {
            if (! $u instanceof User) {
                continue;
            }
            $rows[] = [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'username' => $u->username,
                'phone' => $u->phone,
                'is_active' => $u->is_active,
                'last_login_at' => $u->last_login_at?->toIso8601String(),
                'roles' => $u->roles->pluck('name')->values()->all(),
                'branches' => $u->branches->pluck('code')->values()->all(),
            ];
        }

        return $rows;
    }

    /** @return list<array{id: int, name: string, is_system: bool}> */
    private function roleOptions(): array
    {
        return array_values(Role::query()->where('tenant_id', app(TenantContext::class)->tenantId())->orderBy('name')->get()
            ->map(static fn (Role $r): array => ['id' => (int) $r->id, 'name' => $r->name, 'is_system' => (bool) $r->getAttribute('is_system')])
            ->all());
    }

    /** @return list<array{id: int, name: string, code: string}> */
    private function branchOptions(): array
    {
        return array_values(Branch::query()->where('is_active', true)->orderBy('name')->get()
            ->map(static fn (Branch $b): array => ['id' => $b->id, 'name' => $b->name, 'code' => $b->code])
            ->all());
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
