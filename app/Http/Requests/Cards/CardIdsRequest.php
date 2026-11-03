<?php

declare(strict_types=1);

namespace App\Http\Requests\Cards;

use App\Models\MembershipCard;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What to print or export: `ids` = membership card ids and/or `customers` = customer uuids (a
 * customer without an active card gets one issued on demand), each a comma list in a query string
 * or an array in a body; `layout` cr80 | bleed | a4; `crop` adds crop marks (bleed and a4).
 */
final class CardIdsRequest extends FormRequest
{
    public const int MAX = 500;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('create', MembershipCard::class);
    }

    protected function prepareForValidation(): void
    {
        foreach (['ids', 'customers'] as $key) {
            $value = $this->input($key);
            if (is_string($value)) {
                $this->merge([$key => array_values(array_filter(array_map(trim(...), explode(',', $value)), static fn (string $v): bool => $v !== ''))]);
            }
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'ids' => ['required_without:customers', 'array', 'max:'.self::MAX],
            'ids.*' => ['integer', 'min:1'],
            'customers' => ['required_without:ids', 'array', 'max:'.self::MAX],
            // No exists rule: an unknown or foreign uuid is simply not found (404 when nothing is left).
            'customers.*' => ['uuid'],
            'layout' => ['sometimes', Rule::in(['cr80', 'bleed', 'a4'])],
            'crop' => ['sometimes', 'boolean'],
        ];
    }

    /** @return list<int> */
    public function cardIds(): array
    {
        /** @var list<int|string> $ids */
        $ids = $this->validated('ids', []);

        return array_values(array_unique(array_map(intval(...), $ids)));
    }

    /** @return list<string> */
    public function customerUuids(): array
    {
        /** @var list<string> $uuids */
        $uuids = $this->validated('customers', []);

        return array_values(array_unique($uuids));
    }

    public function layout(): string
    {
        $layout = $this->validated('layout', 'cr80');

        return is_string($layout) ? $layout : 'cr80';
    }

    public function bleed(): bool
    {
        return $this->layout() === 'bleed';
    }

    public function a4(): bool
    {
        return $this->layout() === 'a4';
    }

    public function cropMarks(): bool
    {
        return $this->boolean('crop');
    }
}
