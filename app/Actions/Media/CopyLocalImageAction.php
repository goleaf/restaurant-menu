<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Support\LocalImageVariants;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class CopyLocalImageAction
{
    /** @return array{source: string, target: string} */
    public function plan(string $source, string $directory): array
    {
        if (! str_starts_with($source, 'media/') || Str::contains($source, ['..', '\\', '//'])
            || ! in_array(strtolower(pathinfo($source, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new RuntimeException(__('menu.operations.errors.media_unavailable'));
        }
        $dimensions = LocalImageVariants::forPath($source);
        $suffix = $dimensions['width'] === null ? '' : '.v1-'.$dimensions['width'].'x'.$dimensions['height'];

        return ['source' => $source, 'target' => $directory.'/'.Str::uuid()->toString().$suffix.'.'.pathinfo($source, PATHINFO_EXTENSION)];
    }

    public function handle(string $source, string $target): void
    {
        $sources = LocalImageVariants::paths($source);
        $targets = LocalImageVariants::paths($target);
        if (count($sources) !== count($targets)) {
            throw new RuntimeException(__('menu.operations.errors.media_unavailable'));
        }
        foreach ($sources as $index => $path) {
            if (! Storage::disk('public')->copy($path, $targets[$index])) {
                throw new RuntimeException(__('menu.operations.errors.media_unavailable'));
            }
        }
    }
}
