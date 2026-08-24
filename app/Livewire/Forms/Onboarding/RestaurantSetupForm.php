<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Onboarding;

use App\Enums\SupportedCurrency;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;
use App\Support\PlainText;
use App\Support\RestaurantSetupOptions;
use App\Support\Validation\RestaurantValidationRules;
use Illuminate\Validation\Rule;
use Livewire\Form;

final class RestaurantSetupForm extends Form
{
    public mixed $organizationName = '';

    public mixed $brandName = '';

    public mixed $branchName = '';

    public mixed $branchAddress = '';

    public mixed $branchCity = '';

    public mixed $branchCountryCode = '';

    public mixed $branchTimezone = 'UTC';

    public mixed $branchCurrency = '';

    public mixed $areaName = '';

    public mixed $areaType = 'hall';

    public mixed $areaIcon = 'rectangle-group';

    public mixed $tableCount = 4;

    public mixed $tablePrefix = '';

    public mixed $tableCapacity = 4;

    public mixed $menuName = '';

    public mixed $categoryName = '';

    public mixed $itemName = '';

    public mixed $itemPrice = '';

    /** @return array{organizationName: string} */
    public function validateOrganization(User $user, ?int $existingId = null): array
    {
        $this->organizationName = $this->plainText($this->organizationName);
        $rules = RestaurantValidationRules::organizationName('organizationName');
        array_unshift($rules['organizationName'], 'bail');
        $rules['organizationName'][] = Rule::unique((new Organization)->getTable(), 'name')
            ->where(fn ($query) => $query->where('owner_user_id', $user->id))
            ->ignore($existingId);

        return $this->validate($rules);
    }

    /** @return array{brandName: string} */
    public function validateBrand(Organization $organization, ?int $existingId = null): array
    {
        $this->brandName = $this->plainText($this->brandName);
        $rules = RestaurantValidationRules::brandName('brandName');
        array_unshift($rules['brandName'], 'bail');
        $rules['brandName'][] = Rule::unique((new Brand)->getTable(), 'name')
            ->where(fn ($query) => $query->where('organization_id', $organization->id))
            ->ignore($existingId);

        return $this->validate($rules);
    }

    /** @return array{branchName: string, branchAddress: string, branchCity: string, branchCountryCode: string, branchTimezone: string, branchCurrency: string} */
    public function validateBranch(Brand $brand, ?int $existingId = null): array
    {
        $this->branchName = $this->plainText($this->branchName);
        $this->branchAddress = $this->plainText($this->branchAddress);
        $this->branchCity = $this->plainText($this->branchCity);
        $this->branchCountryCode = strtoupper($this->scalarString($this->branchCountryCode));
        $this->branchTimezone = $this->scalarString($this->branchTimezone);
        $this->branchCurrency = SupportedCurrency::clean($this->scalarString($this->branchCurrency));

        $rules = RestaurantValidationRules::onboardingBranch();
        $rules['branchName'][] = Rule::unique((new Branch)->getTable(), 'name')
            ->where(fn ($query) => $query->where('brand_id', $brand->id))
            ->ignore($existingId);

        return $this->validate($rules);
    }

    /** @return array{areaName: string, areaType: string, areaIcon: string|null} */
    public function validateArea(): array
    {
        $this->areaName = $this->plainText($this->areaName);
        $this->areaType = $this->scalarString($this->areaType);
        $this->areaIcon = $this->scalarString($this->areaIcon);

        return $this->validate(RestaurantValidationRules::onboardingArea(
            array_keys(RestaurantSetupOptions::areaIconOptions()),
        ));
    }

    /** @return array{tableCount: int, tablePrefix: string, tableCapacity: int} */
    public function validateServicePoints(): array
    {
        $this->tablePrefix = $this->plainText($this->tablePrefix);
        $validated = $this->validate(RestaurantValidationRules::onboardingServicePoints());
        $this->tableCount = (int) $validated['tableCount'];
        $this->tableCapacity = (int) $validated['tableCapacity'];

        return [
            'tableCount' => $this->tableCount,
            'tablePrefix' => $validated['tablePrefix'],
            'tableCapacity' => $this->tableCapacity,
        ];
    }

    /** @return array{menuName: string, categoryName: string, itemName: string, itemPrice: string} */
    public function validateStarterMenu(): array
    {
        $this->menuName = $this->plainText($this->menuName);
        $this->categoryName = $this->plainText($this->categoryName);
        $this->itemName = $this->plainText($this->itemName);
        $this->itemPrice = str_replace(',', '.', $this->numericString($this->itemPrice));

        return $this->validate(RestaurantValidationRules::onboardingStarterMenu());
    }

    /** @param array<string, string|int> $values */
    public function hydrateFromPersistentState(array $values): void
    {
        $this->fill($values);
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'organizationName' => __('validation.attributes.organization_name'),
            'brandName' => __('validation.attributes.brand_name'),
            'branchName' => __('validation.attributes.branch_name'),
            'branchAddress' => __('validation.attributes.branch_address'),
            'branchCity' => __('validation.attributes.branch_city'),
            'branchCountryCode' => __('validation.attributes.branch_country'),
            'branchTimezone' => __('validation.attributes.branch_timezone'),
            'branchCurrency' => __('validation.attributes.branch_currency'),
            'areaName' => __('validation.attributes.area_name'),
            'areaType' => __('validation.attributes.area_type'),
            'areaIcon' => __('validation.attributes.area_icon'),
            'tableCount' => __('validation.attributes.table_count'),
            'tablePrefix' => __('validation.attributes.table_prefix'),
            'tableCapacity' => __('validation.attributes.table_capacity'),
            'menuName' => __('validation.attributes.menu_name'),
            'categoryName' => __('validation.attributes.category_name'),
            'itemName' => __('validation.attributes.item_name'),
            'itemPrice' => __('validation.attributes.item_price'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            '*.required' => __('ui.onboarding.restaurant_setup.validation.required'),
            '*.string' => __('ui.onboarding.restaurant_setup.validation.string'),
            '*.max' => __('ui.onboarding.restaurant_setup.validation.max'),
            '*.unique' => __('ui.onboarding.restaurant_setup.validation.unique'),
            '*.size' => __('ui.onboarding.restaurant_setup.validation.size'),
            '*.in' => __('ui.onboarding.restaurant_setup.validation.in'),
            '*.timezone' => __('ui.onboarding.restaurant_setup.validation.timezone'),
            '*.integer' => __('ui.onboarding.restaurant_setup.validation.integer'),
            '*.min' => __('ui.onboarding.restaurant_setup.validation.min'),
            '*.numeric' => __('ui.onboarding.restaurant_setup.validation.numeric'),
            'itemPrice.decimal' => __('ui.onboarding.restaurant_setup.validation.decimal'),
        ];
    }

    private function scalarString(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    private function plainText(mixed $value): string
    {
        return PlainText::required(is_string($value) ? $value : '', 0, squish: true);
    }

    private function numericString(mixed $value): string
    {
        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }
}
