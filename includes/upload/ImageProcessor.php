<?php
/**
 * Ресайз и WebP для загруженных фото объектов.
 */
declare(strict_types=1);

final class ImageProcessor
{
    public const SIZES = [
        'thumb'  => [400, 300],
        'medium' => [800, 600],
    ];

    public static function processUpload(string $sourcePath, string $publicBaseUrl, string $filenameBase): array
    {
        $info = @getimagesize($sourcePath);
        if ($info === false) {
            throw new RuntimeException('Invalid image');
        }

        $mime = $info['mime'];
        $src = match ($mime) {
            'image/jpeg', 'image/jpg' => imagecreatefromjpeg($sourcePath),
            'image/png'  => imagecreatefrompng($sourcePath),
            'image/webp' => imagecreatefromwebp($sourcePath),
            default => throw new RuntimeException('Unsupported type: ' . $mime),
        };

        if ($src === false) {
            throw new RuntimeException('Cannot read image');
        }

        $uploadDir = dirname(__DIR__, 2) . '/uploads/properties/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $urls = [];
        foreach (self::SIZES as $label => [$maxW, $maxH]) {
            $w = imagesx($src);
            $h = imagesy($src);
            $ratio = min($maxW / $w, $maxH / $h, 1.0);
            $nw = (int)max(1, round($w * $ratio));
            $nh = (int)max(1, round($h * $ratio));

            $dst = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

            $file = $filenameBase . '_' . $label . '.webp';
            $path = $uploadDir . $file;
            imagewebp($dst, $path, 82);
            imagedestroy($dst);

            $urls[$label] = rtrim($publicBaseUrl, '/') . '/' . $file;
        }

        imagedestroy($src);

        // Оригинал как fallback (jpeg)
        $origFile = $filenameBase . '_orig.jpg';
        $origPath = $uploadDir . $origFile;
        $src2 = imagecreatefromstring((string)file_get_contents($sourcePath));
        if ($src2) {
            imagejpeg($src2, $origPath, 88);
            imagedestroy($src2);
            $urls['original'] = rtrim($publicBaseUrl, '/') . '/' . $origFile;
        }

        return $urls;
    }
}
