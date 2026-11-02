<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
| Device sync API (/api/pos/v1). No session, no cookies.
| TODO: device auth (Sanctum bearer) goes in front of everything except enroll.
*/

// Connectivity probe.
Route::get('ping', fn () => response()->noContent())->name('ping');
