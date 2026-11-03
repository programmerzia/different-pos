<?php

declare(strict_types=1);

namespace App\Http\Controllers\Purchasing;

use App\Actions\Purchasing\CancelPurchase;
use App\Actions\Purchasing\PayVendor;
use App\Actions\Purchasing\ReceivePurchase;
use App\Enums\AllocatableType;
use App\Enums\PurchaseStatus;
use App\Enums\StockSourceType;
use App\Http\Controllers\Concerns\TranslatesDomainErrors;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\ReceivePurchaseRequest;
use App\Http\Requests\Vendors\PayVendorRequest;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockBatch;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Catalog\ProductLookup;
use App\Support\Http\TableQuery;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use App\Support\Stock\BranchStockColumns;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

final class PurchaseController extends Controller
{
    use AuthorizesRequests;
    use TranslatesDomainErrors;

    public function index(Request $request, BranchStockColumns $branches): Response
    {
        $this->authorize('viewAny', Purchase::class);
        $table = TableQuery::fromRequest($request, sortable: ['number', 'purchased_at', 'total', 'paid', 'due', 'vendor_ref'], filterable: ['status', 'payment', 'vendor', 'branch', 'from', 'to'], defaultSort: '-purchased_at');
        $user = $this->actor($request);
        $currentBranchId = app(TenantContext::class)->branchId();

        // Branch chips exist only for users with reports.all_branches; everyone else stays on the current branch.
        $branchList = $user->can('reports.all_branches') ? $branches->present($branches->branchesFor($user)) : [];
        $branchFilter = $branchList === [] ? '' : ($table->filters['branch'] ?? '');
        $filtered = static function () use ($table, $branchFilter, $branchList, $currentBranchId): Builder {
            $query = Purchase::query();
            if ($branchFilter === 'all') {
                $query->withoutBranchScope();
            } elseif ($branchFilter !== '' && (int) $branchFilter !== $currentBranchId) {
                abort_unless(collect($branchList)->contains('id', (int) $branchFilter), 404);
                $query->withoutBranchScope()->where('branch_id', (int) $branchFilter);
            }
            if ($table->search !== '') {
                $like = '%'.addcslashes($table->search, '\\%_').'%';
                $query->where(static fn ($q) => $q->where('number', 'like', $like)->orWhere('vendor_ref', 'like', $like)
                    ->orWhereHas('vendor', static fn ($v) => $v->where('name', 'like', $like)));
            }
            if (isset($table->filters['status'])) {
                $query->where('status', $table->filters['status']);
            }
            if (isset($table->filters['payment'])) {
                // `due` is the mockup's "Outstanding balance (> 0)": unpaid and partly paid bills.
                $table->filters['payment'] === 'due'
                    ? $query->where('due', '>', 0)->where('status', PurchaseStatus::Received->value)
                    : $query->where('payment_status', $table->filters['payment']);
            }
            foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
                $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $table->filters[$key] ?? '') === 1 ? $table->filters[$key] : null;
                if ($day !== null) {
                    $query->whereDate('purchased_at', $operator, $day);
                }
            }
            if (isset($table->filters['vendor'])) {
                $query->where('vendor_id', (int) $table->filters['vendor']);
            }

            return $query;
        };
        $query = $filtered()->with(['vendor:id,name', 'branch:id,name,code'])->withSum('items as qty_sum', 'qty')->withCount('items');

        // KPI strip and totals row: the whole filtered set, not only this page. Cancelled bills carry no stock or payable.
        $sums = $filtered()->where('status', PurchaseStatus::Received->value)->toBase()
            ->selectRaw('count(*) as bills, coalesce(sum(total), 0) as total, coalesce(sum(paid), 0) as paid, coalesce(sum(due), 0) as due')->first();
        $qtyTotal = $filtered()->where('purchases.status', PurchaseStatus::Received->value)->toBase()
            ->join('purchase_items', 'purchase_items.purchase_id', '=', 'purchases.id')->sum('purchase_items.qty');
        $vendorsOwing = Vendor::query()->where('balance', '>', 0)->count();

        $page = $table->paginate($query);

        return Inertia::render('purchases/index', [
            'rows' => collect($page->items())->map(static fn (Purchase $p): array => [
                'id' => $p->id,
                'uuid' => $p->uuid,
                'number' => $p->number,
                'vendor' => $p->vendor->name ?? '',
                'vendor_ref' => $p->vendor_ref,
                'status' => $p->status->value,
                'payment_status' => $p->payment_status->value,
                'purchased_at' => $p->purchased_at->toDateString(),
                'branch' => $p->branch instanceof Branch ? ['name' => $p->branch->name, 'code' => (string) $p->branch->code] : null,
                'current_branch' => $p->branch_id === app(TenantContext::class)->branchId(),
                'items' => (int) $p->getAttribute('items_count'),
                'qty' => Quantity::parse((string) ($p->getAttribute('qty_sum') ?? '0'))->milli,
                'total' => $p->total->paisa,
                'paid' => $p->paid->paisa,
                'due' => $p->due->paisa,
            ])->all(),
            'meta' => ['page' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => $page->lastPage()],
            'totals' => [
                'bills' => (int) ($sums->bills ?? 0),
                'total' => (int) ($sums->total ?? 0),
                'paid' => (int) ($sums->paid ?? 0),
                'due' => (int) ($sums->due ?? 0),
                'qty' => Quantity::parse((string) ($qtyTotal ?: '0'))->milli,
                'vendors_owing' => $vendorsOwing,
            ],
            'branches' => $branchList,
            'vendors' => Vendor::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Purchase::class);

        return Inertia::render('purchases/create', [
            'vendors' => Vendor::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'requires_reference']),
            'canPay' => $this->actor($request)->checkPermissionTo('payments.pay'),
            'today' => now()->toDateString(),
        ]);
    }

    /** Scan field / product picker. Cost only for users who may see it. */
    public function lookup(Request $request, ProductLookup $lookup): JsonResponse
    {
        $this->authorize('create', Purchase::class);

        return response()->json($lookup->find((string) $request->query('code', ''), $this->actor($request)->checkPermissionTo('products.view_cost')));
    }

    public function store(ReceivePurchaseRequest $request, ReceivePurchase $receive): RedirectResponse
    {
        /** @var array<string, mixed> $v */
        $v = $request->validated();

        $items = [];
        /** @var list<array<string, mixed>> $lines */
        $lines = $v['items'];
        foreach ($lines as $line) {
            $items[] = [
                'product_id' => (int) $line['product_id'],
                'qty' => Quantity::ofMilli((int) $line['qty']),
                'unit_cost' => Money::ofPaisa((int) $line['unit_cost']),
                'discount' => Money::ofPaisa((int) ($line['discount'] ?? 0)),
                'batch_no' => $line['batch_no'] ?? null,
                'expires_at' => isset($line['expires_at']) ? Carbon::parse((string) $line['expires_at']) : null,
            ];
        }

        $input = [
            'vendor_id' => (int) $v['vendor_id'],
            'items' => $items,
            'vendor_ref' => $v['vendor_ref'] ?? null,
            'purchased_at' => isset($v['purchased_at']) ? Carbon::parse((string) $v['purchased_at']) : null,
            'discount' => Money::ofPaisa((int) ($v['discount'] ?? 0)),
            'shipping' => Money::ofPaisa((int) ($v['shipping'] ?? 0)),
            'note' => $v['note'] ?? null,
        ];
        if (isset($v['payment']) && is_array($v['payment'])) {
            abort_unless($this->actor($request)->checkPermissionTo('payments.pay'), 403);
            $input['payment'] = [
                'amount' => Money::ofPaisa((int) $v['payment']['amount']),
                'payment_method_id' => (int) $v['payment']['payment_method_id'],
                'reference' => $v['payment']['reference'] ?? null,
            ];
        }

        $purchase = $this->guarded(fn (): Purchase => $receive->handle($this->actor($request), $input), 'items');
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Purchase received.')]);

        return to_route('purchases.show', $purchase);
    }

    public function show(Request $request, Purchase $purchase): Response
    {
        $this->authorize('view', $purchase);
        $purchase->load(['vendor:id,uuid,name,balance', 'items.product:id,name,sku,variant_label']);

        $itemIds = $purchase->items->pluck('id')->all();
        $batches = StockBatch::query()->where('source_type', StockSourceType::Purchase)->whereIn('source_id', $itemIds)->orderBy('id')->get();
        $payments = PaymentAllocation::query()
            ->where('allocatable_type', AllocatableType::Purchase)->where('allocatable_id', $purchase->id)
            ->with('payment:id,number,paid_at,reference')->orderBy('id')->get();

        return Inertia::render('purchases/show', [
            'purchase' => [
                'id' => $purchase->id,
                'uuid' => $purchase->uuid,
                'number' => $purchase->number,
                'vendor_id' => $purchase->vendor_id,
                'vendor_uuid' => $purchase->vendor->uuid ?? '',
                'vendor' => $purchase->vendor->name ?? '',
                'vendor_ref' => $purchase->vendor_ref,
                'status' => $purchase->status->value,
                'payment_status' => $purchase->payment_status->value,
                'purchased_at' => $purchase->purchased_at->toDateString(),
                'subtotal' => $purchase->subtotal->paisa,
                'discount' => $purchase->discount->paisa,
                'tax_total' => $purchase->tax_total->paisa,
                'shipping' => $purchase->shipping->paisa,
                'total' => $purchase->total->paisa,
                'paid' => $purchase->paid->paisa,
                'due' => $purchase->due->paisa,
                'note' => $purchase->note,
            ],
            'items' => $purchase->items->map(static fn ($i): array => [
                'id' => $i->id,
                'product' => $i->product->name ?? '',
                'variant_label' => $i->product?->variant_label,
                'sku' => $i->product->sku ?? '',
                'qty' => $i->qty->milli,
                'unit_cost' => $i->unit_cost->paisa,
                'discount' => $i->discount->paisa,
                'line_total' => $i->line_total->paisa,
            ])->all(),
            'batches' => $batches->map(static fn (StockBatch $b): array => [
                'id' => $b->id,
                'product_id' => $b->product_id,
                'batch_no' => $b->batch_no,
                'unit_cost' => $b->unit_cost->paisa,
                'qty_received' => $b->qty_received->milli,
                'qty_remaining' => $b->qty_remaining->milli,
                'expires_at' => $b->expires_at?->toDateString(),
            ])->all(),
            'payments' => $payments->map(static fn (PaymentAllocation $a): array => [
                'id' => $a->id,
                'number' => $a->payment->number ?? '',
                'paid_at' => $a->payment?->paid_at->toDateString(),
                'amount' => $a->amount->paisa,
                'reference' => $a->payment->reference ?? null,
            ])->all(),
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'requires_reference']),
            'can' => [
                'cancel' => $this->actor($request)->can('cancel', $purchase),
                'pay' => $this->actor($request)->checkPermissionTo('payments.pay'),
            ],
        ]);
    }

    public function cancel(Request $request, Purchase $purchase, CancelPurchase $cancel): RedirectResponse
    {
        $this->authorize('cancel', $purchase);
        $this->guarded(fn (): Purchase => $cancel->handle($this->actor($request), $purchase));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Purchase cancelled.')]);

        return to_route('purchases.show', $purchase);
    }

    public function pay(PayVendorRequest $request, Purchase $purchase, PayVendor $pay): RedirectResponse
    {
        $this->authorize('view', $purchase);
        $amount = Money::ofPaisa((int) $request->validated('amount'));

        $this->guarded(fn (): Payment => $pay->handle($this->actor($request), [
            'vendor_id' => $purchase->vendor_id,
            'amount' => $amount,
            'payment_method_id' => (int) $request->validated('payment_method_id'),
            'allocations' => [['purchase_id' => $purchase->id, 'amount' => $amount]],
            'reference' => $request->validated('reference'),
            'note' => $request->validated('note'),
        ]), 'amount');
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment recorded.')]);

        return back();
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
