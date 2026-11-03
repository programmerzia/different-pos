<?php

declare(strict_types=1);

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Reports\ColumnType;
use App\Reports\ReportColumn;
use App\Reports\ReportResult;
use App\Support\Catalog\ProductPresenter;
use App\Support\Export\ExportFormat;
use App\Support\Export\ExportMeta;
use App\Support\Export\TabularExport;
use App\Support\Http\RecencyFilter;
use App\Support\Http\TableQuery;
use App\Support\Quantity\Quantity;
use App\Support\Sales\BusinessDay;
use App\Support\Stock\BranchStockColumns;
use App\Support\Stock\ListUnitCost;
use App\Support\Stock\StockQueries;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Read side of stock: on-hand per branch (current branch, or every branch with reports.all_branches), low stock, movement history. */
final class StockController extends Controller
{
    use AuthorizesRequests;

    private const int MOVEMENT_LIMIT = 500;

    public function __construct(
        private readonly TenantContext $context,
        private readonly StockQueries $queries,
        private readonly BranchStockColumns $stockColumns,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', StockAdjustment::class);
        $user = $this->actor($request);
        $canCost = $user->can('products.view_cost');
        $branch = $this->context->branch();

        $table = $this->table($request);
        $filtered = $this->filtered($table);

        $page = $table->paginate((clone $filtered)->selectRaw('products.*, COALESCE(branch_stocks.qty_on_hand, 0) AS on_hand')->with('category:id,name'));
        /** @var list<Product> $items */
        $items = array_values($page->items());

        $branches = $this->stockColumns->branchesFor($user);
        $stock = $this->stockColumns->onHand(array_map(static fn (Product $p): int => $p->id, $items), $branches);
        $branchTotals = $this->stockColumns->totals($filtered, $branches);

        // Stock value at FIFO cost, summed over the branches the user sees.
        $values = [];
        $valuation = 0;
        if ($canCost) {
            foreach ($branches as $b) {
                foreach ($this->queries->valuationByProduct($b) as $productId => $money) {
                    $values[$productId] = ($values[$productId] ?? 0) + $money->paisa;
                }
                $valuation += $this->queries->valuation($b)->paisa;
            }
        }
        $costSum = null;
        $valueSum = null;
        if ($canCost) {
            $costSum = (int) (clone $filtered)->toBase()->sum('products.cost_price');
            $valueSum = 0;
            foreach ((clone $filtered)->pluck('products.id') as $id) {
                $valueSum += $values[(int) $id] ?? 0;
            }
        }

        return Inertia::render('stock/index', [
            'rows' => array_map(static function (Product $p) use ($values, $canCost, $stock): array {
                $onHand = Quantity::parse((string) $p->getAttribute('on_hand'));
                $perBranch = $stock[$p->id] ?? [];
                $unitCost = $canCost ? ListUnitCost::of($p->cost_price->paisa, $values[$p->id] ?? 0, array_sum($perBranch)) : null;

                return [
                    'id' => $p->id,
                    'uuid' => $p->uuid,
                    'name' => $p->name,
                    'name_bn' => $p->name_bn,
                    'variant_label' => $p->variant_label,
                    'sku' => $p->sku,
                    'barcode' => $p->barcode,
                    'category' => $p->category?->name,
                    'image_url' => ProductPresenter::imageUrl($p->image_path),
                    'on_hand' => $onHand->milli,
                    'stock' => $perBranch,
                    'total_stock' => array_sum($perBranch),
                    'reorder_level' => $p->reorder_level->milli,
                    'low_stock' => $p->reorder_level->isPositive() && $onHand->compareTo($p->reorder_level) <= 0,
                    'cost_price' => $unitCost['paisa'] ?? null,
                    'cost_is_average' => $unitCost['average'] ?? false,
                    'value' => $canCost ? $values[$p->id] ?? 0 : null,
                ];
            }, $items),
            'meta' => ['page' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => $page->lastPage()],
            'totals' => [
                'stock' => $branchTotals,
                'total_stock' => array_sum($branchTotals),
                'cost_price' => $costSum,
                'value' => $valueSum,
            ],
            'branches' => $this->stockColumns->present($branches),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'valuation' => $canCost ? $valuation : null,
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'canAdjust' => $user->can('create', StockAdjustment::class),
        ]);
    }

    /** GET /app/stock/export.{csv|xlsx|pdf}: the filtered list (same query string as the page). */
    public function export(Request $request, string $format, TabularExport $export): HttpResponse|StreamedResponse
    {
        $this->authorize('viewAny', StockAdjustment::class);
        $user = $this->actor($request);
        $canCost = $user->can('products.view_cost');
        $table = $this->table($request);
        $tz = BusinessDay::timezone($this->context->tenant());
        $branches = $this->stockColumns->branchesFor($user);
        $filtered = $this->filtered($table);

        // Stock value at FIFO cost, summed over the branches the user sees (same as the page).
        $values = [];
        if ($canCost) {
            foreach ($branches as $b) {
                foreach ($this->queries->valuationByProduct($b) as $productId => $money) {
                    $values[$productId] = ($values[$productId] ?? 0) + $money->paisa;
                }
            }
        }

        $columns = [
            new ReportColumn('sku', 'SKU', ColumnType::Text),
            new ReportColumn('name', 'Product name', ColumnType::Text),
        ];
        foreach ($branches as $i => $branch) {
            $columns[] = new ReportColumn('stock_'.$i, 'Stock ('.$branch->name.')', ColumnType::Qty);
        }
        $columns[] = new ReportColumn('total', 'Total', ColumnType::Qty);
        if ($canCost) {
            $columns[] = new ReportColumn('unit_cost', 'Unit cost', ColumnType::Money);
            $columns[] = new ReportColumn('value', 'Value', ColumnType::Money);
        }

        $query = (clone $filtered)->selectRaw('products.*, COALESCE(branch_stocks.qty_on_hand, 0) AS on_hand')
            ->orderBy('products.name')->orderBy('products.id');
        $rows = [];
        $valueSum = 0;
        foreach ($query->lazy(500)->chunk(500) as $chunk) {
            /** @var list<Product> $products */
            $products = $chunk->values()->all();
            $stock = $this->stockColumns->onHand(array_map(static fn (Product $p): int => $p->id, $products), $branches);
            foreach ($products as $p) {
                $perBranch = $stock[$p->id] ?? [];
                $row = ['sku' => (string) $p->sku, 'name' => $p->name];
                foreach ($branches as $i => $_) {
                    $row['stock_'.$i] = $perBranch[$i] ?? 0;
                }
                $row['total'] = array_sum($perBranch);
                if ($canCost) {
                    $unitCost = ListUnitCost::of($p->cost_price->paisa, $values[$p->id] ?? 0, array_sum($perBranch));
                    $row['unit_cost'] = $unitCost['paisa'];
                    $row['value'] = $values[$p->id] ?? 0;
                    $valueSum += $row['value'];
                }
                $rows[] = $row;
            }
        }

        $totals = [];
        $branchTotals = $this->stockColumns->totals($filtered, $branches);
        foreach ($branchTotals as $i => $milli) {
            $totals['stock_'.$i] = $milli;
        }
        $totals['total'] = array_sum($branchTotals);
        if ($canCost) {
            $totals['value'] = $valueSum;
        }

        return $export->respond(
            new ReportResult($columns, $rows, $totals),
            ExportFormat::from($format),
            'stock-'.now($tz)->format('Ymd-His'),
            ExportMeta::for($this->context, 'Stock'),
        );
    }

    private function table(Request $request): TableQuery
    {
        return TableQuery::fromRequest($request, sortable: ['name', 'sku', 'on_hand'], filterable: ['status', 'category', 'moved'], defaultSort: 'name');
    }

    /**
     * The stock list query: tracked products joined to the current branch's stock row, search and filters applied (no select, no order).
     *
     * @return EloquentBuilder<Product>
     */
    private function filtered(TableQuery $table): EloquentBuilder
    {
        $branch = $this->context->branch();
        $filtered = Product::query()
            ->leftJoin('branch_stocks', static function ($join) use ($branch): void {
                $join->on('branch_stocks.product_id', '=', 'products.id')->where('branch_stocks.branch_id', '=', $branch->id);
            })
            ->where('products.track_stock', true);

        if ($table->search !== '') {
            $like = '%'.addcslashes($table->search, '\\%_').'%';
            $filtered->where(static fn ($q) => $q->where('products.name', 'like', $like)->orWhere('products.name_bn', 'like', $like)->orWhere('products.sku', 'like', $like)->orWhere('products.barcode', 'like', $like));
        }
        if (isset($table->filters['category'])) {
            $filtered->where('products.category_id', (int) $table->filters['category']);
        }
        // "Date:" = last stock movement of the product at this branch.
        $recency = RecencyFilter::resolve($table->filters['moved'] ?? null, BusinessDay::timezone($this->context->tenant()));
        if ($recency !== null) {
            [$cut, $active] = $recency;
            $moved = static fn (Builder $q): Builder => $q->selectRaw('1')->from('stock_movements')
                ->whereColumn('stock_movements.product_id', 'products.id')
                ->where('stock_movements.tenant_id', $branch->tenant_id)
                ->where('stock_movements.branch_id', $branch->id)
                ->where('stock_movements.created_at', '>=', $cut);
            if ($active) {
                $filtered->whereExists($moved);
            } else {
                $filtered->whereNotExists($moved);
            }
        }
        $onHand = 'COALESCE(branch_stocks.qty_on_hand, 0)';
        match ($table->filters['status'] ?? null) {
            'low' => $filtered->where('products.reorder_level', '>', 0)->whereRaw("{$onHand} <= products.reorder_level"),
            'out' => $filtered->whereRaw("{$onHand} <= 0"),
            'ok' => $filtered->whereRaw("{$onHand} > 0")->where(static fn ($q) => $q->where('products.reorder_level', '<=', 0)->orWhereRaw("{$onHand} > products.reorder_level")),
            default => null,
        };

        return $filtered;
    }

    public function movements(Product $product): Response
    {
        $this->authorize('viewAny', StockAdjustment::class);
        $this->authorize('view', $product);
        $branch = $this->context->branch();

        $history = $this->queries->movements($product, $branch);
        $recent = array_reverse(array_slice($history, -self::MOVEMENT_LIMIT));

        $timezone = BusinessDay::timezone($this->context->tenant());

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
                'at' => BusinessDay::localIso($entry['movement']->created_at, $timezone),
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
