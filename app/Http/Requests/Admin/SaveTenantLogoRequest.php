<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

/** The business logo: a raster image only (PNG, JPG, WebP; never SVG, which can carry script), at most 2 MB and 4000 px a side. */
final class SaveTenantLogoRequest extends FormRequest
{
    public const int MAX_KB = 2048;

    public const array TYPES = ['png', 'jpg', 'jpeg', 'webp'];

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('settings.manage');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'logo' => [
                'required',
                File::image()->types(self::TYPES)->max(self::MAX_KB),
                'mimetypes:image/png,image/jpeg,image/webp',
                // Decodes the file: a renamed non-image fails here whatever its name or claimed type.
                'dimensions:min_width=1,min_height=1,max_width=4000,max_height=4000',
            ],
        ];
    }
}
