<?php

declare(strict_types=1);

namespace App\Support\Cards;

use App\Models\MembershipCard;
use App\Support\Tenancy\TenantContext;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\Eloquent\Collection;
use Mpdf\Mpdf;
use Picqer\Barcode\Types\TypeCode128;
use RuntimeException;

/**
 * Membership card artwork (the baseline mockup's teal card, resources/css/membership-card.css): CR80 (85.6 x 54 mm) front and back pages,
 * the print-shop bleed variant (91.6 x 60 mm, optional crop marks on a 101.6 x 70 mm page), and the
 * A4 10-up sheet (2 x 5, fronts then mirrored backs per sheet, crop marks in the margins).
 * html()/a4() are the source of truth; pdf() needs mPDF (deferred until ext-gd exists).
 */
final readonly class CardRenderer
{
    public const int PER_SHEET = 10;

    public function __construct(private TenantContext $context) {}

    /** @param iterable<MembershipCard> $cards */
    public function html(iterable $cards, bool $bleed = false, bool $cropMarks = false): string
    {
        return view('cards.cr80', [
            ...$this->shared(),
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
        if (! class_exists(Mpdf::class)) {
            throw new RuntimeException('PDF rendering needs mPDF (mpdf/mpdf), which needs ext-gd. It is not installed yet; use html() for now.');
        }

        $size = $bleed ? [91.6, 60.0] : [85.6, 54.0];
        $mpdf = new Mpdf(['format' => $size, 'margin_left' => 0, 'margin_right' => 0, 'margin_top' => 0, 'margin_bottom' => 0]);
        $mpdf->WriteHTML($this->html($cards, $bleed));

        return $mpdf->Output('', 'S');
    }

    /**
     * Brand data the artwork shows, plus the shared stylesheet (the same file the studio uses).
     *
     * @return array{css: string, tenantName: string, tenantLogo: string|null, phone: string|null, branchName: string|null}
     */
    private function shared(): array
    {
        $brand = (new CardBrand($this->context))->toArray();
        $css = file_get_contents(resource_path('css/membership-card.css'));

        return [
            'css' => $css === false ? '' : $css,
            'tenantName' => $brand['name'],
            'tenantLogo' => $brand['logo_url'],
            'phone' => $brand['phone'],
            'branchName' => $brand['branch'],
        ];
    }

    /** Same rule as tierOf() in components/cards/card-face.ts. */
    public static function tier(?string $group): string
    {
        return str_contains(mb_strtoupper((string) $group), 'VIP') ? 'vip' : 'gold';
    }

    /**
     * @param  iterable<MembershipCard>  $cards
     * @return list<array{customer_no: string, name: string, name_bn: string|null, shop_name: string|null, tier: string, since: string, cid: string, issue: string, card_code: string, barcode: string, qr: string}>
     */
    private function items(iterable $cards): array
    {
        $cards = is_array($cards) ? $cards : iterator_to_array($cards, false);
        (new Collection(array_map(CardExport::customerOf(...), $cards)))->loadMissing('customerGroup');
        $items = [];
        foreach ($cards as $card) {
            $customer = CardExport::customerOf($card);
            $raw = (string) $customer->customer_no;
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
                'qr' => self::qr($card->card_code),
            ];
        }

        return $items;
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
        $svg = (new Writer(new ImageRenderer(new RendererStyle(96, 0), new SvgImageBackEnd)))->writeString($payload, ecLevel: ErrorCorrectionLevel::M());

        return (string) preg_replace('/^<\?xml[^>]*>\s*/', '', $svg);
    }
}
