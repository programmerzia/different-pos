<?php

declare(strict_types=1);

namespace App\Support\Money;

use App\Exceptions\Domain\InvalidMoney;
use App\Support\Numbers\Digits;
use App\Support\Quantity\Quantity;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use JsonSerializable;

/**
 * An amount of BDT stored as integer paisa (1 taka = 100 paisa).
 *
 * Every operation that can produce a fraction of a paisa rounds half-up (ties away from zero).
 * The TypeScript twin is resources/js/engine/money.ts. Both run tests/fixtures/money/cases.json.
 */
final readonly class Money implements JsonSerializable
{
    /** Number.MAX_SAFE_INTEGER: the TypeScript twin cannot represent more, so neither side allows it. */
    public const int MAX_PAISA = 9_007_199_254_740_991;

    private function __construct(public int $paisa)
    {
        if ($paisa > self::MAX_PAISA || $paisa < -self::MAX_PAISA) {
            throw InvalidMoney::outOfRange((string) $paisa);
        }
    }

    public static function ofPaisa(int $paisa): self
    {
        return new self($paisa);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Parses user input such as "1,234.50", "৳ 1234.5" or "১২৩৪.৫০".
     * More than two decimal places is rejected, never rounded.
     */
    public static function parse(string $input): self
    {
        $decimal = self::toDecimal($input);

        if ($decimal->getScale() > 2) {
            throw InvalidMoney::tooPrecise($input);
        }

        return self::fromDecimal($decimal, $input);
    }

    /**
     * Converts a decimal string with any precision, rounding half-up to the paisa.
     * Only for trusted sources that are known to be imprecise (legacy FLOAT columns).
     */
    public static function parseRounded(string $input): self
    {
        return self::fromDecimal(self::toDecimal($input), $input);
    }

    public function plus(self $other): self
    {
        return new self($this->paisa + $other->paisa);
    }

    public function minus(self $other): self
    {
        return new self($this->paisa - $other->paisa);
    }

    public function negated(): self
    {
        return new self(-$this->paisa);
    }

    public function abs(): self
    {
        return new self(abs($this->paisa));
    }

    /** Unit price × quantity, rounded half-up to the paisa (one line). */
    public function times(Quantity $quantity): self
    {
        return new self(self::mulDivHalfUp($this->paisa, $quantity->milli, Quantity::SCALE_FACTOR));
    }

    /** A rate in basis points (1500 = 15.00 %), rounded half-up to the paisa. */
    public function percentOfBp(int $basisPoints): self
    {
        return new self(self::mulDivHalfUp($this->paisa, $basisPoints, 10_000));
    }

    /**
     * Splits this amount in proportion to the weights so the parts always sum to this amount.
     * Largest-remainder method; ties go to the lower index. Used to prorate order discounts and returns.
     *
     * @param  list<int>  $weights  non-negative, at least one positive
     * @return list<self>
     */
    public function allocate(array $weights): array
    {
        if ($weights === []) {
            throw InvalidMoney::badWeights('no weights');
        }

        $sum = 0;
        foreach ($weights as $weight) {
            if ($weight < 0) {
                throw InvalidMoney::badWeights('negative weight');
            }
            $sum += $weight;
        }

        if ($sum === 0) {
            throw InvalidMoney::badWeights('weights sum to zero');
        }

        $total = abs($this->paisa);
        $sign = $this->paisa < 0 ? -1 : 1;
        $parts = [];
        $remainders = [];
        $allocated = 0;

        foreach ($weights as $weight) {
            [$quotient, $remainder] = BigInteger::of($total)->multipliedBy($weight)->quotientAndRemainder($sum);
            $parts[] = $quotient->toInt();
            $remainders[] = $remainder->toInt();
            $allocated += $quotient->toInt();
        }

        $order = array_keys($remainders);
        usort($order, static fn (int $a, int $b): int => [$remainders[$b], $a] <=> [$remainders[$a], $b]);

        $bonus = array_fill(0, count($parts), 0);
        foreach (array_slice($order, 0, $total - $allocated) as $index) {
            $bonus[$index] = 1;
        }

        return array_map(static fn (int $part, int $extra): self => new self($sign * ($part + $extra)), $parts, $bonus);
    }

    public function isZero(): bool
    {
        return $this->paisa === 0;
    }

    public function isNegative(): bool
    {
        return $this->paisa < 0;
    }

    public function equals(self $other): bool
    {
        return $this->paisa === $other->paisa;
    }

    public function compareTo(self $other): int
    {
        return $this->paisa <=> $other->paisa;
    }

    /** "1234.50": for exports and logs, never for display. */
    public function toDecimalString(): string
    {
        return BigDecimal::ofUnscaledValue($this->paisa, 2)->__toString();
    }

    public function format(string $locale = 'en', bool $symbol = true): string
    {
        return MoneyFormatter::format($this, $locale, $symbol);
    }

    /** @return array{amount: int, formatted: string} */
    public function jsonSerialize(): array
    {
        return ['amount' => $this->paisa, 'formatted' => $this->format()];
    }

    private static function toDecimal(string $input): BigDecimal
    {
        $clean = str_replace([',', '৳', ' ', "\u{00A0}"], '', Digits::toLatin(trim($input)));

        if (preg_match('/^-?\d+(\.\d+)?$/', $clean) !== 1) {
            throw InvalidMoney::unparsable($input);
        }

        try {
            return BigDecimal::of($clean)->strippedOfTrailingZeros();
        } catch (MathException) {
            throw InvalidMoney::unparsable($input);
        }
    }

    private static function fromDecimal(BigDecimal $decimal, string $input): self
    {
        try {
            return new self($decimal->toScale(2, RoundingMode::HalfUp)->getUnscaledValue()->toInt());
        } catch (MathException) {
            throw InvalidMoney::outOfRange($input);
        }
    }

    /** round_half_up(a × b ÷ divisor) without overflow. */
    private static function mulDivHalfUp(int $a, int $b, int $divisor): int
    {
        try {
            return BigInteger::of($a)->multipliedBy($b)->dividedBy($divisor, RoundingMode::HalfUp)->toInt();
        } catch (MathException) {
            throw InvalidMoney::outOfRange($a.' × '.$b);
        }
    }
}
