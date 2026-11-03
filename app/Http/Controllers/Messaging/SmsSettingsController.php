<?php

declare(strict_types=1);

namespace App\Http\Controllers\Messaging;

use App\Actions\Messaging\SaveSmsSettings;
use App\Actions\Messaging\SendTestSms;
use App\Exceptions\Domain\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\SaveSmsSettingsRequest;
use App\Http\Requests\Messaging\SendTestSmsRequest;
use App\Models\Message;
use App\Models\User;
use App\Sms\SmsSettings;
use App\Sms\Templates;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** /app/settings/sms: provider, credentials and the transactional templates. The token is never sent to the page. */
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

        return Inertia::render('settings/sms', [
            'drivers' => SmsSettings::DRIVERS,
            'sms' => [
                'driver' => $settings->driver,
                'sender_id' => $settings->senderId,
                'sid' => $settings->sid,
                'base_url' => $settings->baseUrl,
                'has_token' => $settings->apiToken !== null,
                'cost_per_segment' => $settings->costPerSegment,
                'templates' => $templates,
            ],
        ]);
    }

    public function update(SaveSmsSettingsRequest $request, SaveSmsSettings $action): RedirectResponse
    {
        $this->authorize('configure', Message::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        /** @var array{driver: string, sender_id?: string|null, sid?: string|null, base_url?: string|null, api_token?: string|null, clear_token?: bool, cost_per_segment?: int|null, templates?: array<string, array{enabled?: bool, body?: string|null}>} $data */
        $data = $request->validated();
        $action->handle($user, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('sms.settings_saved')]);

        return to_route('settings.sms.edit');
    }

    public function test(SendTestSmsRequest $request, SendTestSms $action): RedirectResponse
    {
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
}
