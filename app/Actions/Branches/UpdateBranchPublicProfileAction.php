<?php

namespace App\Actions\Branches;

use App\Models\Branch;

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
            'public_name' => $this->nullableString($data['public_name']),
            'public_description' => $this->nullableString($data['public_description']),
            'phone' => $this->nullableString($data['phone']),
            'email' => $this->nullableString($data['email']),
            'website_url' => $this->nullableString($data['website_url']),
            'instagram_url' => $this->nullableString($data['instagram_url']),
            'facebook_url' => $this->nullableString($data['facebook_url']),
            'tiktok_url' => $this->nullableString($data['tiktok_url']),
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

    private function nullableString(?string $value): ?string
    {
        $value = str((string) $value)->squish()->toString();

        return $value === '' ? null : $value;
    }
}
