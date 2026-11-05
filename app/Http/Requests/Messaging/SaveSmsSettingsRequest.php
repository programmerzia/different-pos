<?php

declare(strict_types=1);

namespace App\Http\Requests\Messaging;

use App\Models\Message;
use App\Models\User;
use App\Sms\SmsSettings;
use App\Sms\Templates;
use App\WhatsApp\WhatsAppSettings;
use Closure;
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
            'base_url' => [Rule::requiredIf(fn (): bool => $this->input('driver') === 'onecodesoft'), 'nullable', 'url:https', 'max:200'],
            'api_token' => ['nullable', 'string', 'max:300'],
            'clear_token' => ['nullable', 'boolean'],
            'cost_per_segment' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'whatsapp' => ['nullable', 'array'],
            'whatsapp.driver' => ['required_with:whatsapp', Rule::in(WhatsAppSettings::DRIVERS)],
            'whatsapp.phone_number_id' => ['nullable', 'string', 'max:40', 'regex:/^[0-9]*$/'],
            'whatsapp.access_token' => ['nullable', 'string', 'max:600'],
            'whatsapp.clear_token' => ['nullable', 'boolean'],
            'whatsapp.template_name' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9_]*$/'],
            'whatsapp.template_language' => ['nullable', 'string', 'max:10', 'regex:/^[a-zA-Z_]*$/'],
        ];
        foreach (Templates::keys() as $key) {
            $rules["templates.$key.enabled"] = ['required', 'boolean'];
            $rules["templates.$key.body"] = ['required', 'string', 'max:480', function (string $attribute, mixed $value, Closure $fail) use ($key): void {
                $unfilled = is_string($value) ? Templates::unfilled($value, [...Templates::ALWAYS_FILLED, ...Templates::PLACEHOLDERS[$key]]) : [];
                if ($unfilled !== []) {
                    $fail((string) __('sms.template_unknown_placeholder', ['names' => '{'.implode('}, {', $unfilled).'}']));
                }
            }];
        }

        return $rules;
    }
}
