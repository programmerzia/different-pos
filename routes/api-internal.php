<?php

declare(strict_types=1);

use App\Http\Controllers\Api\GeoController;
use App\Http\Controllers\Api\PreferencesController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Backup\BackupStatusController;
use App\Support\Geo\GeoCatalog;
use Illuminate\Support\Facades\Route;

/*
| Session JSON API, prefix /app/api, route names `api.*`.
| Middleware (bootstrap/app.php): web, auth, tenant.active, tenant.resolve, branch.selected, locale.
| Notifications arrive in a later step.
*/

Route::get('search', SearchController::class)->name('search');
Route::patch('preferences', [PreferencesController::class, 'update'])->name('preferences');
Route::get('backups/{backup}', BackupStatusController::class)->whereNumber('backup')->name('backups.show');

// Bangladesh geo reference (global, read-only, ETag-cached): the LocationFields pickers.
Route::get('geo/search', [GeoController::class, 'search'])->name('geo.search');
Route::get('geo/{level}', [GeoController::class, 'level'])->whereIn('level', GeoCatalog::LEVELS)->name('geo.level');
