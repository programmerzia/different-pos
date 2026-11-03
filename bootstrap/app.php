<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureBranchSelected;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureTenantActive;
use App\Http\Middleware\ExpireImpersonation;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Session routes live under /app; the device API has no session.
            Route::middleware('web')->prefix('app')->group(base_path('routes/app.php'));
            Route::middleware('web')->prefix('platform')->group(base_path('routes/platform.php'));
            Route::middleware(['web', 'auth', 'tenant.active', 'tenant.resolve', 'password.changed', 'branch.selected', 'locale'])
                ->prefix('app/api')->as('api.')->group(base_path('routes/api-internal.php'));
            Route::middleware('api')->prefix('api/pos/v1')->as('pos-api.')->group(base_path('routes/pos-api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant.active' => EnsureTenantActive::class,
            'tenant.resolve' => ResolveTenant::class,
            'branch.selected' => EnsureBranchSelected::class,
            'password.changed' => EnsurePasswordChanged::class,
            'locale' => SetLocale::class,
            'platform.admin' => EnsurePlatformAdmin::class,
        ]);

        // Route-model binding resolves tenant-scoped models, so the tenant/branch context must exist first.
        foreach ([EnsureTenantActive::class, ResolveTenant::class, EnsurePasswordChanged::class, EnsureBranchSelected::class] as $contextMiddleware) {
            $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: $contextMiddleware);
        }

        $middleware->encryptCookies(except: ['appearance', 'scheme', 'density', 'sidebar_state', 'pos_tenant', 'locale']);

        $middleware->web(append: [
            ExpireImpersonation::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
