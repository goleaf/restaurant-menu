<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Enums\MenuOperationKind;
use App\Enums\MenuOperationPhase;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use App\Models\MenuOperation;
use App\Models\MenuOperationCategory;
use App\Models\User;
use App\Support\MenuDeletionContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ContinueMenuOperationAction
{
    public const BATCH_SIZE = 50;

    public function __construct(
        private readonly EnsureMenuOperationAccessAction $access,
        private readonly FlushMenuOperationMediaAction $flushMedia,
        private readonly ContinueMenuDuplicationAction $continueDuplicate,
    ) {}

    public function handle(User $actor, Branch $branch, string $requestId): MenuOperation
    {
        $operation = DB::transaction(function () use ($actor, $branch, $requestId): MenuOperation {
            $branch = $this->access->handle($actor, $branch, $requestId);
            $operation = MenuOperation::query()->where('request_id', $requestId)->lockForUpdate()->first();
            if (! $operation instanceof MenuOperation) {
                throw new AuthorizationException;
            }
            $this->access->assertOwner($operation, $actor, $branch);
            if (! Menu::withTrashed()->whereKey($operation->menu_id)->where('branch_id', $branch->id)->exists()) {
                throw new AuthorizationException;
            }
            if (in_array($operation->kind, [MenuOperationKind::ImageRemove, MenuOperationKind::ImagePromote], true)) {
                if ($operation->completed_at === null || $operation->pending_cleanup !== []) {
                    DB::afterCommit(fn () => $this->flushMedia->handle($operation->id));
                }

                return $operation;
            }
            if ($operation->completed_at !== null) {
                return $operation;
            }
            if ($operation->pending_cleanup !== [] && $operation->phase !== MenuOperationPhase::Media) {
                DB::afterCommit(fn () => $this->flushMedia->handle($operation->id));

                return $operation;
            }
            if ($operation->kind === MenuOperationKind::DuplicateItem) {
                $this->continueDuplicate->handle($operation);
            } else {
                match ($operation->phase) {
                    MenuOperationPhase::Discovering => $this->discover($operation),
                    MenuOperationPhase::Items => $this->deleteItems($operation),
                    MenuOperationPhase::Categories => $this->deleteCategories($operation),
                    MenuOperationPhase::Finalizing => $this->finalizeDeletion($operation),
                    default => throw new RuntimeException('Unsupported menu operation phase.'),
                };
            }
            if ($operation->save() !== true) {
                throw new RuntimeException('The menu operation checkpoint could not be saved.');
            }
            if ($operation->pending_cleanup !== []) {
                DB::afterCommit(fn () => $this->flushMedia->handle($operation->id));
            }

            return $operation;
        });

        return $operation->refresh();
    }

    private function discover(MenuOperation $operation): void
    {
        $node = $operation->categories()->where('discovered', false)->orderBy('id')->first();
        if (! $node instanceof MenuOperationCategory) {
            $operation->phase = MenuOperationPhase::Items;
            $operation->cursor = 0;

            return;
        }
        $children = MenuCategory::query()->select(['id', 'menu_id', 'parent_id'])
            ->where('menu_id', $operation->menu_id)->where('parent_id', $node->menu_category_id)
            ->where('id', '>', $node->scan_cursor)->orderBy('id')->limit(self::BATCH_SIZE)->get();
        foreach ($children as $child) {
            $created = $operation->categories()->firstOrCreate(['menu_category_id' => $child->id]);
            if (! $created->exists) {
                throw new RuntimeException('The menu traversal could not be saved.');
            }
            $node->scan_cursor = $child->id;
        }
        $node->discovered = $children->count() < self::BATCH_SIZE;
        if ($node->save() !== true) {
            throw new RuntimeException('The menu traversal checkpoint could not be saved.');
        }
    }

    /** @return Builder<MenuItem> */
    private function items(MenuOperation $operation): Builder
    {
        return MenuItem::query()->where('menu_id', $operation->menu_id)
            ->when($operation->kind === MenuOperationKind::DeleteCategory, fn (Builder $query): Builder => $query->whereIn('category_id',
                $operation->categories()->select('menu_category_id')));
    }

    private function deleteItems(MenuOperation $operation): void
    {
        $items = $this->items($operation)->select(['id', 'menu_id', 'category_id', 'name', 'price_cents', 'is_available', 'image', 'deleted_at'])
            ->withCount('galleryImages')->with(['galleryImages' => fn ($query) => $query->select(['id', 'menu_item_id', 'path'])->limit(MenuItem::MAX_IMAGES)])
            ->where('id', '>', $operation->cursor)->orderBy('id')->limit(self::BATCH_SIZE)->get();
        $paths = [];
        foreach ($items as $item) {
            if ((int) $item->gallery_images_count + (filled($item->image) ? 1 : 0) > MenuItem::MAX_IMAGES) {
                throw new RuntimeException('The menu image count exceeds the cleanup batch contract.');
            }
            if (filled($item->image)) {
                $paths[] = $item->image;
            }
            foreach ($item->galleryImages as $image) {
                $paths[] = $image->path;
            }
        }
        $operation->pending_cleanup = array_values(array_unique($paths));
        if ($operation->save() !== true) {
            throw new RuntimeException('The media cleanup plan could not be saved.');
        }
        foreach ($items as $item) {
            MenuItemImage::query()->where('menu_item_id', $item->id)->delete();
            if ($item->delete() !== true) {
                throw new RuntimeException('Menu item deletion was cancelled.');
            }
            $operation->cursor = $item->id;
            $operation->processed_count++;
        }
        if ($items->count() < self::BATCH_SIZE) {
            $operation->phase = MenuOperationPhase::Categories;
            $operation->cursor = 0;
        }
    }

    /** @return Builder<MenuCategory> */
    private function categories(MenuOperation $operation): Builder
    {
        return MenuCategory::query()->where('menu_id', $operation->menu_id)
            ->when($operation->kind === MenuOperationKind::DeleteCategory, fn (Builder $query): Builder => $query
                ->where('id', '!=', $operation->target_id)->whereIn('id', $operation->categories()->select('menu_category_id')));
    }

    private function deleteCategories(MenuOperation $operation): void
    {
        $categories = $this->categories($operation)->select(['id', 'menu_id', 'parent_id', 'name', 'image', 'deleted_at'])
            ->where('id', '>', $operation->cursor)->orderBy('id')->limit(self::BATCH_SIZE)->get();
        $operation->pending_cleanup = $categories->pluck('image')->filter(fn (?string $path): bool => filled($path))->values()->all();
        if ($operation->save() !== true) {
            throw new RuntimeException('The media cleanup plan could not be saved.');
        }
        foreach ($categories as $category) {
            if (MenuDeletionContext::withoutCascade($category, fn (): ?bool => $category->delete()) !== true) {
                throw new RuntimeException('Menu category deletion was cancelled.');
            }
            $operation->cursor = $category->id;
            $operation->processed_count++;
        }
        if ($categories->count() < self::BATCH_SIZE) {
            $operation->phase = MenuOperationPhase::Finalizing;
            $operation->cursor = 0;
        }
    }

    private function finalizeDeletion(MenuOperation $operation): void
    {
        if ($this->items($operation)->exists()) {
            $operation->phase = MenuOperationPhase::Items;
            $operation->cursor = 0;

            return;
        }
        if ($this->categories($operation)->exists()) {
            $operation->phase = MenuOperationPhase::Categories;
            $operation->cursor = 0;

            return;
        }
        if ($operation->kind === MenuOperationKind::DeleteCategory) {
            $newChildren = MenuCategory::query()->select('id')->where('menu_id', $operation->menu_id)
                ->whereNotIn('id', $operation->categories()->select('menu_category_id'))
                ->whereIn('parent_id', $operation->categories()->select('menu_category_id'))
                ->orderBy('id')->limit(self::BATCH_SIZE)->get();
            if ($newChildren->isNotEmpty()) {
                foreach ($newChildren as $child) {
                    if (! $operation->categories()->firstOrCreate(['menu_category_id' => $child->id])->exists) {
                        throw new RuntimeException('The late menu traversal could not be saved.');
                    }
                }
                $operation->phase = MenuOperationPhase::Discovering;

                return;
            }
            $root = MenuCategory::withTrashed()->whereKey($operation->target_id)->where('menu_id', $operation->menu_id)->first();
        } else {
            $root = Menu::withTrashed()->whereKey($operation->target_id)->where('branch_id', $operation->branch_id)->first();
        }
        if ($root === null) {
            throw new AuthorizationException;
        }
        if (! $root->trashed()) {
            if ($root instanceof MenuCategory && filled($root->image)) {
                $operation->pending_cleanup = [$root->image];
                if ($operation->save() !== true) {
                    throw new RuntimeException('The root media cleanup plan could not be saved.');
                }
            }
            if (MenuDeletionContext::withoutCascade($root, fn (): ?bool => $root->delete()) !== true) {
                throw new RuntimeException('Menu root deletion was cancelled.');
            }
            $operation->processed_count++;
        }
        if ($operation->pending_cleanup === []) {
            $operation->phase = MenuOperationPhase::Completed;
            $operation->completed_at = now();
            $operation->active_scope = null;
        }
    }
}
