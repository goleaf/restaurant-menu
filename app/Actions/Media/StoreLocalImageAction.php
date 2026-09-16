<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Support\LocalImageVariants;
use App\Support\Media\LocalImageConstraints;
use App\Support\Validation\Media\ImageUploadRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class StoreLocalImageAction
{
    public function __construct(
        private readonly ProcessLocalImageAction $processLocalImage,
        private readonly DeleteLocalMediaFileAction $deleteLocalMediaFile,
    ) {}

    public function handle(UploadedFile $file, string $directory): string
    {
        $this->validateFile($file);

        $directory = $this->safeDirectory($directory);
        $processed = $this->processLocalImage->handle($file, $this->safeExtension($file));
        $path = $directory.'/'.Str::uuid()->toString().'.v1-'.$processed['width'].'x'.$processed['height'].'.'.$processed['extension'];
        $paths = LocalImageVariants::paths($path);
        $disk = Storage::disk('public');

        try {
            if (! $disk->put($path, $processed['display'], 'public')) {
                throw new RuntimeException(__('uploads.errors.upload_failed'));
            }

            if (isset($paths[1]) && ! $disk->put($paths[1], $processed['thumbnail'], 'public')) {
                throw new RuntimeException(__('uploads.errors.upload_failed'));
            }
        } catch (Throwable $exception) {
            $this->deleteLocalMediaFile->handle($path);

            throw $exception;
        }

        return $path;
    }

    private function validateFile(UploadedFile $file): void
    {
        Validator::make(
            ['file' => $file],
            ['file' => ImageUploadRules::required()],
            ImageUploadRules::messages('file'),
        )->validate();
    }

    private function safeExtension(UploadedFile $file): string
    {
        $mimeType = (string) $file->getMimeType();
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => strtolower($file->extension() ?: $file->guessExtension() ?: ''),
        };

        if (! in_array($extension, LocalImageConstraints::allowedExtensions(), true)) {
            throw ValidationException::withMessages([
                'file' => __('uploads.errors.invalid_type', [
                    'formats' => LocalImageConstraints::allowedExtensionsLabel(),
                ]),
            ]);
        }

        return $extension;
    }

    private function safeDirectory(string $directory): string
    {
        $normalizedDirectory = Str::of($directory)
            ->replace('\\', '/')
            ->trim('/');

        if ($normalizedDirectory->isEmpty() || $normalizedDirectory->contains(['..', '//'])) {
            throw new RuntimeException(__('uploads.errors.not_writable'));
        }

        return $normalizedDirectory->toString();
    }
}
