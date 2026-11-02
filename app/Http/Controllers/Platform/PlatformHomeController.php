<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** Placeholder until the tenant management UI exists. */
final class PlatformHomeController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['ok' => true]);
    }
}
