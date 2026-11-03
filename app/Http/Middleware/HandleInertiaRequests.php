<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $context = app(TenantContext::class);

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                // Lazy: the tenant context is set by route middleware that runs after this one.
                'permissions' => fn (): array => $this->permissions($request, $context),
                'branches' => fn (): array => $this->branches($request, $context),
                'branch_id' => fn (): ?int => $context->hasBranch() ? $context->branchId() : null,
            ],
            // Lazy for the same reason; null outside a tenant context (guests, the platform console).
            'tenant' => fn (): ?array => $context->hasTenant()
                ? ['name' => $context->tenant()->name, 'logo_url' => $context->tenant()->logoUrl()]
                : null,
            'preferences' => fn (): array => $this->preferences($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /** @return list<string> */
    private function permissions(Request $request, TenantContext $context): array
    {
        $user = $request->user();
        if (! $user instanceof User || ! $context->hasTenant() || $user->tenant_id !== $context->tenantId()) {
            return [];
        }

        return array_values($user->getAllPermissions()->map(static fn ($permission): string => (string) $permission->name)->values()->all());
    }

    /** @return list<array{id: int, name: string}> */
    private function branches(Request $request, TenantContext $context): array
    {
        $user = $request->user();
        if (! $user instanceof User || ! $context->hasTenant() || $user->tenant_id !== $context->tenantId()) {
            return [];
        }

        return array_values($user->branches()->where('branches.is_active', true)->orderBy('branches.name')->get()
            ->map(static fn ($branch): array => ['id' => (int) $branch->id, 'name' => (string) $branch->name])
            ->all());
    }

    /** @return array{locale: string, theme: string, scheme: string, density: string, bn_digits: bool} */
    private function preferences(Request $request): array
    {
        $user = $request->user();
        $p = $user instanceof User ? ($user->preferences ?? []) : [];

        return [
            'locale' => app()->getLocale() === 'bn' ? 'bn' : 'en',
            'theme' => is_string($p['theme'] ?? null) ? $p['theme'] : 'system',
            'scheme' => is_string($p['scheme'] ?? null) ? $p['scheme'] : 'teal',
            'density' => ($p['density'] ?? null) === 'compact' ? 'compact' : 'comfortable',
            'bn_digits' => (bool) ($p['bn_digits'] ?? true),
        ];
    }
}
