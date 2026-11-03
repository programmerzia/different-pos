<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Enums\PaymentMethodType;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Support\Database\Transaction;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantScope;
use LogicException;

/**
 * Makes sure a business has its system tenders: store credit (customer_credit) and exchange credit
 * (exchange_credit). Idempotent: an existing method of that type is kept (and switched back on if
 * it was off), a missing one is created. Called by the tenant seeders, the legacy importer and,
 * lazily, by the Actions that need the tenders (inside their own transaction).
 */
final readonly class EnsureSystemPaymentMethods
{
    /** @var array<string, array{name: string, sort_order: int}> */
    private const array SYSTEM = [
        'customer_credit' => ['name' => 'Store credit', 'sort_order' => 90],
        'exchange_credit' => ['name' => 'Exchange credit', 'sort_order' => 91],
    ];

    public function __construct(private TenantContext $context) {}

    /**
     * Works in the current tenant context, or for $tenant when tenancy is bypassed (importer, seeders).
     *
     * @return int the number of methods created or switched back on
     */
    public function handle(?Tenant $tenant = null): int
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $this->context->tenantId();
        if (! $this->context->isBypassed() && $this->context->tenantId() !== $tenantId) {
            throw new LogicException(sprintf('Cannot ensure payment methods of tenant %d from tenant %d.', $tenantId, $this->context->tenantId()));
        }

        return Transaction::run(function () use ($tenantId): int {
            $changed = 0;
            foreach (self::SYSTEM as $type => $defaults) {
                $existing = PaymentMethod::query()->withoutGlobalScope(TenantScope::class)
                    ->where('tenant_id', $tenantId)
                    ->where('type', $type)
                    ->orderByDesc('is_active')->orderBy('sort_order')->orderBy('id')
                    ->first();

                if ($existing instanceof PaymentMethod) {
                    if (! $existing->is_active) {
                        $existing->is_active = true;
                        $existing->save();
                        $changed++;
                    }

                    continue;
                }

                $method = new PaymentMethod;
                $method->forceFill([
                    'tenant_id' => $tenantId,
                    'name' => $this->freeName($tenantId, $defaults['name']),
                    'type' => PaymentMethodType::from($type),
                    'requires_reference' => false,
                    'sort_order' => $defaults['sort_order'],
                    'is_active' => true,
                ])->save();
                $changed++;
            }

            return $changed;
        });
    }

    /** Payment method names are unique per business (case-insensitive); a clash gets a suffix. */
    private function freeName(int $tenantId, string $name): string
    {
        $taken = static fn (string $candidate): bool => PaymentMethod::query()->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($candidate)])
            ->exists();

        $candidate = $name;
        for ($n = 2; $taken($candidate); $n++) {
            $candidate = sprintf('%s (%d)', $name, $n);
        }

        return $candidate;
    }
}
