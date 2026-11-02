<?php

declare(strict_types=1);

namespace App\Support\Integrity;

/** The outcome of one check: how many rows broke it and up to ten of their ids. */
final readonly class CheckResult
{
    /** @param  list<int|string>  $sample */
    public function __construct(
        public string $name,
        public CheckStatus $status,
        public int $count,
        public array $sample,
    ) {}

    /** @return array{name: string, status: string, count: int, sample: list<int|string>} */
    public function toArray(): array
    {
        return ['name' => $this->name, 'status' => $this->status->value, 'count' => $this->count, 'sample' => $this->sample];
    }
}
