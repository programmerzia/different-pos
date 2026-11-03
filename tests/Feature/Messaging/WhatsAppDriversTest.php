<?php

declare(strict_types=1);

use App\Enums\MessageStatus;
use App\Models\Tenant;
use App\WhatsApp\Drivers\ClickToChatGateway;
use App\WhatsApp\Drivers\LogWhatsAppGateway;
use App\WhatsApp\Drivers\MetaCloudApiGateway;
use App\WhatsApp\Drivers\NullWhatsAppGateway;
use App\WhatsApp\WhatsAppManager;
use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/*
| The WhatsApp adapters (ADR 0007 style: swappable, never throw, no provider API outside app/WhatsApp/Drivers).
| Meta's Cloud API is faked with Http::fake: nothing here ever reaches the network.
*/

function wa(string $text = 'প্রিয় রহিম, বকেয়া ১,২০০ টাকা।'): WhatsAppMessage
{
    return new WhatsAppMessage('+8801712345678', $text, 'm7');
}

function meta(string $template = ''): MetaCloudApiGateway
{
    return new MetaCloudApiGateway('1098765', 'EAAB-token', $template, 'bn');
}

it('posts a free-form text to the Cloud API with the bearer token and returns the message id', function (): void {
    Http::fake(['*' => Http::response(['messaging_product' => 'whatsapp', 'contacts' => [['wa_id' => '8801712345678']], 'messages' => [['id' => 'wamid.HBgL']]])]);

    $result = meta()->send(wa('Hello'));

    expect($result->ok)->toBeTrue()->and($result->status)->toBe(MessageStatus::Sent)->and($result->providerRef)->toBe('wamid.HBgL');
    Http::assertSent(fn (Request $r): bool => $r->url() === 'https://graph.facebook.com/v21.0/1098765/messages'
        && $r->hasHeader('Authorization', 'Bearer EAAB-token')
        && $r['messaging_product'] === 'whatsapp' && $r['to'] === '8801712345678'
        && $r['type'] === 'text' && $r['text']['body'] === 'Hello' && $r['text']['preview_url'] === false);
});

it('sends through an approved template with the whole text as the single body parameter', function (): void {
    Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.T']]])]);

    expect(meta('due_notice')->send(wa('Hello'))->providerRef)->toBe('wamid.T');
    Http::assertSent(fn (Request $r): bool => $r['type'] === 'template'
        && $r['template']['name'] === 'due_notice' && $r['template']['language']['code'] === 'bn'
        && $r['template']['components'][0]['type'] === 'body'
        && $r['template']['components'][0]['parameters'][0] === ['type' => 'text', 'text' => 'Hello']);
});

it('treats a 4xx with an error object as a rejection that is not retried', function (): void {
    Http::fake(['*' => Http::response(['error' => ['message' => 'Recipient phone number not in allowed list', 'code' => 131030]], 400)]);

    $result = meta()->send(wa());

    expect($result->ok)->toBeFalse()->and($result->retryable)->toBeFalse()
        ->and($result->error)->toBe('Recipient phone number not in allowed list (code 131030)');
});

it('retries on 5xx, 429 and connection failures', function (): void {
    Http::fake(['*' => Http::response('', 503)]);
    expect(meta()->send(wa())->retryable)->toBeTrue();

    Http::fake(['*' => Http::response(['error' => ['message' => 'rate']], 429)]);
    expect(meta()->send(wa())->retryable)->toBeTrue();

    Http::fake(fn () => throw new ConnectionException('timed out'));
    $result = meta()->send(wa());
    expect($result->ok)->toBeFalse()->and($result->retryable)->toBeTrue()->and($result->error)->toContain('timed out');
});

it('fails without credentials before calling anything', function (): void {
    Http::fake();

    $result = (new MetaCloudApiGateway('', ''))->send(wa());

    expect($result->ok)->toBeFalse()->and($result->error)->toContain('phone number ID');
    Http::assertNothingSent();
});

it('builds the wa.me click-to-chat link with the E.164 digits and the url-encoded text', function (): void {
    $result = (new ClickToChatGateway)->send(wa('Hi there & thanks'));

    expect($result->ok)->toBeTrue()->and($result->status)->toBe(MessageStatus::Handoff)
        ->and($result->link)->toBe('https://wa.me/8801712345678?text=Hi%20there%20%26%20thanks')
        ->and($result->providerRef)->toBe('wa.me');
    expect((new ClickToChatGateway)->send(new WhatsAppMessage('+1', 'x', 'm1'))->ok)->toBeFalse();
});

it('logs without the text at info level and never fails', function (): void {
    Log::shouldReceive('info')->once()->withArgs(fn (string $msg, array $ctx): bool => $msg === 'WhatsApp (log driver)' && ! isset($ctx['text']) && $ctx['to'] === '+8801712345678');
    Log::shouldReceive('debug')->once();

    expect((new LogWhatsAppGateway)->send(wa())->providerRef)->toBe('log-m7');
    expect((new NullWhatsAppGateway)->send(wa())->ok)->toBeFalse();
});

it('builds the gateway from the tenant settings, decrypting the token, and reports a truthful status', function (): void {
    $tenant = Tenant::factory()->create(['settings' => ['whatsapp' => ['driver' => 'meta_cloud', 'phone_number_id' => '555', 'access_token' => Crypt::encryptString('secret'), 'template_name' => 'due_notice']]]);
    $settings = WhatsAppSettings::fromTenant($tenant);
    expect($settings->accessToken)->toBe('secret')->and($settings->status())->toBe('connected')->and($settings->bulk())->toBeTrue();
    expect(app(WhatsAppManager::class)->forTenant($tenant))->toBeInstanceOf(MetaCloudApiGateway::class);

    $incomplete = WhatsAppSettings::fromTenant(Tenant::factory()->create(['settings' => ['whatsapp' => ['driver' => 'meta_cloud', 'access_token' => 'not-encrypted']]]));
    expect($incomplete->accessToken)->toBeNull()->and($incomplete->status())->toBe('incomplete');

    $click = WhatsAppSettings::fromTenant(Tenant::factory()->create(['settings' => ['whatsapp' => ['driver' => 'click_to_chat']]]));
    expect($click->status())->toBe('click_to_chat')->and($click->configured())->toBeTrue()->and($click->bulk())->toBeFalse();
    expect(app(WhatsAppManager::class)->make($click))->toBeInstanceOf(ClickToChatGateway::class);

    $off = WhatsAppSettings::fromTenant(Tenant::factory()->create(['settings' => []]));
    expect($off->status())->toBe('off')->and($off->configured())->toBeFalse();
    expect(app(WhatsAppManager::class)->make($off))->toBeInstanceOf(NullWhatsAppGateway::class);
    expect(app(WhatsAppManager::class)->make(WhatsAppSettings::fromTenant(Tenant::factory()->create(['settings' => ['whatsapp' => ['driver' => 'log']]]))))->toBeInstanceOf(LogWhatsAppGateway::class);
});
