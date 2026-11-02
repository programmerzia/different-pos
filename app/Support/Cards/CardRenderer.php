<?php

declare(strict_types=1);

namespace App\Support\Cards;

use App\Models\MembershipCard;
use App\Support\Tenancy\TenantContext;
use Mpdf\Mpdf;
use Picqer\Barcode\BarcodeGeneratorSVG;
use RuntimeException;

/**
 * Membership card artwork: CR80 (85.6 x 54 mm) front and back pages, or the print-shop bleed
 * variant (91.6 x 60 mm). html() is the source of truth; pdf() needs mPDF (deferred until ext-gd exists).
 */
final readonly class CardRenderer
{
    public function __construct(private TenantContext $context) {}

    /** @param iterable<MembershipCard> $cards */
    public function html(iterable $cards, bool $bleed = false): string
    {
        $generator = new BarcodeGeneratorSVG;
        $items = [];
        foreach ($cards as $card) {
            $customer = CardExport::customerOf($card);
            $items[] = [
                'customer_no' => CardExport::customerNo($customer),
                'name' => $customer->name,
                'name_bn' => $customer->name_bn,
                'shop_name' => $customer->shop_name,
                'member_since' => $customer->member_since?->format('M Y'),
                'card_code' => $card->card_code,
                'barcode' => $generator->getBarcode($card->card_code, BarcodeGeneratorSVG::TYPE_CODE_128, 1.4, 40),
            ];
        }

        return view('cards.cr80', [
            'cards' => $items,
            'bleed' => $bleed,
            'tenantName' => $this->context->tenant()->name,
        ])->render();
    }

    /** @param iterable<MembershipCard> $cards */
    public function pdf(iterable $cards, bool $bleed = false): string
    {
        if (! class_exists(Mpdf::class)) {
            throw new RuntimeException('PDF rendering needs mPDF (mpdf/mpdf), which needs ext-gd. It is not installed yet; use html() for now.');
        }

        $size = $bleed ? [91.6, 60.0] : [85.6, 54.0];
        $mpdf = new Mpdf(['format' => $size, 'margin_left' => 0, 'margin_right' => 0, 'margin_top' => 0, 'margin_bottom' => 0]);
        $mpdf->WriteHTML($this->html($cards, $bleed));

        return $mpdf->Output('', 'S');
    }
}
