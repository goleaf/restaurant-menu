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
