<?php

namespace App\Services;

use App\Models\ReceivingBox;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class ReceivingBoxQrCodeService
{
    public function inventoryUrl(ReceivingBox $box): string
    {
        return route('receiving-boxes.inventory', ['qrToken' => $box->qr_token]);
    }

    public function svg(ReceivingBox $box, int $size = 320): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 4),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($this->inventoryUrl($box));
    }
}
