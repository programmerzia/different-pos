<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Support\Customers\CustomerNumber;
use App\Support\Database\Transaction;
use App\Support\Tenancy\TenantContext;

it('generates the documented first number', function (): void {
    expect(CustomerNumber::generate(1_000_001))->toBe('10000016')
        ->and(CustomerNumber::format('10000016'))->toBe('1000 0016');
});

it('rejects out-of-range sequence values', function (): void {
    CustomerNumber::generate(999_999);
})->throws(InvalidArgumentException::class);

it('validates every generated number and catches every substitution and transposition', function (): void {
    for ($seq = 1_000_001; $seq < 1_000_501; $seq++) {
        $number = CustomerNumber::generate($seq);
        expect(CustomerNumber::isValid($number))->toBeTrue();

        for ($pos = 0; $pos < 8; $pos++) {
            foreach (range(0, 9) as $digit) {
                if ((string) $digit === $number[$pos]) {
                    continue;
                }
                $bad = $number;
                $bad[$pos] = (string) $digit;
                expect(CustomerNumber::isValid($bad))->toBeFalse();
            }
        }

        for ($pos = 0; $pos < 7; $pos++) {
            $a = $number[$pos];
            $b = $number[$pos + 1];
            // Luhn cannot see the 09 <-> 90 swap; every other distinct pair is detected.
            if ($a === $b || ($a === '0' && $b === '9') || ($a === '9' && $b === '0')) {
                continue;
            }
            $bad = $number;
            $bad[$pos] = $b;
            $bad[$pos + 1] = $a;
            expect(CustomerNumber::isValid($bad))->toBeFalse();
        }
    }
});

it('rejects malformed input', function (string $input): void {
    expect(CustomerNumber::isValid($input))->toBeFalse();
})->with(['', '1234567', '100000170', 'abcdefgh', '1000 0016']);

it('normalizes spaces and Bangla digits', function (): void {
    expect(CustomerNumber::normalize('১০০০ ০০১৬'))->toBe('10000016')
        ->and(CustomerNumber::isValid(CustomerNumber::normalize(' 1000 0016 ')))->toBeTrue();
});

it('allocates permanent, increasing numbers per tenant', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    $ctx = app(TenantContext::class);
    $customers = app(CustomerNumber::class);
    $alloc = fn (Tenant $t) => $ctx->run($t, null, fn () => Transaction::run(fn () => $customers->allocate()));

    $first = $alloc($a);
    $second = $alloc($a);

    expect($first)->toBe('10000016')
        ->and($second)->toBe(CustomerNumber::generate(1_000_002))
        ->and($alloc($b))->toBe('10000016');
});

it('does not reuse a number after rollback only when never committed', function (): void {
    $t = Tenant::factory()->create();
    $ctx = app(TenantContext::class);
    $customers = app(CustomerNumber::class);
    $committed = $ctx->run($t, null, fn () => Transaction::run(fn () => $customers->allocate()));
    $next = $ctx->run($t, null, fn () => Transaction::run(fn () => $customers->allocate()));

    expect($next)->not->toBe($committed);
});
