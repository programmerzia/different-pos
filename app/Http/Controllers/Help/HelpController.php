<?php

declare(strict_types=1);

namespace App\Http\Controllers\Help;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The in-app user manual (EN + BN). The text lives in the client bundle (`resources/js/manual`), so
 * the page carries no data: every signed-in tenant user may read it; chapters are filtered client-side
 * by the shared `auth.permissions` so a cashier only sees what they can do.
 */
final class HelpController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('help/index');
    }
}
