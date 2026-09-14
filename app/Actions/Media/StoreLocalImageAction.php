<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Support\LocalImageVariants;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class StoreLocalImageAction
{
    public const MAX_IMAGE_KILOBYTES = 2048;

    public function __construct(
        private readonly ProcessLocalImageAction $processLocalImage,
        private readonly DeleteLocalMediaFileAction $deleteLocalMediaFile,
    ) {}

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

    /**
     * @return list<string>
     */
    public static function validationRules(): array
    {
        return self::imageRules(required: true);
    }

    /**
     * @return list<string>
     */
    public static function optionalValidationRules(): array
    {
        return self::imageRules(required: false);
    }

    /**
     * @return array<string, string>
     */
    public static function validationMessages(string $field): array
    {
        $formatMessage = __('uploads.errors.invalid_type', [
            'formats' => self::allowedExtensionsLabel(),
        ]);

        return [
            $field.'.image' => $formatMessage,
            $field.'.mimes' => $formatMessage,
            $field.'.extensions' => $formatMessage,
            $field.'.max' => __('uploads.errors.too_large', [
                'size' => self::maxSizeLabel(),
            ]),
        ];
    }

    /**
     * @return list<string>
     */
    private static function imageRules(bool $required): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'image',
            'mimes:'.implode(',', self::allowedExtensions()),
            'extensions:'.implode(',', self::allowedExtensions()),
            'max:'.self::MAX_IMAGE_KILOBYTES,
        ];
    }

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
            ['file' => self::validationRules()],
            self::validationMessages('file'),
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

        if (! in_array($extension, self::allowedExtensions(), true)) {
            throw ValidationException::withMessages([
                'file' => __('uploads.errors.invalid_type', [
                    'formats' => self::allowedExtensionsLabel(),
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
