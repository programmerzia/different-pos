<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Support\Platform\PlatformStats;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** GET /platform: KPIs, the tenant registry and the infrastructure health panel (platform admins only). */
final class PlatformConsoleController extends Controller
{
    public function __invoke(PlatformStats $stats): Response
    {
        Gate::authorize('platform.admin');

        return Inertia::render('platform/index', [
            'kpis' => $stats->kpis(),
            'tenants' => $stats->tenants(),
            'health' => $stats->health(),
            'timezones' => \DateTimeZone::listIdentifiers(\DateTimeZone::ASIA),
        ]);
    }
}
