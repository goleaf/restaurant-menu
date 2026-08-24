<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ServicePointType;
use Database\Factories\RestaurantOnboardingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['user_id', 'completed_at'])]
class RestaurantOnboarding extends Model
{
    /** @use HasFactory<RestaurantOnboardingFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class)->withTrashed();
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class)->withTrashed();
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    /** @return BelongsTo<AreaNode, $this> */
    public function areaNode(): BelongsTo
    {
        return $this->belongsTo(AreaNode::class)->withTrashed();
    }

    /** @return BelongsTo<Menu, $this> */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class)->withTrashed();
    }

    /** @return BelongsTo<MenuCategory, $this> */
    public function menuCategory(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class)->withTrashed();
    }

    /** @return BelongsTo<MenuItem, $this> */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class)->withTrashed();
    }

    /** @return BelongsToMany<ServicePoint, $this> */
    public function servicePoints(): BelongsToMany
    {
        return $this->belongsToMany(ServicePoint::class, 'restaurant_onboarding_service_points')
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }

    public function expectedServicePointCount(int $currentlyLinkedCount): int
    {
        $persistedCount = (int) $this->getAttribute('expected_service_point_count');

        return max($persistedCount, $currentlyLinkedCount);
    }

    /** @param Collection<int, ServicePoint> $points */
    public function hasCompleteServicePointSet(
        Collection $points,
        int $branchId,
        int $areaNodeId,
        int $totalLinkedCount,
    ): bool {
        $expectedCount = $this->expectedServicePointCount($points->count());

        if ($expectedCount < 1
            || $points->count() !== $expectedCount
            || $totalLinkedCount !== $expectedCount) {
            return false;
        }

        foreach ($points as $position => $point) {
            $pivot = $point->getRelation('pivot');

            if ($point->trashed()
                || $point->type !== ServicePointType::Table
                || (int) $point->branch_id !== $branchId
                || (int) $point->area_node_id !== $areaNodeId
                || ! $pivot instanceof Pivot
                || (int) $pivot->getAttribute('position') !== $position + 1) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'completed_at' => 'immutable_datetime',
            'expected_service_point_count' => 'integer',
        ];
    }
}
