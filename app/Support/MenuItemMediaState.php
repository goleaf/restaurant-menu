<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\MenuItem;
use App\Models\MenuItemImage;
use LogicException;

final class MenuItemMediaState
{
    public static function fingerprint(MenuItem $item): string
    {
        if (! $item->relationLoaded('galleryImages')) {
            throw new LogicException('Load the bounded gallery with its media version before building its fingerprint.');
        }

        return hash('sha256', serialize([
            $item->id, $item->media_version, $item->image,
            $item->galleryImages->sortBy([['sort_order', 'asc'], ['id', 'asc']])
                ->map(fn (MenuItemImage $image): array => [$image->id, $image->path, $image->sort_order])->values()->all(),
        ]));
    }
}
