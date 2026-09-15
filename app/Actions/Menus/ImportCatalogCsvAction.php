<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Enums\MenuOperationKind;
use App\Enums\MenuOperationPhase;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuOperation;
use App\Models\User;
use App\Services\Menus\CatalogCsv;
use App\Support\MoneyFormatter;
use App\Support\PlainText;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** @phpstan-import-type CsvRow from CatalogCsv */
final readonly class ImportCatalogCsvAction
{
    public function __construct(
        private CatalogCsv $csv,
        private EnsureMenuOperationAccessAction $access,
        private CreateMenuItemAction $createItem,
        private UpdateMenuItemAction $updateItem,
    ) {}

    /** @param array<int, string> $expectedVersions */
    public function handle(User $actor, Branch $branch, int $menuId, string $contents, array $expectedVersions, string $expectedHash, string $requestId): MenuOperation
    {
        if (strlen($contents) > CatalogCsv::MAX_BYTES || ! hash_equals(hash('sha256', $contents), $expectedHash)) {
            throw ValidationException::withMessages(['form.file' => __('menu.csv.errors.changed_file')]);
        }

        return DB::transaction(function () use ($actor, $branch, $menuId, $contents, $expectedVersions, $expectedHash, $requestId): MenuOperation {
            $branch = $this->access->handle($actor, $branch, $requestId);
            $actor = $actor->fresh();
            if (! $actor instanceof User) {
                throw new AuthorizationException;
            }
            $menu = $this->csv->menu($branch, $menuId);
            Gate::forUser($actor)->authorize('update', $menu);
            $receipt = MenuOperation::query()->where('request_id', $requestId)->lockForUpdate()->first();
            if ($receipt instanceof MenuOperation) {
                $this->access->assertOwner($receipt, $actor, $branch);
                if ($receipt->kind !== MenuOperationKind::CatalogImport || $receipt->target_id !== $menuId
                    || ($receipt->payload['file_hash'] ?? null) !== $expectedHash || $receipt->completed_at === null) {
                    throw new AuthorizationException;
                }
                $this->authorizeChanges($actor, $menu, ($receipt->payload['changes_price'] ?? false) === true, ($receipt->payload['creates_items'] ?? false) === true);

                return $receipt;
            }

            if (MenuOperation::query()->where('active_scope', 'menu:'.$menuId)->exists()) {
                throw ValidationException::withMessages(['form.file' => __('menu.operations.errors.already_running')]);
            }
            $preview = $this->csv->preview($branch, $menuId, $contents);
            if ($preview['errors'] !== []) {
                throw ValidationException::withMessages(['form.file' => __('menu.csv.errors.fix_rows')]);
            }
            if ($preview['versions'] !== $expectedVersions) {
                throw ValidationException::withMessages(['form.file' => __('menu.csv.errors.stale')]);
            }
            $categories = $this->csv->categories($menuId, array_column($preview['rows'], 'category_id'));
            $items = $this->csv->items($menuId, array_values(array_filter(array_column($preview['rows'], 'id'))));
            $changesPrice = false;
            foreach ($preview['rows'] as $row) {
                $changesPrice = $changesPrice || MoneyFormatter::decimalToCents($row['price']) !== ($items->get($row['id'])->price_cents ?? 0);
            }
            $this->authorizeChanges($actor, $menu, $changesPrice, $preview['create_count'] > 0);

            foreach ($preview['rows'] as $row) {
                $item = $row['id'] === null ? null : $items->get($row['id']);
                $category = $categories->get($row['category_id']);
                if (! $category instanceof MenuCategory || ($row['id'] !== null && ! $item instanceof MenuItem)) {
                    throw ValidationException::withMessages(['form.file' => __('menu.csv.errors.stale')]);
                }
                $data = $this->attributes($row, $item);
                $saved = $item instanceof MenuItem
                    ? $this->updateItem->handle($actor, $branch, $item, $menu, $category, $item->kitchen_department_id, $data, $expectedVersions[$item->id], preserveExistingDepartment: true)
                    : $this->createItem->handle($actor, $branch, $menu, $category, null, $data);
                if (! $saved->exists) {
                    throw new RuntimeException('The catalogue import item write was cancelled.');
                }
                foreach ($data['translations'] as $locale => $translation) {
                    $stored = $saved->translations->firstWhere('language_code', $locale);
                    if ($stored?->name !== PlainText::required($translation['name'], 180, squish: true)
                        || $stored->description !== PlainText::optional($translation['description'], 1200)) {
                        throw new RuntimeException('The catalogue import translation write was cancelled.');
                    }
                }
            }

            $receipt = new MenuOperation;
            $receipt->forceFill([
                'request_id' => $requestId, 'branch_id' => $branch->id, 'actor_user_id' => $actor->id,
                'menu_id' => $menuId, 'kind' => MenuOperationKind::CatalogImport, 'target_id' => $menuId,
                'phase' => MenuOperationPhase::Completed, 'processed_count' => count($preview['rows']),
                'pending_cleanup' => [], 'completed_at' => now(),
                'payload' => ['file_hash' => $expectedHash, 'changes_price' => $changesPrice, 'creates_items' => $preview['create_count'] > 0,
                    'created' => $preview['create_count'], 'updated' => $preview['update_count']],
            ]);
            if ($receipt->save() !== true) {
                throw new RuntimeException('The catalogue import receipt could not be saved.');
            }

            return $receipt;
        });
    }

    private function authorizeChanges(User $actor, Menu $menu, bool $changesPrice, bool $createsItems): void
    {
        if ($changesPrice) {
            Gate::forUser($actor)->authorize('changePrice', $menu);
        }
        if ($createsItems) {
            Gate::forUser($actor)->authorize('changeAvailability', $menu);
        }
    }

    /**
     * @param  CsvRow  $row
     * @return array{name: string, description: string|null, price: string, weight: string|null, volume: string|null, calories: int|null, is_available: bool, hidden_until: string|null, sort_order: int, translations: array<string, array{name: string, description: string}>}
     */
    private function attributes(array $row, ?MenuItem $item): array
    {
        $translations = [];
        foreach (['en', 'lt', 'ru'] as $locale) {
            $translations[$locale] = ['name' => $row['name_'.$locale], 'description' => $row['description_'.$locale]];
        }

        return ['name' => $row['name_en'], 'description' => $row['description_en'], 'price' => $row['price'],
            'weight' => $item?->weight === null ? null : (string) $item->weight,
            'volume' => $item?->volume === null ? null : (string) $item->volume,
            'calories' => $item?->calories, 'is_available' => $item->is_available ?? false,
            'hidden_until' => $item?->hidden_until?->toIso8601String(), 'sort_order' => $item->sort_order ?? 0,
            'translations' => $translations];
    }
}
