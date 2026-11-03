<?php

declare(strict_types=1);

use App\Sms\SmsSegments;

it('counts SMS segments like the composer (GSM-7 vs UCS-2)', function (string $text, int $segments): void {
    expect(SmsSegments::count($text))->toBe($segments);
})->with([
    'empty' => ['', 0],
    'one GSM part' => [str_repeat('a', 160), 1],
    'two GSM parts' => [str_repeat('a', 161), 2],
    'extension characters count twice' => [str_repeat('€', 80), 1],
    'one UCS-2 part' => [str_repeat('ক', 70), 1],
    'two UCS-2 parts' => [str_repeat('ক', 71), 2],
]);
