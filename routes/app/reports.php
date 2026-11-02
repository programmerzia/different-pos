<?php

declare(strict_types=1);

use App\Http\Controllers\Reports\DashboardController;
use App\Http\Controllers\Reports\ReportController;
use Illuminate\Support\Facades\Route;

/*
| Dashboard and reports (02_AppFlow §2 rows 10, 95, 96). Included from routes/app.php inside the
| branch-selected group, so the tenant and branch context are set.
*/

Route::get('/', DashboardController::class)->name('dashboard');

Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
Route::post('reports/{report}/export', [ReportController::class, 'export'])->name('reports.export');
