<?php

declare(strict_types=1);

namespace App\Services\Branches;

use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\ServicePointType;
use App\Enums\TableSessionStatus;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\ServicePoint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\Paginator;

final class ServicePointQueryService
{
    /**
     * @param  array{search: string, area_node_id: string, type: string, status: string, active: string, qr: string}  $filters
     * @return Paginator<int, ServicePoint>
     */
    public function paginate(Branch $branch, array $filters, int $perPage): Paginator
    {
        $servicePoints = $branch->servicePoints()
            ->select($this->servicePointColumns())
            ->with([
                'areaNode' => fn ($query) => $query->select([
                    'id',
                    'branch_id',
                    'parent_id',
                    'type',
                    'name',
                    'icon',
                    'sort_order',
                    'is_active',
                ]),
                'activeQrCode' => fn ($query) => $query->select([
                    'id',
                    'service_point_id',
                    'public_token',
                    'short_code',
                    'status',
                    'created_at',
                ])->where('status', QrCodeStatus::Active->value),
                'activeTableSession' => fn ($query) => $query->select([
                    'id',
                    'branch_id',
                    'service_point_id',
                    'opened_by_user_id',
                    'status',
                    'source',
                    'started_at',
                    'created_at',
                ])->where('status', TableSessionStatus::Active->value),
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
                    ])->where('status', TableSessionStatus::Active->value)])
                    ->whereNull('unlinked_at'),
            ]);

        $this->applyFilters($servicePoints, $filters);

        return $servicePoints
            ->orderBy('area_node_id')
            ->orderBy('display_number')
            ->orderBy('name')
            ->orderBy('id')
            ->simplePaginate($perPage);
    }

    /** @return EloquentCollection<int, AreaNode> */
    public function areaNodes(Branch $branch): EloquentCollection
    {
        return $branch->areaNodes()
            ->select([
                'id',
                'branch_id',
                'parent_id',
                'type',
                'name',
                'icon',
                'sort_order',
                'is_active',
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    public function findForBranch(Branch $branch, int $servicePointId): ServicePoint
    {
        return $branch->servicePoints()
            ->select($this->servicePointColumns())
            ->whereKey($servicePointId)
            ->firstOrFail();
    }

    /**
     * @param  HasMany<ServicePoint, Branch>  $query
     * @param  array{search: string, area_node_id: string, type: string, status: string, active: string, qr: string}  $filters
     */
    private function applyFilters(HasMany $query, array $filters): void
    {
        $search = trim($filters['search']);

        if ($search !== '') {
            $like = '%'.$search.'%';

            $query->where(function (Builder $query) use ($like): void {
                $query
                    ->where('name', 'like', $like)
                    ->orWhere('display_number', 'like', $like)
                    ->orWhere('internal_code', 'like', $like)
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
        ];
    }
}
