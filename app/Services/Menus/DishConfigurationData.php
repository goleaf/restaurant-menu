<?php

declare(strict_types=1);

namespace App\Services\Menus;

use App\Enums\MenuOperationKind;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\MenuOperation;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class DishConfigurationData
{
    public const OPTIONS_PER_PAGE = 10;

    /** @return LengthAwarePaginator<int, MenuItemVariant> */
    public function variants(Branch $branch, int $itemId): LengthAwarePaginator
    {
        return MenuItemVariant::query()->select(['id', 'menu_item_id', 'type', 'name', 'price_cents', 'weight', 'volume', 'is_default', 'is_available', 'sort_order'])
            ->where('menu_item_id', $itemId)->whereHas('item', fn ($query) => $query->whereNull('menu_items.deleted_at')
            ->whereHas('menu', fn ($menu) => $menu->whereNull('menus.deleted_at')->where('branch_id', $branch->id)))
            ->with('translations:id,menu_item_variant_id,language_code,name')->orderByDesc('is_default')->orderBy('sort_order')->orderBy('name')->orderBy('id')
            ->paginate(12, pageName: 'dishVariantsPage');
    }

    public function replayedResultId(User $actor, Branch $branch, string $requestId, MenuOperationKind $kind): ?int
    {
        $operation = MenuOperation::query()->select('payload')->where('request_id', $requestId)
            ->where('actor_user_id', $actor->id)->where('branch_id', $branch->id)->where('kind', $kind)->whereNotNull('completed_at')->first();
        $id = $operation?->payload['result']['id'] ?? null;

        return is_int($id) ? $id : null;
    }

    public function isAttached(Branch $branch, int $itemId, int $groupId): bool
    {
        return $this->item($branch, $itemId)->modifierGroups()->whereKey($groupId)->exists();
    }

    /** @return LengthAwarePaginator<int,ModifierGroup> */
    public function groups(Branch $branch, ?int $itemId, string $search = '', ?int $optionsGroupId = null, int $optionsPage = 1): LengthAwarePaginator
    {
        $groups = ModifierGroup::query()->select(['id', 'branch_id', 'name', 'is_required', 'min_select', 'max_select', 'sort_order', 'content_version'])
            ->where('branch_id', $branch->id)
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($itemId !== null, fn ($query) => $query->whereHas('items', fn ($items) => $items->whereKey($itemId)))
            ->with(['translations:id,modifier_group_id,language_code,name',
                'options' => fn ($query) => $query->select(['id', 'modifier_group_id', 'name', 'price_delta_cents', 'is_available', 'sort_order'])
                    ->when($optionsGroupId !== null && $optionsPage > 1, fn ($query) => $query->where('modifier_group_id', '!=', $optionsGroupId))
                    ->limit(self::OPTIONS_PER_PAGE)->with('translations:id,modifier_option_id,language_code,name')])
            ->withCount(['items' => fn ($query) => $query->withTrashed()->whereHas('menu', fn ($menu) => $menu->where('branch_id', $branch->id)), 'options',
                'options as available_options_count' => fn ($query) => $query->where('is_available', true)])
            ->orderBy('sort_order')->orderBy('name')->orderBy('id')->paginate(5, pageName: 'modifierGroupsPage');
        $selected = $groups->getCollection()->firstWhere('id', $optionsGroupId);
        if ($selected instanceof ModifierGroup && $optionsPage > 1) {
            $selected->setRelation('options', $selected->options()->select(['id', 'modifier_group_id', 'name', 'price_delta_cents', 'is_available', 'sort_order'])
                ->offset(($optionsPage - 1) * self::OPTIONS_PER_PAGE)->limit(self::OPTIONS_PER_PAGE)->with('translations:id,modifier_option_id,language_code,name')->get());
        }

        return $groups;
    }

    /** @return list<array{value:string,label:string}> */
    public function groupOptions(Branch $branch, string $search, ?int $itemId = null): array
    {
        return ModifierGroup::query()->select(['id', 'name'])->where('branch_id', $branch->id)
            ->when($itemId !== null, fn ($query) => $query->whereHas('items', fn ($items) => $items->whereKey($itemId)))
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')->orderBy('id')->limit(20)->get()
            ->map(fn (ModifierGroup $group): array => ['value' => (string) $group->id, 'label' => $group->name])->all();
    }

    public function groupUses(Branch $branch, ModifierGroup $group): int
    {
        return $group->items()->withTrashed()->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id))->count();
    }

    public function item(Branch $branch, int $id): MenuItem
    {
        return MenuItem::query()->select(['id', 'menu_id', 'category_id', 'name', 'price_cents', 'variants_version', 'modifier_links_version'])
            ->whereHas('menu', fn ($query) => $query->whereNull('menus.deleted_at')->where('branch_id', $branch->id))
            ->whereKey($id)->firstOrFail();
    }

    public function group(Branch $branch, int $id, bool $withOptions = false): ModifierGroup
    {
        return ModifierGroup::query()->select(['id', 'branch_id', 'name', 'is_required', 'min_select', 'max_select', 'sort_order', 'content_version'])
            ->where('branch_id', $branch->id)->whereKey($id)
            ->with('translations:id,modifier_group_id,language_code,name')
            ->when($withOptions, fn ($query) => $query->with(['options' => fn ($options) => $options
                ->select(['id', 'modifier_group_id', 'name', 'price_delta_cents', 'is_available', 'sort_order'])
                ->limit(51)->with('translations:id,modifier_option_id,language_code,name')]))->firstOrFail();
    }

    public function option(Branch $branch, int $id): ModifierOption
    {
        return ModifierOption::query()->select(['id', 'modifier_group_id', 'name', 'price_delta_cents', 'is_available', 'sort_order'])
            ->whereHas('group', fn ($query) => $query->where('branch_id', $branch->id))
            ->whereKey($id)->with('translations:id,modifier_option_id,language_code,name')->firstOrFail();
    }
}
