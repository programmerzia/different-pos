<?php

declare(strict_types=1);

namespace App\Http\Requests\Messaging;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class SendTestWhatsAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('configure', Message::class) && $user->can('send', Message::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['phone' => ['required', 'string', 'max:30']];
    }
}
