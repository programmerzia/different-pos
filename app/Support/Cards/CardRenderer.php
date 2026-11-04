<?php

declare(strict_types=1);

namespace App\Support\Cards;

use App\Models\MembershipCard;
use App\Support\Pdf\PdfRenderer;
use App\Support\Qr\QrSvg;
use App\Support\Tenancy\TenantContext;
use chillerlan\QRCode\Common\EccLevel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Mpdf\Output\Destination;
use Picqer\Barcode\Types\TypeCode128;

/**
 * Membership card artwork (the black and gold brand card, resources/css/membership-card.css): CR80 (85.6 x 54 mm) front and back pages,
 * the print-shop bleed variant (91.6 x 60 mm, optional crop marks on a 101.6 x 70 mm page), and the
 * A4 10-up sheet (2 x 5, fronts then mirrored backs per sheet, crop marks in the margins).
 * html()/a4() are the source of truth; pdf() renders them through the shared PdfRenderer (mPDF, ADR 0009).
 */
final readonly class CardRenderer
{
    public const int PER_SHEET = 10;

    public function __construct(private TenantContext $context) {}

    /** @param iterable<MembershipCard> $cards */
    public function html(iterable $cards, bool $bleed = false, bool $cropMarks = false, bool $localLogo = false): string
    {
        return view('cards.cr80', [
            ...$this->shared($localLogo),
            'cards' => $this->items($cards),
            'bleed' => $bleed,
            'crop' => $bleed && $cropMarks,
        ])->render();
    }

    /**
     * A4 portrait sheets of ten cards: each sheet's fronts, then the same sheet's backs with the two
     * columns swapped, so a long-edge duplex print (or a flipped sheet) lines up.
     *
     * @param  iterable<MembershipCard>  $cards
     */
    public function a4(iterable $cards, bool $cropMarks = true): string
    {
        $sheets = [];
        foreach (array_chunk($this->items($cards), self::PER_SHEET) as $chunk) {
            $back = [];
            foreach (array_chunk($chunk, 2) as $row) {
                $back[] = [$row[1] ?? null, $row[0]];
            }
            $sheets[] = ['front' => array_chunk($chunk, 2), 'back' => $back];
        }

        return view('cards.a4', [...$this->shared(), 'sheets' => $sheets, 'crop' => $cropMarks])->render();
    }

    /** @param iterable<MembershipCard> $cards */
    public function pdf(iterable $cards, bool $bleed = false): string
    {
        // The shared engine (fonts, temp dir, Bangla shaping); only the page size is the card's own.
        $mpdf = app(PdfRenderer::class)->mpdf($bleed ? [91.6, 60.0] : [85.6, 54.0], PdfRenderer::PORTRAIT, 0);
        $mpdf->SetHTMLHeader('');
        $mpdf->SetHTMLFooter('');
        $mpdf->WriteHTML($this->html($cards, $bleed, localLogo: true));

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * Brand data the artwork shows, plus the shared stylesheet (the same file the studio uses).
     *
     * @return array{css: string, tenantName: string, tenantLogo: string|null, phone: string|null, branchName: string|null}
     */
    private function shared(bool $localLogo = false): array
    {
        $brand = (new CardBrand($this->context))->toArray();
        $css = file_get_contents(resource_path('css/membership-card.css'));

        return [
            'css' => $css === false ? '' : $css,
            'tenantName' => $brand['name'],
            'tenantLogo' => $localLogo ? $this->localLogo() : $brand['logo_url'],
            'phone' => $brand['phone'],
            'branchName' => $brand['branch'],
        ];
    }

    /** Absolute file path of the tenant logo, for renderers that are not a browser (mPDF cannot fetch a host-relative URL). */
    private function localLogo(): ?string
    {
        $path = $this->context->tenant()->logoPath();
        if ($path === null || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->path($path);
    }

    /** Same rule as tierOf() in components/cards/card-face.ts. */
    public static function tier(?string $group): string
    {
        return str_contains(mb_strtoupper((string) $group), 'VIP') ? 'vip' : 'gold';
    }

    /**
     * @param  iterable<MembershipCard>  $cards
     * @return list<array{customer_no: string, name: string, name_bn: string|null, shop_name: string|null, tier: string, since: string, cid: string, issue: string, card_code: string, barcode: string, qr_payload: string, qr: string}>
     */
    private function items(iterable $cards): array
    {
        $cards = is_array($cards) ? $cards : iterator_to_array($cards, false);
        (new Collection(array_map(CardExport::customerOf(...), $cards)))->loadMissing('customerGroup');
        $items = [];
        foreach ($cards as $card) {
            $customer = CardExport::customerOf($card);
            $raw = (string) $customer->customer_no;
            $payload = self::qrPayload($customer->name, $customer->shop_name, $raw);
            $items[] = [
                'customer_no' => CardExport::customerNo($customer),
                'name' => $customer->name,
                'name_bn' => $customer->name_bn,
                'shop_name' => $customer->shop_name,
                'tier' => self::tier($customer->customerGroup?->name),
                'since' => $customer->member_since?->format('m/y') ?? '--/--',
                'cid' => substr($raw, -3),
                'issue' => str_pad((string) $card->issue_no, 2, '0', STR_PAD_LEFT),
                'card_code' => $card->card_code,
                'barcode' => self::barcode($card->card_code),
                'qr_payload' => $payload,
                'qr' => self::qr($payload),
            ];
        }

        return $items;
    }

    /**
     * The QR payload on the back: `name|shop|number` (member name, the member's shop name or empty, the
     * 8-digit card number). Same rule as qrPayload() in components/cards/card-face.ts; the barcode
     * keeps the card code only.
     */
    public static function qrPayload(string $name, ?string $shop, string $number): string
    {
        $field = static fn (?string $value): string => trim((string) preg_replace('/[|\r\n]+/u', ' ', (string) $value));

        return implode('|', [$field($name), $field($shop), (string) preg_replace('/\D/', '', $number)]);
    }

    /** Code128 bars in the same SVG shape the studio draws (viewBox in modules, stretched to the box). */
    private static function barcode(string $payload): string
    {
        $barcode = (new TypeCode128)->getBarcode($payload);
        $x = 0;
        $rects = '';
        foreach ($barcode->getBars() as $bar) {
            if ($bar->isBar()) {
                $rects .= sprintf('<rect x="%d" y="0" width="%d" height="24"/>', $x, $bar->getWidth());
            }
            $x += $bar->getWidth();
        }

        return sprintf('<svg viewBox="0 0 %d 24" preserveAspectRatio="none" aria-hidden="true"><rect width="100%%" height="24" fill="var(--mc-white)"/><g fill="var(--mc-black)">%s</g></svg>', max($x, 1), $rects);
    }

    private static function qr(string $payload): string
    {
        return QrSvg::render($payload, quietZone: 0, ecc: EccLevel::M);
    }
}
