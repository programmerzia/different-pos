<?php

declare(strict_types=1);

namespace App\Http\Requests\Messaging;

use App\Actions\Messaging\SendDirectMessage;
use App\Enums\MessageChannel;
use App\Models\Message;
use App\Models\User;
use App\Sms\BroadcastAudience;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** POST /app/messages/direct: channel, the customers (uuids, tenant-scoped), the event and an optional edited text (sms.send). */
final class SendDirectMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('send', Message::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->tenantId();

        return [
            'channel' => ['required', Rule::enum(MessageChannel::class)],
            'event' => ['required', Rule::in(SendDirectMessage::EVENTS)],
            'customers' => ['required', 'array', 'min:1', 'max:'.SendDirectMessage::MAX_RECIPIENTS],
            'customers.*' => ['uuid', 'distinct', Rule::exists('customers', 'uuid')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'body' => ['nullable', 'string', 'max:'.BroadcastAudience::MAX_BODY],
            'values' => ['nullable', 'array'],
            'values.*' => ['string', 'max:100'],
        ];
    }
}
