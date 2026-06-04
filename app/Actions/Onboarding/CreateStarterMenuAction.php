<?php

namespace App\Actions\Onboarding;

use App\Enums\KitchenDepartmentType;
use App\Enums\MenuStatus;
use App\Models\Branch;
use App\Models\KitchenDepartment;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Support\Facades\DB;

class CreateStarterMenuAction
{
    /**
     * @param  array{menu_name: string, category_name: string, item_name: string, item_price: string|float|int}  $data
     * @return array{menu: Menu, category: MenuCategory, item: MenuItem}
     */
    public function handle(Branch $branch, array $data): array
    {
        return DB::transaction(function () use ($branch, $data): array {
            $menu = $branch->menus()->create([
                'name' => $data['menu_name'],
                'status' => MenuStatus::Active,
                'sort_order' => 0,
            ]);

            $category = $menu->categories()->create([
                'name' => $data['category_name'],
                'description' => null,
                'icon' => 'book-open',
                'sort_order' => 0,
                'is_active' => true,
            ]);

            $item = $menu->items()->create([
                'category_id' => $category->id,
                'kitchen_department_id' => $this->defaultKitchenDepartmentId($branch),
                'name' => $data['item_name'],
                'description' => null,
                'price' => number_format((float) $data['item_price'], 2, '.', ''),
                'is_available' => true,
                'sort_order' => 0,
            ]);

            return [
                'menu' => $menu,
                'category' => $category,
                'item' => $item,
            ];
        });
    }

    private function defaultKitchenDepartmentId(Branch $branch): ?int
    {
        return KitchenDepartment::query()
            ->select(['id', 'branch_id', 'type', 'is_active'])
            ->where('branch_id', $branch->id)
            ->where('type', KitchenDepartmentType::Kitchen->value)
            ->where('is_active', true)
            ->oldest('sort_order')
            ->oldest('id')
            ->value('id');
    }
}
