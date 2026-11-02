<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Parses the DataTable query string: `page`, `per_page`, `sort` (`name` asc, `-name` desc),
 * `q` (free text) and `filter[column]=value`. Sort columns and filters are whitelisted, so the
 * request can never name an arbitrary column.
 */
final readonly class TableQuery
{
    public const array PER_PAGE_OPTIONS = [10, 25, 50, 100];

    /**
     * @param  array<string, string>  $filters  whitelisted filters that were present and non-empty
     */
    public function __construct(
        public int $page,
        public int $perPage,
        public ?string $sortColumn,
        public string $sortDirection,
        public string $search,
        public array $filters,
    ) {}

    /**
     * @param  list<string>  $sortable
     * @param  list<string>  $filterable
     * @param  string  $defaultSort  same syntax as the `sort` parameter
     */
    public static function fromRequest(
        Request $request,
        array $sortable = [],
        array $filterable = [],
        string $defaultSort = '',
        int $defaultPerPage = 25,
    ): self {
        $page = max(1, $request->integer('page', 1));

        $perPage = $request->integer('per_page', $defaultPerPage);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = $defaultPerPage;
        }

        $sort = $request->query('sort');
        $raw = is_string($sort) && $sort !== '' ? $sort : $defaultSort;
        $direction = str_starts_with($raw, '-') ? 'desc' : 'asc';
        $column = ltrim($raw, '-');
        if (! in_array($column, $sortable, true)) {
            [$column, $direction] = self::parseDefault($defaultSort, $sortable);
        }

        $search = $request->query('q');
        $search = is_string($search) ? trim($search) : '';

        $filters = [];
        $input = $request->query('filter');
        if (is_array($input)) {
            foreach ($filterable as $key) {
                $value = $input[$key] ?? null;
                if (is_string($value) && $value !== '') {
                    $filters[$key] = $value;
                }
            }
        }

        return new self($page, $perPage, $column, $direction, mb_substr($search, 0, 100), $filters);
    }

    /**
     * @param  Builder<*>  $query
     * @return Builder<*>
     */
    public function applySort(Builder $query): Builder
    {
        if ($this->sortColumn !== null) {
            $query->orderBy($this->sortColumn, $this->sortDirection === 'desc' ? 'desc' : 'asc');
        }

        return $query;
    }

    /**
     * Sorts and paginates (the page comes from this object, not from the request resolver).
     *
     * @param  Builder<*>  $query
     * @return LengthAwarePaginator<int, *>
     */
    public function paginate(Builder $query): LengthAwarePaginator
    {
        return $this->applySort($query)->paginate($this->perPage, ['*'], 'page', $this->page);
    }

    /**
     * @param  list<string>  $sortable
     * @return array{0: string|null, 1: string}
     */
    private static function parseDefault(string $defaultSort, array $sortable): array
    {
        $column = ltrim($defaultSort, '-');
        if ($column === '' || ! in_array($column, $sortable, true)) {
            return [null, 'asc'];
        }

        return [$column, str_starts_with($defaultSort, '-') ? 'desc' : 'asc'];
    }
}
