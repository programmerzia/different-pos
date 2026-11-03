<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Models\Message;
use App\Models\User;
use App\Sms\SmsSettings;
use App\Sms\Templates;
use App\Support\Tenancy\TenantContext;
use App\WhatsApp\WhatsAppSettings;

/**
 * The `messaging` page prop shared by the dues list, the due drill-down and the customer profile: what each
 * channel can do (truthfully: WhatsApp `status` is connected / click_to_chat / log / incomplete / off), the
 * shop facts and the default WhatsApp texts the reminder dialog renders client-side. Nothing secret.
 */
final readonly class MessagingUi
{
    public function __construct(private TenantContext $context) {}

    /** @return array<string, mixed> */
    public function props(User $user): array
    {
        $tenant = $this->context->tenant();
        $whatsApp = WhatsAppSettings::fromTenant($tenant);

        return [
            'can_send' => $user->can('send', Message::class),
            'whatsapp' => ['driver' => $whatsApp->driver, 'status' => $whatsApp->status(), 'configured' => $whatsApp->configured(), 'bulk' => $whatsApp->bulk()],
            'sms' => ['configured' => SmsSettings::fromTenant($tenant)->driver !== 'null'],
            'shop' => $tenant->name,
            'shop_phone' => (string) ($tenant->phone ?? ''),
            'defaults' => Templates::WHATSAPP_DEFAULTS,
        ];
    }
}
