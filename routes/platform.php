<?php

declare(strict_types=1);

use App\Http\Controllers\Platform\PlatformConsoleController;
use App\Http\Controllers\Platform\PlatformTenantController;
use Illuminate\Support\Facades\Route;

/* Platform admin area, prefix /platform (02_AppFlow §2 row 120): platform admins only (is_platform_admin, no tenant). */
Route::middleware(['auth', 'platform.admin'])->group(function (): void {
    Route::get('/', PlatformConsoleController::class)->name('platform.index');
    Route::post('tenants', [PlatformTenantController::class, 'store'])->name('platform.tenants.store');
    Route::post('tenants/{tenant}/suspend', [PlatformTenantController::class, 'suspend'])->whereNumber('tenant')->name('platform.tenants.suspend');
    Route::post('tenants/{tenant}/resume', [PlatformTenantController::class, 'resume'])->whereNumber('tenant')->name('platform.tenants.resume');
    Route::post('tenants/{tenant}/impersonate', [PlatformTenantController::class, 'impersonate'])->whereNumber('tenant')->name('platform.tenants.impersonate');
});
