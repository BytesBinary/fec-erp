<?php

namespace App\Services\Clearance;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Inline SVG QR codes (no GD needed, docs/DECISIONS.md D-012).
 */
class QrCodeService
{
    public function svgDataUri(string $content): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => true,
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
            'svgViewBoxSize' => 200,
        ]);

        return (new QRCode($options))->render($content);
    }
}
