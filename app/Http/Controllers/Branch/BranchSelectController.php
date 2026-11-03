<?php

declare(strict_types=1);

namespace App\Http\Controllers\Branch;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureBranchSelected;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** GET /app/branch/select: the branches this user may work in, the last used one first. */
final class BranchSelectController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        if (! $user->can('branches.switch')) {
            $fixed = EnsureBranchSelected::fixedBranch($user, $user->branches()->where('branches.is_active', true)->get());
            if ($fixed !== null) {
                $request->session()->put(EnsureBranchSelected::SESSION_KEY, $fixed->id);

                return redirect()->route('dashboard');
            }
        }

        $lastId = $user->preferences['last_branch_id'] ?? null;
        $branches = $user->branches()
            ->where('branches.is_active', true)
            ->orderBy('branches.name')
            ->get(['branches.id', 'branches.name', 'branches.code', 'branches.address'])
            ->map(static fn ($branch): array => [
                'id' => (int) $branch->id,
                'name' => (string) $branch->name,
                'code' => (string) $branch->code,
                'address' => $branch->address,
            ])
            ->sortByDesc(static fn (array $branch): int => $branch['id'] === $lastId ? 1 : 0)
            ->values()
            ->all();

        return Inertia::render('branch/select', [
            'branches' => $branches,
            'lastBranchId' => is_int($lastId) ? $lastId : null,
        ]);
    }
}
