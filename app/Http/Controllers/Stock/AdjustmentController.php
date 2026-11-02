<?php

declare(strict_types=1);

namespace App\Http\Controllers\Stock;

use App\Actions\Stock\AdjustStock;
use App\Enums\AdjustmentReason;
use App\Http\Controllers\Concerns\TranslatesDomainErrors;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\AdjustStockRequest;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Support\Catalog\ProductLookup;
use App\Support\Http\TableQuery;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AdjustmentController extends Controller
{
    use AuthorizesRequests;
    use TranslatesDomainErrors;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', StockAdjustment::class);
        $table = TableQuery::fromRequest($request, sortable: ['number', 'adjusted_at'], filterable: ['reason'], defaultSort: '-adjusted_at');

        $query = StockAdjustment::query()->withCount('items');
        if ($table->search !== '') {
            $query->where('number', 'like', '%'.addcslashes($table->search, '\\%_').'%');
        }
        if (isset($table->filters['reason']) && AdjustmentReason::tryFrom($table->filters['reason']) !== null) {
            $query->where('reason', $table->filters['reason']);
        }
        $page = $table->paginate($query);

        return Inertia::render('adjustments/index', [
            'rows' => collect($page->items())->map(static fn (StockAdjustment $a): array => [
                'id' => $a->id,
                'number' => $a->number,
                'reason' => $a->reason->value,
                'lines' => (int) $a->getAttribute('items_count'),
                'note' => $a->note,
                'adjusted_at' => $a->adjusted_at->toDateString(),
            ])->all(),
            'meta' => ['page' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => $page->lastPage()],
            'reasons' => array_map(static fn (AdjustmentReason $r): string => $r->value, AdjustmentReason::cases()),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', StockAdjustment::class);

        return Inertia::render('adjustments/create', [
            'reasons' => array_map(static fn (AdjustmentReason $r): string => $r->value, AdjustmentReason::cases()),
        ]);
    }

    public function lookup(Request $request, ProductLookup $lookup): JsonResponse
    {
        $this->authorize('create', StockAdjustment::class);

        return response()->json($lookup->find((string) $request->query('code', ''), false));
    }

    public function store(AdjustStockRequest $request, AdjustStock $adjust): RedirectResponse
    {
        /** @var list<array<string, mixed>> $lines */
        $lines = $request->validated('items');
        $items = array_map(static fn (array $line): array => [
            'product_id' => (int) $line['product_id'],
            'qty' => Quantity::ofMilli((int) $line['qty']),
            'unit_cost' => isset($line['unit_cost']) ? Money::ofPaisa((int) $line['unit_cost']) : null,
        ], $lines);

        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $this->guarded(fn (): StockAdjustment => $adjust->handle($user, [
            'reason' => AdjustmentReason::from((string) $request->validated('reason')),
            'items' => $items,
            'note' => $request->validated('note'),
        ]), 'items');
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stock adjusted.')]);

        return to_route('adjustments.index');
    }
}
