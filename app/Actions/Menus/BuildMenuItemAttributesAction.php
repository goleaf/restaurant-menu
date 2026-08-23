<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Actions\KitchenDepartments\ResolveDefaultKitchenDepartmentAction;
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
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class BuildMenuItemAttributesAction
{
    public function __construct(
        private readonly ResolveDefaultKitchenDepartmentAction $resolveDefaultDepartment,
    ) {}

    /**
     * @param  array{name: string, description: string|null, price?: string|int, allergens?: list<string>, dietary_labels?: list<string>, weight: string|null, volume: string|null, calories: int|null, is_available?: bool, sort_order: int}  $data
     * @return array{menu_id: int, category_id: int, kitchen_department_id: int|null, name: string, description: string|null, price_cents: int, allergens: list<string>, dietary_labels: list<string>, weight: string|null, volume: string|null, calories: int|null, is_available: bool, sort_order: int}
     */
    public function handle(
        User $actor,
        Branch $branch,
        Menu $menu,
        MenuCategory $category,
        ?int $kitchenDepartmentId,
        array $data,
        ?MenuItem $existingItem = null,
    ): array {
        $this->ensureRelationshipsBelongToBranch($branch, $menu, $category, $existingItem);

        $department = $this->resolveDepartment($branch, $kitchenDepartmentId);
        $canChangePrices = Gate::forUser($actor)->allows('changePrice', $menu);
        $canChangeAvailability = Gate::forUser($actor)->allows('changeAvailability', $menu);
        $existingPriceCents = $existingItem instanceof MenuItem ? $existingItem->price_cents : 0;
        $existingAvailability = $existingItem instanceof MenuItem ? $existingItem->is_available : true;

        return [
            'menu_id' => $menu->id,
            'category_id' => $category->id,
            'kitchen_department_id' => $department?->id,
            'name' => PlainText::required($data['name'], 180, squish: true),
            'description' => PlainText::optional($data['description'], 1200),
            'price_cents' => $canChangePrices
                ? MoneyFormatter::decimalToCents($data['price'] ?? 0)
                : $existingPriceCents,
            'allergens' => array_key_exists('allergens', $data)
                ? $this->normalizeLabels($data['allergens'], MenuAllergen::values(), 'allergens')
                : ($existingItem instanceof MenuItem ? $existingItem->allergens : []),
            'dietary_labels' => array_key_exists('dietary_labels', $data)
                ? $this->normalizeLabels($data['dietary_labels'], MenuDietaryLabel::values(), 'dietary_labels')
                : ($existingItem instanceof MenuItem ? $existingItem->dietary_labels : []),
            'weight' => $this->optionalString($data['weight']),
            'volume' => $this->optionalString($data['volume']),
            'calories' => $data['calories'],
            'is_available' => $canChangeAvailability
                ? (bool) ($data['is_available'] ?? true)
                : $existingAvailability,
            'sort_order' => $data['sort_order'],
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

    private function resolveDepartment(Branch $branch, ?int $departmentId): ?KitchenDepartment
    {
        if ($departmentId === null) {
            return $this->resolveDefaultDepartment->handle($branch);
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
