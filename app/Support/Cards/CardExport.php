<?php

declare(strict_types=1);

namespace App\Support\Cards;

use App\Models\Customer;
use App\Models\MembershipCard;
use App\Support\Customers\CustomerNumber;
use LogicException;

/** Variable-data export for the card press: one row per card. */
final class CardExport
{
    /** @var list<string> */
    public const array COLUMNS = ['customer_no', 'name_en', 'name_bn', 'shop_name', 'phone', 'member_since', 'card_code'];

    /**
     * @param  iterable<MembershipCard>  $cards
     * @return list<array{customer_no: string, name_en: string, name_bn: string, shop_name: string, phone: string, member_since: string, card_code: string}>
     */
    public static function rows(iterable $cards): array
    {
        $rows = [];
        foreach ($cards as $card) {
            $customer = self::customerOf($card);
            $rows[] = [
                'customer_no' => self::customerNo($customer),
                'name_en' => $customer->name,
                'name_bn' => (string) $customer->name_bn,
                'shop_name' => (string) $customer->shop_name,
                'phone' => (string) $customer->phone,
                'member_since' => $customer->member_since?->toDateString() ?? '',
                'card_code' => $card->card_code,
            ];
        }

        return $rows;
    }

    /** @param iterable<MembershipCard> $cards */
    public static function csv(iterable $cards): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Cannot open a temporary stream.');
        }
        fputcsv($handle, self::COLUMNS, ',', '"', '');
        foreach (self::rows($cards) as $row) {
            // A cell that starts with = + - @ would run as a formula in a spreadsheet.
            fputcsv($handle, array_map(static fn (string $v): string => $v !== '' && str_contains('=+-@', $v[0]) ? "'".$v : $v, array_values($row)), ',', '"', '');
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return "\u{FEFF}".$csv;
    }

    public static function customerOf(MembershipCard $card): Customer
    {
        return $card->customer ?? throw new LogicException(sprintf('Card %d has no customer.', $card->id));
    }

    public static function customerNo(Customer $customer): string
    {
        return $customer->customer_no === null ? '' : CustomerNumber::format((string) $customer->customer_no);
    }
}
