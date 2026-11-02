<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cards;

use App\Actions\Cards\IssueCard;
use App\Actions\Cards\RecordCardPrint;
use App\Actions\Cards\ReissueCard;
use App\Actions\Cards\ReportCardLost;
use App\Enums\CardPrintLayout;
use App\Enums\CardStatus;
use App\Exceptions\Domain\InvalidPartyData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cards\CardIdsRequest;
use App\Http\Requests\Cards\IssueCardsRequest;
use App\Models\Customer;
use App\Models\MembershipCard;
use App\Models\User;
use App\Support\Cards\CardExport;
use App\Support\Cards\CardRenderer;
use App\Support\Customers\CustomerNumber;
use App\Support\Http\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Membership cards: the list, issue, lost/reissue, and the press hand-off (print-ready HTML for the
 * browser's print dialog, variable-data CSV, print recording). Only active cards are printed or exported.
 */
final class MembershipCardController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        Gate::authorize('viewAny', MembershipCard::class);
        $table = TableQuery::fromRequest($request, ['issued_at', 'card_code', 'print_count', 'last_printed_at'], ['status'], '-issued_at');

        $query = MembershipCard::query()->with('customer');
        $status = CardStatus::tryFrom($table->filters['status'] ?? '');
        if ($status instanceof CardStatus) {
            $query->where('status', $status);
        }
        if ($table->search !== '') {
            $like = '%'.addcslashes($table->search, '%_\\').'%';
            $digits = CustomerNumber::normalize($table->search);
            $query->where(fn (Builder $q) => $q->where('card_code', 'like', $like)
                ->orWhereHas('customer', function (Builder $c) use ($like, $digits): void {
                    $c->where('name', 'like', $like)->orWhere('shop_name', 'like', $like)->orWhere('phone', 'like', $like);
                    if (preg_match('/^[0-9]+$/', $digits) === 1) {
                        $c->orWhere('customer_no', 'like', $digits.'%');
                    }
                }));
        }
        $page = $table->paginate($query);
        /** @var list<MembershipCard> $items */
        $items = $page->items();
        $user = $this->actor($request);

        return Inertia::render('cards/index', [
            'cards' => array_map(static function (MembershipCard $c): array {
                $customer = $c->customer;

                return [
                    'id' => (string) $c->id,
                    'card_code' => $c->card_code,
                    'status' => $c->status->value,
                    'issue_no' => $c->issue_no,
                    'issued_at' => $c->issued_at->toDateString(),
                    'print_count' => $c->print_count,
                    'last_printed_at' => $c->last_printed_at?->toIso8601String(),
                    'customer_uuid' => $customer?->uuid,
                    'customer_no' => $customer?->customer_no === null ? null : CustomerNumber::format((string) $customer->customer_no),
                    'name' => $customer?->name,
                    'shop_name' => $customer?->shop_name,
                ];
            }, $items),
            'meta' => ['page' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => $page->lastPage()],
            'can' => ['issue' => $user->can('create', MembershipCard::class)],
        ]);
    }

    public function issue(IssueCardsRequest $request, IssueCard $issue): RedirectResponse
    {
        Gate::authorize('create', MembershipCard::class);
        $user = $this->actor($request);

        /** @var list<string> $uuids */
        $uuids = $request->validated('customers');
        $issued = 0;
        $errors = [];
        foreach (Customer::query()->whereIn('uuid', $uuids)->get() as $customer) {
            try {
                $issue->handle($user, $customer);
                $issued++;
            } catch (InvalidPartyData $e) {
                $errors[] = $customer->name.': '.$e->getMessage();
            }
        }
        if ($issued > 0) {
            Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{1} :count card issued.|[2,*] :count cards issued.', $issued, ['count' => $issued])]);
        }

        return $errors === [] ? back() : back()->withErrors(['form' => implode(' ', $errors)]);
    }

    /** GET: print-ready HTML (front and back pages with @page); the browser's print dialog saves the PDF. */
    public function print(CardIdsRequest $request, CardRenderer $renderer): Response
    {
        Gate::authorize('create', MembershipCard::class);
        $cards = $this->printable($request);

        return response($renderer->html($cards, $request->bleed()), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /** GET: the press data file, one row per card. */
    public function export(CardIdsRequest $request): Response
    {
        Gate::authorize('create', MembershipCard::class);
        $csv = CardExport::csv($this->printable($request));

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="membership-cards-'.now()->format('Ymd-His').'.csv"',
        ]);
    }

    /** POST: the cards were sent to print; bumps print_count (audited). */
    public function printed(CardIdsRequest $request, RecordCardPrint $record): RedirectResponse
    {
        Gate::authorize('create', MembershipCard::class);
        $user = $this->actor($request);
        $layout = $request->bleed() ? CardPrintLayout::Pdf : CardPrintLayout::Cr80;
        foreach ($this->printable($request) as $card) {
            $record->handle($user, $card, $layout);
        }

        return back();
    }

    public function lost(Request $request, int $card, ReportCardLost $lost): RedirectResponse
    {
        $model = MembershipCard::query()->findOrFail($card);
        Gate::authorize('block', $model);
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:255']])['reason'] ?? null;

        try {
            $lost->handle($this->actor($request), $model, $reason);
        } catch (InvalidPartyData $e) {
            return back()->withErrors(['form' => $e->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Card marked lost.')]);

        return back();
    }

    public function reissue(Request $request, int $card, ReissueCard $reissue): RedirectResponse
    {
        $model = MembershipCard::query()->findOrFail($card);
        Gate::authorize('reissue', $model);
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:255']])['reason'] ?? null;

        try {
            $reissue->handle($this->actor($request), $model, $reason);
        } catch (InvalidPartyData $e) {
            return back()->withErrors(['form' => $e->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Card reissued.')]);

        return back();
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    /** @return Collection<int, MembershipCard> */
    private function printable(CardIdsRequest $request): Collection
    {
        $cards = MembershipCard::query()->whereIn('id', $request->cardIds())->where('status', CardStatus::Active)
            ->with('customer')->orderBy('id')->get();
        abort_if($cards->isEmpty(), 404);

        return $cards;
    }
}
