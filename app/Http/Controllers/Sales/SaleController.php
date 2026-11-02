<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\ShareSaleReceipt;
use App\Actions\Sales\VoidSale;
use App\Enums\AllocatableType;
use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Http\Controllers\Concerns\TranslatesDomainErrors;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Sales\Concerns\PresentsSales;
use App\Http\Requests\Sales\VoidSaleRequest;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemBatch;
use App\Models\SaleReturn;
use App\Models\StockBatch;
use App\Models\User;
use App\Support\Http\TableQuery;
use App\Support\Sales\CustomerAccounts;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/** Sales history (current branch): list, detail, void and share link. */
final class SaleController extends Controller
{
    use AuthorizesRequests, PresentsSales, TranslatesDomainErrors;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Sale::class);

        $table = TableQuery::fromRequest(
            $request,
            ['number', 'sold_at', 'total', 'paid', 'due'],
            ['from', 'to', 'status', 'payment_status', 'customer', 'cashier'],
            '-sold_at',
        );

        $query = Sale::query()
            ->whereNotNull('number')
            ->with(['customer' => fn ($q) => $q->withTrashed(), 'soldBy:id,name'])
            ->withCount('items');

        $this->dateRange($query, 'sold_at', $table->filters['from'] ?? null, $table->filters['to'] ?? null);
        $status = SaleStatus::tryFrom($table->filters['status'] ?? '');
        if ($status instanceof SaleStatus) {
            $query->where('status', $status);
        }
        $paymentStatus = PaymentStatus::tryFrom($table->filters['payment_status'] ?? '');
        if ($paymentStatus instanceof PaymentStatus) {
            $query->where('payment_status', $paymentStatus)->where('status', SaleStatus::Completed);
        }
        if (isset($table->filters['customer'])) {
            $query->where('customer_id', (int) $table->filters['customer']);
        }
        if (isset($table->filters['cashier'])) {
            $query->where('sold_by', (int) $table->filters['cashier']);
        }
        if ($table->search !== '') {
            $prefix = addcslashes($table->search, '\\%_').'%';
            $like = '%'.addcslashes($table->search, '\\%_').'%';
            $digits = preg_replace('/\D+/', '', $table->search) ?? '';
            $query->where(fn ($w) => $w->where('number', 'like', $prefix)
                ->orWhereIn('customer_id', Customer::query()->withTrashed()->select('id')->where(fn ($c) => $c
                    ->where('name', 'like', $like)
                    ->orWhere('shop_name', 'like', $like)
                    ->when(strlen($digits) >= 4, fn ($c) => $c->orWhere('phone', 'like', '%'.$digits)->orWhere('customer_no', 'like', $digits.'%')))));
        }

        $page = $table->paginate($query);

        $customer = isset($table->filters['customer'])
            ? Customer::query()->withTrashed()->find((int) $table->filters['customer'])
            : null;

        return Inertia::render('sales/index', [
            'rows' => array_values(array_map(fn (Sale $sale): array => [
                'id' => $sale->id,
                'number' => (string) $sale->number,
                'sold_at' => $this->localTime($sale->sold_at),
                'customer' => $this->customerSummary($sale->customer),
                'cashier' => $sale->soldBy?->name,
                'items' => (int) $sale->getAttribute('items_count'),
                'total' => $sale->total->paisa,
                'paid' => $sale->paid->paisa,
                'due' => $sale->due->paisa,
                'returned' => $sale->returned_total->paisa,
                'status' => $sale->status->value,
                'payment_status' => $sale->payment_status->value,
                'voidable' => $this->voidable($sale),
                'exchange' => $sale->exchange_return_id !== null,
            ], $page->items())),
            'meta' => ['page' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => $page->lastPage()],
            'filters' => [...$table->filters, 'q' => $table->search],
            'cashiers' => array_values(User::inCurrentTenant()->orderBy('name')->get(['id', 'name'])
                ->map(static fn (User $u): array => ['id' => $u->id, 'name' => $u->name])->all()),
            'customer' => $this->customerSummary($customer),
            'methods' => $this->moneyMethods(),
        ]);
    }

    public function show(Request $request, int $sale): Response
    {
        $model = Sale::query()->with(['items', 'soldBy:id,name', 'voidedBy:id,name', 'customer' => fn ($q) => $q->withTrashed()])->findOrFail($sale);
        $this->authorize('view', $model);
        $actor = $this->actor($request);
        $canCost = $actor->can('products.view_cost');

        /** @var list<SaleItem> $items */
        $items = $model->items->sortBy('id')->values()->all();
        $batches = $canCost
            ? SaleItemBatch::query()->whereIn('sale_item_id', array_map(static fn (SaleItem $i): int => $i->id, $items))->orderBy('id')->get()->groupBy('sale_item_id')
            : collect();
        $batchNumbers = $canCost
            ? StockBatch::query()->withoutBranchScope()->whereIn('id', $batches->flatten()->pluck('stock_batch_id')->all())->get()->keyBy('id')
            : collect();

        $returns = SaleReturn::query()->where('sale_id', $model->id)->orderBy('id')->get();
        $exchangeOf = $model->exchange_return_id !== null ? SaleReturn::query()->with('sale:id,number')->find($model->exchange_return_id) : null;

        $customer = $model->customer;
        $returnable = $model->status === SaleStatus::Completed
            && array_any($items, static fn (SaleItem $i): bool => $i->qty->compareTo($i->qty_returned) > 0);

        return Inertia::render('sales/show', [
            'sale' => [
                'id' => $model->id,
                'number' => (string) $model->number,
                'status' => $model->status->value,
                'payment_status' => $model->payment_status->value,
                'origin' => $model->origin->value,
                'sold_at' => $this->localTime($model->sold_at),
                'cashier' => $model->soldBy?->name,
                'note' => $model->note,
                'courier_name' => $model->courier_name,
                'courier_tracking' => $model->courier_tracking,
                'buyer_vat_bin' => $model->buyer_vat_bin,
                'sync_flags' => $model->sync_flags ?? [],
                'subtotal' => $model->subtotal->paisa,
                'item_discount_total' => $model->item_discount_total->paisa,
                'order_discount' => $model->order_discount->paisa,
                'tax_total' => $model->tax_total->paisa,
                'shipping' => $model->shipping->paisa,
                'rounding' => $model->rounding->paisa,
                'total' => $model->total->paisa,
                'paid' => $model->paid->paisa,
                'change_given' => $model->change_given->paisa,
                'due' => $model->due->paisa,
                'returned_total' => $model->returned_total->paisa,
                'cogs_total' => $canCost ? $model->cogs_total->paisa : null,
                'voided_at' => $this->localTime($model->voided_at),
                'voided_by' => $model->voidedBy?->name,
                'void_reason' => $model->void_reason,
                'public_url' => $model->public_token !== null ? route('receipts.public', $model->public_token) : null,
            ],
            'customer' => $customer instanceof Customer ? [
                ...($this->customerSummary($customer) ?? []),
                'balance' => $customer->balance->paisa,
            ] : null,
            'lines' => array_map(fn (SaleItem $i): array => [
                'id' => $i->id,
                'name' => $i->product_name,
                'sku' => $i->sku,
                'qty' => $i->qty->milli,
                'qty_returned' => $i->qty_returned->milli,
                'unit_price' => $i->unit_price->paisa,
                'discount' => $i->discount->paisa,
                'tax_rate_bp' => $i->tax_rate_bp,
                'tax_amount' => $i->tax_amount->paisa,
                'line_total' => $i->line_total->paisa,
                'cogs' => $canCost ? $i->cogs->paisa : null,
                'batches' => $canCost ? array_values(($batches->get($i->id) ?? collect())->map(function (SaleItemBatch $b) use ($batchNumbers): array {
                    $stock = $batchNumbers->get($b->stock_batch_id);

                    return [
                        'id' => $b->id,
                        'batch' => $stock instanceof StockBatch && $stock->batch_no !== null ? $stock->batch_no : '#'.$b->stock_batch_id,
                        'received_at' => $stock instanceof StockBatch ? $this->localTime($stock->received_at) : null,
                        'qty' => $b->qty->milli,
                        'unit_cost' => $b->unit_cost->paisa,
                    ];
                })->all()) : null,
            ], $items),
            'payments' => $this->payments($model),
            'returns' => array_values($returns->map(fn (SaleReturn $r): array => [
                'id' => $r->id,
                'number' => $r->number,
                'returned_at' => $this->localTime($r->returned_at),
                'total' => $r->total->paisa,
                'refund_mode' => $r->refund_mode->value,
                'exchange_sale_id' => $r->exchange_sale_id,
            ])->all()),
            'exchange_of' => $exchangeOf instanceof SaleReturn ? [
                'return_id' => $exchangeOf->id,
                'return_number' => $exchangeOf->number,
                'sale_id' => $exchangeOf->sale_id,
                'sale_number' => (string) $exchangeOf->sale?->number,
            ] : null,
            'audit' => $this->audit($model, array_values(array_map(intval(...), $returns->modelKeys()))),
            'can' => [
                'void' => $actor->can('void', $model) && $this->voidable($model),
                'collect' => $actor->can('collect', Payment::class) && $model->status === SaleStatus::Completed && $model->due->paisa > 0 && $customer instanceof Customer && ! $customer->is_walk_in,
                'return' => $actor->can('create', SaleReturn::class) && $returnable,
                'exchange' => $actor->can('create', SaleReturn::class) && $actor->can('create', Sale::class) && $returnable,
                'share' => $model->status === SaleStatus::Completed,
            ],
            'methods' => $this->moneyMethods(),
        ]);
    }

    public function void(VoidSaleRequest $request, int $sale, VoidSale $void): RedirectResponse
    {
        $model = Sale::query()->findOrFail($sale);
        $this->authorize('void', $model);

        $voided = $this->guarded(fn (): Sale => $void->handle($this->actor($request), $model, $request->string('reason')->trim()->value()), 'reason');
        Inertia::flash('toast', ['type' => 'success', 'message' => __('receipts.flash.voided', ['number' => $voided->number])]);

        return to_route('sales.show', $voided->id);
    }

    public function share(Request $request, int $sale, ShareSaleReceipt $share): JsonResponse
    {
        $model = Sale::query()->findOrFail($sale);
        $this->authorize('view', $model);

        $token = $this->guarded(fn (): string => $share->handle($this->actor($request), $model), 'sale');

        return response()->json(['url' => route('receipts.public', $token)]);
    }

    /** @return list<array{id: int, number: string, method: string, amount: int, payment_amount: int, reference: string|null, paid_at: string|null, voided: bool, credit: bool}> */
    private function payments(Sale $sale): array
    {
        $allocations = PaymentAllocation::query()
            ->where('allocatable_type', AllocatableType::Sale)
            ->where('allocatable_id', $sale->id)
            ->orderBy('id')
            ->get();
        $payments = Payment::query()->withoutBranchScope()->with('paymentMethod')
            ->whereIn('id', $allocations->pluck('payment_id')->all())->get()->keyBy('id');

        $rows = [];
        foreach ($allocations as $allocation) {
            $payment = $payments->get($allocation->payment_id);
            if (! $payment instanceof Payment) {
                continue;
            }
            $rows[] = [
                'id' => $payment->id,
                'number' => $payment->number,
                'method' => $payment->paymentMethod instanceof PaymentMethod ? $payment->paymentMethod->name : '',
                'amount' => $allocation->amount->paisa,
                'payment_amount' => $payment->amount->paisa,
                'reference' => $payment->reference,
                'paid_at' => $this->localTime($payment->paid_at),
                'voided' => $payment->voided_at !== null,
                'credit' => $payment->paymentMethod instanceof PaymentMethod && CustomerAccounts::isCredit($payment->paymentMethod),
            ];
        }

        return $rows;
    }

    /**
     * The last activity on the sale and its returns.
     *
     * @param  list<int>  $returnIds
     * @return list<array{event: string, by: string|null, at: string|null}>
     */
    private function audit(Sale $sale, array $returnIds): array
    {
        $activities = Activity::query()
            ->where(fn ($q) => $q->where(fn ($s) => $s->where('subject_type', $sale->getMorphClass())->where('subject_id', $sale->id))
                ->when($returnIds !== [], fn ($q) => $q->orWhere(fn ($r) => $r->where('subject_type', (new SaleReturn)->getMorphClass())->whereIn('subject_id', $returnIds))))
            ->where('tenant_id', $sale->tenant_id)
            ->latest('id')
            ->limit(12)
            ->get();
        $users = User::inCurrentTenant()->whereIn('id', $activities->pluck('causer_id')->filter()->all())->pluck('name', 'id');

        return array_values($activities->map(fn (Activity $a): array => [
            'event' => (string) ($a->event ?? $a->description),
            'by' => $a->causer_id !== null ? $users->get($a->causer_id) : null,
            'at' => $this->localTime($a->created_at),
        ])->all());
    }
}
