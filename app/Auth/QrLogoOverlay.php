<?php

declare(strict_types=1);

namespace OneId\App\Auth;

use RuntimeException;

final class QrLogoOverlay
{
    public static function apply(string $qrPng, string $logoPath, float $logoWidthRatio = 0.28): string
    {
        if (!function_exists('imagecreatefromstring')
            || $logoWidthRatio <= 0.0
            || $logoWidthRatio > 0.30
            || !is_file($logoPath)
        ) {
            throw new RuntimeException('QR_LOGO_OVERLAY_UNAVAILABLE');
        }

        $qr = @imagecreatefromstring($qrPng);
        $logo = @imagecreatefrompng($logoPath);
        if ($qr === false || $logo === false) {
            throw new RuntimeException('QR_LOGO_IMAGE_INVALID');
        }

        try {
            $qrWidth = imagesx($qr);
            $qrHeight = imagesy($qr);
            $logoWidth = imagesx($logo);
            $logoHeight = imagesy($logo);
            if ($qrWidth < 100 || $qrHeight < 100 || $logoWidth < 1 || $logoHeight < 1) {
                throw new RuntimeException('QR_LOGO_DIMENSIONS_INVALID');
            }

            $targetWidth = max(1, (int) floor($qrWidth * $logoWidthRatio));
            $targetHeight = max(1, (int) round($targetWidth * ($logoHeight / $logoWidth)));
            $padding = max(4, (int) round($qrWidth * 0.022));
            $left = (int) floor(($qrWidth - $targetWidth) / 2);
            $top = (int) floor(($qrHeight - $targetHeight) / 2);

            $white = imagecolorallocate($qr, 255, 255, 255);
            imagefilledrectangle(
                $qr,
                $left - $padding,
                $top - $padding,
                $left + $targetWidth + $padding,
                $top + $targetHeight + $padding,
                $white
            );
            imagealphablending($qr, true);
            imagesavealpha($qr, false);
            imagecopyresampled(
                $qr,
                $logo,
                $left,
                $top,
                0,
                0,
                $targetWidth,
                $targetHeight,
                $logoWidth,
                $logoHeight
            );

            ob_start();
            imagepng($qr, null, 7);
            $result = ob_get_clean();
            if (!is_string($result) || !str_starts_with($result, "\x89PNG\r\n\x1a\n")) {
                throw new RuntimeException('QR_LOGO_OUTPUT_FAILED');
            }
            return $result;
        } finally {
            imagedestroy($logo);
            imagedestroy($qr);
        }
    }
}
