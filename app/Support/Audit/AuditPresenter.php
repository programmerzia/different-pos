<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Models\Activity;
use App\Models\Branch;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\StockAdjustment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Sales\BusinessDay;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Rows of the activity log page: the time in the tenant timezone (ISO 8601 with offset) and a display
 * name for the subject ("Role · Manager", not "Role #9"), resolved per subject type in one query each
 * and always within the tenant. A subject that cannot be named (deleted, or a type without a name)
 * falls back to "Type #id".
 */
final readonly class AuditPresenter
{
    /**
     * Subject class => [table, label column, extra column appended in parentheses or null].
     *
     * @var array<class-string, array{0: string, 1: string, 2: string|null}>
     */
    private const array NAMES = [
        User::class => ['users', 'name', null],
        Role::class => ['roles', 'name', null],
        Branch::class => ['branches', 'name', null],
        Customer::class => ['customers', 'name', null],
        Product::class => ['products', 'name', 'variant_label'],
        ProductFamily::class => ['product_families', 'name', null],
        Sale::class => ['sales', 'number', null],
        SaleReturn::class => ['sale_returns', 'number', null],
        StockAdjustment::class => ['stock_adjustments', 'number', null],
        Vendor::class => ['vendors', 'name', null],
        PaymentMethod::class => ['payment_methods', 'name', null],
        Courier::class => ['couriers', 'name', null],
        Tenant::class => ['tenants', 'name', null],
    ];

    public function __construct(private int $tenantId, private string $timezone) {}

    /**
     * @param  array<array-key, mixed>  $items
     * @return list<array<string, mixed>>
     */
    public function rows(array $items): array
    {
        $activities = array_values(array_filter($items, static fn (mixed $a): bool => $a instanceof Activity));
        $names = $this->names($activities);

        $rows = [];
        foreach ($activities as $a) {
            $causer = $a->causer;
            $type = $a->subject_type !== null ? class_basename($a->subject_type) : null;
            $id = $a->subject_id !== null ? (int) $a->subject_id : null;
            $name = $a->subject_type !== null && $id !== null ? ($names[$a->subject_type][$id] ?? null) : null;

            $rows[] = [
                'id' => $a->id,
                'created_at' => BusinessDay::localIso($a->created_at, $this->timezone),
                'event' => $a->event,
                'description' => $a->description,
                'user' => $causer instanceof User ? $causer->name : null,
                'ip' => is_string($a->properties['ip'] ?? null) ? $a->properties['ip'] : null,
                'subject_type' => $type,
                'subject_id' => $id,
                'subject_label' => $type === null ? null : ($name !== null ? $type.' · '.$name : $type.' #'.$id),
                'properties' => collect($a->properties)->all(),
                'changes' => collect($a->attribute_changes)->all(),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<Activity>  $activities
     * @return array<string, array<int, string>> subject class => id => name
     */
    private function names(array $activities): array
    {
        $ids = [];
        foreach ($activities as $a) {
            if ($a->subject_type !== null && $a->subject_id !== null && isset(self::NAMES[$a->subject_type])) {
                $ids[$a->subject_type][(int) $a->subject_id] = (int) $a->subject_id;
            }
        }

        $names = [];
        foreach ($ids as $class => $list) {
            [$table, $column, $extra] = self::NAMES[$class];
            $query = DB::table($table)->whereIn('id', array_values($list));
            // Tenant isolation: the tenant row itself, else rows of this tenant only.
            $table === 'tenants' ? $query->where('id', $this->tenantId) : $query->where('tenant_id', $this->tenantId);

            foreach ($query->get($extra === null ? ['id', $column] : ['id', $column, $extra]) as $row) {
                $label = trim((string) ($row->{$column} ?? ''));
                $more = $extra !== null ? trim((string) ($row->{$extra} ?? '')) : '';
                if ($label !== '') {
                    $names[$class][(int) $row->id] = $more !== '' ? $label.' ('.$more.')' : $label;
                }
            }
        }

        return $names;
    }
}
