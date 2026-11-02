<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePreferencesRequest extends FormRequest
{
    public const array SCHEMES = ['teal', 'indigo', 'violet', 'rose', 'ocean', 'graphite'];

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('updatePreferences', $user);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'theme' => ['sometimes', Rule::in(['light', 'dark', 'system'])],
            'scheme' => ['sometimes', Rule::in(self::SCHEMES)],
            'density' => ['sometimes', Rule::in(['comfortable', 'compact'])],
            'locale' => ['sometimes', Rule::in(['en', 'bn'])],
            'bn_digits' => ['sometimes', 'boolean'],
        ];
    }
}
