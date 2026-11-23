<?php

namespace App;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class DocumentVerificationQr
{
    public function dataUrl(string $url): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(240, 24), new SvgImageBackEnd));
        $svg = $writer->writeString($url, 'UTF-8', ErrorCorrectionLevel::H());
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
