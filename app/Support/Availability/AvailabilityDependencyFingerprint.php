<?php

declare(strict_types=1);

namespace App\Support\Availability;

use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Services\Availability\AvailabilityEvaluator;

final class AvailabilityDependencyFingerprint
{
    public static function branch(Branch $branch): string
    {
        $branch->loadMissing(['organization.subscription', 'brand', 'openingHours', 'scheduleExceptions']);

        return self::digest([
            $branch->only(['id', 'organization_id', 'brand_id', 'timezone', 'is_active', 'is_temporarily_closed', 'temporary_closed_until', 'pause_version', 'opening_hours_version', 'deleted_at']),
            $branch->organization?->only(['id', 'deleted_at']), $branch->organization?->subscription?->status,
            $branch->brand?->only(['id', 'organization_id', 'deleted_at']),
            $branch->openingHours->sortBy('id')->map(fn ($row): array => $row->only(['id', 'day_of_week', 'is_closed', 'opens_at', 'closes_at']))->values()->all(),
            $branch->scheduleExceptions->sortBy('id')->map(fn ($row): array => $row->only(['id', 'local_date', 'is_closed', 'intervals']))->values()->all(),
        ]);
    }

    public static function menu(Menu $menu): string
    {
        $menu->loadMissing(['branch', 'availabilitySchedules']);

        return self::digest([self::branch($menu->branch), $menu->only(['id', 'branch_id', 'status', 'schedule_version', 'schedule_is_closed', 'deleted_at']),
            $menu->availabilitySchedules->sortBy('id')->map(fn ($row): array => $row->only(['id', 'day_of_week', 'starts_at', 'ends_at']))->values()->all()]);
    }

    public static function item(MenuItem $item): string
    {
        $item->loadMissing(AvailabilityEvaluator::itemRelations());
        $category = $item->getRelation('category');

        return self::digest([
            self::menu($item->menu), $item->only(['id', 'menu_id', 'category_id', 'is_available', 'hidden_until', 'availability_version', 'deleted_at']),
            $category instanceof MenuCategory ? $category->only(['id', 'menu_id', 'is_active', 'deleted_at']) : null,
            $item->variants->sortBy('id')->map(fn ($variant): array => $variant->only(['id', 'is_available']))->values()->all(),
            $item->modifierGroups->sortBy('id')->map(fn ($group): array => [$group->only(['id', 'branch_id', 'is_required', 'min_select', 'max_select']),
                $group->options->sortBy('id')->map(fn ($option): array => $option->only(['id', 'is_available']))->values()->all()])->values()->all(),
        ]);
    }

    /** @param list<mixed> $values */
    private static function digest(array $values): string
    {
        return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
    }
}
