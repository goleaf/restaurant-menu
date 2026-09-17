<?php

declare(strict_types=1);

namespace App\Services\Branches;

use App\Enums\AreaNodeType;
use App\Enums\TableSessionStatus;
use App\Models\AreaNode;
use App\Models\AreaNodeWaiter;
use App\Models\Branch;
use App\Models\Order;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionServicePoint;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\Paginator;
use Illuminate\Validation\ValidationException;

final class AreaNodeQueryService
{
    /**
     * @param array{type?:string,active?:string,sort?:string} $filters
     * @return array{rows:list<array<string,mixed>>,paginator:Paginator<int,AreaNode>}
     */
    public function browser(Branch $branch, string $search, ?int $selectedId = null, ?int $excludingId = null, int $perPage = 20, string $lifecycle = 'active', array $filters = []): array
    {
        $query = $branch->areaNodes()->select($this->columns())
            ->when($lifecycle === 'archived', fn ($query) => $query->onlyTrashed())
            ->withCount([
                'children' => fn ($query) => $query->where('branch_id', $branch->id),
                'servicePoints as tables_count' => fn ($query) => $query->where('branch_id', $branch->id),
                'waiterAssignments as assignments_count' => fn ($query) => $query->where('branch_id', $branch->id)->where('organization_id', $branch->organization_id),
            ]);
        $filtered = clone $query;
        $this->applyFilters($filtered, ['search' => $search, 'type' => $filters['type'] ?? 'all', 'active' => $filters['active'] ?? 'all', 'lifecycle' => $lifecycle, 'sort' => $filters['sort'] ?? 'position']);
        $this->applySort($filtered, $filters['sort'] ?? 'position');
        $paginator = $filtered->simplePaginate(max(1, min(50, $perPage)), pageName: 'areasPage')->withQueryString();
        $nodes = $paginator->getCollection();
        if ($selectedId !== null && ! $nodes->contains('id', $selectedId)) {
            $selected = (clone $query)->whereKey($selectedId)->first();
            if ($selected instanceof AreaNode) {
                $nodes = new EloquentCollection([$selected, ...$nodes->all()]);
            }
        }
        $paths = $this->paths($branch, $nodes);
        $rows = [];
        foreach ($nodes as $node) {
            $path = $paths[$node->id];
            if ($excludingId !== null && in_array($excludingId, $path['ids'], true)) {
                continue;
            }
            $rows[] = [
                'id' => $node->id, 'parent_id' => $node->parent_id, 'name' => $node->name,
                'label' => $path['path'], 'path' => $path['path'], 'hierarchy_valid' => $path['valid'],
                'type' => $node->type->value, 'type_label' => $node->type->label(), 'icon' => $node->icon,
                'is_active' => $node->is_active, 'is_archived' => $node->trashed(), 'selected' => $selectedId === $node->id,
                'children_count' => (int) $node->getAttribute('children_count'), 'tables_count' => (int) $node->getAttribute('tables_count'),
                'assignments_count' => (int) $node->getAttribute('assignments_count'), 'version' => $node->structure_version,
            ];
        }

        return ['rows' => $rows, 'paginator' => $paginator];
    }

    /** @return array<string,int|bool|string|null> */
    public function archivePreview(Branch $branch, AreaNode $area): array
    {
        $area = $this->findForBranch($branch, $area->id);
        $ids = $this->subtreeIds($branch, $area->id);
        $points = ServicePoint::withTrashed()->select('id')->where('branch_id', $branch->id)->whereIn('area_node_id', $ids);
        $sessions = TableSession::query()->where('branch_id', $branch->id)->whereIn('status', TableSessionStatus::guestViewableValues())
            ->where(fn ($query) => $query->whereIn('service_point_id', clone $points)
                ->orWhereIn('id', TableSessionServicePoint::query()->select('table_session_id')->active()->whereIn('service_point_id', clone $points)))->count();
        $orders = Order::query()->active()->where('branch_id', $branch->id)->whereIn('service_point_id', clone $points)->count();

        $preview = [
            'id' => $area->id, 'version' => $area->structure_version, 'parent_id' => $area->parent_id,
            'children_count' => $area->children()->where('branch_id', $branch->id)->count(),
            'tables_count' => $area->servicePoints()->where('branch_id', $branch->id)->count(),
            'assignments_count' => $area->waiterAssignments()->where('branch_id', $branch->id)->where('organization_id', $branch->organization_id)->count(),
            'affected_areas_count' => count($ids), 'nonterminal_sessions_count' => $sessions, 'active_orders_count' => $orders,
            'can_archive' => $sessions === 0 && $orders === 0,
        ];
        $hash = hash_init('sha256');
        hash_update($hash, json_encode($preview, JSON_THROW_ON_ERROR));
        $areaIds = $area->parent_id === null ? $ids : [...$ids, $area->parent_id];
        foreach (AreaNode::withTrashed()->select(['id', 'parent_id', 'structure_version'])->where('branch_id', $branch->id)->whereIn('id', $areaIds)->lazyById(200) as $dependency) {
            hash_update($hash, json_encode(['area', $dependency->id, $dependency->parent_id, $dependency->structure_version], JSON_THROW_ON_ERROR));
        }
        foreach ((clone $points)->select(['id', 'area_node_id', 'structure_version'])->lazyById(200) as $point) {
            hash_update($hash, json_encode(['point', $point->id, $point->area_node_id, $point->structure_version], JSON_THROW_ON_ERROR));
        }
        foreach (AreaNodeWaiter::query()->select(['id', 'area_node_id', 'user_id'])->where('organization_id', $branch->organization_id)->where('branch_id', $branch->id)->whereIn('area_node_id', $ids)->lazyById(200) as $assignment) {
            hash_update($hash, json_encode(['assignment', $assignment->id, $assignment->area_node_id, $assignment->user_id], JSON_THROW_ON_ERROR));
        }

        return [...$preview, 'fingerprint' => hash_final($hash)];
    }

    /** @return list<int> */
    private function subtreeIds(Branch $branch, int $rootId): array
    {
        $ids = [$rootId => true];
        $frontier = [$rootId];
        for ($depth = 0; $frontier !== [] && $depth < 64; $depth++) {
            $next = [];
            foreach (array_chunk($frontier, 200) as $parents) {
                $children = AreaNode::query()->select(['id'])->where('branch_id', $branch->id)->whereIn('parent_id', $parents)->lazyById(200);
                foreach ($children as $child) {
                    if (isset($ids[$child->id]) || count($ids) >= 5000) {
                        throw ValidationException::withMessages(['structureDeletion' => __('floor.errors.invalid_hierarchy')]);
                    }
                    $ids[$child->id] = true;
                    $next[] = $child->id;
                }
            }
            $frontier = $next;
        }
        if ($frontier !== []) {
            throw ValidationException::withMessages(['structureDeletion' => __('floor.errors.invalid_hierarchy')]);
        }

        return array_keys($ids);
    }

    /** @param EloquentCollection<int,AreaNode> $nodes @return array<int,array{path:string,valid:bool,ids:list<int>}> */
    private function paths(Branch $branch, EloquentCollection $nodes): array
    {
        $known = $nodes->keyBy('id');
        $frontier = $nodes->pluck('parent_id')->filter()->unique()->all();
        for ($depth = 0; $frontier !== [] && $depth < 64; $depth++) {
            $missing = array_values(array_diff($frontier, $known->keys()->all()));
            if ($missing === []) {
                break;
            }
            $parents = AreaNode::withTrashed()->select(['id', 'parent_id', 'name'])->where('branch_id', $branch->id)->whereIn('id', $missing)->get();
            foreach ($parents as $parent) {
                $known->put($parent->id, $parent);
            }
            $frontier = $parents->pluck('parent_id')->filter()->unique()->all();
        }
        $result = [];
        foreach ($nodes as $node) {
            $names = [];
            $visited = [];
            $id = $node->id;
            $valid = true;
            while ($id !== null) {
                if (isset($visited[$id]) || count($visited) >= 64 || ! $known->has($id)) {
                    $valid = false;
                    break;
                }
                $visited[$id] = true;
                $parent = $known->get($id);
                $names[] = $parent->name;
                $id = $parent->parent_id;
            }
            $result[$node->id] = ['path' => implode(' / ', array_reverse($names)), 'valid' => $valid, 'ids' => array_keys($visited)];
        }

        return $result;
    }

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
            'structure_version',
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
