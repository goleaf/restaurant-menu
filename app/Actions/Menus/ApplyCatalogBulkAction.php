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
use App\Services\Menus\CatalogData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class ApplyCatalogBulkAction
{
    public function __construct(private CatalogData $catalog, private EnsureMenuOperationAccessAction $access, private SetMenuItemsRestrictionAction $restrictions) {}

    /**
     * @param  array<array-key, mixed>  $selection
     * @param  array<string, mixed>  $filters
     */
    public function handle(User $actor, Branch $branch, array $selection, array $filters, mixed $operation, mixed $categoryId, string $requestId): int
    {
        Validator::make(compact('selection', 'filters', 'operation', 'categoryId', 'requestId'), [
            'selection' => ['required', 'array', 'list', 'min:1', 'max:24'],
            'selection.*' => ['required', 'array:id,version'],
            'selection.*.id' => ['bail', 'required', 'numeric', 'integer', 'min:1', 'distinct'],
            'selection.*.version' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'filters' => ['required', 'array:search,availability,menu,quality,page'],
            'filters.search' => ['present', 'string', 'max:200'],
            'filters.availability' => ['present', Rule::in(['', 'available', 'unavailable'])],
            'filters.menu' => ['present', 'nullable', 'numeric', 'integer', 'min:1'],
            'filters.quality' => ['present', Rule::in(['', 'photo', 'description', 'translations', 'publication'])],
            'filters.page' => ['required', 'numeric', 'integer', 'between:1,10000'],
            'operation' => ['required', 'string', Rule::in(['available', 'unavailable', 'move', 'archive'])],
            'categoryId' => ['bail', Rule::requiredIf($operation === 'move'), 'nullable', 'numeric', 'integer', 'min:1'],
            'requestId' => ['required', 'uuid'],
        ])->validate();

        $selection = array_map(fn (array $row): array => ['id' => (int) $row['id'], 'version' => $row['version']], $selection);
        usort($selection, fn (array $left, array $right): int => $left['id'] <=> $right['id']);
        $targetCategoryId = $operation === 'move' ? (int) $categoryId : null;
        $filters = ['search' => trim($filters['search']), 'availability' => $filters['availability'], 'menu' => (string) $filters['menu'], 'quality' => $filters['quality'], 'page' => (int) $filters['page']];
        $digest = hash('sha256', serialize([$selection, $filters, $operation, $targetCategoryId]));

        return DB::transaction(function () use ($actor, $branch, $selection, $filters, $operation, $targetCategoryId, $requestId, $digest): int {
            $actor = $actor->fresh() ?? throw new AuthorizationException;
            $branch = $this->access->handle($actor, $branch, $requestId);
            $ids = array_column($selection, 'id');
            $items = MenuItem::withTrashed()->select(['id', 'menu_id', 'category_id', 'kitchen_department_id', 'name', 'description', 'price_cents', 'allergens', 'dietary_labels', 'weight', 'volume', 'calories', 'is_available', 'hidden_until', 'availability_version', 'sort_order', 'deleted_at'])
                ->with(['translations:id,menu_item_id,language_code,name,description'])
                ->whereIn('id', $ids)->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id))->lockForUpdate()->get()->keyBy('id');
            if ($items->count() !== count($ids)) {
                throw new AuthorizationException;
            }
            $menus = Menu::query()->select(['id', 'branch_id'])->whereIn('id', $items->pluck('menu_id')->unique())->where('branch_id', $branch->id)->get()->keyBy('id');
            foreach ($items as $item) {
                $menu = $menus->get($item->menu_id) ?? throw new AuthorizationException;
                $menu->setRelation('branch', $branch);
                Gate::forUser($actor)->authorize(in_array($operation, ['available', 'unavailable'], true) ? 'changeAvailability' : 'update', $menu);
            }

            $receipt = MenuOperation::query()->where('request_id', $requestId)->lockForUpdate()->first();
            if ($receipt !== null) {
                $this->access->assertOwner($receipt, $actor, $branch);
                if ($receipt->kind !== MenuOperationKind::BulkItems || ($receipt->payload['digest'] ?? null) !== $digest || $receipt->completed_at === null) {
                    throw new AuthorizationException;
                }

                return $receipt->processed_count;
            }

            $pageIds = $this->catalog->filteredItemQuery($branch, $filters['search'], $filters['availability'], $filters['menu'], $filters['quality'])
                ->withoutEagerLoads()->offset(($filters['page'] - 1) * 24)->limit(24)->pluck('id')->all();
            if (array_diff($ids, $pageIds) !== []) {
                throw ValidationException::withMessages(['bulkSelection' => __('menu.bulk.selection_changed')]);
            }
            foreach ($selection as $selected) {
                $item = $items->get($selected['id']);
                if ($item === null || $item->trashed() || ! hash_equals($item->contentFingerprint(), $selected['version'])) {
                    throw ValidationException::withMessages(['bulkSelection' => __('menu.bulk.conflict')]);
                }
            }
            if (MenuOperation::query()->whereIn('active_scope', $menus->keys()->map(fn (int $id): string => 'menu:'.$id))->exists()) {
                throw ValidationException::withMessages(['bulkSelection' => __('menu.operations.errors.already_running')]);
            }

            if ($operation === 'move') {
                $category = MenuCategory::query()->select(['id', 'menu_id'])->whereKey($targetCategoryId)->where('menu_id', $filters['menu'])->where('is_active', true)->first();
                if ($category === null || $items->contains(fn (MenuItem $item): bool => $item->menu_id !== $category->menu_id)) {
                    throw ValidationException::withMessages(['bulk.categoryId' => __('menu.bulk.category_invalid')]);
                }
                $names = $items->pluck('name')->all();
                if (count(array_unique($names)) !== count($names) || MenuItem::query()->where('menu_id', $category->menu_id)->where('category_id', $category->id)->whereNotIn('id', $ids)->whereIn('name', $names)->exists()) {
                    throw ValidationException::withMessages(['bulk.categoryId' => __('menu.bulk.name_collision')]);
                }
            }

            if (in_array($operation, ['available', 'unavailable'], true)) {
                $this->restrictions->handle($actor, $branch, $items->map(fn (MenuItem $item): array => ['id' => $item->id, 'version' => $item->availability_version])->values()->all(),
                    $operation === 'available' ? 'resume' : 'stop', null, $branch->timezone, $requestId);
            }
            foreach ($items as $item) {
                if (in_array($operation, ['available', 'unavailable'], true)) {
                    continue;
                }
                $saved = $operation === 'archive' ? $item->delete() : $item->update(['category_id' => $targetCategoryId]);
                if ($saved !== true) {
                    throw new RuntimeException('A selected catalogue item could not be saved.');
                }
            }
            $receipt = new MenuOperation;
            $receipt->forceFill(['request_id' => $requestId, 'branch_id' => $branch->id, 'actor_user_id' => $actor->id, 'menu_id' => $items->firstOrFail()->menu_id,
                'kind' => MenuOperationKind::BulkItems, 'target_id' => $ids[0], 'phase' => MenuOperationPhase::Completed, 'processed_count' => count($ids), 'payload' => ['digest' => $digest], 'completed_at' => now()]);
            if ($receipt->save() !== true) {
                throw new RuntimeException('The catalogue operation receipt could not be saved.');
            }

            return count($ids);
        });
    }
}
