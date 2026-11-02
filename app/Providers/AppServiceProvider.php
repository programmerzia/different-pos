<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Sync\SyncVersionRecorder;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Reset for every request and queued job (Octane/queue safe).
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(SyncVersionRecorder::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Strict models (lazy loading, silent attribute discards) outside production.
        Model::shouldBeStrict(! app()->isProduction());

        if (app()->runningUnitTests()) {
            $this->loadMigrationsFrom(base_path('tests/database/migrations'));
        }

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
