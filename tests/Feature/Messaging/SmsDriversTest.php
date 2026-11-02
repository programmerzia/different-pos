<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Sms\Drivers\LogDriver;
use App\Sms\Drivers\NullDriver;
use App\Sms\Drivers\SslWirelessDriver;
use App\Sms\SmsManager;
use App\Sms\SmsMessage;
use App\Sms\Templates;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

function ssl(string $token = 'tok-123', string $sid = 'SHOP'): SslWirelessDriver
{
    return new SslWirelessDriver($token, $sid);
}

function sms(): SmsMessage
{
    return new SmsMessage('+8801712345678', 'Hello', 'm7');
}

it('posts the SSL Wireless payload and returns the provider reference', function (): void {
    Http::fake(['*' => Http::response(['status' => 'SUCCESS', 'status_code' => 200, 'error_message' => '', 'smsinfo' => [['sms_status' => 'SUCCESS', 'reference_id' => 'REF-9', 'csms_id' => 'm7']]])]);

    $result = ssl()->send(sms());

    expect($result->ok)->toBeTrue()->and($result->providerRef)->toBe('REF-9');
    Http::assertSent(fn (Request $r): bool => $r->url() === 'https://smsplus.sslwireless.com/api/v3/send-sms'
        && $r['api_token'] === 'tok-123' && $r['sid'] === 'SHOP' && $r['msisdn'] === '8801712345678'
        && $r['sms'] === 'Hello' && $r['csms_id'] === 'm7');
});

it('uses a custom base url', function (): void {
    Http::fake(['*' => Http::response(['status' => 'SUCCESS', 'smsinfo' => [['csms_id' => 'm7']]])]);

    (new SslWirelessDriver('t', 's', 'https://sms.example.test/'))->send(sms());

    Http::assertSent(fn (Request $r): bool => $r->url() === 'https://sms.example.test/api/v3/send-sms');
});

it('maps a provider rejection to a non-retryable failure', function (): void {
    Http::fake(['*' => Http::response(['status' => 'FAILED', 'status_code' => 4003, 'error_message' => 'IP Blacklisted'], 200)]);

    $result = ssl()->send(sms());

    expect($result->ok)->toBeFalse()->and($result->error)->toBe('IP Blacklisted')->and($result->retryable)->toBeFalse();
});

it('treats a 5xx as retryable', function (): void {
    Http::fake(['*' => Http::response('boom', 503)]);

    $result = ssl()->send(sms());

    expect($result->ok)->toBeFalse()->and($result->retryable)->toBeTrue();
});

it('turns a timeout into a retryable failure instead of throwing', function (): void {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    $result = ssl()->send(sms());

    expect($result->ok)->toBeFalse()->and($result->retryable)->toBeTrue()->and($result->error)->toContain('timed out');
});

it('fails without credentials and sends nothing', function (): void {
    Http::fake();

    expect(ssl('', '')->send(sms())->ok)->toBeFalse();
    Http::assertNothingSent();
});

it('log driver succeeds and never puts the body at info level', function (): void {
    Log::spy();

    $result = (new LogDriver)->send(sms());

    expect($result->ok)->toBeTrue();
    Log::shouldHaveReceived('info')->withArgs(fn (string $m, array $ctx): bool => ! array_key_exists('body', $ctx))->once();
});

it('null driver reports that nothing is configured', function (): void {
    $result = (new NullDriver)->send(sms());

    expect($result->ok)->toBeFalse()->and($result->error)->toContain('No SMS provider');
});

it('manager picks the driver per tenant and never mixes tenant credentials', function (): void {
    Http::fake(['*' => Http::response(['status' => 'SUCCESS', 'smsinfo' => [['reference_id' => 'R']]])]);
    $a = Tenant::factory()->create(['settings' => ['sms' => ['driver' => 'ssl_wireless', 'sid' => 'A-SID', 'api_token' => Crypt::encryptString('TOKEN-A')]]]);
    $b = Tenant::factory()->create(['settings' => ['sms' => ['driver' => 'ssl_wireless', 'sid' => 'B-SID', 'api_token' => Crypt::encryptString('TOKEN-B')]]]);
    $c = Tenant::factory()->create(['settings' => ['sms' => ['driver' => 'log']]]);
    $d = Tenant::factory()->create();
    $manager = app(SmsManager::class);

    $manager->forTenant($b)->send(sms());
    $manager->forTenant($a)->send(sms());

    $tokens = collect(Http::recorded())->map(fn (array $pair): string => (string) $pair[0]['api_token'])->all();
    expect($tokens)->toBe(['TOKEN-B', 'TOKEN-A'])
        ->and($manager->forTenant($c))->toBeInstanceOf(LogDriver::class)
        ->and($manager->forTenant($d))->toBeInstanceOf(NullDriver::class);
});

it('stores nothing readable: a garbage token decrypts to null', function (): void {
    $tenant = Tenant::factory()->create(['settings' => ['sms' => ['driver' => 'ssl_wireless', 'sid' => 'S', 'api_token' => 'plain-text']]]);
    Http::fake();

    $result = app(SmsManager::class)->forTenant($tenant)->send(sms());

    expect($result->ok)->toBeFalse();
    Http::assertNothingSent();
});

it('renders placeholders and leaves unknown ones visible', function (): void {
    expect(Templates::render('Hi {customer}, due ৳{due} {nope}', ['customer' => 'Rahim', 'due' => '1,200.00']))
        ->toBe('Hi Rahim, due ৳1,200.00 {nope}');
});
