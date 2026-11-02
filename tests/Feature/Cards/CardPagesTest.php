<?php

declare(strict_types=1);

use App\Actions\Cards\IssueCard;
use App\Actions\Cards\ReportCardLost;
use App\Enums\CardStatus;
use App\Models\Customer;
use App\Models\MembershipCard;
use App\Models\Tenant;
use App\Models\User;
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

it('lists cards with a status filter and search', function (): void {
    [$user, $tenant, $customer, $card] = cardWorld();
    $second = CustomerWorld::make($tenant, ['name' => 'Karim', 'customer_no' => 10000025]);
    $lost = app(IssueCard::class)->handle($user, $second);
    app(ReportCardLost::class)->handle($user, $lost);

    $this->actingAs($user);
    $this->get('/app/membership-cards')->assertOk()->assertInertia(fn (Assert $p) => $p->component('cards/index')->has('cards', 2)->where('can.issue', true));
    $this->get('/app/membership-cards?filter[status]=lost')->assertInertia(fn (Assert $p) => $p->has('cards', 1)->where('cards.0.name', 'Karim')->where('cards.0.status', 'lost'));
    $this->get('/app/membership-cards?q=Rahim')->assertInertia(fn (Assert $p) => $p->has('cards', 1)->where('cards.0.card_code', $card->card_code)->where('cards.0.customer_no', '1000 0017'));
    $this->get('/app/membership-cards?q=1000')->assertInertia(fn (Assert $p) => $p->has('cards', 2));
});

it('renders the print route with both sides, and the bleed layout', function (): void {
    [$user, , , $card] = cardWorld();

    $cr80 = $this->actingAs($user)->get("/app/membership-cards/print?ids={$card->id}&layout=cr80")->assertOk();
    $cr80->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    $cr80->assertSee('class="card front"', false)->assertSee('class="card back"', false)
        ->assertSee('Rahim Uddin')->assertSee('Rahim Store')->assertSee('1000 0017')->assertSee('MC1000001701')->assertSee('@page', false)->assertSee('85.6mm', false);

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
    [$user] = cardWorld();
    [, , , $foreign] = cardWorld();

    $this->actingAs($user);
    $this->get("/app/membership-cards/print?ids={$foreign->id}")->assertNotFound();
    $this->get("/app/membership-cards/export.csv?ids={$foreign->id}")->assertNotFound();
    $this->post("/app/membership-cards/{$foreign->id}/lost")->assertNotFound();
    $this->post("/app/membership-cards/{$foreign->id}/reissue")->assertNotFound();
    $this->get('/app/membership-cards')->assertInertia(fn (Assert $p) => $p->has('cards', 1));
    expect($foreign->refresh()->status)->toBe(CardStatus::Active);
});
