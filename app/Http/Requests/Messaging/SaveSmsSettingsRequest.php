<?php

declare(strict_types=1);

namespace App\Http\Requests\Messaging;

use App\Models\Message;
use App\Models\User;
use App\Sms\SmsSettings;
use App\Sms\Templates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveSmsSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('configure', Message::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $rules = [
            'driver' => ['required', Rule::in(SmsSettings::DRIVERS)],
            'sender_id' => ['nullable', 'string', 'max:40'],
            'sid' => ['nullable', 'string', 'max:60'],
            'base_url' => ['nullable', 'url:https', 'max:200'],
            'api_token' => ['nullable', 'string', 'max:300'],
            'clear_token' => ['nullable', 'boolean'],
            'cost_per_segment' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ];
        foreach (Templates::keys() as $key) {
            $rules["templates.$key.enabled"] = ['required', 'boolean'];
            $rules["templates.$key.body"] = ['required', 'string', 'max:480'];
        }

        return $rules;
    }
}
