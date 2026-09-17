<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Models\MenuItem;
use App\Models\ModifierGroup;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

final class AdvanceDishConfigurationVersionAction
{
    public function variants(int $itemId): void
    {
        try {
            MenuItem::query()->withTrashed()->whereKey($itemId)->increment('variants_version');
        } catch (QueryException $exception) {
            if (Schema::hasColumn('menu_items', 'variants_version')) {
                throw $exception;
            }
        }
    }

    public function group(int $groupId): void
    {
        try {
            ModifierGroup::query()->whereKey($groupId)->increment('content_version');
        } catch (QueryException $exception) {
            if (Schema::hasColumn('modifier_groups', 'content_version')) {
                throw $exception;
            }
        }
    }
}
