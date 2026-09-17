<?php

declare(strict_types=1);

namespace App\Services\Availability;

use App\Actions\Branches\GetBranchOpeningStatusAction;
use App\Actions\Menus\GetMenuAvailabilityStatusAction;
use App\Enums\MenuStatus;
use App\Enums\OrganizationSubscriptionStatus;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Support\Availability\AvailabilityReason;
use App\Support\Availability\AvailabilityResult;
use Carbon\CarbonImmutable;
use Closure;
use InvalidArgumentException;

final class AvailabilityEvaluator
{
    public function __construct(
        private readonly GetBranchOpeningStatusAction $opening,
        private readonly GetMenuAvailabilityStatusAction $menuOpening,
    ) {}

    /** @return array<array-key, mixed> */
    public static function itemRelations(): array
    {
        return [
            'menu' => fn ($query) => $query->select(['id', 'branch_id', 'name', 'status', 'schedule_version', 'schedule_is_closed', 'deleted_at']),
            'menu.availabilitySchedules' => fn ($query) => $query->select(['id', 'menu_id', 'day_of_week', 'starts_at', 'ends_at']),
            'menu.branch' => fn ($query) => $query->select(self::branchColumns()),
            'menu.branch.openingHours', 'menu.branch.scheduleExceptions',
            'menu.branch.organization:id,deleted_at', 'menu.branch.organization.subscription:id,organization_id,status',
            'menu.branch.brand:id,organization_id,deleted_at',
            'category' => fn ($query) => $query->select(['id', 'menu_id', 'is_active', 'deleted_at']),
            'variants' => fn ($query) => $query->select(['id', 'menu_item_id', 'is_available']),
            'modifierGroups' => fn ($query) => $query->select(['modifier_groups.id', 'modifier_groups.branch_id', 'modifier_groups.is_required', 'modifier_groups.min_select', 'modifier_groups.max_select']),
            'modifierGroups.options' => fn ($query) => $query->select(['id', 'modifier_group_id', 'is_available']),
        ];
    }

    /** @return list<string> */
    public static function branchColumns(): array
    {
        return ['id', 'organization_id', 'brand_id', 'name', 'is_active', 'timezone', 'is_temporarily_closed',
            'temporary_closed_reason', 'temporary_closed_until', 'pause_version', 'opening_hours_version', 'deleted_at'];
    }

    public function branch(Branch $branch, CarbonImmutable $at): AvailabilityResult
    {
        return $this->scanBranch($branch, $at, false)['availability'];
    }

    /** @return array{availability:AvailabilityResult,menu_available:bool,routing_ready:bool} */
    public function branchSummary(Branch $branch, CarbonImmutable $at): array
    {
        return $this->scanBranch($branch, $at, true);
    }

    /** @return array{availability:AvailabilityResult,menu_available:bool,routing_ready:bool} */
    private function scanBranch(Branch $branch, CarbonImmutable $at, bool $includeRouting): array
    {
        $base = $this->branchRestrictions($branch, $at);
        if (! $base->visible) {
            return ['availability' => $base, 'menu_available' => false, 'routing_ready' => false];
        }
        if (! $includeRouting && $base->primaryCode() === 'branch_paused' && $branch->temporary_closed_until === null) {
            return ['availability' => new AvailabilityResult('branch', true, false, false, $base->reasons, $at, $base->nextChangeAt),
                'menu_available' => false, 'routing_ready' => false];
        }
        $nextChange = $base->nextChangeAt;
        $nextOrderable = null;
        $acceptsOrders = false;
        $menuAvailable = false;
        $unrouted = false;
        $resolveMenu = $this->menuResolver();
        $items = MenuItem::query()->select(['id', 'menu_id', 'category_id', 'is_available', 'hidden_until', 'deleted_at'])
            ->where('is_available', true)
            ->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id)->where('status', MenuStatus::Active->value))
            ->with(self::itemRelations())
            ->when($includeRouting, fn ($query) => $query->addSelect('kitchen_department_id')->with('kitchenDepartment:id,is_active'))
            ->lazyById(100);
        foreach ($items as $item) {
            $item->menu->setRelation('branch', $branch);
            $decision = $this->itemDecision($item, $at, $resolveMenu);
            $nextChange = $this->earliest($nextChange, $decision->nextChangeAt);
            $nextOrderable = $this->earliest($nextOrderable, $decision->nextOrderableAt);
            $acceptsOrders = $acceptsOrders || $decision->acceptsNewOrders;
            if ($includeRouting) {
                $eligible = $decision->visible && $decision->configurable
                    && ! in_array('menu_schedule_closed', array_column($decision->reasons, 'code'), true);
                $menuAvailable = $menuAvailable || $eligible;
                $unrouted = $unrouted || ($eligible && ($item->kitchenDepartment === null || ! $item->kitchenDepartment->is_active));
            } elseif ($acceptsOrders) {
                break;
            }
        }
        if ($acceptsOrders) {
            $availability = new AvailabilityResult('branch', true, true, true, [], $at, $nextChange, $at);
        } else {
            $reasons = $base->reasons;
            if ($reasons === []) {
                $reasons[] = new AvailabilityReason('no_orderable_items', 'menu');
            }
            $availability = new AvailabilityResult('branch', true, false, false, $reasons, $at, $nextChange, $nextOrderable);
        }

        return ['availability' => $availability, 'menu_available' => $menuAvailable, 'routing_ready' => $menuAvailable && ! $unrouted];
    }

    public function menu(Menu $menu, CarbonImmutable $at): AvailabilityResult
    {
        $menu->loadMissing(['branch' => fn ($query) => $query->select(self::branchColumns()), 'availabilitySchedules']);
        $branch = $this->branchRestrictions($menu->branch, $at);
        $reasons = $branch->reasons;
        $visible = $branch->visible && ! $menu->trashed() && $menu->status === MenuStatus::Active;
        if (! $visible && $branch->visible) {
            $reasons[] = new AvailabilityReason('menu_unpublished', 'menu', $menu->id);
        }
        $temporal = $this->menuOpening->handle($menu, $at);
        if ($visible && ! $temporal['is_available']) {
            $codes = $temporal['reason_codes'];
            foreach ($codes as $code) {
                if (in_array($code, ['branch_paused', 'branch_schedule_closed', 'branch_inactive'], true)
                    && in_array($code, array_column($reasons, 'code'), true)) {
                    continue;
                }
                if ($code !== 'branch_schedule_unconfigured') {
                    $reasons[] = new AvailabilityReason($code, str_starts_with($code, 'branch_') ? 'restaurant' : 'menu', $menu->id);
                }
            }
        }
        $nextChange = $this->earliest($branch->nextChangeAt, $this->instant($temporal['next_change_at'] ?? null));
        $next = $visible ? $this->instant($temporal['next_orderable_at'] ?? ($temporal['is_available'] ? $at->toIso8601String() : null)) : null;

        return new AvailabilityResult('menu', $visible, $visible, $reasons === [], $reasons, $at, $nextChange, $next);
    }

    public function item(MenuItem $item, CarbonImmutable $at): AvailabilityResult
    {
        return $this->itemDecision($item, $at);
    }

    /**
     * @param  iterable<MenuItem>  $items
     * @return array<int,AvailabilityResult>
     */
    public function items(iterable $items, CarbonImmutable $at): array
    {
        return $this->itemBatch($items, $at);
    }

    /**
     * @param  iterable<MenuItem>  $items
     * @return array<int,AvailabilityResult>
     */
    public function projectItems(iterable $items, CarbonImmutable $at, string $operation, ?CarbonImmutable $until = null): array
    {
        return $this->itemBatch($items, $at, $operation, $until);
    }

    /**
     * @param  iterable<MenuItem>  $items
     * @return array<int,AvailabilityResult>
     */
    private function itemBatch(iterable $items, CarbonImmutable $at, ?string $operation = null, ?CarbonImmutable $until = null): array
    {
        $resolveMenu = $this->menuResolver();
        $results = [];
        $processed = 0;
        foreach ($items as $item) {
            if (++$processed > 100) {
                throw new InvalidArgumentException('Availability batches may contain at most 100 items.');
            }
            $projected = $operation === null ? $item : $this->projectedItem($item, $operation, $until);
            $results[$item->id] = $this->itemDecision($projected, $at, $resolveMenu);
        }

        return $results;
    }

    /** @return Closure(Menu,CarbonImmutable):AvailabilityResult */
    private function menuResolver(): Closure
    {
        $decisions = [];

        return function (Menu $menu, CarbonImmutable $instant) use (&$decisions): AvailabilityResult {
            $key = $menu->id.':'.$instant->format('U.u');
            if (! isset($decisions[$key])) {
                if (count($decisions) >= 100) {
                    $decisions = [];
                }
                $decisions[$key] = $this->menu($menu, $instant);
            }

            return $decisions[$key];
        };
    }

    /** @param (Closure(Menu,CarbonImmutable):AvailabilityResult)|null $resolveMenu */
    private function itemDecision(MenuItem $item, CarbonImmutable $at, ?Closure $resolveMenu = null): AvailabilityResult
    {
        $item->loadMissing(self::itemRelations());
        if (! $item->menu instanceof Menu) {
            return new AvailabilityResult('item', false, false, false, [new AvailabilityReason('invalid_context', 'item', $item->id)], $at);
        }
        $menu = $resolveMenu === null ? $this->menu($item->menu, $at) : $resolveMenu($item->menu, $at);
        $reasons = $menu->reasons;
        $structurallyVisible = $menu->visible && ! $item->trashed();
        if ($item->trashed()) {
            $reasons[] = new AvailabilityReason('item_archived', 'item', $item->id);
        }
        $category = $item->getRelation('category');
        if (! $category instanceof MenuCategory || $category->menu_id !== $item->menu_id || $category->trashed() || ! $category->is_active) {
            $structurallyVisible = false;
            $reasons[] = new AvailabilityReason('category_inactive', 'category', $item->category_id);
        }
        if (! $item->is_available) {
            $reasons[] = new AvailabilityReason('item_stopped', 'item', $item->id);
        }
        $hidden = $item->isTemporarilyHidden($at);
        if ($hidden) {
            $reasons[] = new AvailabilityReason('item_hidden', 'item', $item->id);
        }
        $configuration = $this->configurationReasons($item);
        $reasons = [...$reasons, ...$configuration];
        $nextChange = $hidden ? $this->earliest($menu->nextChangeAt, $item->hidden_until) : $menu->nextChangeAt;
        $next = null;
        if ($structurallyVisible && $item->is_available && $configuration === []) {
            $future = $hidden ? CarbonImmutable::instance($item->hidden_until) : $at;
            $futureMenu = $hidden ? ($resolveMenu === null ? $this->menu($item->menu, $future) : $resolveMenu($item->menu, $future)) : $menu;
            $next = $futureMenu->acceptsNewOrders ? $future : $futureMenu->nextOrderableAt;
        }

        return new AvailabilityResult('item', $structurallyVisible && ! $hidden, $structurallyVisible && $configuration === [], $reasons === [], $reasons, $at, $nextChange, $next);
    }

    public function projectItem(MenuItem $item, CarbonImmutable $at, string $operation, ?CarbonImmutable $until = null): AvailabilityResult
    {
        return $this->item($this->projectedItem($item, $operation, $until), $at);
    }

    private function projectedItem(MenuItem $item, string $operation, ?CarbonImmutable $until): MenuItem
    {
        $projected = clone $item;
        match ($operation) {
            'stop' => $projected->setAttribute('is_available', false),
            'resume' => $projected->setAttribute('is_available', true),
            'hide' => $projected->setAttribute('hidden_until', $until),
            'unhide' => $projected->setAttribute('hidden_until', null),
            default => throw new InvalidArgumentException('Unknown availability operation.'),
        };

        return $projected;
    }

    public function branchRestrictions(Branch $branch, CarbonImmutable $at): AvailabilityResult
    {
        $branch->loadMissing(['organization.subscription', 'brand', 'openingHours', 'scheduleExceptions']);
        $reasons = [];
        if ($branch->trashed() || $branch->organization === null || $branch->organization->trashed()
            || $branch->brand === null || $branch->brand->trashed() || $branch->brand->organization_id !== $branch->organization_id) {
            $reasons[] = new AvailabilityReason('invalid_context', 'restaurant', $branch->id);
        }
        if ($branch->organization?->subscription?->status === OrganizationSubscriptionStatus::Inactive) {
            $reasons[] = new AvailabilityReason('organization_unavailable', 'organization', $branch->organization_id);
        }
        if (! $branch->is_active) {
            $reasons[] = new AvailabilityReason('branch_inactive', 'restaurant', $branch->id);
        }
        $visible = $reasons === [];
        $status = $this->opening->handle($branch, $at);
        foreach ($status['reason_codes'] as $code) {
            if ($code !== 'branch_schedule_unconfigured' && ! in_array($code, array_column($reasons, 'code'), true)) {
                $reasons[] = new AvailabilityReason($code, 'restaurant', $branch->id);
            }
        }

        return new AvailabilityResult('branch', $visible, $visible, $visible && $status['can_accept_orders'], $reasons, $at,
            $this->instant($status['next_change_at'] ?? null), $visible ? $this->instant($status['next_orderable_at'] ?? null) : null);
    }

    /** @return list<AvailabilityReason> */
    private function configurationReasons(MenuItem $item): array
    {
        $reasons = [];
        if ($item->variants->isNotEmpty() && ! $item->variants->contains('is_available', true)) {
            $reasons[] = new AvailabilityReason('variants_unavailable', 'variant', $item->id);
        }
        foreach ($item->modifierGroups as $group) {
            $minimum = max((int) $group->min_select, $group->is_required ? 1 : 0);
            $available = $group->options->where('is_available', true)->count();
            if ($group->branch_id !== $item->menu->branch_id || $minimum > $available || ($group->max_select > 0 && $minimum > $group->max_select)) {
                $reasons[] = new AvailabilityReason('required_options_unavailable', 'modifier_group', $group->id);
            }
        }

        return $reasons;
    }

    private function instant(mixed $value): ?CarbonImmutable
    {
        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
    }

    private function earliest(?CarbonImmutable $first, ?CarbonImmutable $second): ?CarbonImmutable
    {
        return $first === null ? $second : ($second === null || $first->lessThan($second) ? $first : $second);
    }
}
