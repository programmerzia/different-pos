<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Reports\DashboardQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardQuery $query): Response
    {
        Gate::authorize('dashboard.view');

        return Inertia::render('dashboard', ['dashboard' => $query->get()]);
    }
}
