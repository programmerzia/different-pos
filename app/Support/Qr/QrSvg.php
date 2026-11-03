<?php

declare(strict_types=1);

namespace App\Support\Qr;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Inline SVG QR codes for receipts and membership cards (chillerlan/php-qrcode, 04_TRD §2). The SVG
 * has no XML prolog or fixed size: the surrounding CSS sizes it.
 */
final class QrSvg
{
    /** @param  int  $quietZone  modules of light border around the code */
    public static function render(string $data, int $quietZone = 1, int $ecc = EccLevel::M): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => false,
            'svgAddXmlHeader' => false,
            'eccLevel' => $ecc,
            'addQuietzone' => $quietZone > 0,
            'quietzoneSize' => max(0, $quietZone),
            'drawLightModules' => false,
            'connectPaths' => true,
            'markupDark' => '#000',
        ]);

        return (string) (new QRCode($options))->render($data);
    }
}
