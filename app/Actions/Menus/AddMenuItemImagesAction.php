<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Actions\Media\DeleteRolledBackLocalImageAction;
use App\Actions\Media\StoreLocalImageAction;
use App\Enums\MenuOperationKind;
use App\Enums\MenuOperationPhase;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use App\Models\MenuOperation;
use App\Models\User;
use App\Support\Validation\Media\ImageUploadRules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

final class AddMenuItemImagesAction
{
    public function __construct(
        private readonly StoreLocalImageAction $storeLocalImage,
        private readonly DeleteRolledBackLocalImageAction $deleteRolledBackLocalImage,
        private readonly EnsureMenuOperationAccessAction $operationAccess,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     */
    public function handle(Branch $branch, MenuItem $item, array $files, ?string $requestId = null, ?User $actor = null): MenuItem
    {
        return DB::transaction(function () use ($branch, $item, $files, $requestId, $actor): MenuItem {
            if ($requestId !== null) {
                if (! $actor instanceof User) {
                    throw new AuthorizationException;
                }
                $branch = $this->operationAccess->handle($actor, $branch, $requestId);
                $previous = MenuOperation::query()->where('request_id', $requestId)->lockForUpdate()->first();
                if ($previous instanceof MenuOperation) {
                    $this->operationAccess->assertOwner($previous, $actor, $branch);
                    if ($previous->kind !== MenuOperationKind::ImageUpload || $previous->target_id !== $item->id) {
                        throw new AuthorizationException;
                    }

                    return MenuItem::query()->whereKey($item->id)
                        ->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id))
                        ->with('galleryImages')->firstOrFail();
                }
            }

            Validator::make(
                ['images' => $files],
                ImageUploadRules::imageUploads('images', MenuItem::MAX_IMAGES),
                ImageUploadRules::messages('images.*') + [
                    'images.max' => __('uploads.errors.maximum_images', ['count' => MenuItem::MAX_IMAGES]),
                ],
            )->validate();

            $scopedItem = MenuItem::query()
                ->select(['id', 'menu_id', 'image'])
                ->whereKey($item->id)
                ->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id))
                ->lockForUpdate()
                ->first();

            if (! $scopedItem instanceof MenuItem) {
                throw new InvalidArgumentException('The menu item does not belong to the selected branch.');
            }

            $existingCount = (filled($scopedItem->image) ? 1 : 0)
                + $scopedItem->galleryImages()->count();

            if ($existingCount + count($files) > MenuItem::MAX_IMAGES) {
                throw ValidationException::withMessages([
                    'images' => __('uploads.errors.maximum_images', ['count' => MenuItem::MAX_IMAGES]),
                ]);
            }

            $directory = 'media/organizations/'.$branch->organization_id
                .'/brands/'.$branch->brand_id
                .'/branches/'.$branch->id
                .'/menu-items/'.$scopedItem->id
                .'/images';

            $storedPaths = [];

            foreach ($files as $index => $file) {
                try {
                    $storedPath = $this->storeLocalImage->handle($file, $directory);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(['images.'.$index => $exception->errors()['file'] ?? $exception->validator->errors()->all()]);
                }
                $storedPaths[] = $storedPath;
                DB::afterRollBack(fn () => $this->deleteRolledBackLocalImage->handle($storedPath));
            }

            $galleryPaths = $storedPaths;

            if (blank($scopedItem->image)) {
                $scopedItem->image = array_shift($galleryPaths);
                $scopedItem->image_presentation = null;

                if ($scopedItem->save() !== true) {
                    throw new RuntimeException('The primary image reference could not be saved.');
                }
            }

            if ($galleryPaths !== []) {
                $highestSortOrder = $scopedItem->galleryImages()->max('sort_order');
                $nextSortOrder = is_numeric($highestSortOrder) ? (int) $highestSortOrder + 1 : 0;

                $createdImages = $scopedItem->galleryImages()->createMany(array_map(
                    fn (string $path, int $index): array => [
                        'path' => $path,
                        'sort_order' => $nextSortOrder + $index,
                    ],
                    $galleryPaths,
                    array_keys($galleryPaths),
                ));

                if ($createdImages->contains(fn (MenuItemImage $image): bool => ! $image->exists)) {
                    throw new RuntimeException('The gallery images could not be saved.');
                }
            }

            if ($requestId !== null) {
                $operation = new MenuOperation;
                $operation->forceFill([
                    'request_id' => $requestId,
                    'branch_id' => $branch->id,
                    'actor_user_id' => $actor->id,
                    'menu_id' => $scopedItem->menu_id,
                    'target_id' => $scopedItem->id,
                    'result_id' => $scopedItem->id,
                    'kind' => MenuOperationKind::ImageUpload,
                    'phase' => MenuOperationPhase::Completed,
                    'processed_count' => count($files),
                    'completed_at' => now(),
                ]);
                if ($operation->save() !== true) {
                    throw new RuntimeException('The image upload receipt could not be saved.');
                }
            }

            return $scopedItem->refresh()->load('galleryImages');
        });
    }
}
