<?php

declare(strict_types=1);

namespace App\Services\Branches;

use App\Enums\OrderStatus;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\ServicePointType;
use App\Enums\TableSessionStatus;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\ServicePoint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\Paginator;
use Illuminate\Validation\ValidationException;

final class ServicePointQueryService
{
    /**
     * @param  array{search: string, area_node_id: string, type: string, status: string, active: string, qr: string, lifecycle?: string, sort?: string}  $filters
     * @return Paginator<int, ServicePoint>
     */
    public function paginate(Branch $branch, array $filters, int $perPage): Paginator
    {
        $servicePoints = $this->displayQuery($branch);

        if (($filters['lifecycle'] ?? 'active') === 'archived') {
            $servicePoints->onlyTrashed();
        }

        $this->applyFilters($servicePoints, $filters);

        $this->applySort($servicePoints, $filters['sort'] ?? 'position');

        $page = $servicePoints->simplePaginate($perPage);
        foreach ($page->items() as $point) {
            $point->setRelation('activeTableSession', $point->getRelation('unfinishedTableSession'));
        }

        return $page;
    }

    /** @param array<string, string> $filters */
    public function count(Branch $branch, array $filters): int
    {
        $query = $branch->servicePoints();
        if (($filters['lifecycle'] ?? 'active') === 'archived') {
            $query->onlyTrashed();
        }
        $this->applyFilters($query, $filters);

        return $query->count();
    }

    /**
     * @param  list<int>  $ids
     * @return EloquentCollection<int, ServicePoint>
     */
    public function selected(Branch $branch, array $ids): EloquentCollection
    {
        $this->validateIds($ids);
        $points = $this->displayQuery($branch)->withTrashed()->whereKey($ids)->orderBy('id')->get();
        if ($points->count() !== count($ids)) {
            throw ValidationException::withMessages(['servicePointIds' => __('floor.errors.selection_changed')]);
        }
        foreach ($points as $point) {
            $point->setRelation('activeTableSession', $point->getRelation('unfinishedTableSession'));
        }

        return $points;
    }

    /** @return HasMany<ServicePoint, Branch> */
    private function displayQuery(Branch $branch): HasMany
    {
        return $branch->servicePoints()
            ->select($this->servicePointColumns())
            ->with([
                'areaNode' => fn ($query) => $query->withTrashed()->select([
                    'id',
                    'branch_id',
                    'parent_id',
                    'type',
                    'name',
                    'icon',
                    'sort_order',
                    'is_active',
                    'deleted_at',
                ]),
                'activeQrCode' => fn ($query) => $query->select([
                    'id',
                    'service_point_id',
                    'public_token',
                    'short_code',
                    'status',
                    'created_at',
                ])->where('status', QrCodeStatus::Active->value),
                'unfinishedTableSession' => fn ($query) => $query->select([
                    'id',
                    'branch_id',
                    'service_point_id',
                    'opened_by_user_id',
                    'status',
                    'source',
                    'started_at',
                    'created_at',
                ])->whereIn('status', TableSessionStatus::guestViewableValues()),
                'activeTableSessionServicePointLinks' => fn ($query) => $query
                    ->select([
                        'id',
                        'table_session_id',
                        'service_point_id',
                        'unlinked_at',
                    ])
                    ->with(['tableSession' => fn ($tableSessionQuery) => $tableSessionQuery->select([
                        'id',
                        'branch_id',
                        'service_point_id',
                        'status',
                        'started_at',
                        'created_at',
                    ])->whereIn('status', TableSessionStatus::guestViewableValues())])
                    ->whereNull('unlinked_at'),
            ]);
    }

    /** @param array<mixed> $ids */
    private function validateIds(array $ids): void
    {
        if (count($ids) < 1 || count($ids) > 100
            || array_any($ids, static fn ($id): bool => ! is_int($id) || $id < 1)
            || count(array_unique($ids)) !== count($ids)) {
            throw ValidationException::withMessages(['servicePointIds' => __('floor.errors.invalid_selection')]);
        }
    }

    /**
     * @param  list<int>  $selectedIds
     * @return EloquentCollection<int, AreaNode>
     */
    public function areaNodes(Branch $branch, string $search = '', array $selectedIds = []): EloquentCollection
    {
        $query = $branch->areaNodes()->select(['id', 'branch_id', 'parent_id', 'type', 'name', 'icon', 'sort_order', 'is_active'])
            ->with('parent:id,name')->orderBy('sort_order')->orderBy('name')->orderBy('id');
        $selected = $selectedIds === [] ? new EloquentCollection : (clone $query)->whereKey(array_slice($selectedIds, 0, 4))->get();
        $matches = (clone $query)->whereNotIn('id', $selected->modelKeys())
            ->when(trim($search) !== '', fn ($query) => $query->where('name', 'like', '%'.mb_substr(trim($search), 0, 100).'%'))
            ->limit(100 - $selected->count())->get();

        return $selected->merge($matches);
    }

    public function findForBranch(Branch $branch, int $servicePointId, bool $withTrashed = false): ServicePoint
    {
        return $branch->servicePoints()
            ->when($withTrashed, fn ($query) => $query->withTrashed())
            ->select($this->servicePointColumns())
            ->whereKey($servicePointId)
            ->firstOrFail();
    }

    /**
     * @param  list<int>  $ids
     * @return EloquentCollection<int, ServicePoint>
     */
    public function mutationRows(Branch $branch, array $ids, bool $withTrashed = false): EloquentCollection
    {
        $this->validateIds($ids);

        return $branch->servicePoints()->when($withTrashed, fn ($query) => $query->withTrashed())
            ->select($this->servicePointColumns())
            ->withExists([
                'tableSessions as unfinished_session_exists' => fn ($query) => $query->where('branch_id', $branch->id)->whereIn('status', TableSessionStatus::guestViewableValues()),
                'tableSessionServicePointLinks as unfinished_link_exists' => fn ($query) => $query->whereNull('unlinked_at')->whereHas('tableSession', fn ($session) => $session->where('branch_id', $branch->id)->whereIn('status', TableSessionStatus::guestViewableValues())),
                'tableSessionServicePointLinks as linked_active_order_exists' => fn ($query) => $query->whereNull('unlinked_at')->whereHas('tableSession', fn ($session) => $session->where('branch_id', $branch->id)->whereHas('orders', fn ($orders) => $orders->whereIn('status', OrderStatus::activeValues()))),
                'orders as active_order_exists' => fn ($query) => $query->where('branch_id', $branch->id)->whereIn('status', OrderStatus::activeValues()),
            ])->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
    }

    public function mutationRow(Branch $branch, int $id, bool $withTrashed = false): ServicePoint
    {
        $point = $this->mutationRows($branch, [$id], $withTrashed)->first();
        if (! $point instanceof ServicePoint) {
            throw (new ModelNotFoundException)->setModel(ServicePoint::class, [$id]);
        }

        return $point;
    }

    /**
     * @param  HasMany<ServicePoint, Branch>  $query
     * @param  array{search: string, area_node_id: string, type: string, status: string, active: string, qr: string, lifecycle?: string, sort?: string}  $filters
     */
    private function applyFilters(HasMany $query, array $filters): void
    {
        $search = trim($filters['search']);

        if ($search !== '') {
            $like = '%'.$search.'%';

            $query->where(function (Builder $query) use ($like): void {
                $query
                    ->whereAny(['name', 'display_number', 'internal_code'], 'like', $like)
                    ->orWhereHas('activeQrCode', fn (Builder $qrCodeQuery): Builder => $qrCodeQuery
                        ->where('short_code', 'like', $like));
            });
        }

        if ($filters['area_node_id'] === 'none') {
            $query->whereNull('area_node_id');
        } elseif (ctype_digit($filters['area_node_id'])) {
            $query->where('area_node_id', (int) $filters['area_node_id']);
        }

        if (in_array($filters['type'], ServicePointType::values(), true)) {
            $query->where('type', $filters['type']);
        }

        if (in_array($filters['status'], ServicePointStatus::values(), true)) {
            $query->where('status', $filters['status']);
        }

        if ($filters['active'] === 'active') {
            $query->where('is_active', true);
        } elseif ($filters['active'] === 'inactive') {
            $query->where('is_active', false);
        }

        if ($filters['qr'] === 'with') {
            $query->whereHas('activeQrCode');
        } elseif ($filters['qr'] === 'without') {
            $query->whereDoesntHave('activeQrCode');
        }
    }

    /**
     * @param  HasMany<ServicePoint, Branch>  $query
     */
    private function applySort(HasMany $query, string $sort): void
    {
        match ($sort) {
            'name_asc' => $query->orderBy('name')->orderBy('id'),
            'name_desc' => $query->orderByDesc('name')->orderByDesc('id'),
            'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            default => $query
                ->orderBy('area_node_id')
                ->orderBy('display_number')
                ->orderBy('name')
                ->orderBy('id'),
        };
    }

    /** @return list<string> */
    private function servicePointColumns(): array
    {
        return [
            'id',
            'branch_id',
            'area_node_id',
            'type',
            'name',
            'display_number',
            'internal_code',
            'capacity',
            'icon',
            'status',
            'is_active',
            'created_at',
            'updated_at',
            'deleted_at',
            'structure_version',
        ];
    }
}
