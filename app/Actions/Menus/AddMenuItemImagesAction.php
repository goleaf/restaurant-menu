<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Actions\Media\DeleteLocalMediaFileAction;
use App\Actions\Media\StoreLocalImageAction;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Support\Validation\RestaurantValidationRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

final class AddMenuItemImagesAction
{
    public function __construct(
        private readonly StoreLocalImageAction $storeLocalImage,
        private readonly DeleteLocalMediaFileAction $deleteLocalMediaFile,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     */
    public function handle(Branch $branch, MenuItem $item, array $files): MenuItem
    {
        Validator::make(
            ['images' => $files],
            RestaurantValidationRules::imageUploads('images', MenuItem::MAX_IMAGES),
            StoreLocalImageAction::validationMessages('images.*') + [
                'images.max' => __('uploads.errors.maximum_images', ['count' => MenuItem::MAX_IMAGES]),
            ],
        )->validate();

        $storedPaths = [];

        try {
            return DB::transaction(function () use ($branch, $item, $files, &$storedPaths): MenuItem {
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

                foreach ($files as $file) {
                    $storedPaths[] = $this->storeLocalImage->handle($file, $directory);
                }

                $galleryPaths = $storedPaths;

                if (blank($scopedItem->image)) {
                    $scopedItem->image = array_shift($galleryPaths);
                    $scopedItem->saveOrFail();
                }

                if ($galleryPaths !== []) {
                    $highestSortOrder = $scopedItem->galleryImages()->max('sort_order');
                    $nextSortOrder = is_numeric($highestSortOrder) ? (int) $highestSortOrder + 1 : 0;

                    $scopedItem->galleryImages()->createMany(array_map(
                        fn (string $path, int $index): array => [
                            'path' => $path,
                            'sort_order' => $nextSortOrder + $index,
                        ],
                        $galleryPaths,
                        array_keys($galleryPaths),
                    ));
                }

                return $scopedItem->refresh()->load('galleryImages');
            });
        } catch (Throwable $throwable) {
            foreach ($storedPaths as $storedPath) {
                $this->deleteLocalMediaFile->handle($storedPath);
            }

            throw $throwable;
        }
    }
}
