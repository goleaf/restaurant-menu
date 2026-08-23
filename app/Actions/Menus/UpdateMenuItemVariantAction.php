<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Models\Branch;
use App\Models\MenuItemVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class UpdateMenuItemVariantAction
{
    public function __construct(
        private readonly BuildMenuItemVariantAttributesAction $buildAttributes,
        private readonly SyncMenuItemVariantTranslationsAction $syncTranslations,
    ) {}

    /**
     * @param  array{type: string, name: string, price?: string|int, weight: string|null, volume: string|null, is_default: bool, is_available?: bool, sort_order: int, translations?: array<string, string|null>}  $data
     */
    public function handle(User $actor, Branch $branch, MenuItemVariant $variant, array $data): MenuItemVariant
    {
        Gate::forUser($actor)->authorize('manageMenu', $branch);

        return DB::transaction(function () use ($actor, $branch, $variant, $data): MenuItemVariant {
            $variant = $this->lockedBranchVariant($branch, $variant);
            $item = $variant->item;
            $attributes = $this->buildAttributes->handle($actor, $branch, $item, $data, $variant);

            if ($attributes['is_default']) {
                $item->variants()->whereKeyNot($variant->id)->update(['is_default' => false]);
            } elseif ($variant->is_default) {
                $replacement = $item->variants()
                    ->whereKeyNot($variant->id)
                    ->where('is_available', true)
                    ->first() ?? $item->variants()->whereKeyNot($variant->id)->first();

                if ($replacement instanceof MenuItemVariant) {
                    $replacement->updateOrFail(['is_default' => true]);
                } else {
                    $attributes['is_default'] = true;
                }
            }

            $variant->updateOrFail($attributes);
            $this->syncTranslations->handle($variant, $data['translations'] ?? []);

            return $variant->refresh()->load('translations');
        }, attempts: 3);
    }

    private function lockedBranchVariant(Branch $branch, MenuItemVariant $variant): MenuItemVariant
    {
        $variant = MenuItemVariant::query()
            ->select(['id', 'menu_item_id', 'type', 'name', 'price_cents', 'weight', 'volume', 'is_default', 'is_available', 'sort_order'])
            ->with(['item:id,menu_id,price_cents'])
            ->whereHas('item.menu', fn ($query) => $query->where('branch_id', $branch->id))
            ->whereKey($variant->id)
            ->lockForUpdate()
            ->first();

        if (! $variant instanceof MenuItemVariant) {
            throw new InvalidArgumentException('The menu item variant must belong to the selected branch.');
        }

        return $variant;
    }
}
