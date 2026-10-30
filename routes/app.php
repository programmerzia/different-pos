<?php

declare(strict_types=1);

use App\Http\Controllers\Branch\BranchSelectController;
use Illuminate\Support\Facades\Route;

/*
| Session routes, prefix /app (registered in bootstrap/app.php with the `web` group).
| Stack order follows 05_Schema §11: auth → EnsureTenantActive → ResolveTenant → EnsureBranchSelected → SetLocale.
| Routes marked "(no branch)" in 02_AppFlow §2 sit in the first group, which skips EnsureBranchSelected.
*/

Route::middleware(['auth', 'tenant.active', 'tenant.resolve', 'locale'])->group(function (): void {
    // 6: placeholder until plan step 1.12.
    Route::get('branch/select', BranchSelectController::class)->name('branch.select');
});

Route::middleware(['auth', 'tenant.active', 'tenant.resolve', 'branch.selected', 'locale'])->group(function (): void {
    // 10
    Route::inertia('/', 'dashboard')->middleware('can:dashboard.view')->name('dashboard');
});
