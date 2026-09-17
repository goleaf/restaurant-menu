<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Enums\MenuOperationKind;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class CreateMenuItemVariantAction
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
    public function handle(User $actor, Branch $branch, MenuItem $item, array $data, ?int $expectedVersion = null, ?string $requestId = null): MenuItemVariant
    {
        $result = $this->commands->handle($actor, $branch, MenuOperationKind::VariantChange, $item->id,
            ['operation' => 'create_variant', 'data' => $data, 'expected_version' => $expectedVersion], $requestId,
            function (User $actor, Branch $branch) use ($item, $data, $expectedVersion): array {
                $item = $this->lockedBranchItem($branch, $item);
                $this->commands->assertVersion($item->variants_version, $expectedVersion);
                $data = $this->inputs->variant($item, $data);
                $attributes = $this->buildAttributes->handle($actor, $branch, $item, $data);
                $hasVariants = $item->variants()->exists();
                $attributes['is_default'] = ! $hasVariants || $attributes['is_default'];
                $requiresDefaultPrices = $attributes['is_default'] && $hasVariants;

                if ($attributes['is_default']) {
                    if ($requiresDefaultPrices) {
                        Gate::forUser($actor)->authorize('changeMenuPrices', $branch);
                    }
                    $item->variants()->update(['is_default' => false]);
                }

                $variant = $item->variants()->create($attributes);
                if (! $variant->exists) {
                    throw new \RuntimeException('The variant could not be saved.');
                }
                $this->syncTranslations->handle($variant, $data['translations'] ?? []);

                return ['id' => $variant->id, 'item_id' => $item->id, 'menu_id' => $item->menu_id,
                    'entity_type' => 'menu_item', 'entity_id' => $item->id, 'after' => ['variant_id' => $variant->id, ...$attributes],
                    'required_abilities' => array_values(array_filter([
                        $requiresDefaultPrices || (array_key_exists('price', $data) && Gate::forUser($actor)->allows('changeMenuPrices', $branch)) ? 'changeMenuPrices' : null,
                        ! $attributes['is_available'] ? 'changeMenuAvailability' : null,
                    ]))];
            });

        return MenuItemVariant::query()->whereKey($result['id'])->where('menu_item_id', $item->id)->firstOrFail()->load('translations');
    }

    private function lockedBranchItem(Branch $branch, MenuItem $item): MenuItem
    {
        $item = MenuItem::query()
            ->select(['id', 'menu_id', 'price_cents', 'variants_version'])
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
