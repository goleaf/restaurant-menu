<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class CreateMenuItemVariantAction
{
    public function __construct(
        private readonly BuildMenuItemVariantAttributesAction $buildAttributes,
        private readonly SyncMenuItemVariantTranslationsAction $syncTranslations,
    ) {}

    /**
     * @param  array{type: string, name: string, price?: string|int, weight: string|null, volume: string|null, is_default: bool, is_available?: bool, sort_order: int, translations?: array<string, string|null>}  $data
     */
    public function handle(User $actor, Branch $branch, MenuItem $item, array $data): MenuItemVariant
    {
        Gate::forUser($actor)->authorize('manageMenu', $branch);

        return DB::transaction(function () use ($actor, $branch, $item, $data): MenuItemVariant {
            $item = $this->lockedBranchItem($branch, $item);
            $attributes = $this->buildAttributes->handle($actor, $branch, $item, $data);
            $attributes['is_default'] = ! $item->variants()->exists() || $attributes['is_default'];

            if ($attributes['is_default']) {
                $item->variants()->update(['is_default' => false]);
            }

            $variant = $item->variants()->create($attributes);
            $this->syncTranslations->handle($variant, $data['translations'] ?? []);

            return $variant->load('translations');
        }, attempts: 3);
    }

    private function lockedBranchItem(Branch $branch, MenuItem $item): MenuItem
    {
        $item = MenuItem::query()
            ->select(['id', 'menu_id', 'price_cents'])
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id))
            ->whereKey($item->id)
            ->lockForUpdate()
            ->first();

        if (! $item instanceof MenuItem) {
            throw new InvalidArgumentException('The menu item must belong to the selected branch.');
        }

        return $item;
    }
}
