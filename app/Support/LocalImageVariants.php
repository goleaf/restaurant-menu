<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;

final class LocalImageVariants
{
    public const DISPLAY_MAX_DIMENSION = 1600;

    public const THUMBNAIL_MAX_DIMENSION = 480;

    /**
     * @return array{url: ?string, srcset: ?string, width: ?int, height: ?int, thumbnail_url: ?string, thumbnail_width: ?int, thumbnail_height: ?int}
     */
    public static function forPath(?string $path): array
    {
        $url = filled($path) ? Storage::disk('public')->url($path) : null;
        $dimensions = self::dimensions($path);
        $result = ['url' => $url, 'srcset' => null, 'width' => null, 'height' => null,
            'thumbnail_url' => $url, 'thumbnail_width' => null, 'thumbnail_height' => null];

        if ($dimensions === null) {
            return $result;
        }

        [$width, $height] = $dimensions;
        [$thumbnailWidth, $thumbnailHeight] = self::fit($width, $height, self::THUMBNAIL_MAX_DIMENSION);
        $paths = self::paths($path);
        $thumbnailUrl = isset($paths[1]) ? Storage::disk('public')->url($paths[1]) : $url;

        return ['url' => $url, 'srcset' => isset($paths[1]) && $thumbnailWidth < $width ? $thumbnailUrl.' '.$thumbnailWidth.'w, '.$url.' '.$width.'w' : null,
            'width' => $width, 'height' => $height, 'thumbnail_url' => $thumbnailUrl,
            'thumbnail_width' => $thumbnailWidth, 'thumbnail_height' => $thumbnailHeight];
    }

    /**
     * @return list<string>
     */
    public static function paths(?string $path): array
    {
        if (blank($path)) {
            return [];
        }

        $dimensions = self::dimensions($path);

        if ($dimensions === null || max($dimensions) <= self::THUMBNAIL_MAX_DIMENSION) {
            return [$path];
        }

        return [$path, substr($path, 0, (int) strrpos($path, '.')).'-thumb.'.pathinfo($path, PATHINFO_EXTENSION)];
    }

    /**
     * @return array{int, int}
     */
    public static function fit(int $width, int $height, int $limit): array
    {
        $longest = max($width, $height);

        if ($longest <= $limit) {
            return [$width, $height];
        }

        return [max(1, intdiv($width * $limit, $longest)), max(1, intdiv($height * $limit, $longest))];
    }

    /**
     * @return array{int, int}|null
     */
    private static function dimensions(?string $path): ?array
    {
        if ($path === null || preg_match('/(?:^|\/)[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.v1-([1-9][0-9]{0,3})x([1-9][0-9]{0,3})\.(?:jpg|png|webp)$/D', $path, $matches) !== 1) {
            return null;
        }

        $width = (int) $matches[1];
        $height = (int) $matches[2];

        return max($width, $height) <= self::DISPLAY_MAX_DIMENSION ? [$width, $height] : null;
    }
}
