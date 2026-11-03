<?php

declare(strict_types=1);

namespace App\Http\Requests\Messaging;

use App\Models\Message;
use App\Models\User;
use App\Sms\SendAdHocSms;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** POST /app/messages/send: the composer's audience and text (settings.manage). */
final class SendAdHocSmsRequest extends FormRequest
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
            'target' => ['required', Rule::in(SendAdHocSms::TARGETS)],
            'customer' => ['nullable', 'required_if:target,customer', 'uuid', Rule::exists('customers', 'uuid')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'group_id' => ['nullable', 'required_if:target,group', 'integer', Rule::exists('customer_groups', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'body' => ['required', 'string', 'max:'.SendAdHocSms::MAX_BODY],
        ];
    }
}
