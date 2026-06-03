<?php

namespace App\Actions\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class StoreLocalImageAction
{
    public const MAX_IMAGE_KILOBYTES = 2048;

    /**
     * @return list<string>
     */
    public static function validationRules(): array
    {
        return [
            'required',
            'image',
            'mimes:jpg,jpeg,png,webp',
            'max:'.self::MAX_IMAGE_KILOBYTES,
        ];
    }

    public function handle(UploadedFile $file, string $directory, ?string $oldPath = null): string
    {
        $extension = strtolower($file->extension() ?: $file->guessExtension() ?: 'bin');
        $path = $file->storePubliclyAs(
            path: trim($directory, '/'),
            name: Str::uuid()->toString().'.'.$extension,
            options: 'public',
        );

        if (! is_string($path) || blank($path)) {
            throw new RuntimeException('Unable to store local image.');
        }

        if (filled($oldPath) && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        return $path;
    }
}
