<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\ForcedPasswordController;
use App\Http\Controllers\Branch\BranchSelectController;
use App\Http\Controllers\Branch\BranchSwitchController;
use Illuminate\Support\Facades\Route;

/*
| Session routes under /app. The first group skips EnsureBranchSelected so a user can pick a branch.
*/

Route::middleware(['auth', 'tenant.active', 'tenant.resolve', 'locale'])->group(function (): void {
    Route::get('password/change', [ForcedPasswordController::class, 'edit'])->name('password.change');
    Route::put('password/change', [ForcedPasswordController::class, 'update'])->name('password.change.update');
});

Route::middleware(['auth', 'tenant.active', 'tenant.resolve', 'password.changed', 'locale'])->group(function (): void {
    Route::get('branch/select', BranchSelectController::class)->name('branch.select');
    Route::post('branch/{branch}/switch', BranchSwitchController::class)->whereNumber('branch')->name('branch.switch');
});

Route::middleware(['auth', 'tenant.active', 'tenant.resolve', 'password.changed', 'branch.selected', 'locale'])->group(function (): void {
    require __DIR__.'/app/catalog.php';
    require __DIR__.'/app/customers.php';
    require __DIR__.'/app/stock.php';
    require __DIR__.'/app/reports.php';
    require __DIR__.'/app/admin.php';
    require __DIR__.'/app/messaging.php';
    require __DIR__.'/app/pos.php';
    require __DIR__.'/app/sales.php';
});
