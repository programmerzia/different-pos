<?php

declare(strict_types=1);

use App\Actions\Cards\IssueCard;
use App\Actions\Cards\ReportCardLost;
use App\Enums\CardStatus;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\MembershipCard;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Customers\CustomerNumber;
use App\Support\Permissions\SystemRoles;
use App\Support\Tenancy\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\CustomerWorld;

/**
 * @return array{0: User, 1: Tenant, 2: Customer, 3: MembershipCard}
 */
function cardWorld(?string $role = SystemRoles::OWNER): array
{
    [$user, $tenant, $branch] = CustomerWorld::actor($role);
    $customer = CustomerWorld::make($tenant, ['name' => 'Rahim Uddin', 'name_bn' => 'রহিম', 'shop_name' => 'Rahim Store', 'phone' => '+8801711111111', 'customer_no' => 10000017, 'member_since' => '2026-01-05']);
    app(TenantContext::class)->set($tenant, $branch);
    $card = app(IssueCard::class)->handle($user, $customer);

    return [$user, $tenant, $customer, $card];
}

/** A member with a valid customer number (sequence 1000001 + $seq). */
function member(Tenant $tenant, int $seq, array $attributes = []): Customer
{
    return CustomerWorld::make($tenant, ['customer_no' => (int) CustomerNumber::generate(1000000 + $seq), ...$attributes]);
}

it('lists every member with their card, 40 per page, with totals', function (): void {
    [$user, $tenant, $customer, $card] = cardWorld();
    foreach (range(2, 45) as $i) {
        member($tenant, $i, ['name' => sprintf('Member %02d', $i)]);
    }
    CustomerWorld::make($tenant, ['name' => 'Walk-in', 'is_walk_in' => true, 'customer_no' => (int) CustomerNumber::generate(1000099)]);
    CustomerWorld::make($tenant, ['name' => 'Gone', 'is_active' => false, 'customer_no' => (int) CustomerNumber::generate(1000098)]);

    $this->actingAs($user);
    $this->get('/app/membership-cards')->assertOk()->assertInertia(fn (Assert $p) => $p->component('cards/index')
        ->has('members', 40)
        ->where('meta.total', 45)->where('meta.perPage', 40)->where('meta.lastPage', 2)
        ->where('totals', ['members' => 45, 'issued' => 1, 'not_issued' => 44, 'lost' => 0])
        ->where('members.0.name', 'Member 02')
        ->where('members.0.card', null)
        ->where('can.issue', true)
        ->where('brand.name', $tenant->name)
        ->has('members.0', fn (Assert $m) => $m->hasAll(['uuid', 'name', 'name_bn', 'shop_name', 'phone', 'customer_no', 'member_since', 'group', 'card'])));

    $this->get('/app/membership-cards?page=2')->assertInertia(fn (Assert $p) => $p->has('members', 5)
        ->where('members.4.name', 'Rahim Uddin')
        ->where('members.4.customer_no', '1000 0017')
        ->where('members.4.member_since', '2026-01-05')
        ->where('members.4.card.code', $card->card_code)
        ->where('members.4.card.status', 'active')
        ->where('members.4.card.printed_at', null));
});

it('searches members by name, shop, phone, number and card code', function (): void {
    [$user, $tenant] = cardWorld();
    member($tenant, 2, ['name' => 'Karim', 'shop_name' => 'Karim Traders', 'phone' => '+8801899999999']);

    $this->actingAs($user);
    $one = fn (string $q, string $name) => $this->get('/app/membership-cards?q='.urlencode($q))
        ->assertInertia(fn (Assert $p) => $p->has('members', 1)->where('members.0.name', $name));
    $one('rahim', 'Rahim Uddin');
    $one('Traders', 'Karim');
    $one('01899999', 'Karim');
    $one('1000 0017', 'Rahim Uddin');
    $one('MC1000001701', 'Rahim Uddin');
    $this->get('/app/membership-cards?q=nobody')->assertInertia(fn (Assert $p) => $p->has('members', 0)->where('meta.total', 0)->where('totals.members', 2));
});

it('filters members by group and card status', function (): void {
    [$user, $tenant, , $card] = cardWorld();
    $group = app(TenantContext::class)->withoutTenancy(fn () => CustomerGroup::factory()->for($tenant)->create(['name' => 'VIP']));
    $vip = member($tenant, 2, ['name' => 'Vip One', 'customer_group_id' => $group->id]);
    $lostOwner = member($tenant, 3, ['name' => 'Lost One']);
    $lost = app(IssueCard::class)->handle($user, $lostOwner);
    app(ReportCardLost::class)->handle($user, $lost);

    $this->actingAs($user);
    $this->get('/app/membership-cards')->assertInertia(fn (Assert $p) => $p
        ->where('totals', ['members' => 3, 'issued' => 1, 'not_issued' => 1, 'lost' => 1])
        ->where('groups', [['id' => $group->id, 'name' => 'VIP', 'count' => 1]]));
    $this->get("/app/membership-cards?filter[group]={$group->id}")->assertInertia(fn (Assert $p) => $p->has('members', 1)
        ->where('members.0.uuid', $vip->uuid)->where('members.0.group', 'VIP'));
    $this->get('/app/membership-cards?filter[status]=issued')->assertInertia(fn (Assert $p) => $p->has('members', 1)->where('members.0.card.id', $card->id));
    $this->get('/app/membership-cards?filter[status]=not_issued')->assertInertia(fn (Assert $p) => $p->has('members', 1)->where('members.0.name', 'Vip One'));
    $this->get('/app/membership-cards?filter[status]=lost')->assertInertia(fn (Assert $p) => $p->has('members', 1)
        ->where('members.0.name', 'Lost One')->where('members.0.card.status', 'lost')->where('members.0.card.id', $lost->id));
    $this->get('/app/membership-cards?filter[status]=bogus')->assertInertia(fn (Assert $p) => $p->has('members', 3));
});

it('issues a card on demand when printing or exporting by customer', function (): void {
    [$user, $tenant, $customer, $card] = cardWorld();
    $fresh = member($tenant, 2, ['name' => 'Fresh Member']);
    $lostOwner = member($tenant, 3, ['name' => 'Lost Member']);
    $lost = app(IssueCard::class)->handle($user, $lostOwner);
    app(ReportCardLost::class)->handle($user, $lost);

    $this->actingAs($user);
    $html = $this->get("/app/membership-cards/print?customers={$customer->uuid},{$fresh->uuid},{$lostOwner->uuid}")->assertOk()->getContent();

    $freshCard = MembershipCard::query()->where('customer_id', $fresh->id)->where('status', CardStatus::Active)->sole();
    $reissued = MembershipCard::query()->where('customer_id', $lostOwner->id)->where('status', CardStatus::Active)->sole();
    expect($freshCard->card_code)->toBe('MC'.$fresh->customer_no.'01')
        ->and($reissued->issue_no)->toBe(2)
        ->and($reissued->id)->not->toBe($lost->id)
        ->and(MembershipCard::query()->where('customer_id', $customer->id)->count())->toBe(1)
        ->and(substr_count((string) $html, 'class="card front '))->toBe(3)
        ->and((string) $html)->toContain($card->card_code)->toContain($freshCard->card_code)->toContain($reissued->card_code)->not->toContain($lost->card_code);

    // A second print and the CSV reuse the cards just issued.
    $this->get("/app/membership-cards/export.csv?customers={$fresh->uuid}")->assertOk()->assertSee($freshCard->card_code);
    $this->post('/app/membership-cards/printed', ['customers' => [$fresh->uuid], 'layout' => 'a4'])->assertRedirect();
    expect(MembershipCard::query()->where('customer_id', $fresh->id)->count())->toBe(1)
        ->and($freshCard->refresh()->print_count)->toBe(1)
        ->and($freshCard->last_print_layout?->value)->toBe('a4_10');
});

it('skips customers that cannot hold a card and 404s when nothing is printable', function (): void {
    [$user, $tenant, $customer] = cardWorld();
    $walkIn = member($tenant, 2, ['is_walk_in' => true]);

    $this->actingAs($user);
    $this->get("/app/membership-cards/print?customers={$walkIn->uuid}")->assertNotFound();
    $this->get("/app/membership-cards/print?customers={$walkIn->uuid},{$customer->uuid}")->assertOk()->assertSee('1000 0017');
    expect(MembershipCard::query()->where('customer_id', $walkIn->id)->exists())->toBeFalse();
    $this->getJson('/app/membership-cards/print?customers=not-a-uuid')->assertStatus(422)->assertJsonValidationErrors('customers.0');
});

it('renders the A4 10-up sheet: ten cards a sheet, fronts then mirrored backs, crop marks', function (): void {
    [$user, $tenant, $customer] = cardWorld();
    $uuids = [$customer->uuid];
    foreach (range(2, 12) as $i) {
        $uuids[] = member($tenant, $i, ['name' => sprintf('Sheet Member %02d', $i)])->uuid;
    }

    $this->actingAs($user);
    $html = (string) $this->get('/app/membership-cards/print?layout=a4&customers='.implode(',', $uuids))->assertOk()
        ->assertSee('size: 210mm 297mm', false)->getContent();

    expect(substr_count($html, 'class="sheet front"'))->toBe(2)
        ->and(substr_count($html, 'class="sheet back"'))->toBe(2)
        ->and(substr_count($html, 'class="card front '))->toBe(12)
        ->and(substr_count($html, 'class="card back '))->toBe(12)
        ->and(substr_count($html, 'class="mark '))->toBe(4 * 18);

    // Sheet 1 has ten cards on each face; the back row swaps the two columns.
    $first = substr($html, 0, (int) strpos($html, 'data-sheet="2"'));
    expect(substr_count($first, 'class="card front '))->toBe(10)->and(substr_count($first, 'class="card back '))->toBe(10);
    preg_match('/class="sheet back".*?class="slot" style="left: ([\d.]+)mm; top: 13.5mm">.*?data-card="(MC\d+)"/s', $html, $m);
    expect($m[1] ?? null)->toBe('19.4');

    $plain = (string) $this->get('/app/membership-cards/print?layout=a4&crop=0&customers='.$customer->uuid)->getContent();
    expect($plain)->not->toContain('class="mark ');
});

it('adds crop marks to the bleed layout on a slug page', function (): void {
    [$user, , , $card] = cardWorld();

    $this->actingAs($user);
    $this->get("/app/membership-cards/print?ids={$card->id}&layout=bleed&crop=1")->assertOk()
        ->assertSee('size: 101.6mm 70mm', false)->assertSee('class="mark v"', false);
    $this->get("/app/membership-cards/print?ids={$card->id}&layout=bleed")->assertOk()
        ->assertSee('size: 91.6mm 60mm', false)->assertDontSee('class="mark v"', false);
});

it('renders the print route with both sides, and the bleed layout', function (): void {
    [$user, , , $card] = cardWorld();

    $cr80 = $this->actingAs($user)->get("/app/membership-cards/print?ids={$card->id}&layout=cr80")->assertOk();
    $cr80->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    $cr80->assertSee('class="card front ', false)->assertSee('class="card back ', false)
        ->assertSee('Rahim Uddin')->assertSee('1000 0017')->assertSee('MC1000001701')->assertSee('@page', false)->assertSee('85.6mm', false);

    $this->get("/app/membership-cards/print?ids={$card->id}&layout=bleed")->assertOk()->assertSee('91.6mm', false)->assertSee('MC1000001701');
});

it('exports the press CSV with the variable data columns', function (): void {
    [$user, , , $card] = cardWorld();

    $response = $this->actingAs($user)->get("/app/membership-cards/export.csv?ids={$card->id}")->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($response->headers->get('Content-Disposition'))->toContain('attachment; filename="membership-cards-');
    $lines = preg_split('/\R/', trim((string) $response->getContent(), "\u{FEFF}\r\n"));
    expect($response->getContent())->toStartWith("\u{FEFF}customer_no,name_en,name_bn,shop_name,phone,member_since,card_code")
        ->and(str_getcsv((string) $lines[1], ',', '"', ''))->toBe(['1000 0017', 'Rahim Uddin', 'রহিম', 'Rahim Store', '+8801711111111', '2026-01-05', 'MC1000001701']);
});

it('prints and exports only active cards and rejects bad input', function (): void {
    [$user, , , $card] = cardWorld();
    app(ReportCardLost::class)->handle($user, $card);

    $this->actingAs($user);
    $this->get("/app/membership-cards/print?ids={$card->id}")->assertNotFound();
    $this->get("/app/membership-cards/export.csv?ids={$card->id}")->assertNotFound();
    $this->getJson('/app/membership-cards/print')->assertStatus(422)->assertJsonValidationErrors('ids');
    $this->getJson("/app/membership-cards/print?ids={$card->id}&layout=a3")->assertStatus(422)->assertJsonValidationErrors('layout');
});

it('records prints', function (): void {
    [$user, , , $card] = cardWorld();

    $this->actingAs($user)->post('/app/membership-cards/printed', ['ids' => [$card->id], 'layout' => 'bleed'])->assertRedirect();
    $card->refresh();
    expect($card->print_count)->toBe(1)->and($card->last_print_layout?->value)->toBe('pdf')->and($card->last_printed_at)->not->toBeNull();
});

it('issues cards to selected customers and reports the ones it cannot', function (): void {
    [$user, $tenant, $customer] = cardWorld();
    $fresh = CustomerWorld::make($tenant, ['customer_no' => 10000025]);

    $this->actingAs($user)->post('/app/membership-cards/issue', ['customers' => [$fresh->uuid, $customer->uuid]])
        ->assertRedirect()->assertSessionHasErrors('form');
    expect(MembershipCard::query()->where('customer_id', $fresh->id)->where('status', CardStatus::Active)->exists())->toBeTrue();

    $this->postJson('/app/membership-cards/issue', ['customers' => ['not-a-uuid']])->assertStatus(422);
});

it('reports a card lost and reissues it', function (): void {
    [$user, , $customer, $card] = cardWorld();

    $this->actingAs($user);
    $this->post("/app/membership-cards/{$card->id}/lost", ['reason' => 'dropped'])->assertRedirect();
    expect($card->refresh()->status)->toBe(CardStatus::Lost)->and($card->block_reason)->toBe('dropped');
    $this->post("/app/membership-cards/{$card->id}/lost")->assertSessionHasErrors('form');

    $this->post("/app/membership-cards/{$card->id}/reissue")->assertRedirect();
    $active = MembershipCard::query()->where('customer_id', $customer->id)->where('status', CardStatus::Active)->firstOrFail();
    expect($active->issue_no)->toBe(2)->and($active->card_code)->toBe('MC1000001702');
});

it('authorizes per permission: a cashier may list cards but not issue, print or export', function (): void {
    [$cashier, , $customer, $card] = cardWorld(SystemRoles::CASHIER);

    $this->actingAs($cashier);
    $this->get('/app/membership-cards')->assertOk()->assertInertia(fn (Assert $p) => $p->where('can.issue', false));
    $this->get("/app/membership-cards/print?ids={$card->id}")->assertForbidden();
    $this->get("/app/membership-cards/export.csv?ids={$card->id}")->assertForbidden();
    $this->post('/app/membership-cards/printed', ['ids' => [$card->id]])->assertForbidden();
    $this->post('/app/membership-cards/issue', ['customers' => [$customer->uuid]])->assertForbidden();
    $this->post("/app/membership-cards/{$card->id}/lost")->assertForbidden();
    $this->post("/app/membership-cards/{$card->id}/reissue")->assertForbidden();

    [$keeper] = CustomerWorld::actor(SystemRoles::STOCK_KEEPER);
    $this->actingAs($keeper)->get('/app/membership-cards')->assertForbidden();
});

it('never reaches another tenant\'s cards', function (): void {
    [$user, , , $own] = cardWorld();
    [, , $foreignCustomer, $foreign] = cardWorld();

    $this->actingAs($user);
    $this->get("/app/membership-cards/print?ids={$foreign->id}")->assertNotFound();
    $this->get("/app/membership-cards/export.csv?ids={$foreign->id}")->assertNotFound();
    $this->post("/app/membership-cards/{$foreign->id}/lost")->assertNotFound();
    $this->post("/app/membership-cards/{$foreign->id}/reissue")->assertNotFound();
    $this->get('/app/membership-cards')->assertInertia(fn (Assert $p) => $p->has('members', 1)->where('totals.members', 1)->where('members.0.card.id', $own->id));
    $this->get("/app/membership-cards/print?customers={$foreignCustomer->uuid}")->assertNotFound();
    $this->get("/app/membership-cards/export.csv?customers={$foreignCustomer->uuid}")->assertNotFound();
    $this->post('/app/membership-cards/printed', ['customers' => [$foreignCustomer->uuid]])->assertNotFound();
    expect(MembershipCard::query()->withoutGlobalScopes()->where('customer_id', $foreignCustomer->id)->count())->toBe(1);
    expect($foreign->refresh()->status)->toBe(CardStatus::Active);
});
