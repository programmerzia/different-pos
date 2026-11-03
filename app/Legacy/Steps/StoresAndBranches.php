<?php

declare(strict_types=1);

namespace App\Legacy\Steps;

use App\Actions\Settings\EnsureSystemPaymentMethods;
use App\Legacy\ImportContext;
use App\Legacy\Transform\Phone;
use App\Legacy\Transform\Text;
use App\Models\Branch;
use App\Models\Register;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use RuntimeException;
use stdClass;

/** Step 1: legacy stores -> the tenant (found or created by slug, with its system tenders) + one branch and register R1 per store. */
final readonly class StoresAndBranches implements Step
{
    private const string TABLE = 'store_storelist';

    public function __construct(private TenantContext $tenancy, private EnsureSystemPaymentMethods $systemMethods) {}

    public function name(): string
    {
        return 'StoresAndBranches';
    }

    public function run(ImportContext $context): void
    {
        $stores = $context->select(self::TABLE, ['id', 'is_use_stock_invoice', 'status'], ['store_name', 'address', 'contact_no', 'logo'])
            ->whereIn('id', $context->stores())->orderBy('id')->get()->keyBy('id');

        foreach ($context->stores() as $storeId) {
            if (! $stores->has($storeId)) {
                throw new RuntimeException("Legacy store {$storeId} does not exist.");
            }
        }

        /** @var array{name?: string, stores?: array<int, array{code: string, name: string}>} $config */
        $config = (array) config('legacy.tenants.'.$context->options->tenantSlug, []);

        $this->tenancy->withoutTenancy(function () use ($context, $stores, $config): void {
            $first = $stores->first() ?? throw new RuntimeException('No legacy store selected.');
            $tenant = Tenant::query()->where('slug', $context->options->tenantSlug)->first();
            if (! $tenant instanceof Tenant) {
                $tenant = Tenant::query()->create([
                    'slug' => $context->options->tenantSlug,
                    'name' => $config['name'] ?? $context->text($first->store_name, self::TABLE, (int) $first->id, 'store_name') ?? $context->options->tenantSlug,
                    'phone' => Phone::toE164($context->text($first->contact_no, self::TABLE, (int) $first->id, 'contact_no')),
                    'address' => Text::limit($context->text($first->address, self::TABLE, (int) $first->id, 'address'), 500),
                    'currency' => 'BDT',
                    'timezone' => 'Asia/Dhaka',
                    'locale' => 'en',
                ]);
                $context->created();
            } else {
                $context->skipped();
            }
            $context->setTenant($tenant);
            // Store credit and exchange credit tenders: returns and exchanges need them.
            $context->created($this->systemMethods->handle($tenant));

            foreach ($context->stores() as $storeId) {
                $store = $stores->get($storeId) ?? throw new RuntimeException("Legacy store {$storeId} does not exist.");
                $branch = $this->branch($context, $tenant, $store, $config['stores'][$storeId] ?? null);
                $register = Register::query()->withoutGlobalScopes()->where('branch_id', $branch->id)->where('code', 'R1')->first();
                if (! $register instanceof Register) {
                    $register = new Register;
                    $register->forceFill([
                        'tenant_id' => $tenant->id,
                        'branch_id' => $branch->id,
                        'code' => 'R1',
                        'name' => 'R1',
                        'number_year' => (int) now()->year,
                        'is_active' => true,
                    ])->save();
                    $context->created();
                }
                $context->setBranch($storeId, $branch, $register->id);
            }
        });
    }

    /** @param array{code: string, name: string}|null $config */
    private function branch(ImportContext $context, Tenant $tenant, stdClass $store, ?array $config): Branch
    {
        $storeId = (int) $store->id;
        $mapped = $context->ids->get(self::TABLE, $storeId);
        $branch = $mapped === null ? null : Branch::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->find($mapped);
        if ($branch instanceof Branch) {
            $context->skipped();

            return $branch;
        }

        $code = $config['code'] ?? 'L'.$storeId;
        $branch = Branch::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('code', $code)->first() ?? new Branch;
        $logo = $context->text($store->logo, self::TABLE, $storeId, 'logo');

        $branch->forceFill([
            'tenant_id' => $tenant->id,
            'code' => $code,
            'name' => $config['name'] ?? $context->text($store->store_name, self::TABLE, $storeId, 'store_name') ?? $code,
            'phone' => Phone::toE164($context->text($store->contact_no, self::TABLE, $storeId, 'contact_no')),
            'address' => Text::limit($context->text($store->address, self::TABLE, $storeId, 'address'), 500),
            'settings' => ['batch_selection' => (int) $store->is_use_stock_invoice === 1 ? 'auto' : 'manual'],
            'is_active' => $store->status === 'Active',
            'legacy_id' => $storeId,
        ])->save();

        if ($logo !== null) {
            $context->report->add('logo_not_copied', self::TABLE, $storeId, "Store logo [{$logo}] was not copied; upload it in branch settings");
        }

        $context->ids->put(self::TABLE, $storeId, $storeId, $branch->id);
        $context->created();

        return $branch;
    }
}
