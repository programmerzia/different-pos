<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

arch('app uses strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debugging calls in app')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die', 'exit', 'print_r', 'var_export', 'ddd', 'td'])
    ->not->toBeUsedIn('App');

arch('controllers do not touch the DB facade')
    ->expect(DB::class)
    ->not->toBeUsedIn('App\Http\Controllers');

arch('actions are final and expose handle()')
    ->expect('App\Actions')
    ->classes()
    ->toBeFinal()
    ->toHaveMethod('handle')
    ->ignoring('App\Actions\Fortify');

arch('actions do not depend on the HTTP layer')
    ->expect('App\Actions')
    ->not->toUse('App\Http');
