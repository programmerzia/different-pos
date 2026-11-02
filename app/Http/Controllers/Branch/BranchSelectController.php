<?php

declare(strict_types=1);

namespace App\Http\Controllers\Branch;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Placeholder branch picker. */
final class BranchSelectController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return Inertia::render('branch/select', [
            'branches' => $user->branches()->where('branches.is_active', true)->get(['branches.id', 'branches.name', 'branches.code']),
        ]);
    }
}
