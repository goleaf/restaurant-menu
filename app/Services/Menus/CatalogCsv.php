<?php

declare(strict_types=1);

namespace App\Services\Menus;

use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use App\Support\MoneyFormatter;
use App\Support\PlainText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * @phpstan-type CsvRow array{line: int, id: int|null, category_id: int, price: string, name_en: string, description_en: string, name_lt: string, description_lt: string, name_ru: string, description_ru: string}
 * @phpstan-type CsvError array{line: int, message: string}
 * @phpstan-type CsvPreview array{hash: string, rows: list<CsvRow>, errors: list<CsvError>, versions: array<int, string>, create_count: int, update_count: int}
 */
final class CatalogCsv
{
    public const MAX_ROWS = 100;

    public const MAX_BYTES = 1048576;

    public const HEADERS = ['id', 'category_id', 'price', 'name_en', 'description_en', 'name_lt', 'description_lt', 'name_ru', 'description_ru'];

    private const ITEM_COLUMNS = ['id', 'menu_id', 'category_id', 'kitchen_department_id', 'name', 'description', 'price_cents', 'allergens', 'dietary_labels', 'weight', 'volume', 'calories', 'is_available', 'hidden_until', 'availability_version', 'sort_order'];

    /** @return CsvPreview */
    public function preview(Branch $branch, int $menuId, string $contents): array
    {
        $this->menu($branch, $menuId);
        $parsed = $this->parse($contents);
        $parsed['errors'] = [...$parsed['errors'], ...$this->nameCollisions($menuId, $parsed['rows'])];
        $categories = $this->categories($menuId, array_column($parsed['rows'], 'category_id'));
        $items = $this->items($menuId, array_values(array_filter(array_column($parsed['rows'], 'id'))));
        $versions = [];
        $createCount = 0;
        $updateCount = 0;
        foreach ($parsed['rows'] as $row) {
            if (! $categories->has($row['category_id'])) {
                $parsed['errors'][] = ['line' => $row['line'], 'message' => __('menu.csv.errors.category')];
            }
            if ($row['id'] === null) {
                $createCount++;
            } else {
                $updateCount++;
                $item = $items->get($row['id']);
                if (! $item instanceof MenuItem) {
                    $parsed['errors'][] = ['line' => $row['line'], 'message' => __('menu.csv.errors.item')];
                } else {
                    $versions[$item->id] = $item->contentFingerprint();
                }
            }
        }

        return ['hash' => hash('sha256', $contents), ...$parsed, 'versions' => $versions, 'create_count' => $createCount, 'update_count' => $updateCount];
    }

    /** @return array{rows: list<CsvRow>, errors: list<CsvError>} */
    public function parse(string $contents): array
    {
        if (strlen($contents) > self::MAX_BYTES || ! mb_check_encoding($contents, 'UTF-8') || str_contains($contents, "\0")) {
            throw ValidationException::withMessages(['form.file' => __('menu.csv.errors.encoding_size')]);
        }
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new RuntimeException('The CSV temporary stream could not be opened.');
        }
        try {
            fwrite($stream, preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents);
            rewind($stream);
            if (fgetcsv($stream, escape: '') !== self::HEADERS) {
                throw ValidationException::withMessages(['form.file' => __('menu.csv.errors.header')]);
            }
            $rows = [];
            $errors = [];
            $seen = [];
            $record = 1;
            while (($cells = fgetcsv($stream, escape: '')) !== false) {
                $record++;
                if ($cells === [null]) {
                    continue;
                }
                if (count($rows) >= self::MAX_ROWS || $record > self::MAX_ROWS + 1) {
                    throw ValidationException::withMessages(['form.file' => __('menu.csv.errors.rows')]);
                }
                if (count($cells) !== count(self::HEADERS)) {
                    $errors[] = ['line' => $record, 'message' => __('menu.csv.errors.columns')];

                    continue;
                }
                $data = array_combine(self::HEADERS, array_map(fn (?string $cell): string => $this->unescapeCell($cell ?? ''), $cells));
                $rules = [
                    'id' => ['nullable', 'regex:/^[1-9][0-9]{0,17}$/D'],
                    'category_id' => ['required', 'regex:/^[1-9][0-9]{0,17}$/D'],
                    'price' => ['required', 'regex:/^(?:0|[1-9][0-9]{0,5})(?:\.[0-9]{1,2})?$/D', 'numeric', 'max:100000'],
                ];
                $attributes = ['id' => __('menu.csv.fields.id'), 'category_id' => __('menu.csv.fields.category_id'), 'price' => __('menu.csv.fields.price')];
                foreach (['en', 'lt', 'ru'] as $locale) {
                    $data['name_'.$locale] = PlainText::required($data['name_'.$locale], 0, squish: true);
                    $data['description_'.$locale] = PlainText::optional($data['description_'.$locale], 0) ?? '';
                    $rules['name_'.$locale] = ['required', 'string', 'max:180'];
                    $rules['description_'.$locale] = ['nullable', 'string', 'max:1200'];
                    $attributes['name_'.$locale] = __('menu.csv.fields.name', ['locale' => strtoupper($locale)]);
                    $attributes['description_'.$locale] = __('menu.csv.fields.description', ['locale' => strtoupper($locale)]);
                }
                $validator = Validator::make($data, $rules, [], $attributes);
                if ($validator->fails()) {
                    foreach ($validator->errors()->all() as $error) {
                        $errors[] = ['line' => $record, 'message' => $error];
                    }

                    continue;
                }
                $id = $data['id'] === '' ? null : (int) $data['id'];
                if ($id !== null && isset($seen[$id])) {
                    $errors[] = ['line' => $record, 'message' => __('menu.csv.errors.duplicate')];
                }
                if ($id !== null) {
                    $seen[$id] = true;
                }
                $rows[] = ['line' => $record, 'id' => $id, 'category_id' => (int) $data['category_id'],
                    'price' => $data['price'], 'name_en' => $data['name_en'], 'description_en' => $data['description_en'],
                    'name_lt' => $data['name_lt'], 'description_lt' => $data['description_lt'], 'name_ru' => $data['name_ru'], 'description_ru' => $data['description_ru']];
            }
            if ($rows === [] && $errors === []) {
                $errors[] = ['line' => 2, 'message' => __('menu.csv.errors.empty')];
            }

            return ['rows' => $rows, 'errors' => $errors];
        } finally {
            fclose($stream);
        }
    }

    /** @param list<CsvRow> $rows
     * @return list<CsvError>
     */
    private function nameCollisions(int $menuId, array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $updatedIds = array_values(array_filter(array_column($rows, 'id')));
        $existing = MenuItem::query()->select(['category_id', 'name'])->distinct()
            ->where('menu_id', $menuId)->whereNotIn('id', $updatedIds)
            ->where(function (Builder $query) use ($rows): void {
                foreach ($rows as $row) {
                    $query->orWhere(fn (Builder $pair): Builder => $pair->where('category_id', $row['category_id'])->where('name', $row['name_en']));
                }
            })->limit(self::MAX_ROWS)->get();
        $taken = [];
        foreach ($existing as $item) {
            $taken[serialize([$item->category_id, $item->name])] = true;
        }
        $errors = [];
        foreach ($rows as $row) {
            $identity = serialize([$row['category_id'], $row['name_en']]);
            if (isset($taken[$identity])) {
                $errors[] = ['line' => $row['line'], 'message' => __('menu.csv.errors.name_collision')];
            }
            $taken[$identity] = true;
        }

        return $errors;
    }

    public function menu(Branch $branch, int $menuId): Menu
    {
        $menu = Menu::query()->select(['id', 'branch_id', 'name', 'status'])->where('branch_id', $branch->id)->whereKey($menuId)->firstOrFail();
        $menu->setRelation('branch', $branch);

        return $menu;
    }

    /** @param list<int> $ids
     * @return Collection<int, MenuCategory>
     */
    public function categories(int $menuId, array $ids): Collection
    {
        return MenuCategory::query()->select(['id', 'menu_id', 'name'])->where('menu_id', $menuId)->whereIn('id', $ids)->limit(self::MAX_ROWS)->get()->keyBy('id');
    }

    /** @param list<int> $ids
     * @return Collection<int, MenuItem>
     */
    public function items(int $menuId, array $ids): Collection
    {
        return $this->itemQuery($menuId)->whereIn('id', $ids)->limit(self::MAX_ROWS)->get()->keyBy('id');
    }

    /** @return array{menus: list<array{id: int, name: string}>, categories: list<array{id: int, name: string}>} */
    public function options(Branch $branch, int $menuId): array
    {
        $menus = Menu::query()->select(['id', 'name'])->where('branch_id', $branch->id)->orderBy('id')->limit(100)->get();
        $categories = MenuCategory::query()->select(['id', 'name'])->where('menu_id', $menuId)
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id))->orderBy('id')->limit(100)->get();

        return ['menus' => $menus->map(fn (Menu $menu): array => ['id' => $menu->id, 'name' => $menu->name])->all(),
            'categories' => $categories->map(fn (MenuCategory $category): array => ['id' => $category->id, 'name' => $category->name])->all()];
    }

    /** @return array{contents: string, count: int, first_id: int|null, last_id: int|null, has_more: bool} */
    public function export(Branch $branch, int $menuId, int $afterId = 0): array
    {
        $this->menu($branch, $menuId);
        $rows = [];
        $firstId = null;
        $lastId = null;
        $hasMore = false;
        $headerBytes = strlen($this->encode([]));
        $encodedBytes = $headerBytes;
        foreach ($this->itemQuery($menuId)->where('id', '>', max(0, $afterId))->lazyById(self::MAX_ROWS + 1) as $item) {
            if (count($rows) === self::MAX_ROWS) {
                $hasMore = true;
                break;
            }
            $row = [(string) $item->id, (string) $item->category_id, MoneyFormatter::centsToDecimal($item->price_cents)];
            foreach (['en', 'lt', 'ru'] as $locale) {
                $translation = $item->translations->firstWhere('language_code', $locale);
                $row[] = $translation->name ?? ($locale === 'en' ? $item->name : '');
                $row[] = $translation->description ?? ($locale === 'en' && ! $translation instanceof MenuItemTranslation ? ($item->description ?? '') : '');
            }
            $rowBytes = strlen($this->encode([$row])) - $headerBytes;
            if ($encodedBytes + $rowBytes > self::MAX_BYTES) {
                $hasMore = true;
                break;
            }
            $encodedBytes += $rowBytes;
            $firstId ??= $item->id;
            $lastId = $item->id;
            $rows[] = $row;
        }

        return ['contents' => $this->encode($rows), 'count' => count($rows), 'first_id' => $firstId, 'last_id' => $lastId, 'has_more' => $hasMore];
    }

    public function sample(int $categoryId): string
    {
        return $this->encode([['', (string) $categoryId, '12.50', 'Vegetable soup', "Seasonal vegetables.\nServed warm.", 'Daržovių sriuba', 'Sezoninės daržovės.', 'Овощной суп', 'Сезонные овощи.']]);
    }

    /** @param list<list<string>> $rows */
    private function encode(array $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new RuntimeException('The CSV temporary stream could not be opened.');
        }
        try {
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, self::HEADERS, escape: '');
            foreach ($rows as $row) {
                fputcsv($stream, array_map($this->escapeCell(...), $row), escape: '');
            }
            rewind($stream);
            $contents = stream_get_contents($stream);
            if ($contents === false) {
                throw new RuntimeException('The CSV temporary stream could not be read.');
            }

            return $contents;
        } finally {
            fclose($stream);
        }
    }

    private function escapeCell(string $value): string
    {
        return preg_match('/^(?:\x27|\s*[=+@-]|[\t\r\n])/u', $value) === 1 ? "'".$value : $value;
    }

    private function unescapeCell(string $value): string
    {
        return str_starts_with($value, "'") && $this->escapeCell(substr($value, 1)) !== substr($value, 1) ? substr($value, 1) : $value;
    }

    /** @return Builder<MenuItem> */
    private function itemQuery(int $menuId): Builder
    {
        return MenuItem::query()->select(self::ITEM_COLUMNS)->where('menu_id', $menuId)
            ->with(['translations' => fn ($query) => $query->select(['id', 'menu_item_id', 'language_code', 'name', 'description'])->orderBy('language_code')]);
    }
}
