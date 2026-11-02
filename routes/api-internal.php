<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PreferencesController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Support\Facades\Route;

/*
| Session JSON API, prefix /app/api, route names `api.*`.
| Middleware (bootstrap/app.php): web, auth, tenant.active, tenant.resolve, branch.selected, locale.
| Notifications arrive in a later step.
*/

Route::get('search', SearchController::class)->name('search');
Route::patch('preferences', [PreferencesController::class, 'update'])->name('preferences');
