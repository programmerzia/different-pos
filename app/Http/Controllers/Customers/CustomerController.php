<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customers;

use App\Actions\Customers\DeactivateCustomer;
use App\Actions\Customers\MergeCustomers;
use App\Actions\Customers\SaveCustomer;
use App\Enums\CardStatus;
use App\Enums\PartyType;
use App\Exceptions\Domain\DuplicateCustomerPhone;
use App\Exceptions\Domain\InvalidPartyData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\MergeCustomerRequest;
use App\Http\Requests\Customers\SaveCustomerRequest;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\MembershipCard;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use App\Support\Customers\CustomerNumber;
use App\Support\Customers\CustomerSearch;
use App\Support\Customers\CustomerSearchResult;
use App\Support\Http\TableQuery;
use App\Support\Phone\BdPhone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customers: list, create/edit, profile (card panel + history tabs), deactivate, merge and the two
 * small JSON helpers (picker search, live duplicate-phone check). Customers bind by uuid here.
 */
final class CustomerController extends Controller
{
    private const int TAB_LIMIT = 50;

    public function __construct(private readonly CustomerSearch $search) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Customer::class);
        $table = TableQuery::fromRequest(
            $request,
            ['name', 'customer_no', 'balance', 'last_purchase_at'],
            ['group', 'card', 'status', 'due'],
            'name',
        );

        $query = Customer::query()->where('is_walk_in', false)->with(['customerGroup', 'membershipCards']);
        $query->where('is_active', ($table->filters['status'] ?? 'active') !== 'inactive');
        if (isset($table->filters['group'])) {
            $query->where('customer_group_id', (int) $table->filters['group']);
        }
        if (isset($table->filters['card'])) {
            $table->filters['card'] === 'yes'
                ? $query->whereHas('membershipCards', fn (Builder $q) => $q->where('status', CardStatus::Active))
                : $query->whereDoesntHave('membershipCards', fn (Builder $q) => $q->where('status', CardStatus::Active));
        }
        if (isset($table->filters['due'])) {
            $query->where('balance', '>', 0);
        }
        if ($table->search !== '') {
            $this->applySearch($query, $table->search);
        }

        $page = $table->paginate($query);
        /** @var list<Customer> $items */
        $items = $page->items();

        return Inertia::render('customers/index', [
            'customers' => array_map($this->row(...), $items),
            'meta' => ['page' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => $page->lastPage()],
            'groups' => $this->groups(),
            'can' => $this->abilities($request),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Customer::class);

        return Inertia::render('customers/form', ['customer' => null, 'groups' => $this->groups()]);
    }

    public function store(SaveCustomerRequest $request, SaveCustomer $save): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', Customer::class);

        try {
            $customer = $save->handle($this->actor($request), $request->customerData());
        } catch (DuplicateCustomerPhone $e) {
            return $this->duplicate($request, $e);
        } catch (InvalidPartyData $e) {
            return $this->invalid($request, $e);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer saved.')]);

        return to_route('customers.show', $customer->uuid);
    }

    public function show(Request $request, string $customer): Response
    {
        $model = $this->find($customer);
        Gate::authorize('view', $model);
        $user = $this->actor($request);
        $model->load(['customerGroup', 'membershipCards']);

        $card = $model->membershipCards->sortByDesc('issue_no')->first();

        return Inertia::render('customers/show', [
            'customer' => $this->detail($model),
            'card' => $card instanceof MembershipCard ? $this->cardData($card, $model) : null,
            'sales' => $user->can('viewAny', Sale::class) ? $this->sales($model) : [],
            'returns' => $user->can('viewAny', SaleReturn::class) ? $this->returns($model) : [],
            'payments' => $user->can('viewAny', Payment::class) ? $this->payments($model) : [],
            'messages' => $user->can('viewAny', Message::class) ? $this->messages($model) : [],
            'can' => $this->abilities($request),
        ]);
    }

    public function edit(string $customer): Response
    {
        $model = $this->find($customer);
        Gate::authorize('update', $model);

        return Inertia::render('customers/form', ['customer' => $this->detail($model), 'groups' => $this->groups()]);
    }

    public function update(SaveCustomerRequest $request, string $customer, SaveCustomer $save): RedirectResponse|JsonResponse
    {
        $model = $this->find($customer);
        Gate::authorize('update', $model);

        try {
            $save->handle($this->actor($request), $request->customerData(), $model);
        } catch (DuplicateCustomerPhone $e) {
            return $this->duplicate($request, $e);
        } catch (InvalidPartyData $e) {
            return $this->invalid($request, $e);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer saved.')]);

        return to_route('customers.show', $model->uuid);
    }

    public function deactivate(Request $request, string $customer, DeactivateCustomer $deactivate): RedirectResponse
    {
        $model = $this->find($customer);
        Gate::authorize('delete', $model);

        try {
            $deactivate->handle($this->actor($request), $model);
        } catch (InvalidPartyData $e) {
            return back()->withErrors(['form' => $e->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer deactivated.')]);

        return to_route('customers.index');
    }

    public function merge(MergeCustomerRequest $request, string $customer, MergeCustomers $merge): RedirectResponse
    {
        $loser = $this->find($customer);
        Gate::authorize('delete', $loser);
        $survivor = $this->find((string) $request->validated('into'));
        Gate::authorize('update', $survivor);

        try {
            $merge->handle($this->actor($request), $survivor, $loser);
        } catch (InvalidPartyData $e) {
            return back()->withErrors(['form' => $e->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customers merged.')]);

        return to_route('customers.show', $survivor->uuid);
    }

    /** GET /app/customers/search?q=: the picker (number, phone suffix, name or shop name). */
    public function search(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Customer::class);
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 60);

        return response()->json([
            'customers' => $this->search->search(ltrim($q, '#'))->map(static fn (CustomerSearchResult $c): array => [
                'uuid' => $c->uuid,
                'customer_no' => $c->customerNo,
                'name' => $c->name,
                'name_bn' => $c->nameBn,
                'shop_name' => $c->shopName,
                'phone' => $c->phone,
                'is_active' => $c->isActive,
            ])->values(),
        ]);
    }

    /** GET /app/customers/check-phone?phone=&except=: the customer already using this number, if any. */
    public function checkPhone(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Customer::class);
        $phone = BdPhone::toE164((string) $request->query('phone', ''));
        $existing = null;
        if ($phone !== null) {
            $except = (string) $request->query('except', '');
            $existing = Customer::query()->where('phone', $phone)
                ->when($except !== '', fn (Builder $q) => $q->where('uuid', '!=', $except))
                ->first();
        }

        return response()->json(['existing' => $existing instanceof Customer ? $this->brief($existing) : null]);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    /** Tenant-scoped lookup: another tenant's customer is a 404. */
    private function find(string $uuid): Customer
    {
        return Customer::query()->where('uuid', $uuid)->firstOrFail();
    }

    /** @param Builder<Customer> $query */
    private function applySearch(Builder $query, string $term): void
    {
        $like = '%'.addcslashes($term, '%_\\').'%';
        $digits = CustomerNumber::normalize($term);
        $query->where(function (Builder $q) use ($like, $digits): void {
            $q->where('name', 'like', $like)->orWhere('name_bn', 'like', $like)->orWhere('shop_name', 'like', $like);
            if (preg_match('/^[0-9]+$/', $digits) === 1) {
                $q->orWhere('customer_no', 'like', $digits.'%');
                $national = ltrim(str_starts_with($digits, '880') ? substr($digits, 3) : $digits, '0');
                if (strlen($national) >= 3) {
                    $q->orWhere('phone', 'like', '%'.$national.'%')->orWhere('alt_phone', 'like', '%'.$national.'%');
                }
            }
        });
    }

    /** @return array<int, array{id: int, name: string}> */
    private function groups(): array
    {
        return CustomerGroup::query()->where('is_active', true)->orderBy('name')->get()
            ->map(static fn (CustomerGroup $g): array => ['id' => $g->id, 'name' => $g->name])->values()->all();
    }

    /** @return array<string, bool> */
    private function abilities(Request $request): array
    {
        $user = $this->actor($request);

        return [
            'create' => $user->can('create', Customer::class),
            'update' => $user->can('customers.update'),
            'delete' => $user->can('customers.delete'),
            'sell' => $user->can('create', Sale::class),
            'collect' => $user->can('payments.collect'),
            'cards' => $user->can('viewAny', MembershipCard::class),
            'issue_card' => $user->can('create', MembershipCard::class),
        ];
    }

    /** @return array<string, mixed> */
    private function row(Customer $c): array
    {
        $card = $c->membershipCards->sortByDesc('issue_no')->first();

        return [
            ...$this->brief($c),
            'group' => $c->customerGroup?->name,
            'balance' => $c->balance->paisa,
            'last_purchase_at' => $c->last_purchase_at?->toIso8601String(),
            'card_status' => $card instanceof MembershipCard ? $card->status->value : null,
            'is_active' => $c->is_active,
        ];
    }

    /** @return array<string, mixed> */
    private function brief(Customer $c): array
    {
        return [
            'id' => $c->uuid,
            'uuid' => $c->uuid,
            'customer_no' => $c->customer_no === null ? null : CustomerNumber::format((string) $c->customer_no),
            'name' => $c->name,
            'name_bn' => $c->name_bn,
            'shop_name' => $c->shop_name,
            'phone' => $c->phone,
        ];
    }

    /**
     * Everything the profile and the edit form need; never the PIN.
     *
     * @return array<string, mixed>
     */
    private function detail(Customer $c): array
    {
        $c->loadMissing('customerGroup');

        return [
            ...$this->brief($c),
            'alt_phone' => $c->alt_phone,
            'email' => $c->email,
            'customer_group_id' => $c->customer_group_id,
            'group' => $c->customerGroup?->name,
            'credit_limit' => $c->credit_limit?->paisa,
            'balance' => $c->balance->paisa,
            'address_line' => $c->address_line,
            'area' => $c->area,
            'post_code' => $c->post_code,
            'dob' => $c->dob?->toDateString(),
            'anniversary_date' => $c->anniversary_date?->toDateString(),
            'notes' => $c->notes,
            'member_since' => $c->member_since?->toDateString(),
            'last_purchase_at' => $c->last_purchase_at?->toIso8601String(),
            'is_active' => $c->is_active,
            'is_walk_in' => $c->is_walk_in,
        ];
    }

    /** @return array<string, mixed> */
    private function cardData(MembershipCard $card, Customer $customer): array
    {
        return [
            'id' => $card->id,
            'card_code' => $card->card_code,
            'status' => $card->status->value,
            'issue_no' => $card->issue_no,
            'issued_at' => $card->issued_at->toDateString(),
            'print_count' => $card->print_count,
            'member_since' => $customer->member_since?->toDateString(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function sales(Customer $c): array
    {
        return Sale::query()->where('customer_id', $c->id)->latest('sold_at')->limit(self::TAB_LIMIT)->get()
            ->map(static fn (Sale $s): array => [
                'id' => $s->id,
                'number' => $s->number,
                'sold_at' => $s->sold_at->toIso8601String(),
                'status' => $s->status->value,
                'payment_status' => $s->payment_status->value,
                'total' => $s->total->paisa,
                'paid' => $s->paid->paisa,
                'due' => $s->due->paisa,
            ])->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function returns(Customer $c): array
    {
        return SaleReturn::query()->where('customer_id', $c->id)->latest('returned_at')->limit(self::TAB_LIMIT)->get()
            ->map(static fn (SaleReturn $r): array => [
                'id' => $r->id,
                'number' => $r->number,
                'returned_at' => $r->returned_at->toIso8601String(),
                'refund_mode' => $r->refund_mode->value,
                'total' => $r->total->paisa,
            ])->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function payments(Customer $c): array
    {
        return Payment::query()->where('party_type', PartyType::Customer)->where('party_id', $c->id)
            ->with(['paymentMethod', 'allocations'])->latest('paid_at')->limit(self::TAB_LIMIT)->get()
            ->map(static fn (Payment $p): array => [
                'id' => $p->id,
                'number' => $p->number,
                'paid_at' => $p->paid_at->toIso8601String(),
                'direction' => $p->direction->value,
                'method' => $p->paymentMethod->name ?? null,
                'amount' => $p->amount->paisa,
                'allocated' => $p->allocations->reduce(static fn (int $sum, $a): int => $sum + $a->amount->paisa, 0),
                'voided' => $p->voided_at !== null,
            ])->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function messages(Customer $c): array
    {
        return Message::query()->where('customer_id', $c->id)->latest('id')->limit(self::TAB_LIMIT)->get()
            ->map(static fn (Message $m): array => [
                'id' => $m->id,
                'event' => $m->event,
                'body' => $m->body,
                'status' => $m->status->value,
                'created_at' => $m->created_at?->toIso8601String(),
            ])->values()->all();
    }

    private function duplicate(Request $request, DuplicateCustomerPhone $e): RedirectResponse|JsonResponse
    {
        $message = __('This phone number already belongs to :name.', ['name' => $e->existing->name]);
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'errors' => ['phone' => [$message]],
                'existing' => $this->brief($e->existing),
            ], 422);
        }

        return back()->withInput()->withErrors(['phone' => $message]);
    }

    private function invalid(Request $request, InvalidPartyData $e): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['form' => [$e->getMessage()]]], 422);
        }

        return back()->withInput()->withErrors(['form' => $e->getMessage()]);
    }
}
