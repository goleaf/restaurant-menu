<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Models\Branch;
use App\Models\MenuItemVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class DeleteMenuItemVariantAction
{
    public function handle(User $actor, Branch $branch, MenuItemVariant $variant): void
    {
        Gate::forUser($actor)->authorize('manageMenu', $branch);

        DB::transaction(function () use ($branch, $variant): void {
            $variant = MenuItemVariant::query()
                ->select(['id', 'menu_item_id', 'is_default'])
                ->whereHas('item.menu', fn ($query) => $query->where('branch_id', $branch->id))
                ->whereKey($variant->id)
                ->lockForUpdate()
                ->first();

            if (! $variant instanceof MenuItemVariant) {
                throw new InvalidArgumentException('The menu item variant must belong to the selected branch.');
            }

            $item = $variant->item;
            $wasDefault = $variant->is_default;
            $variant->deleteOrFail();

            if (! $wasDefault) {
                return;
            }

            $replacement = $item->variants()->where('is_available', true)->first()
                ?? $item->variants()->first();

            $replacement?->updateOrFail(['is_default' => true]);
        }, attempts: 3);
    }
}
