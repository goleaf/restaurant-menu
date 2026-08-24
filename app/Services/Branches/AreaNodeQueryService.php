<?php

declare(strict_types=1);

namespace App\Services\Branches;

use App\Enums\AreaNodeType;
use App\Models\AreaNode;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\Paginator;

final class AreaNodeQueryService
{
    /** @return EloquentCollection<int, AreaNode> */
    public function forBranch(Branch $branch): EloquentCollection
    {
        return $branch->areaNodes()
            ->select($this->columns())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array{search: string, type: string, active: string, lifecycle: string, sort: string}  $filters
     * @return Paginator<int, AreaNode>
     */
    public function paginateForBranch(Branch $branch, array $filters, int $perPage): Paginator
    {
        $areaNodes = $branch->areaNodes();

        if ($filters['lifecycle'] === 'archived') {
            $areaNodes->onlyTrashed();
        }

        $areaNodes->select($this->columns());
        $this->applyFilters($areaNodes, $filters);
        $this->applySort($areaNodes, $filters['sort']);

        return $areaNodes->simplePaginate($perPage, pageName: 'areasPage');
    }

    public function findForBranch(Branch $branch, int $areaNodeId, bool $withTrashed = false): AreaNode
    {
        return $branch->areaNodes()
            ->when($withTrashed, fn ($query) => $query->withTrashed())
            ->select($this->columns())
            ->whereKey($areaNodeId)
            ->firstOrFail();
    }

    /** @return list<string> */
    private function columns(): array
    {
        return [
            'id',
            'branch_id',
            'parent_id',
            'type',
            'name',
            'icon',
            'sort_order',
            'is_active',
            'metadata',
            'created_at',
            'updated_at',
            'deleted_at',
        ];
    }

    /**
     * @param  HasMany<AreaNode, Branch>  $query
     * @param  array{search: string, type: string, active: string, lifecycle: string, sort: string}  $filters
     */
    private function applyFilters(HasMany $query, array $filters): void
    {
        $search = trim($filters['search']);

        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        if (in_array($filters['type'], AreaNodeType::values(), true)) {
            $query->where('type', $filters['type']);
        }

        if ($filters['active'] === 'active') {
            $query->where('is_active', true);
        } elseif ($filters['active'] === 'inactive') {
            $query->where('is_active', false);
        }
    }

    /**
     * @param  HasMany<AreaNode, Branch>  $query
     */
    private function applySort(HasMany $query, string $sort): void
    {
        match ($sort) {
            'name_asc' => $query->orderBy('name')->orderBy('id'),
            'name_desc' => $query->orderByDesc('name')->orderByDesc('id'),
            'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            default => $query->orderBy('sort_order')->orderBy('name')->orderBy('id'),
        };
    }
}
