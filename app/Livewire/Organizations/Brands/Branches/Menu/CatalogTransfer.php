<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Menu;

use App\Actions\Menus\ImportCatalogCsvAction;
use App\Livewire\Forms\Menus\CatalogTransferForm;
use App\Services\Menus\CatalogCsv;
use App\Services\Menus\CatalogData;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\WithFileUploads;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** @phpstan-import-type CsvError from CatalogCsv */
final class CatalogTransfer extends BranchMenuComponent
{
    use WithFileUploads;

    public CatalogTransferForm $form;

    #[Locked]
    public string $fileHash = '';

    #[Locked]
    public string $requestId = '';

    /** @var array<int, string> */
    #[Locked]
    public array $versions = [];

    /** @var list<array{line: int, name: string, price: string, operation: string, category_id: int, translations: array<string, array{name: string, description: string}>}> */
    #[Locked]
    public array $previewRows = [];

    /** @var list<CsvError> */
    #[Locked]
    public array $rowErrors = [];

    #[Locked]
    public int $createCount = 0;

    #[Locked]
    public int $updateCount = 0;

    #[Locked]
    public bool $canApply = false;

    #[Locked]
    public string $success = '';

    #[Locked]
    public int $exportCursor = 0;

    #[Locked]
    public bool $exportHasMore = false;

    #[Locked]
    public string $exportSummary = '';

    private CatalogData $menuQueries;

    private CatalogCsv $csv;

    public function boot(CatalogData $menuQueries, CatalogCsv $csv): void
    {
        $this->menuQueries = $menuQueries;
        $this->csv = $csv;
    }

    public function mount(int $organizationId, int $brandId, int $branchId): void
    {
        $this->initializeBranchContext($organizationId, $brandId, $branchId);
        $this->authorizeBranchAbility('manageMenu');
        $options = $this->csv->options($this->branch, 0);
        $this->form->menuId = (string) ($options['menus'][0]['id'] ?? '');
    }

    public function hydrate(): void
    {
        $this->initializeBranchContext($this->organizationId, $this->brandId, $this->branchId);
        $this->authorizeBranchAbility('manageMenu');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['form.menuId', 'form.file'], true)) {
            $this->clearPreview();
        }
        if ($property === 'form.menuId') {
            $this->exportCursor = 0;
            $this->exportHasMore = false;
            $this->exportSummary = '';
        }
    }

    public function previewImport(): void
    {
        $this->authorizeBranchAbility('manageMenu');
        $this->clearPreview();
        $preview = $this->csv->preview($this->branch, $this->form->selectedMenu(), $this->form->contents());
        $this->fileHash = $preview['hash'];
        $this->requestId = Str::uuid()->toString();
        $this->versions = $preview['versions'];
        $this->rowErrors = $preview['errors'];
        $this->createCount = $preview['create_count'];
        $this->updateCount = $preview['update_count'];
        $this->previewRows = array_map(fn (array $row): array => ['line' => $row['line'], 'name' => $row['name_en'], 'price' => $row['price'],
            'operation' => $row['id'] === null ? 'menu.csv.create' : 'menu.csv.update', 'category_id' => $row['category_id'],
            'translations' => [
                'EN' => ['name' => $row['name_en'], 'description' => $row['description_en']],
                'LT' => ['name' => $row['name_lt'], 'description' => $row['description_lt']],
                'RU' => ['name' => $row['name_ru'], 'description' => $row['description_ru']],
            ]], $preview['rows']);
        $this->canApply = $this->rowErrors === [] && $this->previewRows !== [];
        $this->dispatch('menu-workspace-dirty', key: 'catalog-transfer', dirty: true);
        $this->dispatch('catalog-transfer-feedback');
    }

    public function applyImport(ImportCatalogCsvAction $import): void
    {
        $this->success = '';
        $this->resetErrorBag();
        $this->authorizeBranchAbility('manageMenu');
        if (! $this->canApply || $this->fileHash === '' || $this->requestId === '') {
            throw ValidationException::withMessages(['form.file' => __('menu.csv.errors.preview_first')]);
        }
        try {
            $receipt = $import->handle($this->currentUser(), $this->branch, $this->form->selectedMenu(), $this->form->contents(), $this->versions, $this->fileHash, $this->requestId);
        } catch (ValidationException $exception) {
            $this->canApply = false;
            $this->dispatch('catalog-transfer-feedback');

            throw $exception;
        } catch (RuntimeException $exception) {
            report($exception);
            $this->addError('form.file', __('menu.csv.errors.save'));
            $this->dispatch('catalog-transfer-feedback');

            return;
        }
        $this->clearPreview();
        $this->form->file = null;
        $this->success = __('menu.csv.imported', ['count' => $receipt->processed_count]);
        $this->dispatch('menu-workspace-dirty', key: 'catalog-transfer', dirty: false);
        $this->dispatch('catalog-transfer-feedback');
        $this->dispatch('menu-catalog-updated');
    }

    public function discardImport(): void
    {
        $this->authorizeBranchAbility('manageMenu');
        $this->clearPreview();
        $this->form->file = null;
        $this->dispatch('menu-workspace-dirty', key: 'catalog-transfer', dirty: false);
    }

    public function downloadSample(): StreamedResponse
    {
        $this->authorizeBranchAbility('manageMenu');
        $menuId = $this->form->selectedMenu();
        $this->csv->menu($this->branch, $menuId);
        $options = $this->csv->options($this->branch, $menuId);
        $contents = $this->csv->sample($options['categories'][0]['id'] ?? 0);

        return response()->streamDownload(static function () use ($contents): void {
            echo $contents;
        }, 'catalog-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportCatalog(bool $next = false): StreamedResponse
    {
        $this->authorizeBranchAbility('manageMenu');
        $this->resetErrorBag('export');
        $this->success = '';
        $menuId = $this->form->selectedMenu();
        $batch = $this->csv->export($this->branch, $menuId, $next && $this->exportHasMore ? $this->exportCursor : 0);
        $this->exportCursor = $batch['last_id'] ?? 0;
        $this->exportHasMore = $batch['has_more'];
        $this->exportSummary = __('menu.csv.exported', ['count' => $batch['count'], 'first' => $batch['first_id'] ?? '—', 'last' => $batch['last_id'] ?? '—']);
        $contents = $batch['contents'];

        return response()->streamDownload(static function () use ($contents): void {
            echo $contents;
        }, 'catalog-'.$menuId.'-'.($batch['first_id'] ?? 0).'-'.($batch['last_id'] ?? 0).'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render(): View
    {
        $this->authorizeBranchAbility('manageMenu');
        $menuId = is_string($this->form->menuId) || is_int($this->form->menuId) ? (int) $this->form->menuId : 0;

        return view('livewire.organizations.brands.branches.menu.catalog-transfer', $this->csv->options($this->branch, $menuId));
    }

    private function clearPreview(): void
    {
        $this->reset(['fileHash', 'requestId', 'versions', 'previewRows', 'rowErrors', 'createCount', 'updateCount', 'canApply', 'success']);
        $this->resetErrorBag();
    }

    protected function catalogData(): CatalogData
    {
        return $this->menuQueries;
    }
}
