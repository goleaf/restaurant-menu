<?php

declare(strict_types=1);

namespace App\Services\Branches;

use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\User;
use App\Support\Branches\BranchPublicContent;
use App\Support\Branches\BranchSettingsGroup;
use App\Support\MoneyFormatter;
use App\Support\Validation\Branches\BranchProfileRules;
use App\Support\Validation\Branches\BranchSettingsGroupRules;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class SettingsCenterQuery
{
    public function context(User $actor, int $branchId, int $organizationId, int $brandId): Branch
    {
        $branch = Branch::query()->select([
            'id', 'organization_id', 'brand_id', 'name', 'public_name', 'public_description', 'public_translations',
            'phone', 'email', 'website_url', 'instagram_url', 'facebook_url', 'tiktok_url',
            'logo_path', 'cover_image_path', 'address', 'city', 'country', 'timezone', 'currency',
            'is_active', 'is_temporarily_closed', 'temporary_closed_reason', 'temporary_closed_until',
            'pause_version', 'opening_hours_version', 'created_at', 'updated_at', 'deleted_at',
        ])->whereKey($branchId)->where('organization_id', $organizationId)->where('brand_id', $brandId)
            ->with(['brand', 'organization'])->firstOrFail();
        Gate::forUser($actor)->authorize('manageSettings', $branch);

        return $branch;
    }

    /** @return array<string, bool> */
    public function invalidStoredGroups(BranchSetting $settings, Branch $branch): array
    {
        $invalid = ['profile' => Validator::make(
            ['translations' => BranchPublicContent::translations($branch) ?? []],
            BranchProfileRules::publicTranslations(),
        )->fails()];
        foreach (['guests' => 'guest_process', 'settlement' => 'settlement', 'locale' => 'locale', 'advanced' => 'advanced'] as $section => $group) {
            $values = BranchSettingsGroup::values($settings, $group);
            if ($group === 'settlement') {
                $basisPoints = $values['service_charge_basis_points'];
                $values['service_charge_percent'] = is_int($basisPoints) ? MoneyFormatter::centsToDecimal($basisPoints) : $basisPoints;
            }
            $invalid[$section] = Validator::make($values, BranchSettingsGroupRules::for($group))->fails();
        }

        return $invalid;
    }

    /** @return list<array{key:string,label:string,description:string,icon:string}> */
    public function sections(): array
    {
        return [
            ['key' => 'profile', 'label' => __('settings.section.profile'), 'description' => __('settings.help.profile'), 'icon' => 'building-storefront'],
            ['key' => 'guests', 'label' => __('settings.section.guests'), 'description' => __('settings.help.guests'), 'icon' => 'users'],
            ['key' => 'settlement', 'label' => __('settings.section.settlement'), 'description' => __('settings.help.settlement'), 'icon' => 'banknotes'],
            ['key' => 'locale', 'label' => __('settings.section.locale'), 'description' => __('settings.help.locale'), 'icon' => 'language'],
            ['key' => 'advanced', 'label' => __('settings.section.advanced'), 'description' => __('settings.help.advanced'), 'icon' => 'adjustments-horizontal'],
        ];
    }

    /** @return list<array{section:string,target:string,label:string,description:string}> */
    public function search(string $search): array
    {
        $entries = [
            ['profile', 'public-text', 'public_text'], ['profile', 'contacts', 'contacts'], ['profile', 'images', 'images'],
            ['guests', 'joining', 'joining'], ['guests', 'invitations', 'invitations'],
            ['settlement', 'currency', 'currency'], ['settlement', 'service-charge', 'service_charge'], ['settlement', 'tips', 'tips'],
            ['locale', 'menu-language', 'menu_language'], ['locale', 'timezone', 'timezone'],
            ['advanced', 'polling', 'polling'], ['advanced', 'inactivity', 'inactivity'], ['advanced', 'cleanup', 'cleanup'],
        ];
        $needle = mb_strtolower(trim(mb_substr($search, 0, 100)));
        if ($needle === '') {
            return [];
        }

        return collect($entries)->map(fn (array $entry): array => [
            'section' => $entry[0], 'target' => $entry[1],
            'label' => __('settings.search.'.$entry[2]), 'description' => __('settings.search_help.'.$entry[2]),
        ])->filter(fn (array $entry): bool => str_contains(mb_strtolower($entry['label'].' '.$entry['description']), $needle))->values()->all();
    }

    /** @return array<string, string> */
    public function targets(): array
    {
        return ['public-text' => 'profile', 'contacts' => 'profile', 'images' => 'profile', 'joining' => 'guests', 'invitations' => 'guests', 'currency' => 'settlement', 'service-charge' => 'settlement', 'tips' => 'settlement', 'menu-language' => 'locale', 'timezone' => 'locale', 'polling' => 'advanced', 'inactivity' => 'advanced', 'cleanup' => 'advanced'];
    }
}
