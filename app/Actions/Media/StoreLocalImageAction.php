<?php

namespace App\Actions\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class StoreLocalImageAction
{
    public const MAX_IMAGE_KILOBYTES = 2048;

    /**
     * @return list<string>
     */
    public static function allowedExtensions(): array
    {
        $extensions = ['jpg', 'jpeg', 'png'];

        if (defined('IMAGETYPE_WEBP')) {
            $extensions[] = 'webp';
        }

        return $extensions;
    }

    public static function acceptedMimeTypes(): string
    {
        $mimeTypes = ['image/jpeg', 'image/png'];

        if (in_array('webp', self::allowedExtensions(), true)) {
            $mimeTypes[] = 'image/webp';
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

    public function handle(UploadedFile $file, string $directory, ?string $oldPath = null): string
    {
        $this->validateFile($file);

        $extension = $this->safeExtension($file);
        $path = $file->storePubliclyAs(
            path: $this->safeDirectory($directory),
            name: Str::uuid()->toString().'.'.$extension,
            options: 'public',
        );

        if (! is_string($path) || blank($path)) {
            throw new RuntimeException(__('uploads.errors.upload_failed'));
        }

        if (filled($oldPath) && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
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
        $directory = trim(str_replace('\\', '/', $directory), '/');

        if ($directory === '' || str($directory)->contains(['..', '//'])) {
            throw new RuntimeException(__('uploads.errors.not_writable'));
        }

        return $directory;
    }
}
