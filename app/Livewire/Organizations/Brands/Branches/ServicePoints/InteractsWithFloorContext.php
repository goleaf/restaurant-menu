<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\ServicePoints;

use App\Models\Branch;
use App\Models\User;
use App\Services\Branches\FloorWorkspaceQuery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Throwable;

trait InteractsWithFloorContext
{
    protected FloorWorkspaceQuery $floorContext;
    protected \App\Services\Branches\AreaNodeQueryService $floorAreas;

    public function bootInteractsWithFloorContext(FloorWorkspaceQuery $floorContext, \App\Services\Branches\AreaNodeQueryService $floorAreas): void
    {
        $this->floorContext = $floorContext;
        $this->floorAreas = $floorAreas;
    }

    #[Locked]
    public int $branchId;

    protected function actor(): User
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 401);
        return $actor;
    }

    protected function branch(): Branch
    {
        return $this->floorContext->branch($this->actor(), $this->branchId);
    }

    public function exception(Throwable $e, mixed $stopPropagation): void
    {
        if ($e instanceof ValidationException) {
            $this->dispatch('floor-invalid');
        }
    }

    /** @return list<array<string,mixed>> */
    protected function areaOptions(string $search, mixed $selected): array
    {
        $id = is_scalar($selected) && ctype_digit((string) $selected) ? (int) $selected : null;
        return $this->floorAreas->browser($this->branch(), $search, $id)['rows'];
    }

    protected function saved(): void
    {
        $this->dispatch('floor-saved');
    }
}
