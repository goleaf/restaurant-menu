<?php

declare(strict_types=1);

namespace App\Actions\DraftOrders\Support;

use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\MenuItemVariantTranslation;
use Illuminate\Validation\ValidationException;

class ResolveMenuItemVariantSelectionAction
{
    public function handle(MenuItem $menuItem, ?int $variantId, ?string $languageCode = null): ?MenuItemVariant
    {
        if ($menuItem->relationLoaded('variants') && $languageCode === null) {
            if ($variantId === null && $menuItem->variants->isEmpty()) {
                return null;
            }
            $selected = $variantId === null ? null : $menuItem->variants->firstWhere('id', $variantId);
            if ($selected instanceof MenuItemVariant && $selected->is_available && array_key_exists('price_cents', $selected->getAttributes())) {
                return $selected;
            }
            if ($selected === null || ! $selected->is_available) {
                throw ValidationException::withMessages([
                    'selectedItemVariantId' => __($variantId === null ? 'menu.variants.validation.required' : 'menu.variants.validation.unavailable'),
                ]);
            }
        }
        if ($variantId === null) {
            if (! $menuItem->variants()->exists()) {
                return null;
            }

            throw ValidationException::withMessages([
                'selectedItemVariantId' => __('menu.variants.validation.required'),
            ]);
        }

        $variant = $menuItem->variants()
            ->select([
                'id',
                'menu_item_id',
                'type',
                'name',
                'price_cents',
                'weight',
                'volume',
                'is_default',
                'is_available',
                'sort_order',
            ])
            ->when($languageCode !== null, fn ($query) => $query->addSelect([
                'localized_name' => MenuItemVariantTranslation::query()
                    ->select('name')
                    ->whereColumn('menu_item_variant_id', 'menu_item_variants.id')
                    ->where('language_code', $languageCode)
                    ->limit(1),
            ]))
            ->whereKey($variantId)
            ->where('is_available', true)
            ->first();

        if (! $variant instanceof MenuItemVariant) {
            throw ValidationException::withMessages([
                'selectedItemVariantId' => __('menu.variants.validation.unavailable'),
            ]);
        }

        return $variant;
    }
}
