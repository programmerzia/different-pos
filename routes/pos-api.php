<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
| Device sync API, prefix /api/pos/v1, route names `pos-api.*`. NO session, NO cookies.
| TODO(step 2.22): wrap everything except `enroll` in the AuthenticateDevice middleware
| (Sanctum bearer, ability pos:device). Until then this group is unauthenticated and holds only
| the connectivity probe, which carries no data.
*/

// 28: connectivity probe.
Route::get('ping', fn () => response()->noContent())->name('ping');
