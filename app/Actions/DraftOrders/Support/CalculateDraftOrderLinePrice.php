<?php

declare(strict_types=1);

namespace App\Actions\DraftOrders\Support;

use App\Models\DraftOrderItem;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use Illuminate\Validation\ValidationException;

class CalculateDraftOrderLinePrice
{
    public function __construct(
        private readonly BuildDraftOrderItemModifierSnapshots $modifierSnapshots,
        private readonly ResolveMenuItemVariantSelectionAction $resolveVariant,
    ) {}

    /**
     * @param  array<string, mixed>  $selectedModifierOptions
     * @return array{menu_item_variant_id: int|null, variant_name: string|null, variant_type: string|null, unit_price_cents: int, modifier_total_cents: int, total_price_cents: int, selected_modifiers: list<array{group_id: int, group_name: string, option_id: int, option_name: string, price_delta_cents: int}>}
     */
    public function forMenuItem(
        MenuItem $menuItem,
        array $selectedModifierOptions,
        int $quantity,
        ?int $menuItemVariantId = null,
        ?string $languageCode = null,
    ): array {
        $variant = $this->resolveVariant->handle($menuItem, $menuItemVariantId, $languageCode);
        $modifierGroups = $this->modifierSnapshots->groupsFor($menuItem, $languageCode);
        $selectedModifiers = $this->modifierSnapshots->snapshotsFor($modifierGroups, $selectedModifierOptions);
        $unitPriceCents = $variant instanceof MenuItemVariant ? $variant->price_cents : $menuItem->price_cents;
        $modifierTotalCents = $this->modifierSnapshots->modifierTotalCents($selectedModifiers);

        return $this->payload(
            menuItemVariantId: $variant instanceof MenuItemVariant ? $variant->id : null,
            variantName: $variant instanceof MenuItemVariant ? $this->localizedName($variant) : null,
            variantType: $variant instanceof MenuItemVariant ? $variant->type->value : null,
            unitPriceCents: $unitPriceCents,
            modifierTotalCents: $modifierTotalCents,
            quantity: $quantity,
            selectedModifiers: $selectedModifiers,
        );
    }

    /**
     * @param  array<string, mixed>  $selectedModifierOptions
     * @return array{menu_item_variant_id: int|null, variant_name: string|null, variant_type: string|null, unit_price_cents: int, modifier_total_cents: int, total_price_cents: int, selected_modifiers: list<array{group_id: int, group_name: string, option_id: int, option_name: string, price_delta_cents: int}>}
     */
    public function forDraftOrderItem(
        DraftOrderItem $draftOrderItem,
        array $selectedModifierOptions,
        int $quantity,
        ?int $menuItemVariantId = null,
        ?string $languageCode = null,
    ): array {
        $selectedModifiers = $this->existingModifierSnapshots($draftOrderItem->selected_modifiers);
        $modifierTotalCents = $this->modifierSnapshots->modifierTotalCents($selectedModifiers);
        $selectedVariantId = $draftOrderItem->menu_item_variant_id;
        $variantName = $draftOrderItem->variant_name;
        $variantType = $draftOrderItem->variant_type?->value;
        $unitPriceCents = $draftOrderItem->unit_price_cents;

        if ($draftOrderItem->menuItem instanceof MenuItem) {
            $modifierGroups = $this->modifierSnapshots->groupsFor($draftOrderItem->menuItem, $languageCode);
            $selectedModifiers = $this->preserveExistingModifierPrices(
                currentSnapshots: $this->modifierSnapshots->snapshotsFor($modifierGroups, $selectedModifierOptions),
                existingSnapshots: $selectedModifiers,
            );
            $modifierTotalCents = $this->modifierSnapshots->modifierTotalCents($selectedModifiers);

            if ($menuItemVariantId !== null && $menuItemVariantId !== $selectedVariantId) {
                $variant = $this->resolveVariant->handle($draftOrderItem->menuItem, $menuItemVariantId, $languageCode);

                if (! $variant instanceof MenuItemVariant) {
                    throw ValidationException::withMessages([
                        'selectedItemVariantId' => __('menu.variants.validation.unavailable'),
                    ]);
                }

                $selectedVariantId = $variant->id;
                $variantName = $this->localizedName($variant);
                $variantType = $variant->type->value;
                $unitPriceCents = $variant->price_cents;
            }
        }

        return $this->payload(
            menuItemVariantId: $selectedVariantId,
            variantName: $variantName,
            variantType: $variantType,
            unitPriceCents: $unitPriceCents,
            modifierTotalCents: $modifierTotalCents,
            quantity: $quantity,
            selectedModifiers: $selectedModifiers,
        );
    }

    /**
     * @param  list<array{group_id: int, group_name: string, option_id: int, option_name: string, price_delta_cents: int}>  $selectedModifiers
     * @return array{menu_item_variant_id: int|null, variant_name: string|null, variant_type: string|null, unit_price_cents: int, modifier_total_cents: int, total_price_cents: int, selected_modifiers: list<array{group_id: int, group_name: string, option_id: int, option_name: string, price_delta_cents: int}>}
     */
    private function payload(
        ?int $menuItemVariantId,
        ?string $variantName,
        ?string $variantType,
        int $unitPriceCents,
        int $modifierTotalCents,
        int $quantity,
        array $selectedModifiers,
    ): array {
        if ($unitPriceCents < 0) {
            throw ValidationException::withMessages([
                'menu_item' => __('ui.actions.draftorders.support.calculatedraftorderlineprice.cena_pozicii_ne'),
            ]);
        }

        $lineUnitTotalCents = $unitPriceCents + $modifierTotalCents;

        if ($lineUnitTotalCents < 0) {
            throw ValidationException::withMessages([
                'selectedModifierOptions' => __('ui.actions.draftorders.support.calculatedraftorderlineprice.itogovaia_cena'),
            ]);
        }

        return [
            'menu_item_variant_id' => $menuItemVariantId,
            'variant_name' => $variantName,
            'variant_type' => $variantType,
            'unit_price_cents' => $unitPriceCents,
            'modifier_total_cents' => $modifierTotalCents,
            'total_price_cents' => $lineUnitTotalCents * $quantity,
            'selected_modifiers' => $selectedModifiers,
        ];
    }

    /**
     * @return list<array{group_id: int, group_name: string, option_id: int, option_name: string, price_delta_cents: int}>
     */
    private function existingModifierSnapshots(mixed $selectedModifiers): array
    {
        if (! is_array($selectedModifiers)) {
            return [];
        }

        return collect($selectedModifiers)
            ->map(function (mixed $modifier): ?array {
                if (! is_array($modifier)) {
                    return null;
                }

                $groupId = (int) ($modifier['group_id'] ?? 0);
                $optionId = (int) ($modifier['option_id'] ?? 0);

                if ($groupId < 1 || $optionId < 1) {
                    return null;
                }

                return [
                    'group_id' => $groupId,
                    'group_name' => (string) ($modifier['group_name'] ?? ''),
                    'option_id' => $optionId,
                    'option_name' => (string) ($modifier['option_name'] ?? ''),
                    'price_delta_cents' => (int) ($modifier['price_delta_cents'] ?? 0),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  list<array{group_id: int, group_name: string, option_id: int, option_name: string, price_delta_cents: int}>  $currentSnapshots
     * @param  list<array{group_id: int, group_name: string, option_id: int, option_name: string, price_delta_cents: int}>  $existingSnapshots
     * @return list<array{group_id: int, group_name: string, option_id: int, option_name: string, price_delta_cents: int}>
     */
    private function preserveExistingModifierPrices(array $currentSnapshots, array $existingSnapshots): array
    {
        return collect($currentSnapshots)
            ->map(function (array $currentSnapshot) use ($existingSnapshots): array {
                $existingSnapshot = collect($existingSnapshots)
                    ->first(fn (array $snapshot): bool => (int) $snapshot['group_id'] === (int) $currentSnapshot['group_id']
                        && (int) $snapshot['option_id'] === (int) $currentSnapshot['option_id']);

                return is_array($existingSnapshot) ? $existingSnapshot : $currentSnapshot;
            })
            ->values()
            ->all();
    }

    private function localizedName(MenuItemVariant $variant): string
    {
        $localizedName = $variant->getAttribute('localized_name');

        return is_string($localizedName) && filled($localizedName)
            ? $localizedName
            : $variant->name;
    }
}
