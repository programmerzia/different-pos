<?php

declare(strict_types=1);

use App\Actions\Cards\EnsureActiveCard;
use App\Actions\Cards\IssueCard;
use App\Actions\Cards\ReportCardLost;
use App\Enums\CardStatus;
use App\Exceptions\Domain\InvalidPartyData;
use App\Models\Customer;
use App\Models\MembershipCard;
use App\Support\Tenancy\TenantContext;
use Tests\Fixtures\StockWorld;

beforeEach(function (): void {
    $this->world = StockWorld::make();
    $this->user = $this->world->user;
    $this->customer = app(TenantContext::class)->withoutTenancy(fn (): Customer => Customer::factory()->create([
        'tenant_id' => $this->world->tenant->id, 'customer_no' => 10000017, 'name' => 'Rahim',
    ]));
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('issues a card when the customer has none', function (): void {
    $card = app(EnsureActiveCard::class)->handle($this->user, $this->customer);

    expect($card->card_code)->toBe('MC1000001701')->and($card->status)->toBe(CardStatus::Active)
        ->and(MembershipCard::query()->count())->toBe(1);
});

it('returns the existing active card without issuing again', function (): void {
    $existing = app(IssueCard::class)->handle($this->user, $this->customer);

    $card = app(EnsureActiveCard::class)->handle($this->user, $this->customer);
    $again = app(EnsureActiveCard::class)->handle($this->user, $this->customer);

    expect($card->id)->toBe($existing->id)->and($again->id)->toBe($existing->id)
        ->and(MembershipCard::query()->count())->toBe(1);
});

it('never reuses a lost card: the next issue number is used', function (): void {
    $lost = app(IssueCard::class)->handle($this->user, $this->customer);
    app(ReportCardLost::class)->handle($this->user, $lost);

    $card = app(EnsureActiveCard::class)->handle($this->user, $this->customer);

    expect($card->id)->not->toBe($lost->id)->and($card->issue_no)->toBe(2)->and($card->card_code)->toBe('MC1000001702')
        ->and($lost->refresh()->status)->toBe(CardStatus::Lost);
});

it('refuses a customer that cannot hold a card', function (): void {
    $this->customer->forceFill(['is_walk_in' => true])->save();

    app(EnsureActiveCard::class)->handle($this->user, $this->customer);
})->throws(InvalidPartyData::class);
