<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Menu\Concerns;

use App\Actions\Media\StoreLocalImageAction;
use App\Actions\Menus\AddMenuItemImagesAction;
use App\Actions\Menus\PromoteMenuItemImageAction;
use App\Actions\Menus\RemoveMenuItemGalleryImageAction;
use App\Actions\Menus\RemoveMenuItemImageAction;
use App\Models\MenuItem;
use App\Support\Validation\RestaurantValidationRules;
use Flux\Flux;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

trait ManagesItemImages
{
    public function saveItemImages(int $itemId, AddMenuItemImagesAction $addImages): void
    {
        $this->authorizeMenuManagement();

        $item = $this->catalogData->findBranchItem($this->branchId, $itemId);
        $remainingSlots = MenuItem::MAX_IMAGES
            - (filled($item->image) ? 1 : 0)
            - $item->galleryImages->count();
        $field = 'itemImageUploads.'.$item->id;

        if ($this->editingItemId !== $item->id) {
            throw ValidationException::withMessages([
                $field => __('uploads.errors.upload_failed'),
            ]);
        }

        if ($remainingSlots < 1) {
            $this->addError($field, __('uploads.errors.maximum_images', ['count' => MenuItem::MAX_IMAGES]));

            return;
        }

        $this->validate(
            RestaurantValidationRules::imageUploads($field, $remainingSlots),
            StoreLocalImageAction::validationMessages($field.'.*') + [
                $field.'.max' => __('uploads.errors.maximum_images', ['count' => MenuItem::MAX_IMAGES]),
            ],
        );

        $files = $this->itemImageUploads[$item->id] ?? [];

        if (collect($files)->contains(fn (mixed $file): bool => ! $file instanceof UploadedFile)) {
            throw ValidationException::withMessages([
                $field => __('uploads.errors.upload_failed'),
            ]);
        }

        try {
            $addImages->handle($this->branch, $item, $files);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }

            return;
        }

        $uploadedCount = count($files);
        $this->clearItemImageUpload($item->id);
        $this->forgetMenuComputed();

        Flux::toast(
            variant: 'success',
            text: __('uploads.messages.images_uploaded', ['count' => $uploadedCount]),
        );
    }

    public function promoteItemImage(
        int $itemId,
        int $imageId,
        PromoteMenuItemImageAction $promoteImage,
    ): void {
        $this->authorizeMenuManagement();

        $item = $this->catalogData->findBranchItem($this->branchId, $itemId);
        $image = $this->catalogData->findBranchItemImage($this->branchId, $item->id, $imageId);

        $promoteImage->handle($this->branch, $item, $image);

        $this->clearItemImageUpload($item->id);
        $this->forgetMenuComputed();

        Flux::toast(variant: 'success', text: __('uploads.messages.primary_changed'));
    }

    public function removeItemGalleryImage(
        int $itemId,
        int $imageId,
        RemoveMenuItemGalleryImageAction $removeImage,
    ): void {
        $this->authorizeMenuManagement();

        $item = $this->catalogData->findBranchItem($this->branchId, $itemId);
        $image = $this->catalogData->findBranchItemImage($this->branchId, $item->id, $imageId);

        $removeImage->handle($this->branch, $item, $image);

        $this->clearItemImageUpload($item->id);
        $this->forgetMenuComputed();
        Flux::modals()->close();

        Flux::toast(variant: 'success', text: __('uploads.messages.removed'));
    }

    public function removeItemImage(int $itemId, RemoveMenuItemImageAction $removeItemImage): void
    {
        $this->authorizeMenuManagement();

        $item = $this->catalogData->findBranchItem($this->branchId, $itemId);

        $removeItemImage->handle($this->branch, $item);

        $this->clearItemImageUpload($item->id);
        $this->forgetMenuComputed();
        Flux::modals()->close();

        Flux::toast(variant: 'success', text: __('uploads.messages.removed'));
    }

    private function clearItemImageUpload(int $itemId): void
    {
        unset($this->itemImageUploads[$itemId]);
        $this->resetValidation([
            'itemImageUploads.'.$itemId,
            'itemImageUploads.'.$itemId.'.*',
        ]);
    }
}
