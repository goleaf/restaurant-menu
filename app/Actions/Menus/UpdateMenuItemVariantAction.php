<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Enums\MenuOperationKind;
use App\Models\Branch;
use App\Models\MenuItemVariant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class UpdateMenuItemVariantAction
{
    public function __construct(
        private readonly BuildMenuItemVariantAttributesAction $buildAttributes,
        private readonly SyncMenuItemVariantTranslationsAction $syncTranslations,
        private readonly RunDishConfigurationCommandAction $commands,
        private readonly ValidateDishConfigurationInputAction $inputs,
    ) {}

    /**
     * @param  array{type: string, name: string, price?: string|int, weight: string|null, volume: string|null, is_default: bool, is_available?: bool, sort_order: int, translations?: array<string, string|null>}  $data
     */
    public function handle(User $actor, Branch $branch, MenuItemVariant $variant, array $data, ?int $expectedVersion = null, ?string $requestId = null): MenuItemVariant
    {
        $result = $this->commands->handle($actor, $branch, MenuOperationKind::VariantChange, $variant->id,
            ['operation' => 'update_variant', 'item_id' => $variant->menu_item_id, 'data' => $data, 'expected_version' => $expectedVersion], $requestId,
            function (User $actor, Branch $branch) use ($variant, $data, $expectedVersion): array {
                $variant = $this->lockedBranchVariant($branch, $variant);
                $item = $variant->item;
                $this->commands->assertVersion($item->variants_version, $expectedVersion);
                $data = $this->inputs->variant($item, $data, $variant);
                $attributes = $this->buildAttributes->handle($actor, $branch, $item, $data, $variant);
                $before = $variant->only(array_keys($attributes));
                if ($attributes['is_default'] !== $variant->is_default) {
                    Gate::forUser($actor)->authorize('changeMenuPrices', $branch);
                }

                if ($attributes['is_default']) {
                    $item->variants()->whereKeyNot($variant->id)->update(['is_default' => false]);
                } elseif ($variant->is_default) {
                    $replacement = $item->variants()
                        ->whereKeyNot($variant->id)
                        ->where('is_available', true)
                        ->first() ?? $item->variants()->whereKeyNot($variant->id)->first();

                    if ($replacement instanceof MenuItemVariant) {
                        if ($replacement->updateOrFail(['is_default' => true]) !== true) {
                            throw new \RuntimeException('The default variant could not be saved.');
                        }
                    } else {
                        $attributes['is_default'] = true;
                    }
                }

                if ($variant->updateOrFail($attributes) !== true) {
                    throw new \RuntimeException('The variant could not be saved.');
                }
                $this->syncTranslations->handle($variant, $data['translations'] ?? []);

                return ['id' => $variant->id, 'item_id' => $item->id, 'menu_id' => $item->menu_id,
                    'entity_type' => 'menu_item', 'entity_id' => $item->id, 'before' => $before, 'after' => $attributes,
                    'required_abilities' => array_values(array_filter([
                        $before['price_cents'] !== $attributes['price_cents'] || $before['is_default'] !== $attributes['is_default'] ? 'changeMenuPrices' : null,
                        $before['is_available'] !== $attributes['is_available'] ? 'changeMenuAvailability' : null,
                    ])),
                    'changed' => $item->variants_version !== $item->fresh()->variants_version];
            });

        return MenuItemVariant::query()->whereKey($result['id'])->where('menu_item_id', $variant->menu_item_id)->firstOrFail()->load('translations');
    }

    private function lockedBranchVariant(Branch $branch, MenuItemVariant $variant): MenuItemVariant
    {
        $variant = MenuItemVariant::query()
            ->select(['id', 'menu_item_id', 'type', 'name', 'price_cents', 'weight', 'volume', 'is_default', 'is_available', 'sort_order'])
            ->with(['item:id,menu_id,price_cents,variants_version'])
            ->where('menu_item_id', $variant->menu_item_id)
            ->whereHas('item', fn ($query) => $query->whereNull('menu_items.deleted_at')
                ->whereHas('menu', fn ($menu) => $menu->whereNull('menus.deleted_at')->where('branch_id', $branch->id)))
            ->whereKey($variant->id)
            ->lockForUpdate()
            ->first();

        if (! $variant instanceof MenuItemVariant) {
            throw new InvalidArgumentException('The menu item variant must belong to the selected branch.');
        }

        return $variant;
    }
}
