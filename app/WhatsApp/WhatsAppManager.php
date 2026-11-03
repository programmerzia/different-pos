<?php

declare(strict_types=1);

namespace App\WhatsApp;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use App\WhatsApp\Contracts\WhatsAppGateway;
use App\WhatsApp\Drivers\ClickToChatGateway;
use App\WhatsApp\Drivers\LogWhatsAppGateway;
use App\WhatsApp\Drivers\MetaCloudApiGateway;
use App\WhatsApp\Drivers\NullWhatsAppGateway;

/**
 * Builds the WhatsApp gateway from a tenant's own settings, so one tenant's credentials are never used
 * for another. Swapping providers = a new adapter in Drivers plus one `match` arm here.
 */
final readonly class WhatsAppManager
{
    public function __construct(private TenantContext $context) {}

    public function forTenant(Tenant $tenant): WhatsAppGateway
    {
        return $this->make(WhatsAppSettings::fromTenant($tenant));
    }

    public function forCurrentTenant(): WhatsAppGateway
    {
        return $this->forTenant($this->context->tenant());
    }

    public function make(WhatsAppSettings $settings): WhatsAppGateway
    {
        return match ($settings->driver) {
            'meta_cloud' => new MetaCloudApiGateway($settings->phoneNumberId, $settings->accessToken ?? '', $settings->templateName, $settings->templateLanguage),
            'click_to_chat' => new ClickToChatGateway,
            'log' => new LogWhatsAppGateway,
            default => new NullWhatsAppGateway,
        };
    }
}
