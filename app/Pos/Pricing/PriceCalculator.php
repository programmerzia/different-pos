<?php

declare(strict_types=1);

namespace App\Pos\Pricing;

use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Pure cart pricing on integer paisa and milli-quantities. No floats, no database.
 * The TypeScript twin is resources/js/engine/pricing.ts. Both run tests/fixtures/pricing/cases.json.
 *
 * Order of operations (each step rounds half-up to the paisa):
 *   gross = unit_price x qty
 *   line discount (percent_bp of gross, or a fixed amount capped at gross)
 *   group discount (bp of the amount left after the line discount, per line)
 *   order discount (percent_bp or fixed amount of the remaining total, capped), prorated across lines
 *     with the largest-remainder method using the remaining line amounts as weights
 *   VAT: exclusive adds net x rate; inclusive backs out price x rate / (10000 + rate)
 *   cash rounding of the grand total to the nearest step (ties away from zero)
 *
 * In inclusive mode `gross` is the price as entered (VAT included) and `net` excludes VAT.
 * Discount caps are authorisation and live in the Action, not here: the effective discount basis points
 * are exposed so the Action can compare them with the role cap.
 *
 * @phpstan-type Discount array{type: string, value: int}
 * @phpstan-type Line array{unit_price: int, qty_milli: int, line_discount: Discount, tax_rate_bp: int, tax_inclusive: bool}
 * @phpstan-type Tender array{type: string, amount: int}
 * @phpstan-type Input array{lines: list<Line>, order_discount: Discount, group_discount_bp: int, rounding_step: int, tenders: list<Tender>}
 * @phpstan-type LineTotals array{gross: int, line_discount: int, group_discount: int, order_discount: int, discount: int, net: int, tax: int, total: int, line_discount_bp: int, effective_discount_bp: int}
 * @phpstan-type OrderTotals array{subtotal: int, line_discount_total: int, group_discount: int, order_discount: int, discount_total: int, taxable: int, tax: int, rounding: int, grand_total: int, effective_discount_bp: int, tendered: int, balance: int, change: int, due: int}
 * @phpstan-type CartTotals array{lines: list<LineTotals>, order: OrderTotals}
 */
final class PriceCalculator
{
    public const string NONE = 'none';

    public const string PERCENT_BP = 'percent_bp';

    public const string AMOUNT = 'amount';

    /**
     * @param  Input  $input
     * @return CartTotals
     */
    public static function calculateCart(array $input): array
    {
        $groupBp = $input['group_discount_bp'];
        $step = $input['rounding_step'];
        if ($groupBp < 0 || $groupBp > 10_000 || $step < 0) {
            throw new InvalidArgumentException('Group discount must be 0-10000 bp and the rounding step must not be negative.');
        }

        $gross = [];
        $lineDiscount = [];
        $afterGroup = [];
        $groupDiscount = [];

        foreach ($input['lines'] as $i => $line) {
            if ($line['unit_price'] < 0 || $line['qty_milli'] < 0 || $line['tax_rate_bp'] < 0) {
                throw new InvalidArgumentException('Line '.$i.' has a negative price, quantity or tax rate.');
            }
            $g = Money::ofPaisa($line['unit_price'])->times(Quantity::ofMilli($line['qty_milli']))->paisa;
            $ld = self::discountOf($line['line_discount'], $g);
            $gd = Money::ofPaisa($g - $ld)->percentOfBp($groupBp)->paisa;
            $gross[$i] = $g;
            $lineDiscount[$i] = $ld;
            $groupDiscount[$i] = $gd;
            $afterGroup[$i] = $g - $ld - $gd;
        }

        $remaining = array_sum($afterGroup);
        $orderDiscountTotal = self::discountOf($input['order_discount'], $remaining);
        $orderShare = array_fill(0, count($afterGroup), 0);
        if ($orderDiscountTotal > 0) {
            $parts = Money::ofPaisa($orderDiscountTotal)->allocate(array_values($afterGroup));
            foreach ($parts as $i => $part) {
                $orderShare[$i] = $part->paisa;
            }
        }

        $lines = [];
        $subtotal = $lineDiscountSum = $groupSum = $taxable = $tax = $linesTotal = 0;

        foreach ($input['lines'] as $i => $line) {
            $priced = $afterGroup[$i] - $orderShare[$i];
            $rate = $line['tax_rate_bp'];
            if ($line['tax_inclusive']) {
                $lineTax = self::mulDivHalfUp($priced, $rate, 10_000 + $rate);
                $net = $priced - $lineTax;
            } else {
                $net = $priced;
                $lineTax = Money::ofPaisa($priced)->percentOfBp($rate)->paisa;
            }
            $discount = $lineDiscount[$i] + $groupDiscount[$i] + $orderShare[$i];

            $lines[] = [
                'gross' => $gross[$i],
                'line_discount' => $lineDiscount[$i],
                'group_discount' => $groupDiscount[$i],
                'order_discount' => $orderShare[$i],
                'discount' => $discount,
                'net' => $net,
                'tax' => $lineTax,
                'total' => $net + $lineTax,
                'line_discount_bp' => self::bp($lineDiscount[$i], $gross[$i]),
                'effective_discount_bp' => self::bp($discount, $gross[$i]),
            ];

            $subtotal += $gross[$i];
            $lineDiscountSum += $lineDiscount[$i];
            $groupSum += $groupDiscount[$i];
            $taxable += $net;
            $tax += $lineTax;
            $linesTotal += $net + $lineTax;
        }

        $rounding = 0;
        if ($step > 0) {
            $rounding = self::mulDivHalfUp($linesTotal, 1, $step) * $step - $linesTotal;
        }
        $grandTotal = $linesTotal + $rounding;

        $tendered = 0;
        foreach ($input['tenders'] as $tender) {
            $tendered += $tender['amount'];
        }
        $balance = $grandTotal - $tendered;
        $discountTotal = $lineDiscountSum + $groupSum + $orderDiscountTotal;

        return [
            'lines' => $lines,
            'order' => [
                'subtotal' => $subtotal,
                'line_discount_total' => $lineDiscountSum,
                'group_discount' => $groupSum,
                'order_discount' => $orderDiscountTotal,
                'discount_total' => $discountTotal,
                'taxable' => $taxable,
                'tax' => $tax,
                'rounding' => $rounding,
                'grand_total' => $grandTotal,
                'effective_discount_bp' => self::bp($discountTotal, $subtotal),
                'tendered' => $tendered,
                'balance' => $balance,
                'change' => max(0, -$balance),
                'due' => max(0, $balance),
            ],
        ];
    }

    /** @param  Discount  $discount */
    private static function discountOf(array $discount, int $base): int
    {
        if ($discount['value'] < 0) {
            throw new InvalidArgumentException('A discount value must not be negative.');
        }

        return match ($discount['type']) {
            self::NONE => 0,
            self::PERCENT_BP => Money::ofPaisa($base)->percentOfBp(min($discount['value'], 10_000))->paisa,
            self::AMOUNT => min($discount['value'], $base),
            default => throw new InvalidArgumentException('Unknown discount type "'.$discount['type'].'".'),
        };
    }

    /** Effective discount in basis points of the gross amount, rounded half-up (0 when the gross is 0). */
    private static function bp(int $discount, int $gross): int
    {
        return $gross === 0 ? 0 : self::mulDivHalfUp($discount, 10_000, $gross);
    }

    /** round_half_up(a x b / divisor), ties away from zero, without overflow. */
    private static function mulDivHalfUp(int $a, int $b, int $divisor): int
    {
        return BigInteger::of($a)->multipliedBy($b)->dividedBy($divisor, RoundingMode::HalfUp)->toInt();
    }
}
