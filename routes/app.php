<?php

declare(strict_types=1);

use App\Http\Controllers\Branch\BranchSelectController;
use Illuminate\Support\Facades\Route;

/*
| Session routes under /app. The first group skips EnsureBranchSelected so a user can pick a branch.
*/

Route::middleware(['auth', 'tenant.active', 'tenant.resolve', 'locale'])->group(function (): void {
    Route::get('branch/select', BranchSelectController::class)->name('branch.select');
});

Route::middleware(['auth', 'tenant.active', 'tenant.resolve', 'branch.selected', 'locale'])->group(function (): void {
    Route::inertia('/', 'dashboard')->middleware('can:dashboard.view')->name('dashboard');
});
