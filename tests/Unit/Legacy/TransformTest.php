<?php

declare(strict_types=1);

use App\Legacy\Transform\LegacyDate;
use App\Legacy\Transform\Phone;
use App\Legacy\Transform\Text;

it('normalises Bangladeshi mobile numbers to E.164', function (?string $input, ?string $expected): void {
    expect(Phone::toE164($input))->toBe($expected);
})->with([
    ['01711000001', '+8801711000001'],
    ['+8801711000001', '+8801711000001'],
    ['8801711000001', '+8801711000001'],
    ['01711-000 001', '+8801711000001'],
    ['০১৭১১০০০০০১', '+8801711000001'],
    ['1711000001', '+8801711000001'],
    ['012227222', null],          // too short
    ['018512345678', null],       // 12 digits
    ['01211000001', null],        // no such operator prefix
    ['01717', null],
    ['', null],
    [null, null],
]);

it('decodes UTF-8 bytes and falls back to Windows-1252', function (): void {
    expect(Text::decode("  \xE0\xA6\xA2\xE0\xA6\xBE\xE0\xA6\x95\xE0\xA6\xBE  "))->toBe(['ঢাকা', false])
        ->and(Text::decode("Caf\xE9"))->toBe(['Café', true])
        ->and(Text::decode('   '))->toBe([null, false])
        ->and(Text::decode(null))->toBe([null, false]);
});

it('treats legacy placeholders as empty', function (): void {
    expect(Text::orNull('No Description'))->toBeNull()
        ->and(Text::orNull('null@gmail.com'))->toBeNull()
        ->and(Text::orNull('NA'))->toBeNull()
        ->and(Text::orNull('Mirpur'))->toBe('Mirpur')
        ->and(Text::email('null@gmail.com'))->toBeNull()
        ->and(Text::email('not-an-email'))->toBeNull()
        ->and(Text::email('A@Example.com'))->toBe('a@example.com')
        ->and(Text::key('  Track   PANT '))->toBe('track pant');
});

it('reads legacy dates as Asia/Dhaka and rejects zero dates', function (): void {
    expect(LegacyDate::utc('2024-05-12 00:00:00'))->toBe('2024-05-11 18:00:00')
        ->and(LegacyDate::date('1990-02-03'))->toBe('1990-02-03')
        ->and(LegacyDate::date('0000-00-00'))->toBeNull()
        ->and(LegacyDate::utc('0000-00-00 00:00:00'))->toBeNull()
        ->and(LegacyDate::utc('garbage'))->toBeNull()
        ->and(LegacyDate::utc(null))->toBeNull();
});
