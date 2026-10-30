<?php

declare(strict_types=1);

namespace App\Http\Controllers\Branch;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Placeholder for route 6 (`branch.select`); the real page is plan step 1.12. */
final class BranchSelectController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401); // authorization: any authenticated tenant user may list own branches

        return Inertia::render('branch/select', [
            'branches' => $user->branches()->where('branches.is_active', true)->get(['branches.id', 'branches.name', 'branches.code']),
        ]);
    }
}
