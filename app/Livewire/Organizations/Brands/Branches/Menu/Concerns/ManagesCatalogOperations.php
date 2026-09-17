<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Menu\Concerns;

use App\Actions\Menus\ContinueMenuOperationAction;
use App\Actions\Menus\DuplicateMenuItemAction;
use App\Actions\Menus\StartMenuDeletionAction;
use App\Enums\MenuOperationKind;
use App\Enums\MenuOperationPhase;
use Flux\Flux;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Throwable;

trait ManagesCatalogOperations
{
    #[Locked]
    public string $catalogOperationRequestId = '';

    #[Locked]
    public string $activeCatalogOperationId = '';

    #[Locked]
    public bool $catalogOperationPaused = false;

    #[Locked]
    public ?int $completedCatalogCopyId = null;

    private function initializeCatalogOperation(): void
    {
        $this->activeCatalogOperationId = $this->catalogData->pendingOperationId($this->branch, $this->currentUser());
        $this->catalogOperationRequestId = $this->activeCatalogOperationId ?: (string) Str::uuid();
    }

    public function deleteMenu(int $menuId, StartMenuDeletionAction $start, ContinueMenuOperationAction $continue): void
    {
        $this->authorizeMenuManagement();
        if (! $this->reuseCatalogOperation(MenuOperationKind::DeleteMenu, $menuId)) {
            $operation = $start->handle($this->currentUser(), $this->branch,
                $this->catalogData->findBranchMenu($this->branch, $menuId), $this->catalogOperationRequestId);
            $this->activeCatalogOperationId = $operation->request_id;
        }
        Flux::modal('delete-menu-'.$menuId)->close();
        $this->advanceCatalogOperation($continue);
    }

    public function deleteCategory(int $categoryId, StartMenuDeletionAction $start, ContinueMenuOperationAction $continue): void
    {
        $this->authorizeMenuManagement();
        if (! $this->reuseCatalogOperation(MenuOperationKind::DeleteCategory, $categoryId)) {
            $operation = $start->handle($this->currentUser(), $this->branch,
                $this->catalogData->findBranchCategory($this->branchId, $categoryId), $this->catalogOperationRequestId);
            $this->activeCatalogOperationId = $operation->request_id;
        }
        Flux::modal('delete-menu-category-'.$categoryId)->close();
        $this->advanceCatalogOperation($continue);
    }

    public function duplicateItem(int $itemId, DuplicateMenuItemAction $duplicate, ContinueMenuOperationAction $continue): void
    {
        $this->authorizeMenuManagement();
        if (! $this->reuseCatalogOperation(MenuOperationKind::DuplicateItem, $itemId)) {
            $operation = $duplicate->handle($this->currentUser(), $this->branch,
                $this->catalogData->findBranchItem($this->branchId, $itemId), $this->catalogOperationRequestId);
            $this->activeCatalogOperationId = $operation->request_id;
        }
        $this->advanceCatalogOperation($continue);
    }

    public function resumeCatalogOperation(ContinueMenuOperationAction $continue): void
    {
        $this->authorizeMenuManagement();
        $this->catalogOperationPaused = false;
        $this->resetValidation('catalogOperation');
        $this->advanceCatalogOperation($continue);
    }

    public function advanceCatalogOperation(ContinueMenuOperationAction $continue): void
    {
        $this->authorizeMenuManagement();
        if ($this->activeCatalogOperationId === '' || $this->catalogOperationPaused) {
            return;
        }
        try {
            $operation = $continue->handle($this->currentUser(), $this->branch, $this->activeCatalogOperationId);
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (ValidationException $exception) {
            $this->catalogOperationPaused = true;
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->catalogOperationPaused = true;
            $this->addError('catalogOperation', __('menu.operations.retry_help'));

            return;
        }
        $this->forgetMenuComputed();
        if ($operation->completed_at === null) {
            return;
        }
        $this->activeCatalogOperationId = '';
        $this->catalogOperationRequestId = (string) Str::uuid();
        if ($operation->phase === MenuOperationPhase::Failed) {
            Flux::toast(variant: 'warning', text: __('menu.operations.source_changed'));

            return;
        }
        if ($operation->kind === MenuOperationKind::DuplicateItem && $operation->result_id !== null) {
            $this->completedCatalogCopyId = $operation->result_id;
            if ($this->editingMenuId === null && $this->editingCategoryId === null) {
                $this->openCompletedCatalogCopy();
            }
        } else {
            $this->reconcileDeletedCatalogContexts();
        }
        Flux::toast(variant: 'success', text: __('menu.operations.completed'));
    }

    private function reuseCatalogOperation(MenuOperationKind $kind, int $targetId): bool
    {
        $operation = $this->catalogData->operation($this->branch, $this->currentUser(), $this->catalogOperationRequestId);
        if ($operation === null) {
            return false;
        }
        if ($operation->kind !== $kind || $operation->target_id !== $targetId) {
            throw ValidationException::withMessages(['catalogOperation' => __('menu.operations.errors.already_running')]);
        }
        $this->activeCatalogOperationId = $operation->request_id;

        return true;
    }

    /** @return array{request_id: string, kind: string, phase: string, processed_count: int, completed: bool, result_id: ?int}|null */
    private function catalogOperationProgress(): ?array
    {
        if ($this->activeCatalogOperationId === '') {
            return null;
        }

        return $this->catalogData->operation($this->branch, $this->currentUser(), $this->activeCatalogOperationId)?->progress();
    }

    public function openCompletedCatalogCopy(): void
    {
        $this->authorizeMenuManagement();
        if ($this->completedCatalogCopyId !== null) {
            $this->startEditingItem($this->completedCatalogCopyId);
            $this->completedCatalogCopyId = null;
        }
    }

    private function reconcileDeletedCatalogContexts(): void
    {
        $surviving = $this->catalogData->survivingEditors($this->branch, $this->editingMenuId, $this->editingCategoryId, null);
        if (! $surviving['menu']) {
            $this->cancelMenuEditing();
        }
        if (! $surviving['category']) {
            $this->cancelCategoryEditing();
        }
        $selections = $this->catalogData->survivingMenuSelections($this->branch, [
            $this->selectionValue($this->categoryForm->categoryMenuId),
        ]);
        $fallback = $this->catalogData->firstMenuId($this->branch);
        if (! in_array($this->selectionValue($this->categoryForm->categoryMenuId), $selections, true)) {
            $this->categoryForm->categoryMenuId = $fallback;
            $this->categoryForm->categoryParentId = '';
        }

        if (! $this->catalogData->categorySelectionExists($this->branch, $this->selectionValue($this->categoryForm->categoryMenuId), $this->selectionValue($this->categoryForm->categoryParentId))) {
            $this->categoryForm->categoryParentId = '';
        }

    }
}
