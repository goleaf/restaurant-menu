<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Actions\KitchenDepartments\ResolveDefaultKitchenDepartmentAction;
use App\Data\Menus\MenuItemData;
use App\Enums\MenuAllergen;
use App\Enums\MenuDietaryLabel;
use App\Models\Branch;
use App\Models\KitchenDepartment;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use App\Support\MoneyFormatter;
use App\Support\PlainText;
use App\Support\Validation\Menus\MenuFieldLabels;
use App\Support\Validation\Menus\MenuScopeRules;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

final class BuildMenuItemAttributesAction
{
    public function __construct(
        private readonly ResolveDefaultKitchenDepartmentAction $resolveDefaultDepartment,
    ) {}

    /**
     * @return array{menu_id: int, category_id: int, kitchen_department_id: int|null, name: string, description: string|null, price_cents: int, allergens: list<string>, dietary_labels: list<string>, weight: string|null, volume: string|null, calories: int|null, is_available: bool, hidden_until: CarbonInterface|null, sort_order: int}
     */
    public function handle(
        User $actor,
        Branch $branch,
        Menu $menu,
        MenuCategory $category,
        ?int $kitchenDepartmentId,
        MenuItemData $data,
        ?MenuItem $existingItem = null,
        bool $preserveExistingDepartment = false,
        bool $seedMissingDepartment = true,
        bool $namesValidatedAsBatch = false,
    ): array {
        $this->ensureRelationshipsBelongToBranch($branch, $menu, $category, $existingItem);
        $name = PlainText::required($data->originalName(), 0, squish: true);
        $nameField = $data->translations !== null && array_key_exists('en', $data->translations) ? 'itemTranslations.en.name' : 'itemName';
        $nameRules = ['required', 'string', 'max:180'];
        if (! $namesValidatedAsBatch) {
            $nameRules[] = MenuScopeRules::itemName($category->id, $existingItem);
        }
        Validator::make(Arr::undot([$nameField => $name]), [$nameField => $nameRules], [], MenuFieldLabels::forEditor('item'))->validate();

        $departmentId = $preserveExistingDepartment && $existingItem instanceof MenuItem
            ? $existingItem->kitchen_department_id
            : $kitchenDepartmentId;
        $department = $preserveExistingDepartment && $existingItem instanceof MenuItem && $departmentId === null
            ? null
            : $this->resolveDepartment($branch, $departmentId, $seedMissingDepartment);
        $canChangePrices = Gate::forUser($actor)->allows('changePrice', $menu);
        $canChangeAvailability = Gate::forUser($actor)->allows('changeAvailability', $menu);
        $existingPriceCents = $existingItem instanceof MenuItem ? $existingItem->price_cents : 0;
        $existingAvailability = $existingItem instanceof MenuItem ? $existingItem->is_available : true;
        $existingHiddenUntil = $existingItem instanceof MenuItem ? $existingItem->hidden_until : null;

        return [
            'menu_id' => $menu->id,
            'category_id' => $category->id,
            'kitchen_department_id' => $department?->id,
            'name' => $name,
            'description' => PlainText::optional($data->originalDescription(), 1200),
            'price_cents' => $canChangePrices
                ? ($data->price === null ? $existingPriceCents : MoneyFormatter::decimalToCents($data->price))
                : $existingPriceCents,
            'allergens' => $data->allergens !== null
                ? $this->normalizeLabels($data->allergens, MenuAllergen::values(), 'allergens')
                : ($existingItem instanceof MenuItem ? $existingItem->allergens : []),
            'dietary_labels' => $data->dietaryLabels !== null
                ? $this->normalizeLabels($data->dietaryLabels, MenuDietaryLabel::values(), 'dietary_labels')
                : ($existingItem instanceof MenuItem ? $existingItem->dietary_labels : []),
            'weight' => $this->optionalString($data->weight),
            'volume' => $this->optionalString($data->volume),
            'calories' => $data->calories,
            'is_available' => $canChangeAvailability
                ? ($data->isAvailable ?? $existingAvailability)
                : $existingAvailability,
            'hidden_until' => $canChangeAvailability
                ? ($data->updatesHiddenUntil ? $this->hiddenUntil($data->hiddenUntil, $branch->timezone) : $existingHiddenUntil)
                : $existingHiddenUntil,
            'sort_order' => $data->sortOrder,
        ];
    }

    private function ensureRelationshipsBelongToBranch(
        Branch $branch,
        Menu $menu,
        MenuCategory $category,
        ?MenuItem $existingItem,
    ): void {
        if ($menu->branch_id !== $branch->id || $category->menu_id !== $menu->id) {
            throw new InvalidArgumentException('The menu category must belong to the selected branch menu.');
        }

        if ($existingItem instanceof MenuItem && ! Menu::query()
            ->whereKey($existingItem->menu_id)
            ->where('branch_id', $branch->id)
            ->exists()) {
            throw new InvalidArgumentException('The menu item must belong to the selected branch.');
        }
    }

    private function resolveDepartment(Branch $branch, ?int $departmentId, bool $seedMissingDepartment): ?KitchenDepartment
    {
        if ($departmentId === null) {
            return $this->resolveDefaultDepartment->handle($branch, seedIfMissing: $seedMissingDepartment);
        }

        $department = KitchenDepartment::query()
            ->select(['id', 'branch_id', 'type', 'name', 'sort_order', 'is_active'])
            ->where('branch_id', $branch->id)
            ->whereKey($departmentId)
            ->first();

        if (! $department instanceof KitchenDepartment) {
            throw new InvalidArgumentException('The kitchen department must belong to the selected branch.');
        }

        return $department;
    }

    private function optionalString(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }

    private function hiddenUntil(?string $value, string $timezone): ?CarbonInterface
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return Date::parse($value, $timezone)->utc();
    }

    /**
     * @param  list<string>  $values
     * @param  list<string>  $allowedValues
     * @return list<string>
     */
    private function normalizeLabels(array $values, array $allowedValues, string $field): array
    {
        foreach ($values as $value) {
            if (! in_array($value, $allowedValues, true)) {
                throw new InvalidArgumentException("The {$field} selection is invalid.");
            }
        }

        return array_values(array_filter(
            $allowedValues,
            fn (string $allowedValue): bool => in_array($allowedValue, $values, true),
        ));
    }
}
