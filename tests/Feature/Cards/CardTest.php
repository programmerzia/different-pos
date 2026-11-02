<?php

declare(strict_types=1);

use App\Actions\Cards\IssueCard;
use App\Actions\Cards\RecordCardPrint;
use App\Actions\Cards\ReissueCard;
use App\Actions\Cards\ReportCardLost;
use App\Enums\CardPrintLayout;
use App\Enums\CardStatus;
use App\Exceptions\Domain\InvalidPartyData;
use App\Models\Customer;
use App\Models\MembershipCard;
use App\Models\Tenant;
use App\Support\Cards\CardExport;
use App\Support\Cards\CardRenderer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\Fixtures\StockWorld;

beforeEach(function (): void {
    $this->world = StockWorld::make();
    $this->user = $this->world->user;
    $this->customer = app(TenantContext::class)->withoutTenancy(fn (): Customer => Customer::factory()->create([
        'tenant_id' => $this->world->tenant->id, 'customer_no' => 10000017, 'name' => 'Rahim', 'name_bn' => 'রহিম',
        'shop_name' => 'Rahim Store', 'member_since' => '2026-01-05',
    ]));
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('issues an active card whose code encodes the customer number and issue number', function (): void {
    $card = app(IssueCard::class)->handle($this->user, $this->customer);

    expect($card->card_code)->toBe('MC1000001701')
        ->and($card->issue_no)->toBe(1)
        ->and($card->status)->toBe(CardStatus::Active)
        ->and($card->issued_by)->toBe($this->user->id)
        ->and($card->print_count)->toBe(0);
});

it('allows only one active card per customer', function (): void {
    app(IssueCard::class)->handle($this->user, $this->customer);

    app(IssueCard::class)->handle($this->user, $this->customer);
})->throws(InvalidPartyData::class, 'already has an active card');

it('refuses a customer without a number, inactive or walk-in', function (array $state): void {
    $customer = app(TenantContext::class)->withoutTenancy(fn (): Customer => Customer::factory()->create(['tenant_id' => $this->world->tenant->id, ...$state]));

    app(IssueCard::class)->handle($this->user, $customer);
})->with([
    'no number' => [['customer_no' => null]],
    'inactive' => [['customer_no' => 10000025, 'is_active' => false]],
    'walk-in' => [['customer_no' => 10000033, 'is_walk_in' => true]],
])->throws(InvalidPartyData::class);

it('reports a card lost, then reissues with the next issue number', function (): void {
    $card = app(IssueCard::class)->handle($this->user, $this->customer);
    $lost = app(ReportCardLost::class)->handle($this->user, $card, 'dropped in the river');
    $new = app(ReissueCard::class)->handle($this->user, $lost);

    expect($lost->status)->toBe(CardStatus::Lost)
        ->and($lost->block_reason)->toBe('dropped in the river')
        ->and($lost->blocked_by)->toBe($this->user->id)
        ->and($new->issue_no)->toBe(2)
        ->and($new->card_code)->toBe('MC1000001702')
        ->and($new->status)->toBe(CardStatus::Active)
        ->and($lost->refresh()->status)->toBe(CardStatus::Lost)
        ->and(MembershipCard::query()->where('status', CardStatus::Active)->count())->toBe(1);
});

it('replaces a damaged active card and refuses a second reissue of the old one', function (): void {
    $card = app(IssueCard::class)->handle($this->user, $this->customer);
    $new = app(ReissueCard::class)->handle($this->user, $card, 'cracked');

    expect($card->refresh()->status)->toBe(CardStatus::Replaced)->and($new->issue_no)->toBe(2);
    expect(fn () => app(ReissueCard::class)->handle($this->user, $card))->toThrow(InvalidPartyData::class);

    $lost = app(ReportCardLost::class)->handle($this->user, $new);
    app(ReissueCard::class)->handle($this->user, $lost);
    expect(fn () => app(ReissueCard::class)->handle($this->user, $lost))->toThrow(InvalidPartyData::class, 'newer active card');
});

it('only a still-active card can be reported lost', function (): void {
    $card = app(IssueCard::class)->handle($this->user, $this->customer);
    app(ReportCardLost::class)->handle($this->user, $card);

    app(ReportCardLost::class)->handle($this->user, $card);
})->throws(InvalidPartyData::class);

it('stops issuing after card 99', function (): void {
    app(TenantContext::class)->withoutTenancy(fn () => MembershipCard::factory()->create([
        'tenant_id' => $this->world->tenant->id, 'customer_id' => $this->customer->id, 'issued_by' => $this->user->id,
        'issue_no' => 99, 'card_code' => 'MC1000001799', 'status' => CardStatus::Lost,
    ]));

    app(IssueCard::class)->handle($this->user, $this->customer);
})->throws(InvalidPartyData::class, 'limit');

it('records prints on active cards only', function (): void {
    $card = app(IssueCard::class)->handle($this->user, $this->customer);
    $this->travelTo(now()->addDay());
    app(RecordCardPrint::class)->handle($this->user, $card, CardPrintLayout::Cr80);
    $printed = app(RecordCardPrint::class)->handle($this->user, $card, CardPrintLayout::A410);

    expect($printed->print_count)->toBe(2)
        ->and($printed->last_print_layout)->toBe(CardPrintLayout::A410)
        ->and($printed->last_printed_at?->isSameDay(now()))->toBeTrue();

    app(ReportCardLost::class)->handle($this->user, $card);
    expect(fn () => app(RecordCardPrint::class)->handle($this->user, $card, CardPrintLayout::Cr80))->toThrow(InvalidPartyData::class);
});

it('exports the press CSV rows with formatted customer numbers and neutralised formulas', function (): void {
    $card = app(IssueCard::class)->handle($this->user, $this->customer);
    $evil = app(TenantContext::class)->withoutTenancy(fn (): Customer => Customer::factory()->create([
        'tenant_id' => $this->world->tenant->id, 'customer_no' => 10000025, 'name' => '=HYPERLINK("x")',
    ]));
    $evilCard = app(IssueCard::class)->handle($this->user, $evil);
    $cards = MembershipCard::query()->with('customer')->orderBy('id')->get();

    $rows = CardExport::rows($cards);
    expect($rows[0])->toBe([
        'customer_no' => '1000 0017', 'name_en' => 'Rahim', 'name_bn' => 'রহিম', 'shop_name' => 'Rahim Store',
        'phone' => (string) $this->customer->phone, 'member_since' => '2026-01-05', 'card_code' => $card->card_code,
    ]);

    $csv = CardExport::csv($cards);
    expect($csv)->toStartWith("\u{FEFF}customer_no,name_en,name_bn,shop_name,phone,member_since,card_code")
        ->and($csv)->toContain('1000 0017')
        ->and($csv)->toContain("'=HYPERLINK")
        ->and($evilCard->card_code)->toBe('MC1000002501');
});

it('renders CR80 front/back html and the bleed variant', function (): void {
    $card = app(IssueCard::class)->handle($this->user, $this->customer);
    $cards = MembershipCard::query()->with('customer')->get();
    $renderer = app(CardRenderer::class);

    $html = $renderer->html($cards);
    $bleed = $renderer->html($cards, bleed: true);

    expect($html)->toContain('size: 85.6mm 54mm')
        ->and($html)->toContain('1000 0017')
        ->and($html)->toContain($card->card_code)
        ->and($html)->toContain('<svg')
        ->and($html)->toContain(e($this->world->tenant->name))
        ->and(substr_count($html, 'class="card '))->toBe(2)
        ->and($bleed)->toContain('size: 91.6mm 60mm');
});

it('fails clearly when mPDF is not installed', function (): void {
    if (class_exists(Mpdf\Mpdf::class)) {
        $this->markTestSkipped('mPDF is installed.');
    }

    app(CardRenderer::class)->pdf(MembershipCard::query()->with('customer')->get());
})->throws(RuntimeException::class, 'mPDF');

it('isolates cards per tenant', function (): void {
    [$foreignCustomer, $foreignCard] = app(TenantContext::class)->withoutTenancy(function (): array {
        $tenant = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'customer_no' => 10000017]);

        return [$customer, MembershipCard::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id])];
    });

    // The same customer number in another tenant does not block or leak.
    expect(app(IssueCard::class)->handle($this->user, $this->customer)->card_code)->toBe('MC1000001701');
    expect(fn () => app(IssueCard::class)->handle($this->user, $foreignCustomer))->toThrow(ModelNotFoundException::class);
    expect(fn () => app(ReportCardLost::class)->handle($this->user, $foreignCard))->toThrow(ModelNotFoundException::class);
    expect(fn () => app(ReissueCard::class)->handle($this->user, $foreignCard))->toThrow(ModelNotFoundException::class);
    expect(fn () => app(RecordCardPrint::class)->handle($this->user, $foreignCard, CardPrintLayout::Cr80))->toThrow(ModelNotFoundException::class);
    expect(CardExport::rows(MembershipCard::query()->with('customer')->get()))->toHaveCount(1);
});
