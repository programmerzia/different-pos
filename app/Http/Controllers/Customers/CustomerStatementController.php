<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use App\Sms\SendCustomerStatement;
use App\Support\Customers\CustomerNumber;
use App\Support\Customers\CustomerStatement;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Customer statement: completed sales in a period with totals; printable; SMS summary. */
final class CustomerStatementController extends Controller
{
    public function __construct(private readonly CustomerStatement $statement) {}

    /** The statement screen before a customer is picked. */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Customer::class);
        [$from, $to] = $this->period($request);

        return Inertia::render('customers/statement', [
            'customer' => null,
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'rows' => [],
            'totals' => null,
        ]);
    }

    public function show(Request $request, string $customer): Response
    {
        $model = Customer::query()->where('uuid', $customer)->firstOrFail();
        Gate::authorize('view', $model);
        [$from, $to] = $this->period($request);
        $result = $this->statement->build($model, $from, $to, $this->allBranches($request));

        return Inertia::render('customers/statement', [
            'customer' => [
                'uuid' => $model->uuid,
                'customer_no' => $model->customer_no === null ? null : CustomerNumber::format((string) $model->customer_no),
                'name' => $model->name,
                'name_bn' => $model->name_bn,
                'shop_name' => $model->shop_name,
                'phone' => $model->phone,
                'balance' => $model->balance->paisa,
            ],
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'rows' => $result['rows'],
            'totals' => $result['totals'],
            'can' => ['sms' => $request->user()?->can('send', Message::class) === true],
        ]);
    }

    public function sms(Request $request, string $customer, SendCustomerStatement $sender): RedirectResponse
    {
        $model = Customer::query()->where('uuid', $customer)->firstOrFail();
        Gate::authorize('view', $model);
        [$from, $to] = $this->period($request);
        $result = $this->statement->build($model, $from, $to, $this->allBranches($request));
        $body = sprintf(
            '%s: %s - %s, %d invoices, total %s, paid %s, due %s.',
            $model->name,
            $from->toDateString(),
            $to->toDateString(),
            $result['totals']['count'],
            Money::ofPaisa($result['totals']['total'])->format('en', false),
            Money::ofPaisa($result['totals']['paid'])->format('en', false),
            Money::ofPaisa($result['totals']['due'])->format('en', false),
        );

        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $message = $sender->handle($user, $model, $body);
        Inertia::flash('toast', $message instanceof Message
            ? ['type' => 'success', 'message' => __('Statement summary queued by SMS.')]
            : ['type' => 'warning', 'message' => __('This customer has no valid mobile number for SMS.')]);

        return back();
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function period(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $today = CarbonImmutable::today();
        $from = isset($validated['from']) ? CarbonImmutable::parse($validated['from']) : $today->startOfMonth();
        $to = isset($validated['to']) ? CarbonImmutable::parse($validated['to']) : $today;

        return [$from, $to];
    }

    private function allBranches(Request $request): bool
    {
        return $request->user()?->can('reports.all_branches') === true;
    }
}
