<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PaymentMethodType;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * DIFFERENT FASHION's real data: customers, products, stock, vendors, purchases, sales and payments
 * come from the legacy POS dump through `legacy:import` (ADR 0002), read over the `legacy` connection
 * (LEGACY_DB_* in .env). The import runs once (never under the test suite): a tenant that already has
 * customers is left alone.
 * Afterwards the shop's tenders and logo are set, the same way scratchpad rebuilds did.
 */
final class DifferentFashionSeeder extends Seeder
{
    public const string SLUG = 'different-fashion';

    private const string STORES = '7,10';

    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', self::SLUG)->firstOrFail();

        if (app()->runningUnitTests()) {
            // The suite seeds a bare shop; LegacyImportTest covers the import against its own fixture.
        } elseif ($this->alreadyImported($tenant)) {
            $this->command?->info('Different Fashion: legacy data already imported, skipping legacy:import.');
        } elseif (! $this->legacyReachable()) {
            $this->command?->warn('Different Fashion: the `legacy` connection is not reachable (LEGACY_DB_*), skipping legacy:import. Load the dump and run `php artisan legacy:import --connection=legacy --stores='.self::STORES.' --tenant='.self::SLUG.'`.');
        } else {
            $exit = Artisan::call('legacy:import', [
                '--connection' => 'legacy',
                '--stores' => self::STORES,
                '--tenant' => self::SLUG,
            ], $this->command?->getOutput());

            if ($exit !== 0) {
                throw new \RuntimeException('legacy:import failed with exit code '.$exit);
            }
        }

        app(TenantContext::class)->run($tenant, null, function () use ($tenant): void {
            $this->tenders();
            $this->logo($tenant);
        });
    }

    private function alreadyImported(Tenant $tenant): bool
    {
        return app(TenantContext::class)->run(
            $tenant,
            null,
            static fn (): bool => Customer::query()->where('is_walk_in', false)->exists(),
        );
    }

    private function legacyReachable(): bool
    {
        try {
            DB::connection('legacy')->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** The tenders the shop takes besides cash (system tenders come from EnsureSystemPaymentMethods). */
    private function tenders(): void
    {
        $tenders = [
            ['Card', PaymentMethodType::Card, 2, true],
            ['bKash', PaymentMethodType::MobileWallet, 3, true],
            ['Nagad', PaymentMethodType::MobileWallet, 4, true],
            ['Rocket', PaymentMethodType::MobileWallet, 5, true],
            ['Bank transfer', PaymentMethodType::Bank, 6, true],
        ];

        foreach ($tenders as [$name, $type, $order, $reference]) {
            PaymentMethod::query()->firstOrCreate(
                ['name' => $name],
                ['type' => $type, 'requires_reference' => $reference, 'sort_order' => $order, 'is_active' => true],
            );
        }
    }

    private function logo(Tenant $tenant): void
    {
        $source = base_path('tools/mockup/logo.jpg');
        if (! File::exists($source)) {
            return;
        }

        $relative = 'tenants/'.$tenant->id.'/logo.jpg';
        $target = storage_path('app/public/'.$relative);
        File::ensureDirectoryExists(dirname($target));
        if (! File::exists($target)) {
            File::copy($source, $target);
        }

        $branding = $tenant->branding ?? [];
        if (($branding['logo_path'] ?? null) !== $relative) {
            $branding['logo_path'] = $relative;
            $tenant->branding = $branding;
            $tenant->save();
        }
    }
}
