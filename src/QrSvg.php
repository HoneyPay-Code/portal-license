<?php

declare(strict_types=1);

namespace LicenseApi;

final class QrSvg
{
    public static function svg(string $data, int $scale = 4): string
    {
        require_once __DIR__.'/lib/qrcode_kazuhiko.php';
        $qr = \QRCode::getMinimumQRCode($data, QR_ERROR_CORRECT_LEVEL_M);
        $n = $qr->getModuleCount();
        $margin = 2;
        $size = ($n + 2 * $margin) * $scale;
        $rects = [];
        for ($row = 0; $row < $n; $row++) {
            for ($col = 0; $col < $n; $col++) {
                if ($qr->isDark($row, $col)) {
                    $x = ($col + $margin) * $scale;
                    $y = ($row + $margin) * $scale;
                    $rects[] = '<rect x="'.$x.'" y="'.$y.'" width="'.$scale.'" height="'.$scale.'"/>';
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 '.$size.' '.$size.'" role="img" aria-label="QR Code">'
            .'<rect width="100%" height="100%" fill="#ffffff"/>'
            .'<g fill="#0f172a">'.implode('', $rects).'</g></svg>';
    }
}
