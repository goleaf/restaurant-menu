<?php

declare(strict_types=1);

namespace App\Support\Validation\Media;

use App\Support\Media\LocalImageConstraints;

final class ImageUploadRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function imageUpload(string $field = 'image'): array
    {
        return [
            $field => self::required(),
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function optionalImageUpload(string $field = 'image'): array
    {
        return [
            $field => self::optional(),
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function imageUploads(string $field, int $maxFiles): array
    {
        return [
            $field => ['required', 'array', 'min:1', 'max:'.$maxFiles],
            $field.'.*' => self::required(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function required(): array
    {
        return self::imageRules(required: true);
    }

    /**
     * @return list<string>
     */
    public static function optional(): array
    {
        return self::imageRules(required: false);
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $field): array
    {
        $formatMessage = __('uploads.errors.invalid_type', [
            'formats' => LocalImageConstraints::allowedExtensionsLabel(),
        ]);

        return [
            $field.'.image' => $formatMessage,
            $field.'.mimes' => $formatMessage,
            $field.'.extensions' => $formatMessage,
            $field.'.max' => __('uploads.errors.too_large', [
                'size' => LocalImageConstraints::maxSizeLabel(),
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
            'mimes:'.implode(',', LocalImageConstraints::allowedExtensions()),
            'extensions:'.implode(',', LocalImageConstraints::allowedExtensions()),
            'max:'.LocalImageConstraints::MAX_IMAGE_KILOBYTES,
        ];
    }
}
