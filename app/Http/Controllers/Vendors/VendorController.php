<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendors;

use App\Actions\Purchasing\PayVendor;
use App\Actions\Vendors\DeactivateVendor;
use App\Actions\Vendors\SaveVendor;
use App\Enums\PurchaseStatus;
use App\Http\Controllers\Concerns\TranslatesDomainErrors;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendors\PayVendorRequest;
use App\Http\Requests\Vendors\SaveVendorRequest;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Http\TableQuery;
use App\Support\Money\Money;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

final class VendorController extends Controller
{
    use AuthorizesRequests;
    use TranslatesDomainErrors;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Vendor::class);
        $table = TableQuery::fromRequest($request, sortable: ['name', 'balance', 'company'], filterable: ['status', 'hub'], defaultSort: 'name');

        $query = Vendor::query();
        if ($table->search !== '') {
            $like = '%'.addcslashes($table->search, '\\%_').'%';
            $query->where(static fn ($q) => $q->where('name', 'like', $like)->orWhere('company', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('address', 'like', $like));
        }
        match ($table->filters['status'] ?? null) {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            'owing' => $query->where('balance', '>', 0),
            'cleared' => $query->where('balance', '<=', 0),
            default => null,
        };
        if (isset($table->filters['hub'])) {
            $query->where('address', 'like', '%'.addcslashes($table->filters['hub'], '\\%_').'%');
        }
        $totals = (clone $query)->toBase()->reorder()->selectRaw('coalesce(sum(case when balance > 0 then balance else 0 end), 0) as payable')->first();
        $openBills = Purchase::query()->withoutBranchScope()->where('status', PurchaseStatus::Received->value)->where('due', '>', 0)
            ->whereIn('vendor_id', (clone $query)->toBase()->reorder()->select('id'))->count();
        $page = $table->paginate($query);

        // Open bills and last order across the tenant's branches (a vendor is tenant-wide, like its balance).
        /** @var array<int, array{open: int, last: string|null}> $bills */
        $bills = [];
        $grouped = Purchase::query()->withoutBranchScope()->where('status', PurchaseStatus::Received->value)
            ->whereIn('vendor_id', collect($page->items())->pluck('id')->all())
            ->groupBy('vendor_id')
            ->toBase()
            ->selectRaw('vendor_id, sum(due > 0) as open_bills, max(purchased_at) as last_order')
            ->get();
        foreach ($grouped as $g) {
            $bills[(int) $g->vendor_id] = ['open' => (int) $g->open_bills, 'last' => $g->last_order !== null ? Carbon::parse((string) $g->last_order)->toDateString() : null];
        }
        $user = $this->actor($request);

        return Inertia::render('vendors/index', [
            'rows' => collect($page->items())->map(fn (Vendor $v): array => $this->row($v) + [
                'open_bills' => $bills[$v->id]['open'] ?? 0,
                'last_order' => $bills[$v->id]['last'] ?? null,
            ])->all(),
            'meta' => ['page' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => $page->lastPage()],
            'totals' => ['payable' => (int) ($totals->payable ?? 0), 'open_bills' => $openBills],
            'hubs' => $this->hubs(),
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'requires_reference']),
            'canPay' => $user->checkPermissionTo('payments.pay'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Vendor::class);

        return Inertia::render('vendors/form', ['vendor' => null]);
    }

    public function store(SaveVendorRequest $request, SaveVendor $save): RedirectResponse
    {
        $vendor = $this->guarded(fn (): Vendor => $save->handle($this->actor($request), $this->data($request)));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Vendor saved.')]);

        return to_route('vendors.show', $vendor);
    }

    public function show(Request $request, Vendor $vendor): Response
    {
        $this->authorize('view', $vendor);
        $user = $this->actor($request);

        return Inertia::render('vendors/show', [
            'vendor' => $this->row($vendor) + ['email' => $vendor->email, 'address' => $vendor->address, 'notes' => $vendor->notes],
            'purchases' => Purchase::query()->where('vendor_id', $vendor->id)->latest('purchased_at')->latest('id')->limit(50)->get()
                ->map(static fn (Purchase $p): array => [
                    'id' => $p->id,
                    'uuid' => $p->uuid,
                    'number' => $p->number,
                    'status' => $p->status->value,
                    'payment_status' => $p->payment_status->value,
                    'purchased_at' => $p->purchased_at->toDateString(),
                    'total' => $p->total->paisa,
                    'due' => $p->due->paisa,
                ])->all(),
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'requires_reference']),
            'canPay' => $user->checkPermissionTo('payments.pay'),
        ]);
    }

    public function edit(Vendor $vendor): Response
    {
        $this->authorize('update', $vendor);

        return Inertia::render('vendors/form', [
            'vendor' => ['id' => $vendor->id, 'uuid' => $vendor->uuid, 'name' => $vendor->name, 'company' => $vendor->company, 'phone' => $vendor->phone, 'email' => $vendor->email, 'address' => $vendor->address, 'notes' => $vendor->notes],
        ]);
    }

    public function update(SaveVendorRequest $request, Vendor $vendor, SaveVendor $save): RedirectResponse
    {
        $this->guarded(fn (): Vendor => $save->handle($this->actor($request), $this->data($request), $vendor));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Vendor saved.')]);

        return to_route('vendors.show', $vendor);
    }

    public function destroy(Request $request, Vendor $vendor, DeactivateVendor $deactivate): RedirectResponse
    {
        $this->authorize('delete', $vendor);
        $this->guarded(fn (): Vendor => $deactivate->handle($this->actor($request), $vendor));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Vendor deactivated.')]);

        return to_route('vendors.index');
    }

    public function pay(PayVendorRequest $request, Vendor $vendor, PayVendor $pay): RedirectResponse
    {
        $this->authorize('view', $vendor);
        $this->guarded(fn (): Payment => $pay->handle($this->actor($request), [
            'vendor_id' => $vendor->id,
            'amount' => Money::ofPaisa((int) $request->validated('amount')),
            'payment_method_id' => (int) $request->validated('payment_method_id'),
            'reference' => $request->validated('reference'),
            'note' => $request->validated('note'),
        ]), 'amount');
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment recorded.')]);

        return back();
    }

    /** The vendor's hub: the last comma-separated part of its address (vendors have no separate hub field). */
    private function hubOf(?string $address): ?string
    {
        if ($address === null) {
            return null;
        }
        $parts = array_values(array_filter(array_map(trim(...), explode(',', $address)), static fn (string $p): bool => $p !== ''));
        $hub = $parts === [] ? '' : $parts[count($parts) - 1];

        return $hub === '' ? null : $hub;
    }

    /**
     * Hub chips: the most common hubs among the tenant's vendors.
     *
     * @return list<string>
     */
    private function hubs(): array
    {
        $counts = [];
        foreach (Vendor::query()->whereNotNull('address')->pluck('address') as $address) {
            $hub = $this->hubOf((string) $address);
            if ($hub !== null) {
                $counts[$hub] = ($counts[$hub] ?? 0) + 1;
            }
        }
        arsort($counts);

        return array_slice(array_map(strval(...), array_keys($counts)), 0, 6);
    }

    /** @return array<string, mixed> */
    private function row(Vendor $vendor): array
    {
        return [
            'id' => $vendor->id,
            'uuid' => $vendor->uuid,
            'name' => $vendor->name,
            'company' => $vendor->company,
            'phone' => $vendor->phone,
            'address' => $vendor->address,
            'hub' => $this->hubOf($vendor->address),
            'balance' => $vendor->balance->paisa,
            'is_active' => $vendor->is_active,
        ];
    }

    /** @return array{name: string, company: string|null, phone: string|null, email: string|null, address: string|null, notes: string|null} */
    private function data(SaveVendorRequest $request): array
    {
        return [
            'name' => (string) $request->validated('name'),
            'company' => $request->validated('company'),
            'phone' => $request->validated('phone'),
            'email' => $request->validated('email'),
            'address' => $request->validated('address'),
            'notes' => $request->validated('notes'),
        ];
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
