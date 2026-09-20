<?php

declare(strict_types=1);

namespace App\Services\Branches;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Support\Branches\BranchPublicContent;
use App\Support\PlainText;

final class BranchPublicProfilePresenter
{
    /** @return array<string, mixed> */
    public function present(Branch $branch, string $language, string $defaultLanguage = 'en'): array
    {
        $brand = $branch->relationLoaded('brand') ? $branch->getRelation('brand') : null;
        $organization = $branch->relationLoaded('organization') ? $branch->getRelation('organization') : null;
        $logo = $branch->logoUrl();
        $logoSource = $logo !== null ? 'restaurant' : null;
        if ($logo === null && $brand instanceof Brand) {
            $logo = $brand->logoUrl();
            $logoSource = $logo !== null ? 'brand' : null;
        }
        if ($logo === null && $organization instanceof Organization) {
            $logo = $organization->logoUrl();
            $logoSource = $logo !== null ? 'organization' : null;
        }
        $contacts = [
            'phone' => PlainText::optional($branch->phone, 80, squish: true),
            'email' => is_string($branch->email) && filter_var($branch->email, FILTER_VALIDATE_EMAIL) ? $branch->email : null,
        ];
        foreach (['website_url', 'instagram_url', 'facebook_url', 'tiktok_url'] as $field) {
            $value = $branch->getAttribute($field);
            $contacts[$field] = is_string($value)
                && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['https', 'http'], true)
                && filter_var($value, FILTER_VALIDATE_URL) ? $value : null;
        }

        return [
            'venue_name' => BranchPublicContent::text($branch, 'name', $language, $defaultLanguage) ?? (string) $branch->name,
            'public_description' => BranchPublicContent::text($branch, 'description', $language, $defaultLanguage)
                ?? __('guest.table.restaurant_description_placeholder', [], $language),
            'logo_url' => $logo,
            'logo_source' => $logoSource,
            'cover_image_url' => $branch->coverImageUrl(),
            ...$contacts,
            'has_contact_details' => count(array_filter($contacts)) > 0,
        ];
    }
}
