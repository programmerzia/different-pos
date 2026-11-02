<?php

declare(strict_types=1);

namespace App\Actions\Sales\Data;

use App\Pos\Pricing\PriceCalculator;
use App\Support\Money\Money;
use InvalidArgumentException;

/** A line or order discount as the pricing engine takes it: none, percent in basis points, or a fixed amount. */
final readonly class Discount
{
    private function __construct(public string $type, public int $value) {}

    public static function none(): self
    {
        return new self(PriceCalculator::NONE, 0);
    }

    public static function percentBp(int $bp): self
    {
        if ($bp < 0 || $bp > 10_000) {
            throw new InvalidArgumentException('A percent discount must be 0-10000 bp.');
        }

        return new self(PriceCalculator::PERCENT_BP, $bp);
    }

    public static function amount(Money $amount): self
    {
        if ($amount->isNegative()) {
            throw new InvalidArgumentException('A discount amount must not be negative.');
        }

        return new self(PriceCalculator::AMOUNT, $amount->paisa);
    }

    /** @return array{type: string, value: int} */
    public function toArray(): array
    {
        return ['type' => $this->type, 'value' => $this->value];
    }
}
