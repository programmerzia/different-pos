<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * session('branch_id') must be one of the user's assigned, active branches of the current tenant;
 * re-checked on every request. With no session branch the last used one (users.preferences.last_branch_id)
 * is restored, and a user with exactly one such branch gets it auto-selected.
 * A user without branches.switch never sees the picker: see fixedBranch().
 * Runs after ResolveTenant (the Branch tenant scope then excludes foreign branches).
 */
final readonly class EnsureBranchSelected
{
    public const string SESSION_KEY = 'branch_id';

    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $branches = $user->branches()->where('branches.is_active', true)->get();
        $selectedId = $request->session()->get(self::SESSION_KEY);

        $branch = is_int($selectedId) || is_string($selectedId)
            ? $branches->firstWhere('id', (int) $selectedId)
            : null;

        if (! $branch instanceof Branch && ! $user->can('branches.switch')) {
            $branch = self::fixedBranch($user, $branches);
        }

        if (! $branch instanceof Branch) {
            $lastId = $user->preferences['last_branch_id'] ?? null;
            $branch = is_int($lastId) ? $branches->firstWhere('id', $lastId) : null;
        }

        if (! $branch instanceof Branch && $branches->count() === 1) {
            $branch = $branches->first();
        }

        if (! $branch instanceof Branch) {
            $request->session()->forget(self::SESSION_KEY);

            if ($request->expectsJson()) {
                return response()->json(['code' => 'branch_required'], 409);
            }

            return redirect()->route('branch.select');
        }

        $request->session()->put(self::SESSION_KEY, $branch->id);
        $this->context->set($this->context->tenant(), $branch);

        return $next($request);
    }

    /**
     * The branch of a user who may not switch: the pivot default, else the remembered one, else the first active one.
     *
     * @param  Collection<int, Branch>  $branches
     */
    public static function fixedBranch(User $user, Collection $branches): ?Branch
    {
        $default = $branches->first(static fn (Branch $b): bool => (bool) $b->getRelation('pivot')->is_default);
        if ($default instanceof Branch) {
            return $default;
        }

        $lastId = $user->preferences['last_branch_id'] ?? null;
        $last = is_int($lastId) ? $branches->firstWhere('id', $lastId) : null;

        return $last instanceof Branch ? $last : $branches->sortBy('id')->first();
    }
}
