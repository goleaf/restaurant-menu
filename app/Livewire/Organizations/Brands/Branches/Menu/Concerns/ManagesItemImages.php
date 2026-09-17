<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Menu\Concerns;

use App\Actions\Menus\AddMenuItemImagesAction;
use App\Actions\Menus\PromoteMenuItemImageAction;
use App\Actions\Menus\RemoveMenuItemGalleryImageAction;
use App\Actions\Menus\RemoveMenuItemImageAction;
use App\Actions\Menus\ReorderMenuItemImagesAction;
use App\Actions\Menus\ResumeMenuImageCleanupAction;
use App\Actions\Menus\UpdateMenuItemImagePresentationAction;
use App\Enums\MenuOperationKind;
use App\Livewire\Forms\MenuImagePresentationForm;
use App\Models\MenuItem;
use App\Support\LocalImageVariants;
use App\Support\MenuImagePresentation;
use App\Support\Validation\Media\ImageUploadRules;
use Closure;
use Flux\Flux;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use RuntimeException;

trait ManagesItemImages
{
    /** @var array<int, string> */
    #[Locked]
    public array $itemImageRequestIds = [];

    public MenuImagePresentationForm $imagePresentationForm;

    /** @var array<string, mixed> */
    #[Locked]
    public array $imagePresentationContext = [];

    #[Locked]
    public ?string $pendingImageOperationRequestId = null;

    public function editItemImagePresentation(int $itemId, ?int $imageId, string $expectedIdentity): void
    {
        $this->authorizeImageMutation($itemId);
        if ($this->imagePresentationContext !== []) {
            return;
        }
        $item = $this->catalogData->findBranchItem($this->branchId, $itemId);
        $image = $imageId === null ? null : $this->catalogData->findBranchItemImage($this->branchId, $itemId, $imageId);
        $path = $image === null ? $item->image : $image->path;
        if (! is_string($path) || $path === '' || ! hash_equals(hash('sha256', $path), $expectedIdentity)) {
            $this->addError('itemImageUploads.'.$itemId, __('uploads.errors.image_changed'));

            return;
        }
        $presentation = $image === null ? $item->image_presentation : $image->presentation;
        $variants = LocalImageVariants::forPath($path);
        $size = null;
        try {
            $disk = Storage::disk('public');
            if ($disk->exists($path)) {
                $size = (int) ceil($disk->size($path) / 1024);
                if ($variants['width'] === null) {
                    $dimensions = @getimagesize($disk->path($path));
                    if ($dimensions !== false) {
                        $variants['width'] = $dimensions[0];
                        $variants['height'] = $dimensions[1];
                    }
                }
            }
        } catch (\Throwable $exception) {
            report($exception);
        }
        $this->imagePresentationForm->setPresentation($presentation);
        $this->imagePresentationContext = [
            'item_id' => $itemId, 'image_id' => $imageId, 'identity' => $expectedIdentity,
            'request_id' => (string) Str::uuid(),
            'version' => MenuImagePresentation::version($presentation), 'alt' => $item->name,
            'url' => $variants['url'], 'thumbnail_url' => $variants['thumbnail_url'],
            'width' => $variants['width'] ?? 800, 'height' => $variants['height'] ?? 600,
            'file_details' => __('uploads.presentation.file_details', [
                'type' => strtoupper(pathinfo($path, PATHINFO_EXTENSION)),
                'width' => $variants['width'] ?? '—', 'height' => $variants['height'] ?? '—',
                'size' => $size ?? '—',
            ]),
        ];
        $this->clearImagePresentationErrors();
        $this->dispatch('image-presentation-opened', itemId: $itemId);
    }

    public function closeItemImagePresentation(): void
    {
        $itemId = $this->imagePresentationContext['item_id'] ?? null;
        $this->imagePresentationContext = [];
        $this->imagePresentationForm->reset();
        $this->clearImagePresentationErrors();
        if ($itemId !== null) {
            $this->dispatch('image-presentation-closed', itemId: $itemId);
        }
    }

    public function saveItemImagePresentation(UpdateMenuItemImagePresentationAction $update): void
    {
        $this->authorizeMenuManagement();
        $context = $this->imagePresentationContext;
        abort_unless($context !== [] && $this->editingItemId === $context['item_id'], 403);
        $this->authorizeImageMutation($context['item_id']);
        $this->clearImagePresentationErrors();
        try {
            $update->handle($this->currentUser(), $this->branch, $context['item_id'], $context['image_id'],
                $context['identity'], $context['version'], $this->imagePresentationForm->all(), $context['request_id']);
        } catch (ValidationException $exception) {
            $locale = null;
            foreach ($exception->errors() as $field => $messages) {
                $target = str_replace('presentation', 'imagePresentationForm', $field);
                foreach ($messages as $message) {
                    $this->addError($target, $message);
                }
                if ($locale === null && preg_match('/translations\.(en|lt|ru)\./', $field, $matches) === 1) {
                    $locale = $matches[1];
                }
            }
            $this->dispatch('image-presentation-invalid', locale: $locale ?? 'en');

            return;
        } catch (RuntimeException $exception) {
            report($exception);
            $this->addError('imagePresentationForm', __('uploads.editor.retry_help'));

            return;
        }
        $this->closeItemImagePresentation();
        $this->forgetMenuComputed();
        Flux::toast(variant: 'success', text: __('uploads.presentation.saved'));
    }

    private function clearImagePresentationErrors(): void
    {
        $fields = array_filter($this->getErrorBag()->keys(), fn (string $field): bool => str_starts_with($field, 'imagePresentationForm'));
        if ($fields !== []) {
            $this->resetValidation($fields);
        }
    }

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
    public function reorderItemImages(int $itemId, array $imageIds, string $expectedFingerprint, string $requestId, ReorderMenuItemImagesAction $reorder): void
    {
        $this->authorizeImageMutation($itemId);
        $item = $this->catalogData->findBranchItem($this->branchId, $itemId);
        if ($this->performImageMutation($itemId, $requestId, fn (): MenuItem => $reorder->handle($this->currentUser(), $this->branch, $item, $imageIds, $expectedFingerprint, $requestId))) {
            Flux::toast(variant: 'success', text: __('uploads.editor.order_saved'));
        }
    }

    public function saveItemImages(int $itemId, AddMenuItemImagesAction $addImages): void
    {
        $this->authorizeImageMutation($itemId);

        $item = $this->catalogData->findBranchItem($this->branchId, $itemId);
        $field = 'itemImageUploads.'.$item->id;

        if ($this->editingItemId !== $item->id) {
            throw ValidationException::withMessages([
                $field => __('uploads.errors.upload_failed'),
            ]);
        }

        $this->validate(
            ImageUploadRules::imageUploads($field, MenuItem::MAX_IMAGES),
            ImageUploadRules::messages($field.'.*') + [
                $field.'.max' => __('uploads.errors.maximum_images', ['count' => MenuItem::MAX_IMAGES]),
            ],
        );

        $files = $this->itemImageUploads[$item->id] ?? [];

        if (collect($files)->contains(fn (mixed $file): bool => ! $file instanceof UploadedFile)) {
            throw ValidationException::withMessages([
                $field => __('uploads.errors.upload_failed'),
            ]);
        }

        $requestId = $this->itemImageRequestIds[$item->id] ??= (string) Str::uuid();

        try {
            $addImages->handle(
                $this->branch,
                $item,
                $files,
                $requestId,
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
        } catch (RuntimeException $exception) {
            report($exception);
            $operation = $this->catalogData->operation($this->branch, $this->currentUser(), $requestId);
            if ($operation === null || $operation->kind !== MenuOperationKind::ImageUpload
                || $operation->target_id !== $item->id || $operation->completed_at === null) {
                $this->addError($field, __('uploads.errors.upload_failed'));

                return;
            }
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
        $this->authorizeImageMutation($itemId);

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
        $this->authorizeImageMutation($itemId);

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
        $this->authorizeImageMutation($itemId);

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
            if ($operation !== null && $operation->completed_at === null
                && in_array($operation->kind, [MenuOperationKind::ImageRemove, MenuOperationKind::ImagePromote], true)) {
                $this->pendingImageOperationRequestId = $requestId;
                $this->addError('pendingImageOperation', __('menu.operations.retry_help'));
            } else {
                $this->addError('itemImageUploads.'.$itemId, __('uploads.editor.retry_help'));
            }

            return false;
        } finally {
            $this->forgetMenuComputed();
        }

        return true;
    }

    public function retryItemImageCleanup(ResumeMenuImageCleanupAction $resume): void
    {
        $this->authorizeMenuManagement();
        if ($this->editingItemId === null || $this->pendingImageOperationRequestId === null) {
            return;
        }
        try {
            $resume->handle($this->currentUser(), $this->branch, $this->editingItemId, $this->pendingImageOperationRequestId);
        } catch (RuntimeException $exception) {
            report($exception);
            $this->addError('pendingImageOperation', __('menu.operations.retry_help'));

            return;
        }
        $this->pendingImageOperationRequestId = null;
        $this->resetValidation('pendingImageOperation');
        $this->forgetMenuComputed();
    }

    private function authorizeImageMutation(int $itemId): void
    {
        $this->authorizeMenuManagement();
        abort_unless($this->editingItemId === $itemId, 403);
        if ($this->pendingImageOperationRequestId !== null) {
            throw ValidationException::withMessages(['pendingImageOperation' => __('menu.operations.retry_help')]);
        }
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
