<?php

declare(strict_types=1);

namespace App\Http\Controllers\Messaging;

use App\Actions\Messaging\SaveSmsSettings;
use App\Actions\Messaging\SaveWhatsAppSettings;
use App\Actions\Messaging\SendTestSms;
use App\Actions\Messaging\SendTestWhatsApp;
use App\Actions\Messaging\SetSmsSending;
use App\Exceptions\Domain\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\SaveSmsSettingsRequest;
use App\Http\Requests\Messaging\SendTestSmsRequest;
use App\Http\Requests\Messaging\SendTestWhatsAppRequest;
use App\Models\Message;
use App\Models\User;
use App\Sms\SmsSettings;
use App\Sms\Templates;
use App\Support\Tenancy\TenantContext;
use App\WhatsApp\WhatsAppSettings;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** /app/settings/sms: Messaging Providers: SMS provider, WhatsApp provider, credentials and the templates. No token is ever sent to the page. */
final class SmsSettingsController extends Controller
{
    use AuthorizesRequests;

    public function edit(Request $request, TenantContext $context): Response
    {
        $this->authorize('configure', Message::class);

        $settings = SmsSettings::fromTenant($context->tenant());
        $templates = [];
        foreach (Templates::keys() as $key) {
            $templates[] = [
                'key' => $key,
                'enabled' => $settings->templates[$key]['enabled'],
                'body' => $settings->templates[$key]['body'],
                'default' => Templates::DEFAULTS[$key],
                'placeholders' => Templates::PLACEHOLDERS[$key],
            ];
        }

        $whatsApp = WhatsAppSettings::fromTenant($context->tenant());

        return Inertia::render('settings/sms', [
            'drivers' => SmsSettings::DRIVERS,
            'whatsappDrivers' => WhatsAppSettings::DRIVERS,
            'whatsapp' => [
                'driver' => $whatsApp->driver,
                'status' => $whatsApp->status(),
                'phone_number_id' => $whatsApp->phoneNumberId,
                'has_token' => $whatsApp->accessToken !== null,
                'template_name' => $whatsApp->templateName,
                'template_language' => $whatsApp->templateLanguage,
            ],
            'sms' => [
                'driver' => $settings->driver,
                'sender_id' => $settings->senderId,
                'sid' => $settings->sid,
                'base_url' => $settings->baseUrl,
                'has_token' => $settings->apiToken !== null,
                'configured' => $settings->isConfigured(),
                'sending_enabled' => $settings->sendingEnabled,
                'cost_per_segment' => $settings->costPerSegment,
                'templates' => $templates,
            ],
        ]);
    }

    public function update(SaveSmsSettingsRequest $request, SaveSmsSettings $action, SaveWhatsAppSettings $whatsApp): RedirectResponse
    {
        $this->authorize('configure', Message::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        /** @var array{driver: string, sender_id?: string|null, sid?: string|null, base_url?: string|null, api_token?: string|null, clear_token?: bool, cost_per_segment?: int|null, templates?: array<string, array{enabled?: bool, body?: string|null}>, whatsapp?: array{driver: string, phone_number_id?: string|null, access_token?: string|null, clear_token?: bool, template_name?: string|null, template_language?: string|null}|null} $data */
        $data = $request->validated();
        $action->handle($user, $data);
        if (isset($data['whatsapp'])) {
            $whatsApp->handle($user, $data['whatsapp']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('sms.settings_saved')]);

        return to_route('settings.sms.edit');
    }

    /** The master switch: applies at once, with no need to save the rest of the form. */
    public function sending(Request $request, SetSmsSending $action): RedirectResponse
    {
        $this->authorize('configure', Message::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $enabled = $request->validate(['enabled' => ['required', 'boolean']])['enabled'];
        $action->handle($user, (bool) $enabled);

        Inertia::flash('toast', ['type' => 'success', 'message' => __($enabled ? 'sms.sending_turned_on' : 'sms.sending_turned_off')]);

        return to_route('settings.sms.edit');
    }

    public function test(SendTestSmsRequest $request, SendTestSms $action): RedirectResponse
    {
        // A test SMS belongs to the provider settings (settings.manage) and still sends (sms.send).
        $this->authorize('configure', Message::class);
        $this->authorize('send', Message::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        try {
            $message = $action->handle($user, (string) $request->validated('phone'));
        } catch (DomainException $e) {
            return back()->withErrors(['phone' => $e->getMessage()]);
        }

        $sent = $message->status->value === 'sent';
        Inertia::flash('toast', [
            'type' => $sent ? 'success' : 'error',
            'message' => $sent ? __('sms.test_sent', ['phone' => $message->phone]) : __('sms.test_failed', ['error' => (string) $message->error]),
        ]);

        return to_route('settings.sms.edit');
    }

    /** POST /app/settings/whatsapp/test: sends a test WhatsApp now; click-to-chat flashes the wa.me link for the page to open. */
    public function testWhatsApp(SendTestWhatsAppRequest $request, SendTestWhatsApp $action): RedirectResponse
    {
        $this->authorize('configure', Message::class);
        $this->authorize('send', Message::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        try {
            $result = $action->handle($user, (string) $request->validated('phone'));
        } catch (DomainException $e) {
            return back()->withErrors(['whatsapp_phone' => $e->getMessage()]);
        }

        $message = $result['message'];
        $ok = $message->status->value === 'sent' || $message->status->value === 'handoff';
        Inertia::flash('toast', [
            'type' => $ok ? 'success' : 'error',
            'message' => $ok ? __('sms.whatsapp.test_sent', ['phone' => $message->phone]) : __('sms.whatsapp.test_failed', ['error' => (string) $message->error]),
        ]);
        if ($result['link'] !== null) {
            Inertia::flash('whatsapp_link', $result['link']);
        }

        return to_route('settings.sms.edit');
    }
}
