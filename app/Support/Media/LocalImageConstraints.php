<?php

declare(strict_types=1);

namespace App\Support\Media;

final class LocalImageConstraints
{
    public const MAX_IMAGE_KILOBYTES = 2048;

    public const MAX_PIXELS = 20_000_000;

    public const MAX_DIMENSION = 8192;

    /**
     * @return list<string>
     */
    public static function allowedExtensions(): array
    {
        $extensions = [];
        $support = function_exists('gd_info') ? gd_info() : [];

        if (($support['JPEG Support'] ?? false) && function_exists('imagecreatefromjpeg') && function_exists('imagejpeg') && function_exists('exif_read_data')) {
            $extensions = ['jpg', 'jpeg'];
        }

        if (($support['PNG Support'] ?? false) && function_exists('imagecreatefrompng') && function_exists('imagepng')) {
            $extensions[] = 'png';
        }

        if (($support['WebP Support'] ?? false) && function_exists('imagecreatefromwebp') && function_exists('imagewebp')) {
            $extensions[] = 'webp';
        }

        return $extensions;
    }

    public static function acceptedMimeTypes(): string
    {
        $mimeTypes = [];

        foreach (['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'] as $extension => $mimeType) {
            if (in_array($extension, self::allowedExtensions(), true)) {
                $mimeTypes[] = $mimeType;
            }
        }

        return implode(',', $mimeTypes);
    }

    public static function allowedExtensionsLabel(): string
    {
        return implode(', ', array_map('strtoupper', self::allowedExtensions()));
    }

    public static function maxSizeLabel(): string
    {
        return ((int) (self::MAX_IMAGE_KILOBYTES / 1024)).' MB';
    }

    public static function helpText(): string
    {
        return __('uploads.labels.allowed_types', ['types' => self::allowedExtensionsLabel()])
            .' '.__('uploads.labels.max_size', ['size' => self::maxSizeLabel()]);
    }
}
