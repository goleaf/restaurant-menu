<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Enums\MenuStatus;
use App\Enums\SupportedLocale;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use App\Support\LocalImageVariants;
use App\Support\MenuImagePresentation;

class GetGuestMenuItemGalleryAction
{
    /** @return list<array<string, mixed>> */
    public function handle(int $branchId, int $menuItemId, string $languageCode = 'en'): array
    {
        $locale = SupportedLocale::tryFrom($languageCode)->value ?? 'en';
        $item = MenuItem::query()
            ->select(['id', 'menu_id', 'name', 'image', 'image_presentation', 'hidden_until'])
            ->addSelect(['translated_name' => MenuItemTranslation::query()->select('name')
                ->whereColumn('menu_item_id', 'menu_items.id')->where('language_code', $locale)->limit(1)])
            ->with(['galleryImages' => fn ($query) => $query
                ->select(['id', 'menu_item_id', 'path', 'presentation', 'sort_order'])
                ->orderBy('sort_order')->orderBy('id')->limit(MenuItem::MAX_IMAGES)])
            ->whereKey($menuItemId)
            ->where(fn ($query) => $query->whereNull('hidden_until')->orWhere('hidden_until', '<=', now()))
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $branchId)->where('status', MenuStatus::Active->value))
            ->first();

        if (! $item instanceof MenuItem) {
            return [];
        }

        $name = filled($item->getAttribute('translated_name')) ? (string) $item->getAttribute('translated_name') : $item->name;
        $images = [];
        if (filled($item->image)) {
            $images[] = [...LocalImageVariants::forPath($item->image), ...MenuImagePresentation::localized($item->image_presentation, $locale, $name)];
        }
        foreach ($item->galleryImages as $image) {
            $images[] = [...LocalImageVariants::forPath($image->path), ...MenuImagePresentation::localized($image->presentation, $locale, $name)];
        }

        return $images;
    }
}
