<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Support\LocalImageVariants;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class ProcessLocalImageAction
{
    public const MAX_PIXELS = 20_000_000;

    public const MAX_DIMENSION = 8192;

    /**
     * @return array{display: string, thumbnail: ?string, width: int, height: int, extension: string}
     */
    public function handle(UploadedFile $file, string $extension): array
    {
        $dimensions = @getimagesize($file->getPathname());

        if ($dimensions === false || $dimensions[0] < 1 || $dimensions[1] < 1) {
            $this->invalidImage();
        }

        [$width, $height] = $dimensions;

        if (max($width, $height) > self::MAX_DIMENSION || $width * $height > self::MAX_PIXELS) {
            throw ValidationException::withMessages(['file' => __('uploads.errors.dimensions_too_large', [
                'max_pixels' => (int) (self::MAX_PIXELS / 1_000_000), 'max_dimension' => self::MAX_DIMENSION,
            ])]);
        }

        $memoryLimit = ini_parse_quantity((string) ini_get('memory_limit'));
        $requiredBytes = $width * $height * 16 + 32 * 1024 * 1024;

        if (! extension_loaded('gd') || ($extension === 'jpg' && ! function_exists('exif_read_data'))
            || ($memoryLimit > 0 && $requiredBytes > $memoryLimit - memory_get_usage(true))) {
            throw ValidationException::withMessages(['file' => __('uploads.errors.processing_unavailable')]);
        }

        $contents = file_get_contents($file->getPathname());
        $image = $contents === false ? false : @imagecreatefromstring($contents);
        unset($contents);

        if (! $image instanceof GdImage) {
            $this->invalidImage();
        }

        if ($extension === 'jpg') {
            $exif = @exif_read_data($file->getPathname(), 'IFD0');
            $image = $this->orient($image, (int) ($exif['Orientation'] ?? 1));
        }

        [$width, $height] = LocalImageVariants::fit(imagesx($image), imagesy($image), LocalImageVariants::DISPLAY_MAX_DIMENSION);
        $display = $this->resize($image, $width, $height);
        unset($image);
        $outputExtension = in_array('webp', StoreLocalImageAction::allowedExtensions(), true) ? 'webp' : $extension;
        $displayContents = $this->encode($display, $outputExtension);
        $thumbnailContents = null;

        if (max($width, $height) > LocalImageVariants::THUMBNAIL_MAX_DIMENSION) {
            [$thumbnailWidth, $thumbnailHeight] = LocalImageVariants::fit($width, $height, LocalImageVariants::THUMBNAIL_MAX_DIMENSION);
            $thumbnailContents = $this->encode($this->resize($display, $thumbnailWidth, $thumbnailHeight), $outputExtension);
        }

        return ['display' => $displayContents, 'thumbnail' => $thumbnailContents, 'width' => $width, 'height' => $height, 'extension' => $outputExtension];
    }

    private function orient(GdImage $image, int $orientation): GdImage
    {
        if (in_array($orientation, [2, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        } elseif ($orientation === 4) {
            imageflip($image, IMG_FLIP_VERTICAL);
        }

        $angle = match ($orientation) {
            3 => 180,
            5, 8 => 90,
            6, 7 => -90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        if (! $rotated instanceof GdImage) {
            $this->invalidImage();
        }

        return $rotated;
    }

    private function resize(GdImage $image, int $width, int $height): GdImage
    {
        $resized = imagecreatetruecolor($width, $height);

        if (! $resized instanceof GdImage) {
            throw new RuntimeException(__('uploads.errors.processing_unavailable'));
        }

        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));

        return $resized;
    }

    private function encode(GdImage $image, string $extension): string
    {
        ob_start();

        try {
            $success = match ($extension) {
                'webp' => imagewebp($image, null, 82),
                'jpg' => imagejpeg($image, null, 82),
                default => imagepng($image, null, 6),
            };
            $contents = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        if (! $success || ! is_string($contents) || $contents === '') {
            throw new RuntimeException(__('uploads.errors.upload_failed'));
        }

        return $contents;
    }

    private function invalidImage(): never
    {
        throw ValidationException::withMessages(['file' => __('uploads.errors.invalid_image')]);
    }
}
