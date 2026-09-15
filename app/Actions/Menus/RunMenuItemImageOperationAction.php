<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Actions\Media\DeleteLocalMediaFileAction;
use App\Enums\MenuOperationKind;
use App\Enums\MenuOperationPhase;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use App\Models\MenuOperation;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

final class RunMenuItemImageOperationAction
{
    public function __construct(
        private readonly EnsureMenuOperationAccessAction $access,
        private readonly FlushMenuOperationMediaAction $flushMedia,
        private readonly DeleteLocalMediaFileAction $deleteMedia,
    ) {}

    /** @param Closure(MenuItem, ?MenuItemImage): list<string> $mutate */
    public function handle(
        Branch $branch,
        MenuItem $item,
        ?int $imageId,
        MenuOperationKind $kind,
        ?string $expectedImageIdentity,
        ?string $requestId,
        ?User $actor,
        Closure $mutate,
    ): MenuItem {
        return DB::transaction(function () use ($branch, $item, $imageId, $kind, $expectedImageIdentity, $requestId, $actor, $mutate): MenuItem {
            $receipt = null;
            if ($requestId !== null) {
                if (! $actor instanceof User) {
                    throw new AuthorizationException;
                }
                $branch = $this->access->handle($actor, $branch, $requestId);
                $receipt = MenuOperation::query()->where('request_id', $requestId)->lockForUpdate()->first();
                if ($receipt instanceof MenuOperation) {
                    $this->access->assertOwner($receipt, $actor, $branch);
                    if ($receipt->kind !== $kind || $receipt->target_id !== $item->id
                        || ($receipt->payload['image_id'] ?? null) !== $imageId
                        || ($receipt->payload['image_identity'] ?? null) !== $expectedImageIdentity) {
                        throw new AuthorizationException;
                    }
                } elseif ($expectedImageIdentity === null) {
                    $this->imageChanged();
                }
            }

            $scopedItem = MenuItem::query()->select(['id', 'menu_id', 'image', 'image_presentation'])
                ->whereKey($item->id)
                ->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id))
                ->lockForUpdate()->first();
            if (! $scopedItem instanceof MenuItem) {
                $this->invalidScope($requestId);
            }

            if ($receipt instanceof MenuOperation) {
                if ($receipt->completed_at === null || $receipt->pending_cleanup !== []) {
                    DB::afterCommit(fn () => $this->flushMedia->handle($receipt->id));
                }

                return $scopedItem->refresh()->load('galleryImages');
            }

            $scopedImage = $imageId === null ? null : MenuItemImage::query()
                ->select(['id', 'menu_item_id', 'path', 'sort_order', 'presentation'])
                ->whereKey($imageId)->where('menu_item_id', $scopedItem->id)->lockForUpdate()->first();
            if ($imageId !== null && ! $scopedImage instanceof MenuItemImage) {
                $this->invalidScope($requestId);
            }

            $path = $scopedImage->path ?? $scopedItem->image;
            if ($expectedImageIdentity !== null
                && (preg_match('/^[a-f0-9]{64}$/D', $expectedImageIdentity) !== 1
                    || ! hash_equals(hash('sha256', (string) $path), $expectedImageIdentity))) {
                $this->imageChanged();
            }

            $pendingPaths = $mutate($scopedItem, $scopedImage);
            if ($requestId === null) {
                foreach ($pendingPaths as $pendingPath) {
                    DB::afterCommit(fn () => $this->deleteMedia->handle($pendingPath));
                }
            } else {
                $receipt = new MenuOperation;
                $receipt->forceFill([
                    'request_id' => $requestId,
                    'branch_id' => $branch->id,
                    'actor_user_id' => $actor->id,
                    'menu_id' => $scopedItem->menu_id,
                    'kind' => $kind,
                    'target_id' => $scopedItem->id,
                    'result_id' => $scopedItem->id,
                    'phase' => $pendingPaths === [] ? MenuOperationPhase::Completed : MenuOperationPhase::Finalizing,
                    'processed_count' => 1,
                    'pending_cleanup' => $pendingPaths,
                    'payload' => ['image_id' => $imageId, 'image_identity' => $expectedImageIdentity],
                    'completed_at' => $pendingPaths === [] ? now() : null,
                ]);
                if ($receipt->save() !== true) {
                    throw new RuntimeException('The image mutation receipt could not be saved.');
                }
                if ($pendingPaths !== []) {
                    DB::afterCommit(fn () => $this->flushMedia->handle($receipt->id));
                }
            }

            return $scopedItem->refresh()->load('galleryImages');
        });
    }

    private function invalidScope(?string $requestId): never
    {
        if ($requestId !== null) {
            throw new AuthorizationException;
        }

        throw new InvalidArgumentException('The image does not belong to the selected branch and menu item.');
    }

    private function imageChanged(): never
    {
        throw ValidationException::withMessages(['images' => __('uploads.errors.image_changed')]);
    }
}
