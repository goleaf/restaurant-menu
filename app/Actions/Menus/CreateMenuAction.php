<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Enums\MenuStatus;
use App\Models\Branch;
use App\Models\Menu;

final class CreateMenuAction
{
    /**
     * @param  array{name: string, status: MenuStatus|string, sort_order: int}  $data
     */
    public function handle(Branch $branch, array $data): Menu
    {
        $menu = $branch->menus()->make([
            'name' => $data['name'],
            'sort_order' => $data['sort_order'],
        ]);
        $menu->forceFill([
            'status' => $data['status'] instanceof MenuStatus
                ? $data['status']
                : MenuStatus::from($data['status']),
        ])->saveOrFail();

        return $menu;
    }
}
