<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** Placeholder; tenant management UI (route 120) comes later. Guarded by EnsurePlatformAdmin. */
final class PlatformHomeController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['ok' => true]);
    }
}
