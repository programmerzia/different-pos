<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Support\Http\TableQuery;
use Illuminate\Http\Request;

function tq(array $query, string $defaultSort = '-name'): TableQuery
{
    return TableQuery::fromRequest(Request::create('/x', 'GET', $query), ['name', 'total'], ['status'], $defaultSort);
}

it('parses page, per_page, sort, search and whitelisted filters', function (): void {
    $q = tq(['page' => '3', 'per_page' => '50', 'sort' => 'total', 'q' => '  shirt ', 'filter' => ['status' => 'paid', 'evil' => 'x', 'empty' => '']]);

    expect($q->page)->toBe(3)
        ->and($q->perPage)->toBe(50)
        ->and($q->sortColumn)->toBe('total')
        ->and($q->sortDirection)->toBe('asc')
        ->and($q->search)->toBe('shirt')
        ->and($q->filters)->toBe(['status' => 'paid']);
});

it('falls back to defaults for junk and never accepts a column outside the whitelist', function (): void {
    $q = tq(['page' => '-4', 'per_page' => '9999', 'sort' => 'password; drop table']);

    expect($q->page)->toBe(1)
        ->and($q->perPage)->toBe(25)
        ->and($q->sortColumn)->toBe('name')
        ->and($q->sortDirection)->toBe('desc');
    expect(tq(['sort' => '-secret'], '')->sortColumn)->toBeNull();
});

it('sorts and paginates a query', function (): void {
    Tenant::factory()->count(3)->sequence(['name' => 'B'], ['name' => 'A'], ['name' => 'C'])->create();
    $page = tq(['sort' => '-name', 'per_page' => '10'])->paginate(Tenant::query());

    expect($page->pluck('name')->all())->toBe(['C', 'B', 'A']);
});
