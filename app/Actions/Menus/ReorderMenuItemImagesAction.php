<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Enums\MenuOperationKind;
use App\Enums\MenuOperationPhase;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use App\Models\MenuOperation;
use App\Models\User;
use App\Support\MenuItemMediaState;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

final class ReorderMenuItemImagesAction
{
    public function __construct(private readonly EnsureMenuOperationAccessAction $access) {}

    /**
     * @param  array<array-key, mixed>  $imageIds
     */
    public function handle(User $actor, Branch $branch, MenuItem $item, array $imageIds, string $expectedFingerprint, string $requestId): MenuItem
    {
        if (! array_is_list($imageIds) || count($imageIds) >= MenuItem::MAX_IMAGES) {
            $this->invalidOrder();
        }

        foreach ($imageIds as $id) {
            if (! is_int($id) || $id < 1) {
                $this->invalidOrder();
            }
        }

        if (count(array_unique($imageIds)) !== count($imageIds)) {
            $this->invalidOrder();
        }

        return DB::transaction(function () use ($actor, $branch, $item, $imageIds, $expectedFingerprint, $requestId): MenuItem {
            $branch = $this->access->handle($actor, $branch, $requestId);
            $payload = ['image_ids' => $imageIds, 'expected_fingerprint' => $expectedFingerprint];
            $receipt = MenuOperation::query()->where('request_id', $requestId)->lockForUpdate()->first();
            if ($receipt instanceof MenuOperation) {
                $this->access->assertOwner($receipt, $actor, $branch);
                if ($receipt->kind !== MenuOperationKind::ImageReorder || $receipt->target_id !== $item->id
                    || $receipt->payload !== $payload || $receipt->completed_at === null) {
                    throw new AuthorizationException;
                }
            }
            $scopedItem = MenuItem::query()
                ->select(['id', 'menu_id', 'image', 'media_version'])
                ->whereKey($item->id)
                ->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id))
                ->lockForUpdate()
                ->first();

            if (! $scopedItem instanceof MenuItem) {
                throw new InvalidArgumentException('The menu item does not belong to the selected branch.');
            }

            if ($receipt instanceof MenuOperation) {
                return $scopedItem->refresh()->load('galleryImages');
            }

            $images = $scopedItem->galleryImages()
                ->select(['id', 'menu_item_id', 'path', 'sort_order'])
                ->limit(MenuItem::MAX_IMAGES)
                ->lockForUpdate()
                ->get();
            $currentIds = $images->modelKeys();
            $scopedItem->setRelation('galleryImages', $images);
            if (! hash_equals(MenuItemMediaState::fingerprint($scopedItem), $expectedFingerprint)) {
                throw ValidationException::withMessages(['images' => __('uploads.errors.image_changed')]);
            }

            if (count($currentIds) !== count($imageIds) || array_diff($currentIds, $imageIds) !== []) {
                $this->invalidOrder();
            }

            if ($currentIds !== $imageIds) {
                $highestPosition = (int) $images->max('sort_order');

                if ($highestPosition > PHP_INT_MAX - MenuItem::MAX_IMAGES) {
                    $this->invalidOrder();
                }

                foreach ($images as $index => $image) {
                    $this->savePosition($image, $highestPosition + 1 + $index);
                }

                $imagesById = $images->keyBy('id');

                foreach ($imageIds as $position => $id) {
                    $this->savePosition($imagesById[$id], $position);
                }
            }

            $receipt = new MenuOperation;
            $receipt->forceFill(['request_id' => $requestId, 'branch_id' => $branch->id, 'actor_user_id' => $actor->id,
                'menu_id' => $scopedItem->menu_id, 'target_id' => $scopedItem->id, 'result_id' => $scopedItem->id,
                'kind' => MenuOperationKind::ImageReorder, 'phase' => MenuOperationPhase::Completed,
                'payload' => $payload, 'processed_count' => count($imageIds), 'completed_at' => now()]);
            if ($receipt->save() !== true) {
                throw new RuntimeException('The image order receipt could not be saved.');
            }

            return $scopedItem->refresh()->load('galleryImages');
        }, attempts: 3);
    }

    private function savePosition(MenuItemImage $image, int $position): void
    {
        $image->sort_order = $position;

        if ($image->save() !== true) {
            throw new RuntimeException('The gallery image position could not be saved.');
        }
    }

    private function invalidOrder(): never
    {
        throw ValidationException::withMessages(['images' => __('uploads.errors.invalid_order')]);
    }
}
