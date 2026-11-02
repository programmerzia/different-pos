<?php

declare(strict_types=1);

use App\Http\Controllers\Platform\PlatformHomeController;
use Illuminate\Support\Facades\Route;

/* Platform admin area, prefix /platform */
Route::middleware(['auth', 'platform.admin'])->group(function (): void {
    Route::get('/', PlatformHomeController::class)->name('platform.index');
});
