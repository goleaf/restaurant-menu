<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Onboarding;

use App\Enums\AreaNodeType;
use App\Enums\SupportedCurrency;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;
use App\Support\Validation\RestaurantValidationRules;
use Illuminate\Validation\Rule;
use Livewire\Form;

final class RestaurantSetupForm extends Form
{
    public string $organizationName = '';

    public string $brandName = '';

    public string $branchName = '';

    public string $branchAddress = '';

    public string $branchCity = '';

    public string $branchCountry = '';

    public string $branchTimezone = 'Europe/Vilnius';

    public string $branchCurrency = 'EUR';

    public string $areaName = 'Главный зал';

    public string $areaType = 'hall';

    public string $areaIcon = 'rectangle-group';

    public int $tableCount = 4;

    public string $tablePrefix = 'Стол';

    public int $tableCapacity = 4;

    public string $menuName = 'Основное меню';

    public string $categoryName = 'Основное';

    public string $itemName = 'Тестовое блюдо';

    public string $itemPrice = '10.00';

    /** @return array{organizationName: string} */
    public function validateOrganization(User $user): array
    {
        $this->organizationName = trim($this->organizationName);
        $rules = RestaurantValidationRules::organizationName('organizationName');
        $rules['organizationName'][] = Rule::unique((new Organization)->getTable(), 'name')
            ->where(fn ($query) => $query->where('owner_user_id', $user->id));

        return $this->validate($rules);
    }

    /** @return array{brandName: string} */
    public function validateBrand(Organization $organization): array
    {
        $this->brandName = trim($this->brandName);
        $rules = RestaurantValidationRules::brandName('brandName');
        $rules['brandName'][] = Rule::unique((new Brand)->getTable(), 'name')
            ->where(fn ($query) => $query->where('organization_id', $organization->id));

        return $this->validate($rules);
    }

    /** @return array{branchName: string, branchAddress: string, branchCity: string, branchCountry: string, branchTimezone: string, branchCurrency: string} */
    public function validateBranch(Brand $brand): array
    {
        $this->branchName = trim($this->branchName);
        $this->branchAddress = trim($this->branchAddress);
        $this->branchCity = trim($this->branchCity);
        $this->branchCountry = trim($this->branchCountry);
        $this->branchCurrency = SupportedCurrency::clean($this->branchCurrency);

        $rules = RestaurantValidationRules::branchBase('branch');
        unset($rules['branchIsActive']);
        $rules['branchName'][] = Rule::unique((new Branch)->getTable(), 'name')
            ->where(fn ($query) => $query->where('brand_id', $brand->id));

        return $this->validate($rules);
    }

    /** @return array{areaName: string, areaType: string, areaIcon: string|null} */
    public function validateArea(): array
    {
        $this->areaName = trim($this->areaName);
        $this->areaIcon = trim($this->areaIcon);

        return $this->validate([
            'areaName' => ['required', 'string', 'max:160'],
            'areaType' => ['required', Rule::in(AreaNodeType::values())],
            'areaIcon' => ['nullable', 'string', 'max:80'],
        ]);
    }

    /** @return array{tableCount: int, tablePrefix: string, tableCapacity: int} */
    public function validateServicePoints(): array
    {
        $this->tablePrefix = trim($this->tablePrefix);

        return $this->validate([
            'tableCount' => ['required', 'integer', 'min:1', 'max:20'],
            'tablePrefix' => ['required', 'string', 'max:40'],
            'tableCapacity' => ['required', 'integer', 'min:1', 'max:50'],
        ]);
    }

    /** @return array{menuName: string, categoryName: string, itemName: string, itemPrice: string} */
    public function validateStarterMenu(): array
    {
        $this->menuName = trim($this->menuName);
        $this->categoryName = trim($this->categoryName);
        $this->itemName = trim($this->itemName);
        $this->itemPrice = trim($this->itemPrice);

        return $this->validate([
            'menuName' => ['required', 'string', 'max:160'],
            'categoryName' => ['required', 'string', 'max:160'],
            'itemName' => ['required', 'string', 'max:180'],
            'itemPrice' => ['required', 'numeric', 'min:0', 'max:999999.99'],
        ]);
    }
}
