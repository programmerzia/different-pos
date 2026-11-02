<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\CreateSaleReturn;
use App\Actions\Sales\Data\ReturnDraft;
use App\Enums\RefundMode;
use App\Enums\SaleStatus;
use App\Http\Controllers\Concerns\TranslatesDomainErrors;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Sales\Concerns\PresentsSales;
use App\Http\Requests\Sales\StoreSaleReturnRequest;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\User;
use App\Support\Http\TableQuery;
use App\Support\Sales\BusinessDay;
use App\Support\Sales\OrderDiscountShares;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/** Returns ledger (current branch), the return / exchange wizard and the return detail. */
final class ReturnController extends Controller
{
    use AuthorizesRequests, PresentsSales, TranslatesDomainErrors;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SaleReturn::class);

        $table = TableQuery::fromRequest($request, ['number', 'returned_at', 'total'], ['from', 'to', 'mode'], '-returned_at');
        $query = SaleReturn::query()
            ->with(['sale:id,number', 'customer' => fn ($q) => $q->withTrashed(), 'exchangeSale:id,number'])
            ->withCount('items');
        $this->dateRange($query, 'returned_at', $table->filters['from'] ?? null, $table->filters['to'] ?? null);
        $mode = RefundMode::tryFrom($table->filters['mode'] ?? '');
        if ($mode instanceof RefundMode) {
            $query->where('refund_mode', $mode);
        }
        if ($table->search !== '') {
            $prefix = addcslashes($table->search, '\\%_').'%';
            $like = '%'.addcslashes($table->search, '\\%_').'%';
            $query->where(fn ($w) => $w->where('number', 'like', $prefix)
                ->orWhereIn('sale_id', Sale::query()->select('id')->where('number', 'like', $prefix))
                ->orWhereIn('customer_id', Customer::query()->withTrashed()->select('id')->where(fn ($c) => $c->where('name', 'like', $like)->orWhere('shop_name', 'like', $like))));
        }
        $page = $table->paginate($query);

        return Inertia::render('returns/index', [
            'rows' => array_values(array_map(fn (SaleReturn $r): array => [
                'id' => $r->id,
                'number' => $r->number,
                'returned_at' => $this->localTime($r->returned_at),
                'sale' => ['id' => $r->sale_id, 'number' => (string) $r->sale?->number],
                'customer' => $this->customerSummary($r->customer),
                'items' => (int) $r->getAttribute('items_count'),
                'total' => $r->total->paisa,
                'refunded' => $r->refunded->paisa,
                'credited' => $r->credited->paisa,
                'refund_mode' => $r->refund_mode->value,
                'reason' => $r->reason,
                'exchange_sale' => $r->exchangeSale instanceof Sale ? ['id' => $r->exchangeSale->id, 'number' => (string) $r->exchangeSale->number] : null,
            ], $page->items())),
            'meta' => ['page' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => $page->lastPage()],
            'filters' => [...$table->filters, 'q' => $table->search],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', SaleReturn::class);

        return $this->wizard($request, 'return');
    }

    public function createExchange(Request $request): Response
    {
        $this->authorize('create', SaleReturn::class);
        $this->authorize('create', Sale::class);

        return $this->wizard($request, 'exchange');
    }

    public function store(StoreSaleReturnRequest $request, CreateSaleReturn $create): RedirectResponse
    {
        $this->authorize('create', SaleReturn::class);

        $return = $this->guarded(fn (): SaleReturn => $create->handle($this->actor($request), new ReturnDraft(
            saleId: $request->integer('sale_id'),
            lines: $this->returnLines($request),
            refundMode: RefundMode::from($request->string('refund_mode')->value()),
            reason: $request->string('reason')->value(),
            refundMethodId: $request->filled('refund_method_id') ? $request->integer('refund_method_id') : null,
            note: $request->string('note')->trim()->value() ?: null,
        )), 'lines');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('receipts.flash.returned', ['number' => $return->number])]);

        return to_route('returns.show', $return->id);
    }

    public function show(Request $request, int $return): Response
    {
        $model = SaleReturn::query()->with(['items.saleItem', 'sale', 'exchangeSale', 'customer' => fn ($q) => $q->withTrashed()])->findOrFail($return);
        $this->authorize('view', $model);
        $canCost = $this->actor($request)->can('products.view_cost');
        $creator = $model->created_by !== null ? User::inCurrentTenant()->find($model->created_by) : null;

        return Inertia::render('returns/show', [
            'saleReturn' => [
                'id' => $model->id,
                'number' => $model->number,
                'returned_at' => $this->localTime($model->returned_at),
                'created_by' => $creator?->name,
                'refund_mode' => $model->refund_mode->value,
                'reason' => $model->reason,
                'note' => $model->note,
                'subtotal' => $model->subtotal->paisa,
                'tax_total' => $model->tax_total->paisa,
                'total' => $model->total->paisa,
                'refunded' => $model->refunded->paisa,
                'credited' => $model->credited->paisa,
            ],
            'sale' => ['id' => $model->sale_id, 'number' => (string) $model->sale?->number, 'sold_at' => $this->localTime($model->sale?->sold_at)],
            'exchange_sale' => $model->exchangeSale instanceof Sale ? [
                'id' => $model->exchangeSale->id,
                'number' => (string) $model->exchangeSale->number,
                'total' => $model->exchangeSale->total->paisa,
                'difference' => $model->exchangeSale->total->minus($model->total)->paisa,
            ] : null,
            'customer' => $this->customerSummary($model->customer),
            'lines' => array_values($model->items->sortBy('id')->map(static fn (SaleReturnItem $i): array => [
                'id' => $i->id,
                'name' => $i->saleItem->product_name ?? '',
                'sku' => $i->saleItem->sku ?? '',
                'qty' => $i->qty->milli,
                'unit_price' => $i->unit_price->paisa,
                'tax_amount' => $i->tax_amount->paisa,
                'line_total' => $i->line_total->paisa,
                'restock' => $i->restock,
                'cogs_reversed' => $canCost ? $i->cogs_reversed->paisa : null,
            ])->all()),
        ]);
    }

    /** The shared return / exchange wizard page (returns.create and exchanges.create). */
    private function wizard(Request $request, string $mode): Response
    {
        $actor = $this->actor($request);
        $q = trim($request->string('q')->value());
        $saleId = $request->integer('sale');

        $sale = $saleId > 0 ? Sale::query()->with(['items', 'customer' => fn ($c) => $c->withTrashed()])->find($saleId) : null;
        if ($sale instanceof Sale) {
            $this->authorize('view', $sale);
        }

        return Inertia::render('returns/create', [
            'mode' => $mode,
            'q' => $q,
            'candidates' => $sale === null && mb_strlen($q) >= 2 ? $this->candidates($q) : [],
            'sale' => $sale instanceof Sale ? $this->returnableSale($sale) : null,
            'methods' => $this->moneyMethods(),
            'reasons' => StoreSaleReturnRequest::REASONS,
            'can' => [
                'exchange' => $actor->can('create', SaleReturn::class) && $actor->can('create', Sale::class),
            ],
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function candidates(string $q): array
    {
        $prefix = addcslashes($q, '\\%_').'%';
        $like = '%'.addcslashes($q, '\\%_').'%';
        $digits = preg_replace('/\D+/', '', $q) ?? '';

        return array_values(Sale::query()
            ->where('status', SaleStatus::Completed)
            ->whereNotNull('number')
            ->where(fn ($w) => $w->where('number', 'like', $prefix)
                ->orWhereIn('customer_id', Customer::query()->withTrashed()->select('id')->where(fn ($c) => $c
                    ->where('name', 'like', $like)
                    ->orWhere('shop_name', 'like', $like)
                    ->when(strlen($digits) >= 4, fn ($c) => $c->orWhere('phone', 'like', '%'.$digits)->orWhere('customer_no', 'like', $digits.'%')))))
            ->with(['customer' => fn ($c) => $c->withTrashed()])
            ->withCount('items')
            ->latest('sold_at')
            ->limit(15)
            ->get()
            ->map(fn (Sale $s): array => [
                'id' => $s->id,
                'number' => (string) $s->number,
                'sold_at' => $this->localTime($s->sold_at),
                'customer' => $this->customerSummary($s->customer),
                'items' => (int) $s->getAttribute('items_count'),
                'total' => $s->total->paisa,
                'due' => $s->due->paisa,
                'returned' => $s->returned_total->paisa,
            ])->all());
    }

    /** @return array<string, mixed> */
    private function returnableSale(Sale $sale): array
    {
        /** @var list<SaleItem> $items */
        $items = $sale->items->sortBy('id')->values()->all();
        $shares = OrderDiscountShares::of($sale, $items);
        $tenant = app(TenantContext::class)->tenant();
        $window = $tenant->setting('return_days');
        $age = BusinessDay::daysBetween($sale->sold_at, Carbon::now(), BusinessDay::timezone($tenant));

        return [
            'id' => $sale->id,
            'number' => (string) $sale->number,
            'status' => $sale->status->value,
            'sold_at' => $this->localTime($sale->sold_at),
            'customer' => $this->customerSummary($sale->customer),
            'total' => $sale->total->paisa,
            'paid' => $sale->paid->paisa,
            'due' => $sale->due->paisa,
            'returned_total' => $sale->returned_total->paisa,
            'window' => ['days' => is_int($window) ? $window : null, 'age' => $age, 'expired' => is_int($window) && $age > $window],
            'lines' => array_map(static fn (SaleItem $i): array => [
                'id' => $i->id,
                'product_id' => $i->product_id,
                'name' => $i->product_name,
                'sku' => $i->sku,
                'qty' => $i->qty->milli,
                'qty_returned' => $i->qty_returned->milli,
                'returnable' => $i->qty->minus($i->qty_returned)->milli,
                'unit_price' => $i->unit_price->paisa,
                'payable' => $i->line_total->minus($shares[$i->id])->paisa,
            ], $items),
        ];
    }
}
