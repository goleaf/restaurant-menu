<?php

declare(strict_types=1);

namespace App\Services\Availability;

use App\Actions\Branches\GetBranchOpeningStatusAction;
use App\Models\Branch;
use App\Models\BranchOpeningHour;
use App\Models\BranchScheduleException;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuAvailabilitySchedule;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Models\User;
use App\Services\Branches\BranchSettingsQueryService;
use App\Support\Availability\AvailabilityResult;
use App\Support\LocalizedDateFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

final class AvailabilityWorkspaceQuery
{
    public function __construct(private readonly AvailabilityEvaluator $evaluator, private readonly BranchSettingsQueryService $settings) {}

    /** @return array{organization:Organization,brand:Brand,branch:Branch} */
    public function context(int $organizationId, int $brandId, int $branchId): array
    {
        $organization = Organization::query()->select(['id', 'name'])->findOrFail($organizationId);
        $brand = Brand::query()->select(['id', 'organization_id', 'name'])->findOrFail($brandId);
        $branch = Branch::query()->select([
            'id', 'organization_id', 'brand_id', 'name', 'timezone', 'is_active', 'is_temporarily_closed',
            'temporary_closed_reason', 'temporary_closed_until', 'pause_version', 'opening_hours_version', 'deleted_at',
        ])->findOrFail($branchId);
        abort_unless($brand->organization_id === $organizationId && $branch->organization_id === $organizationId && $branch->brand_id === $brandId, 403);

        return compact('organization', 'brand', 'branch');
    }

    /** @param array{search:string,state:string,page:int} $filters @return LengthAwarePaginator<int,array<string,mixed>> */
    public function items(Branch $branch, array $filters, ?int $menuId, CarbonImmutable $at): LengthAwarePaginator
    {
        $query = $this->itemQuery($branch)->when($menuId !== null, fn (Builder $query) => $query->where('menu_id', $menuId));
        if ($filters['search'] !== '') {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }
        if ($filters['state'] === 'stopped') {
            $query->where('is_available', false);
        } elseif ($filters['state'] === 'hidden') {
            $query->where('hidden_until', '>', $at);
        } elseif ($filters['state'] === 'unrestricted') {
            $query->where('is_available', true)->where(fn (Builder $query) => $query->whereNull('hidden_until')->orWhere('hidden_until', '<=', $at));
        }
        $page = $query->orderBy('name')->orderBy('id')->paginate(20, ['*'], 'page', $filters['page'])->withQueryString();
        $results = $this->evaluator->items($page->getCollection(), $at);

        return new LengthAwarePaginator($page->getCollection()->map(fn (MenuItem $item): array => $this->itemRow($item, $at, result: $results[$item->id])), $page->total(), $page->perPage(), $page->currentPage(), $page->getOptions());
    }

    public function item(Branch $branch, int $id): MenuItem
    {
        return $this->itemQuery($branch)->findOrFail($id);
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int,MenuItem>
     */
    public function selectedItems(Branch $branch, array $ids): Collection
    {
        abort_if(count($ids) > 100, 422);

        return $this->itemQuery($branch)->whereKey($ids)->limit(100)->get()->keyBy('id');
    }

    /** @return array<string,mixed> */
    public function itemRow(MenuItem $item, CarbonImmutable $at, ?User $actor = null, ?AvailabilityResult $result = null): array
    {
        $result = $this->present(($result ?? $this->evaluator->item($item, $at))->toArray(), $item->menu->branch->timezone);
        if ($actor !== null) {
            $result = $this->repairLinks($item, $actor, $result);
        }

        $category = $item->getRelation('category');

        return ['id' => $item->id, 'name' => $item->name, 'menu_name' => $item->menu->name, 'category_name' => $category instanceof MenuCategory ? $category->name : null,
            'version' => (int) $item->availability_version, 'stopped' => ! $item->is_available,
            'hidden_until' => $item->isTemporarilyHidden($at) ? LocalizedDateFormatter::dateTime($item->hidden_until?->setTimezone($item->menu->branch->timezone)) : null,
            'result' => $result];
    }

    /** @param array<string,mixed> $result @return array<string,mixed> */
    public function present(array $result, string $timezone): array
    {
        foreach (['evaluated_at', 'next_change_at', 'next_orderable_at'] as $field) {
            if (is_string($result[$field] ?? null)) {
                $result[$field] = LocalizedDateFormatter::dateTime(CarbonImmutable::parse($result[$field])->setTimezone($timezone));
            }
        }

        return $result;
    }

    /** @return list<array{id:int,name:string}> */
    public function menuOptions(Branch $branch, string $search = '', ?Menu $selected = null): array
    {
        $options = Menu::query()->select(['id', 'name'])->where('branch_id', $branch->id)
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')->orderBy('id')->limit(20)->get()->map(fn (Menu $menu): array => ['id' => $menu->id, 'name' => $menu->name])->all();
        if ($selected !== null && ! in_array($selected->id, array_column($options, 'id'), true)) {
            array_unshift($options, ['id' => $selected->id, 'name' => $selected->name]);
        }

        return $options;
    }

    public function menu(Branch $branch, int $id): Menu
    {
        return $this->findMenu($branch, $id) ?? abort(404);
    }

    public function findMenu(Branch $branch, int $id): ?Menu
    {
        return Menu::query()->select(['id', 'branch_id', 'name', 'status', 'schedule_is_closed', 'schedule_version', 'deleted_at'])
            ->where('branch_id', $branch->id)->with(['availabilitySchedules:id,menu_id,day_of_week,starts_at,ends_at'])->find($id)?->setRelation('branch', $branch);
    }

    /** @return array{configured:bool,days:list<array<string,mixed>>} */
    public function branchDays(Branch $branch): array
    {
        return $this->settings->openingHours($branch);
    }

    /** @return list<array<string,mixed>> */
    public function menuDays(Menu $menu): array
    {
        $days = [];
        foreach (GetBranchOpeningStatusAction::dayLabels() as $number => $label) {
            $rows = $menu->availabilitySchedules->where('day_of_week', $number);
            $intervals = $rows->map(fn (MenuAvailabilitySchedule $row): array => ['opens_at' => substr($row->starts_at, 0, 5), 'closes_at' => substr($row->ends_at, 0, 5)])->values()->all();
            $days[] = ['day_of_week' => $number, 'label' => $label, 'is_closed' => $intervals === [], 'intervals' => $intervals];
        }

        return $days;
    }

    /** @return list<array<string,mixed>> */
    public function exceptions(Branch $branch): array
    {
        return BranchScheduleException::query()->select(['id', 'branch_id', 'local_date', 'is_closed', 'intervals'])
            ->where('branch_id', $branch->id)->orderBy('local_date')->limit(366)->get()
            ->map(fn (BranchScheduleException $row): array => $row->only(['local_date', 'is_closed', 'intervals']))->all();
    }

    /** @param list<array<string,mixed>> $days */
    public function projectedHours(Branch $branch, array $days, bool $configured): Branch
    {
        $projected = clone $branch;
        $rows = [];
        if ($configured) {
            foreach ($days as $day) {
                if ($day['is_closed']) {
                    $rows[] = new BranchOpeningHour(['branch_id' => $branch->id, 'day_of_week' => $day['day_of_week'], 'is_closed' => true, 'sort_order' => 0]);
                } else {
                    foreach ($day['intervals'] as $index => $interval) {
                        $rows[] = new BranchOpeningHour(['branch_id' => $branch->id, 'day_of_week' => $day['day_of_week'], 'is_closed' => false, ...$interval, 'sort_order' => $index]);
                    }
                }
            }
        }
        $projected->setRelation('openingHours', new Collection($rows));

        return $projected;
    }

    /** @param list<array<string,mixed>> $intervals */
    public function projectedMenu(Menu $menu, array $intervals, bool $closed): Menu
    {
        $projected = clone $menu;
        $projected->setAttribute('schedule_is_closed', $closed);
        $projected->setRelation('availabilitySchedules', new Collection(array_map(fn (array $interval): MenuAvailabilitySchedule => new MenuAvailabilitySchedule(['menu_id' => $menu->id, ...$interval]), $intervals)));

        return $projected;
    }

    /** @return Builder<MenuItem> */
    private function itemQuery(Branch $branch): Builder
    {
        return MenuItem::query()->select(['id', 'menu_id', 'category_id', 'name', 'is_available', 'hidden_until', 'availability_version', 'deleted_at'])
            ->whereHas('menu', fn (Builder $query) => $query->where('branch_id', $branch->id))
            ->with([...AvailabilityEvaluator::itemRelations(),
                'category' => fn ($query) => $query->select(['id', 'menu_id', 'parent_id', 'name', 'is_active', 'deleted_at'])]);
    }

    /** @param array<string,mixed> $result @return array<string,mixed> */
    private function repairLinks(MenuItem $item, User $actor, array $result): array
    {
        $branch = $item->menu->branch;
        $gate = Gate::forUser($actor);
        $settings = $gate->allows('manageSettings', $branch);
        $menu = $gate->allows('manageMenu', $branch);
        $parameters = [$branch->organization_id, $branch->brand_id, $branch->id];
        foreach ($result['reasons'] as &$reason) {
            $reason['repair_url'] = match (true) {
                $settings && $reason['code'] === 'branch_paused' => route('organizations.brands.branches.availability.index', [...$parameters, 'section' => 'now']),
                $settings && $reason['code'] === 'branch_schedule_closed' => route('organizations.brands.branches.availability.index', [...$parameters, 'section' => 'schedules']),
                $menu && $reason['code'] === 'menu_schedule_closed' => route('organizations.brands.branches.availability.index', [...$parameters, 'section' => 'schedules', 'menu' => $item->menu_id]),
                $menu && $reason['code'] === 'required_options_unavailable' => route('organizations.brands.branches.menu.dish.edit', [...$parameters, 'item' => $item->id, 'section' => 'modifiers']),
                $menu && $reason['code'] === 'variants_unavailable' => route('organizations.brands.branches.menu.dish.edit', [...$parameters, 'item' => $item->id, 'section' => 'variants']),
                $menu && in_array($reason['code'], ['menu_unpublished', 'category_inactive'], true) => route('organizations.brands.branches.menu.index', [...$parameters, 'section' => 'catalog', 'menu' => $item->menu_id, 'q' => $item->name]),
                default => null,
            };
        }
        unset($reason);

        return $result;
    }
}
