<?php

declare(strict_types=1);

namespace App\Services\Branches;

use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\ServicePoint;
use App\Models\User;
use App\Support\LocalizedDateFormatter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class FloorWorkspaceQuery
{
    /** @var array<string, Branch> */
    private array $branches = [];
    /** @var array<string, array{managePoints:bool,manageAreas:bool,qr:bool,open:bool}> */
    private array $permissions = [];

    public function branch(User $actor, int $branchId): Branch
    {
        $key = $actor->id.':'.$branchId;
        if (isset($this->branches[$key])) { return $this->branches[$key]; }
        $actor->loadMissing('roles:id,code');
        $branch = Branch::query()->whereKey($branchId)->with(['organization:id,name', 'brand:id,organization_id,name'])->firstOrFail();
        Gate::forUser($actor)->authorize('view', $branch);
        abort_unless(array_filter($this->abilities($actor, $branch)) !== [], 403);
        return $this->branches[$key] = $branch;
    }

    /** @return array{managePoints:bool,manageAreas:bool,qr:bool,open:bool} */
    public function abilities(User $actor, Branch $branch): array
    {
        $key = $actor->id.':'.$branch->id;
        if (isset($this->permissions[$key])) { return $this->permissions[$key]; }
        $gate = Gate::forUser($actor);
        return $this->permissions[$key] = ['managePoints' => $gate->allows('manageServicePoints', $branch), 'manageAreas' => $gate->allows('manageZones', $branch),
            'qr' => $gate->allows('generateQr', $branch), 'open' => $gate->allows('openTable', $branch)];
    }

    public function area(Branch $branch, int $id): AreaNode
    {
        return $branch->areaNodes()->withTrashed()->whereKey($id)->firstOrFail();
    }

    public function validateArea(Branch $branch, string $area): void
    {
        if (ctype_digit($area) && ! $branch->areaNodes()->withTrashed()->whereKey((int) $area)->exists()) {
            throw ValidationException::withMessages(['filters.area' => __('floor.errors.area_scope')]);
        }
    }

    /** @return array<string,mixed> */
    public function present(ServicePoint $point, Branch $branch, array $abilities): array
    {
        $session = $point->activeTableSession;
        $linked = $point->activeTableSessionServicePointLinks->first();
        $linkedSession = $linked?->tableSession;
        $occupied = $session !== null || $linkedSession !== null;
        return ['id' => $point->id, 'name' => $point->name, 'number' => $point->display_number, 'capacity' => $point->capacity,
            'type' => __($point->type->label()), 'area' => $point->areaNode === null ? __('floor.no_area') : $point->areaNode->name.($point->areaNode->trashed() ? ' · '.__('floor.archived') : ''),
            'active' => $point->is_active, 'archived' => $point->trashed(), 'status' => __($point->status->label()),
            'version' => $point->structure_version, 'occupied' => $occupied, 'linked' => $linkedSession !== null,
            'sessionTime' => LocalizedDateFormatter::dateTime(($session ?? $linkedSession)?->started_at),
            'qr' => $point->activeQrCode?->short_code, 'serviceUrl' => $occupied && $abilities['open'] ? route('restaurant.waiter.dashboard', ['branch' => $branch->id, 'table' => ($session ?? $linkedSession)->id]) : null,
            'canOpen' => $abilities['open'] && ! $occupied && ! $point->trashed() && $point->is_active && $point->status->allowsTableOpening()];
    }
}
