<?php

declare(strict_types=1);

namespace App\Sms;

use App\Models\Tenant;
use App\Sms\Contracts\SmsGateway;
use App\Sms\Drivers\LogDriver;
use App\Sms\Drivers\NullDriver;
use App\Sms\Drivers\OneCodeSoftDriver;
use App\Sms\Drivers\SslWirelessDriver;
use App\Support\Tenancy\TenantContext;

/**
 * Builds the gateway from a tenant's own settings, so one tenant's credentials are never used for
 * another. Drivers are not cached: they are cheap and the settings can change between calls.
 */
final readonly class SmsManager
{
    public function __construct(private TenantContext $context) {}

    public function forTenant(Tenant $tenant): SmsGateway
    {
        return $this->make(SmsSettings::fromTenant($tenant));
    }

    public function forCurrentTenant(): SmsGateway
    {
        return $this->forTenant($this->context->tenant());
    }

    public function make(SmsSettings $settings): SmsGateway
    {
        return match ($settings->driver) {
            'ssl_wireless' => new SslWirelessDriver($settings->apiToken ?? '', $settings->sid !== '' ? $settings->sid : $settings->senderId, $settings->baseUrl),
            'onecodesoft' => new OneCodeSoftDriver($settings->apiToken ?? '', $settings->senderId, $settings->baseUrl),
            'log' => new LogDriver,
            default => new NullDriver,
        };
    }
}
