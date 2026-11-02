<?php

declare(strict_types=1);

namespace App\Http\Requests\Cards;

use App\Models\MembershipCard;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** `ids` = membership card ids (comma list in a query string, or an array in a body) and a print layout. */
final class CardIdsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('create', MembershipCard::class);
    }

    protected function prepareForValidation(): void
    {
        $ids = $this->input('ids');
        if (is_string($ids)) {
            $this->merge(['ids' => array_values(array_filter(array_map(trim(...), explode(',', $ids)), static fn (string $v): bool => $v !== ''))]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'min:1'],
            'layout' => ['sometimes', Rule::in(['cr80', 'bleed'])],
        ];
    }

    /** @return list<int> */
    public function cardIds(): array
    {
        /** @var list<int|string> $ids */
        $ids = $this->validated('ids');

        return array_values(array_unique(array_map(intval(...), $ids)));
    }

    public function bleed(): bool
    {
        return $this->validated('layout', 'cr80') === 'bleed';
    }
}
