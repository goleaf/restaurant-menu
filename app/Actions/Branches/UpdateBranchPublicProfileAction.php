<?php

namespace App\Actions\Branches;

use App\Models\Branch;
use App\Support\PlainText;

class UpdateBranchPublicProfileAction
{
    /**
     * @param  array{
     *     public_name: string|null,
     *     public_description: string|null,
     *     logo_path?: string|null,
     *     cover_image_path?: string|null,
     *     phone: string|null,
     *     email: string|null,
     *     website_url: string|null,
     *     instagram_url: string|null,
     *     facebook_url: string|null,
     *     tiktok_url: string|null
     * }  $data
     */
    public function handle(Branch $branch, array $data): Branch
    {
        $branch->fill([
            'public_name' => $this->nullableString($data['public_name'], 160, squish: true),
            'public_description' => $this->nullableString($data['public_description'], 1200),
            'phone' => $this->nullableString($data['phone'], 80, squish: true),
            'email' => $this->nullableString($data['email'], 255, squish: true),
            'website_url' => $this->nullableString($data['website_url'], 2048, squish: true),
            'instagram_url' => $this->nullableString($data['instagram_url'], 2048, squish: true),
            'facebook_url' => $this->nullableString($data['facebook_url'], 2048, squish: true),
            'tiktok_url' => $this->nullableString($data['tiktok_url'], 2048, squish: true),
        ]);

        if (array_key_exists('logo_path', $data)) {
            $branch->logo_path = $data['logo_path'];
        }

        if (array_key_exists('cover_image_path', $data)) {
            $branch->cover_image_path = $data['cover_image_path'];
        }

        $branch->save();

        return $branch->refresh();
    }

    private function nullableString(?string $value, int $maxLength, bool $squish = false): ?string
    {
        return PlainText::optional($value, $maxLength, $squish);
    }
}
