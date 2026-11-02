<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\CollectCustomerPayment;
use App\Actions\Sales\Data\CustomerPaymentDraft;
use App\Enums\PartyType;
use App\Enums\PaymentDirection;
use App\Enums\SaleStatus;
use App\Http\Controllers\Concerns\TranslatesDomainErrors;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Sales\Concerns\PresentsSales;
use App\Http\Requests\Sales\CollectPaymentRequest;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Support\Http\TableQuery;
use App\Support\Money\Money;
use App\Support\Sales\CustomerAccounts;
use App\Support\Sales\DueAging;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Open dues of the current branch: customers with aging buckets (0–30, 31–60, 61–90, 90+ days),
 * a customer drill-down with its open invoices, and the collect dialog (CollectCustomerPayment).
 */
final class DueController extends Controller
{
    use AuthorizesRequests, PresentsSales, TranslatesDomainErrors;

    /** One CASE per bucket; the bindings come from bucketBindings() in the same order. */
    private const array BUCKET_CASE = [
        'd0_30' => 'CASE WHEN sold_at >= ? THEN due ELSE 0 END',
        'd31_60' => 'CASE WHEN sold_at >= ? AND sold_at < ? THEN due ELSE 0 END',
        'd61_90' => 'CASE WHEN sold_at >= ? AND sold_at < ? THEN due ELSE 0 END',
        'd90_plus' => 'CASE WHEN sold_at < ? THEN due ELSE 0 END',
    ];

    private const string BUCKET_SQL = 'SUM(CASE WHEN sold_at >= ? THEN due ELSE 0 END) AS d0_30, '
        .'SUM(CASE WHEN sold_at >= ? AND sold_at < ? THEN due ELSE 0 END) AS d31_60, '
        .'SUM(CASE WHEN sold_at >= ? AND sold_at < ? THEN due ELSE 0 END) AS d61_90, '
        .'SUM(CASE WHEN sold_at < ? THEN due ELSE 0 END) AS d90_plus';

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Customer::class);

        $table = TableQuery::fromRequest($request, ['total_due', 'oldest_at', 'invoices'], ['bucket'], '-total_due');
        $bindings = $this->bucketBindings();

        $query = $this->openDues()
            ->selectRaw('customer_id, COUNT(*) AS invoices, SUM(due) AS total_due, MIN(sold_at) AS oldest_at, '.self::BUCKET_SQL, $bindings)
            ->groupBy('customer_id');
        if ($table->search !== '') {
            $like = '%'.addcslashes($table->search, '\\%_').'%';
            $digits = preg_replace('/\D+/', '', $table->search) ?? '';
            $query->whereIn('customer_id', Customer::query()->withTrashed()->select('id')->where(fn ($c) => $c
                ->where('name', 'like', $like)
                ->orWhere('shop_name', 'like', $like)
                ->when(strlen($digits) >= 4, fn ($c) => $c->orWhere('phone', 'like', '%'.$digits)->orWhere('customer_no', 'like', $digits.'%'))));
        }
        $bucket = $table->filters['bucket'] ?? null;
        if (in_array($bucket, DueAging::BUCKETS, true)) {
            $query->havingRaw('SUM('.self::BUCKET_CASE[$bucket].') > 0', $this->bucketBindings($bucket));
        }
        $page = $table->paginate($query);

        /** @var list<Sale> $groups */
        $groups = $page->items();
        $customers = Customer::query()->withTrashed()
            ->whereIn('id', array_map(static fn (Sale $g): int => (int) $g->getAttribute('customer_id'), $groups))
            ->get()->keyBy('id');

        $totals = $this->openDues()->selectRaw('COUNT(DISTINCT customer_id) AS customers, COUNT(*) AS invoices, COALESCE(SUM(due), 0) AS total_due, '.self::BUCKET_SQL, $bindings)->toBase()->first();

        return Inertia::render('dues/index', [
            'rows' => array_map(function (Sale $g) use ($customers): array {
                $customer = $customers->get((int) $g->getAttribute('customer_id'));
                $oldest = $g->getAttribute('oldest_at');

                return [
                    'customer' => $customer instanceof Customer ? [
                        ...($this->customerSummary($customer) ?? []),
                        'balance' => $customer->balance->paisa,
                        'credit_limit' => $customer->credit_limit?->paisa,
                    ] : null,
                    'invoices' => (int) $g->getAttribute('invoices'),
                    'total_due' => (int) $g->getAttribute('total_due'),
                    'oldest_at' => $oldest !== null ? $this->localTime(Carbon::parse((string) $oldest, 'UTC')) : null,
                    'buckets' => $this->bucketsOf($g->getAttributes()),
                ];
            }, $groups),
            'meta' => ['page' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => $page->lastPage()],
            'filters' => [...$table->filters, 'q' => $table->search],
            'totals' => [
                'customers' => (int) ($totals->customers ?? 0),
                'invoices' => (int) ($totals->invoices ?? 0),
                'total_due' => (int) ($totals->total_due ?? 0),
                'buckets' => $this->bucketsOf((array) $totals),
            ],
        ]);
    }

    public function show(Request $request, string $customer): Response
    {
        $model = Customer::query()->where('uuid', $customer)->firstOrFail();
        $this->authorize('view', $model);
        $actor = $this->actor($request);

        $tz = $this->timezone();
        $now = Carbon::now();
        $invoices = $this->openDues()->where('customer_id', $model->id)->orderBy('sold_at')->orderBy('id')->get();

        $amount = $request->integer('amount');
        $preview = $amount > 0 ? DueAging::allocateOldestFirst(
            array_values($invoices->map(static fn (Sale $s): array => ['id' => $s->id, 'due' => $s->due])->all()),
            Money::ofPaisa($amount),
        ) : null;

        $payments = Payment::query()
            ->with('paymentMethod')
            ->where('party_type', PartyType::Customer)
            ->where('party_id', $model->id)
            ->where('direction', PaymentDirection::In)
            ->whereNull('voided_at')
            ->latest('paid_at')->latest('id')
            ->limit(10)
            ->get()
            ->filter(static fn (Payment $p): bool => ! $p->paymentMethod instanceof PaymentMethod || ! CustomerAccounts::isCredit($p->paymentMethod));

        $receiptId = $request->integer('receipt');

        return Inertia::render('dues/show', [
            'customer' => [
                ...($this->customerSummary($model) ?? []),
                'balance' => $model->balance->paisa,
                'credit_limit' => $model->credit_limit?->paisa,
                'advance' => app(CustomerAccounts::class)->advance($model)->paisa,
            ],
            'invoices' => array_values($invoices->map(function (Sale $s) use ($now, $tz): array {
                $age = DueAging::age($s->sold_at, $now, $tz);

                return [
                    'id' => $s->id,
                    'number' => (string) $s->number,
                    'sold_at' => $this->localTime($s->sold_at),
                    'total' => $s->total->paisa,
                    'paid' => $s->paid->paisa,
                    'due' => $s->due->paisa,
                    'age' => $age,
                    'bucket' => DueAging::bucket($age),
                ];
            })->all()),
            'payments' => array_values($payments->map(fn (Payment $p): array => [
                'id' => $p->id,
                'number' => $p->number,
                'paid_at' => $this->localTime($p->paid_at),
                'method' => $p->paymentMethod instanceof PaymentMethod ? $p->paymentMethod->name : '',
                'amount' => $p->amount->paisa,
                'reference' => $p->reference,
            ])->all()),
            'preview' => $preview !== null ? [
                'amount' => $amount,
                'allocations' => array_map(static fn (Money $m): int => $m->paisa, $preview['allocations']),
                'advance' => $preview['advance']->paisa,
            ] : null,
            'receipt' => $receiptId > 0 && Payment::query()->whereKey($receiptId)->where('party_id', $model->id)->exists() ? $receiptId : null,
            'methods' => $this->moneyMethods(),
            'can' => ['collect' => $actor->can('collect', Payment::class) && ! $model->is_walk_in],
        ]);
    }

    public function store(CollectPaymentRequest $request, string $customer, CollectCustomerPayment $collect): RedirectResponse
    {
        $model = Customer::query()->where('uuid', $customer)->firstOrFail();
        $this->authorize('view', $model);
        $this->authorize('collect', Payment::class);

        $allocations = null;
        if ($request->filled('allocations')) {
            $allocations = [];
            foreach ((array) $request->input('allocations') as $row) {
                if (is_array($row)) {
                    $allocations[(int) $row['sale_id']] = Money::ofPaisa((int) $row['amount']);
                }
            }
        }

        $amount = Money::ofPaisa($request->integer('amount'));
        $payment = $this->guarded(fn (): Payment => $collect->handle($this->actor($request), new CustomerPaymentDraft(
            customerId: $model->id,
            amount: $amount,
            paymentMethodId: $request->integer('payment_method_id'),
            allocations: $allocations,
            reference: $request->string('reference')->trim()->value() ?: null,
            note: $request->string('note')->trim()->value() ?: null,
        )), 'amount');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('receipts.flash.collected', ['number' => $payment->number, 'amount' => $amount->format(app()->getLocale())])]);

        return to_route('dues.show', ['customer' => $model->uuid, 'receipt' => $payment->id]);
    }

    /** @return Builder<Sale> */
    private function openDues(): Builder
    {
        return Sale::query()->where('status', SaleStatus::Completed)->where('due', '>', 0);
    }

    /** @return list<CarbonImmutable> UTC instants (start of the local day N days ago); all buckets in order when $bucket is null. */
    private function bucketBindings(?string $bucket = null): array
    {
        $today = CarbonImmutable::now($this->timezone())->startOfDay();
        $edge = static fn (int $days): CarbonImmutable => $today->subDays($days)->utc();

        return match ($bucket) {
            null => [$edge(30), $edge(60), $edge(30), $edge(90), $edge(60), $edge(90)],
            'd0_30' => [$edge(30)],
            'd31_60' => [$edge(60), $edge(30)],
            'd61_90' => [$edge(90), $edge(60)],
            default => [$edge(90)],
        };
    }

    /**
     * @param  array<array-key, mixed>  $row
     * @return array<string, int>
     */
    private function bucketsOf(array $row): array
    {
        $out = [];
        foreach (DueAging::BUCKETS as $bucket) {
            $out[$bucket] = (int) ($row[$bucket] ?? 0);
        }

        return $out;
    }
}
