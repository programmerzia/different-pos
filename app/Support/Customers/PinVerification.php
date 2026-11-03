<?php

declare(strict_types=1);

namespace App\Support\Customers;

use Carbon\CarbonInterface;

/** The outcome of one PIN check. Never carries the PIN or the hash. */
final readonly class PinVerification
{
    public const string OK = 'ok';

    public const string WRONG = 'wrong';

    public const string LOCKED = 'locked';

    public const string NOT_SET = 'not_set';

    public function __construct(
        public string $status,
        public int $attemptsLeft,
        public ?CarbonInterface $lockedUntil = null,
    ) {}

    public function ok(): bool
    {
        return $this->status === self::OK;
    }

    /** @return array{status: string, attempts_left: int, locked_until: string|null} */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'attempts_left' => $this->attemptsLeft,
            'locked_until' => $this->lockedUntil?->toIso8601String(),
        ];
    }
}
