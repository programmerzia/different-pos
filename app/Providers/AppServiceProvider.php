<?php

declare(strict_types=1);

namespace App\Providers;

use App\Backup\BackupRunner;
use App\Backup\SpatieBackupRunner;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Platform\Impersonation;
use App\Support\Sync\SyncVersionRecorder;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Activitylog\Actions\LogActivityAction;

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
        $this->app->bind(BackupRunner::class, SpatieBackupRunner::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        LogActivityAction::beforeLogging(Audit::prepare(...));
        // Not permissions (spatie's before-hook yields null for unknown names): platform-level abilities.
        Gate::define('platform.admin', static fn (User $user): bool => $user->is_platform_admin && $user->tenant_id === null);
        Gate::define('impersonating', static fn (User $user): bool => app(Impersonation::class)->isActive());

        // Customer PIN checks at the register (04_TRD §5.3): 10/min per operator and 5/min per customer.
        RateLimiter::for('pos-verify-pin', fn (Request $request): array => [
            Limit::perMinute(10)->by('operator:'.($request->user()?->getAuthIdentifier() ?? $request->ip())),
            Limit::perMinute(5)->by('customer:'.$request->route('customer')),
        ]);
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
