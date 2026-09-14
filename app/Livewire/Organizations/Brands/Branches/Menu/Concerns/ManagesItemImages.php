<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Menu\Concerns;

use App\Actions\Media\StoreLocalImageAction;
use App\Actions\Menus\AddMenuItemImagesAction;
use App\Actions\Menus\PromoteMenuItemImageAction;
use App\Actions\Menus\RemoveMenuItemGalleryImageAction;
use App\Actions\Menus\RemoveMenuItemImageAction;
use App\Actions\Menus\ReorderMenuItemImagesAction;
use App\Models\MenuItem;
use App\Support\Validation\RestaurantValidationRules;
use Closure;
use Flux\Flux;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use RuntimeException;

trait ManagesItemImages
{
    /** @var array<int, string> */
    #[Locked]
    public array $itemImageRequestIds = [];

    public function updatedItemImageUploads(mixed $value, string $key): void
    {
        $this->authorizeMenuManagement();
        $itemId = (int) explode('.', $key)[0];
        abort_unless($this->editingItemId === $itemId, 403);
        $this->itemImageRequestIds[$itemId] = (string) Str::uuid();
    }

    public function removePendingItemImage(int $itemId, int $index): void
    {
        $this->authorizeMenuManagement();
        $this->catalogData->findBranchItem($this->branchId, $itemId);
        abort_unless($this->editingItemId === $itemId, 403);
        $files = $this->itemImageUploads[$itemId] ?? [];
        if (! is_array($files) || $index < 0 || ! array_key_exists($index, $files) || ! $files[$index] instanceof UploadedFile) {
            return;
        }
        array_splice($files, $index, 1);
        $this->itemImageUploads[$itemId] = $files;
        $this->itemImageRequestIds[$itemId] = (string) Str::uuid();
        $this->resetValidation(['itemImageUploads.'.$itemId, 'itemImageUploads.'.$itemId.'.*']);
    }

    /** @param list<int> $imageIds */
    public function reorderItemImages(int $itemId, array $imageIds, ReorderMenuItemImagesAction $reorder): void
    {
        $this->authorizeMenuManagement();
        $item = $this->catalogData->findBranchItem($this->branchId, $itemId);
        $reorder->handle($this->branch, $item, $imageIds);
        $this->forgetMenuComputed();
        Flux::toast(variant: 'success', text: __('uploads.editor.order_saved'));
    }

    public function saveItemImages(int $itemId, AddMenuItemImagesAction $addImages): void
    {
        $this->authorizeMenuManagement();

        $item = $this->catalogData->findBranchItem($this->branchId, $itemId);
        $field = 'itemImageUploads.'.$item->id;

        if ($this->editingItemId !== $item->id) {
            throw ValidationException::withMessages([
                $field => __('uploads.errors.upload_failed'),
            ]);
        }

        $this->validate(
            RestaurantValidationRules::imageUploads($field, MenuItem::MAX_IMAGES),
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
            $addImages->handle(
                $this->branch,
                $item,
                $files,
                $this->itemImageRequestIds[$item->id] ?? null,
                $this->currentUser(),
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $errorField => $messages) {
                $target = preg_match('/^images\.(\d+)$/', $errorField, $matches) === 1
                    ? $field.'.'.$matches[1]
                    : $field;
                foreach ($messages as $message) {
                    $this->addError($target, $message);
                }
            }

            return;
        }

        $this->dispatch('item-images-saved', itemId: $item->id);
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
        string $expectedImageIdentity,
        string $requestId,
        PromoteMenuItemImageAction $promoteImage,
    ): void {
        $this->authorizeMenuManagement();

        $item = $this->catalogData->findBranchItem($this->branchId, $itemId);
        if ($this->performImageMutation($itemId, $requestId, fn (): MenuItem => $promoteImage->handle(
            $this->branch, $item, $imageId, $expectedImageIdentity, $requestId, $this->currentUser(),
        ))) {
            Flux::toast(variant: 'success', text: __('uploads.messages.primary_changed'));
        }
    }

    public function removeItemGalleryImage(
        int $itemId,
        int $imageId,
        string $expectedImageIdentity,
        string $requestId,
        RemoveMenuItemGalleryImageAction $removeImage,
    ): void {
        $this->authorizeMenuManagement();

        $item = $this->catalogData->findBranchItem($this->branchId, $itemId);
        $removed = $this->performImageMutation($itemId, $requestId, fn (): MenuItem => $removeImage->handle(
            $this->branch, $item, $imageId, $expectedImageIdentity, $requestId, $this->currentUser(),
        ));
        Flux::modal('remove-menu-item-image-'.$item->id.'-gallery-'.$imageId)->close();
        if ($removed) {
            Flux::toast(variant: 'success', text: __('uploads.messages.removed'));
        }
    }

    public function removeItemImage(int $itemId, string $expectedImageIdentity, string $requestId, RemoveMenuItemImageAction $removeItemImage): void
    {
        $this->authorizeMenuManagement();

        $item = $this->catalogData->findBranchItem($this->branchId, $itemId);

        $removed = $this->performImageMutation($itemId, $requestId, fn (): MenuItem => $removeItemImage->handle(
            $this->branch, $item, $expectedImageIdentity, $requestId, $this->currentUser(),
        ));
        Flux::modal('remove-menu-item-image-'.$item->id.'-primary-'.$item->id)->close();
        if ($removed) {
            Flux::toast(variant: 'success', text: __('uploads.messages.removed'));
        }
    }

    /** @param Closure(): MenuItem $mutation */
    private function performImageMutation(int $itemId, string $requestId, Closure $mutation): bool
    {
        $this->resetValidation('itemImageUploads.'.$itemId);
        try {
            $mutation();
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->addError('itemImageUploads.'.$itemId, $message);
                }
            }

            return false;
        } catch (RuntimeException $exception) {
            report($exception);
            $operation = $this->catalogData->operation($this->branch, $this->currentUser(), $requestId);
            if ($operation !== null && $operation->completed_at === null) {
                $this->activeCatalogOperationId = $requestId;
                $this->catalogOperationPaused = true;
                $this->addError('catalogOperation', __('menu.operations.retry_help'));
            } else {
                $this->addError('itemImageUploads.'.$itemId, __('uploads.editor.retry_help'));
            }

            return false;
        } finally {
            $this->forgetMenuComputed();
        }

        return true;
    }

    private function clearItemImageUpload(int $itemId): void
    {
        unset($this->itemImageUploads[$itemId], $this->itemImageRequestIds[$itemId]);
        $this->resetValidation([
            'itemImageUploads.'.$itemId,
            'itemImageUploads.'.$itemId.'.*',
        ]);
    }

    /** @return array<int, list<mixed>> */
    private function imageUploadPresentation(): array
    {
        if ($this->editingItemId === null) {
            return [];
        }
        $files = $this->itemImageUploads[$this->editingItemId] ?? [];

        return [$this->editingItemId => is_array($files) ? array_values($files) : []];
    }
}
