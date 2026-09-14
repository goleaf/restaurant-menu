<?php

namespace App\Actions\Menus;

use App\Enums\MenuStatus;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use App\Support\LocalImageVariants;

class GetGuestMenuItemGalleryAction
{
    /** @return list<array{url: string, srcset: ?string, width: ?int, height: ?int, thumbnail_url: string, thumbnail_width: ?int, thumbnail_height: ?int}> */
    public function handle(int $branchId, int $menuItemId): array
    {
        $item = MenuItem::query()
            ->select(['id', 'menu_id', 'image', 'hidden_until'])
            ->with([
                'galleryImages' => fn ($query) => $query
                    ->select(['id', 'menu_item_id', 'path', 'sort_order'])
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->whereKey($menuItemId)
            ->where(fn ($query) => $query
                ->whereNull('hidden_until')
                ->orWhere('hidden_until', '<=', now()))
            ->whereHas('menu', fn ($query) => $query
                ->where('branch_id', $branchId)
                ->where('status', MenuStatus::Active->value))
            ->first();

        if (! $item instanceof MenuItem) {
            return [];
        }

        return collect([$item->image, ...$item->galleryImages->map(
            fn (MenuItemImage $image): string => $image->path,
        )->all()])
            ->filter(fn (mixed $path): bool => is_string($path) && filled($path))
            ->map(fn (string $path): array => LocalImageVariants::forPath($path))
            ->filter(fn (array $image): bool => is_string($image['url']) && is_string($image['thumbnail_url']))
            ->values()
            ->all();
    }
}
