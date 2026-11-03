<?php

declare(strict_types=1);

namespace App\Http\Controllers\Stock;

use App\Actions\Stock\CreateStockTransfer;
use App\Actions\Stock\ReceiveStockTransfer;
use App\Enums\StockTransferStatus;
use App\Http\Controllers\Concerns\TranslatesDomainErrors;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\CreateStockTransferRequest;
use App\Http\Requests\Stock\ReceiveStockTransferRequest;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Support\Catalog\ProductLookup;
use App\Support\Http\TableQuery;
use App\Support\Quantity\Quantity;
use App\Support\Sales\BusinessDay;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Inter-branch stock transfers: gate passes sent from the current branch and those arriving at it. */
final class TransferController extends Controller
{
    use AuthorizesRequests;
    use TranslatesDomainErrors;

    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', StockTransfer::class);
        $branch = $this->context->branch();
        $table = TableQuery::fromRequest($request, sortable: ['number', 'sent_at'], filterable: ['status', 'direction'], defaultSort: '-sent_at');

        $query = StockTransfer::query()->withCount('items')->with(['fromBranch:id,code,name', 'toBranch:id,code,name']);
        $direction = $table->filters['direction'] ?? '';
        match ($direction) {
            'incoming' => $query->where('to_branch_id', $branch->id),
            'outgoing' => $query->where('from_branch_id', $branch->id),
            default => $query->where(fn ($q) => $q->where('to_branch_id', $branch->id)->orWhere('from_branch_id', $branch->id)),
        };
        if ($table->search !== '') {
            $query->where('number', 'like', '%'.addcslashes($table->search, '\\%_').'%');
        }
        if (isset($table->filters['status']) && StockTransferStatus::tryFrom($table->filters['status']) !== null) {
            $query->where('status', $table->filters['status']);
        }
        $page = $table->paginate($query);
        $tz = BusinessDay::timezone($this->context->tenant());

        return Inertia::render('transfers/index', [
            'rows' => collect($page->items())->map(fn (StockTransfer $t): array => [
                'id' => $t->id,
                'uuid' => $t->uuid,
                'number' => $t->number,
                'status' => $t->status->value,
                'from' => self::branchLabel($t->fromBranch),
                'to' => self::branchLabel($t->toBranch),
                'incoming' => $t->to_branch_id === $branch->id,
                'lines' => (int) $t->getAttribute('items_count'),
                'note' => $t->note,
                'sent_at' => BusinessDay::localDate($t->sent_at, $tz),
                'received_at' => BusinessDay::localDate($t->received_at, $tz),
            ])->all(),
            'meta' => ['page' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => $page->lastPage()],
            'statuses' => array_map(static fn (StockTransferStatus $s): string => $s->value, StockTransferStatus::cases()),
            'incomingCount' => StockTransfer::query()->where('to_branch_id', $branch->id)->where('status', StockTransferStatus::InTransit)->count(),
        ]);
    }

    /** `?product={uuid}` (the stock list's row "Transfer") starts the gate pass with that product as its first line. */
    public function create(Request $request): Response
    {
        $this->authorize('create', StockTransfer::class);
        $branch = $this->context->branch();
        $uuid = $request->query('product');
        $product = is_string($uuid) && $uuid !== '' ? Product::query()->where('uuid', $uuid)->where('track_stock', true)->first() : null;

        return Inertia::render('transfers/create', [
            'from' => ['id' => $branch->id, 'code' => $branch->code, 'name' => $branch->name],
            'branches' => Branch::query()->where('is_active', true)->where('id', '!=', $branch->id)->orderBy('name')->get(['id', 'code', 'name'])
                ->map(static fn (Branch $b): array => ['id' => $b->id, 'code' => $b->code, 'name' => $b->name])->all(),
            'prefill' => $product instanceof Product ? [
                'id' => $product->id,
                'name' => $product->name,
                'variant_label' => $product->variant_label,
                'sku' => $product->sku,
                'on_hand' => self::onHand($product)->milli,
                'cost_price' => $this->actor($request)->can('products.view_cost') ? $product->cost_price->paisa : 0,
            ] : null,
            'canCost' => $this->actor($request)->can('products.view_cost'),
        ]);
    }

    public function lookup(Request $request, ProductLookup $lookup): JsonResponse
    {
        $this->authorize('create', StockTransfer::class);

        return response()->json($lookup->find((string) $request->query('code', ''), $this->actor($request)->can('products.view_cost')));
    }

    public function store(CreateStockTransferRequest $request, CreateStockTransfer $create): RedirectResponse
    {
        /** @var list<array<string, mixed>> $lines */
        $lines = $request->validated('items');
        $items = array_map(static fn (array $line): array => [
            'product_id' => (int) $line['product_id'],
            'qty' => Quantity::ofMilli((int) $line['qty']),
        ], $lines);

        $transfer = $this->guarded(fn (): StockTransfer => $create->handle($this->actor($request), [
            'to_branch_id' => (int) $request->validated('to_branch_id'),
            'items' => $items,
            'note' => $request->validated('note'),
        ]), 'items');
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Gate pass :number dispatched.', ['number' => $transfer->number])]);

        return to_route('transfers.show', $transfer);
    }

    public function show(Request $request, StockTransfer $transfer): Response
    {
        $this->authorize('view', $transfer);
        $user = $this->actor($request);
        $tz = BusinessDay::timezone($this->context->tenant());
        $transfer->load(['fromBranch:id,code,name', 'toBranch:id,code,name', 'sentBy:id,name', 'receivedBy:id,name', 'items.product:id,name,sku,variant_label']);

        return Inertia::render('transfers/show', [
            'transfer' => [
                'id' => $transfer->id,
                'uuid' => $transfer->uuid,
                'number' => $transfer->number,
                'status' => $transfer->status->value,
                'from' => self::branchLabel($transfer->fromBranch),
                'to' => self::branchLabel($transfer->toBranch),
                'note' => $transfer->note,
                'sent_at' => BusinessDay::localIso($transfer->sent_at, $tz),
                'sent_by' => $transfer->sentBy?->name,
                'received_at' => BusinessDay::localIso($transfer->received_at, $tz),
                'received_by' => $transfer->receivedBy?->name,
            ],
            'items' => $transfer->items->map(static fn (StockTransferItem $i): array => [
                'id' => $i->id,
                'product' => $i->product->name ?? '',
                'variant_label' => $i->product->variant_label ?? null,
                'sku' => $i->product->sku ?? '',
                'qty_sent' => $i->qty_sent->milli,
                'qty_received' => $i->qty_received?->milli,
                'unit_cost' => $user->can('products.view_cost') ? $i->unit_cost->paisa : null,
                'short_reason' => $i->short_reason,
            ])->all(),
            'canReceive' => $user->can('receive', $transfer),
        ]);
    }

    public function receive(ReceiveStockTransferRequest $request, StockTransfer $transfer, ReceiveStockTransfer $receive): RedirectResponse
    {
        $this->authorize('receive', $transfer);
        /** @var list<array<string, mixed>> $lines */
        $lines = $request->validated('items');
        $items = array_map(static fn (array $line): array => [
            'item_id' => (int) $line['item_id'],
            'qty_received' => Quantity::ofMilli((int) $line['qty_received']),
            'short_reason' => isset($line['short_reason']) ? (string) $line['short_reason'] : null,
        ], $lines);

        $this->guarded(fn (): StockTransfer => $receive->handle($this->actor($request), $transfer, ['items' => $items]), 'items');
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transfer received.')]);

        return to_route('transfers.show', $transfer);
    }

    /** @return array{id: int, code: string, name: string} */
    private static function branchLabel(?Branch $branch): array
    {
        return ['id' => $branch->id ?? 0, 'code' => $branch->code ?? '', 'name' => $branch->name ?? ''];
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    /** The current branch's quantity on hand (zero when the product has never been stocked here). */
    private static function onHand(Product $product): Quantity
    {
        $stock = BranchStock::query()->where('product_id', $product->id)->first();

        return $stock instanceof BranchStock ? $stock->qty_on_hand : Quantity::zero();
    }
}
