<?php

declare(strict_types=1);

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Support\Http\TableQuery;
use App\Support\Quantity\Quantity;
use App\Support\Stock\StockQueries;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Read side of stock for the current branch: on-hand, low stock, per-product movement history. */
final class StockController extends Controller
{
    use AuthorizesRequests;

    private const int MOVEMENT_LIMIT = 500;

    public function __construct(private readonly TenantContext $context, private readonly StockQueries $queries) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', StockAdjustment::class);
        $canCost = $this->actor($request)->checkPermissionTo('products.view_cost');
        $branch = $this->context->branch();

        $table = TableQuery::fromRequest($request, sortable: ['name', 'sku', 'on_hand'], filterable: ['status', 'category'], defaultSort: 'name');

        $query = Product::query()
            ->leftJoin('branch_stocks', static function ($join) use ($branch): void {
                $join->on('branch_stocks.product_id', '=', 'products.id')->where('branch_stocks.branch_id', '=', $branch->id);
            })
            ->where('products.track_stock', true)
            ->selectRaw('products.*, COALESCE(branch_stocks.qty_on_hand, 0) AS on_hand');

        if ($table->search !== '') {
            $like = '%'.addcslashes($table->search, '\\%_').'%';
            $query->where(static fn ($q) => $q->where('products.name', 'like', $like)->orWhere('products.sku', 'like', $like)->orWhere('products.barcode', 'like', $like));
        }
        if (isset($table->filters['category'])) {
            $query->where('products.category_id', (int) $table->filters['category']);
        }
        match ($table->filters['status'] ?? null) {
            'low' => $query->where('products.reorder_level', '>', 0)->whereRaw('COALESCE(branch_stocks.qty_on_hand, 0) <= products.reorder_level'),
            'out' => $query->whereRaw('COALESCE(branch_stocks.qty_on_hand, 0) <= 0'),
            default => null,
        };

        $page = $table->paginate($query);
        $values = $canCost ? $this->queries->valuationByProduct($branch) : [];

        return Inertia::render('stock/index', [
            'rows' => collect($page->items())->map(static function (Product $p) use ($values, $canCost): array {
                $onHand = Quantity::parse((string) $p->getAttribute('on_hand'));

                return [
                    'id' => $p->id,
                    'uuid' => $p->uuid,
                    'name' => $p->name,
                    'variant_label' => $p->variant_label,
                    'sku' => $p->sku,
                    'on_hand' => $onHand->milli,
                    'reorder_level' => $p->reorder_level->milli,
                    'low_stock' => $p->reorder_level->isPositive() && $onHand->compareTo($p->reorder_level) <= 0,
                    'value' => $canCost ? isset($values[$p->id]) ? $values[$p->id]->paisa : 0 : null,
                ];
            })->all(),
            'meta' => ['page' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => $page->lastPage()],
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'valuation' => $canCost ? $this->queries->valuation($branch)->paisa : null,
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'canAdjust' => $this->actor($request)->can('create', StockAdjustment::class),
        ]);
    }

    public function movements(Product $product): Response
    {
        $this->authorize('viewAny', StockAdjustment::class);
        $this->authorize('view', $product);
        $branch = $this->context->branch();

        $history = $this->queries->movements($product, $branch);
        $recent = array_reverse(array_slice($history, -self::MOVEMENT_LIMIT));

        return Inertia::render('stock/movements', [
            'product' => ['id' => $product->id, 'uuid' => $product->uuid, 'name' => $product->name, 'variant_label' => $product->variant_label, 'sku' => $product->sku],
            'onHand' => $this->queries->onHand($product, $branch)->milli,
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'rows' => array_map(static fn (array $entry): array => [
                'id' => $entry['movement']->id,
                'type' => $entry['movement']->type->value,
                'qty' => $entry['movement']->qty->milli,
                'balance' => $entry['balance']->milli,
                'unit_cost' => $entry['movement']->unit_cost->paisa,
                'note' => $entry['movement']->note,
                'at' => $entry['movement']->created_at?->toDateTimeString(),
            ], $recent),
            'truncated' => count($history) > self::MOVEMENT_LIMIT,
        ]);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
