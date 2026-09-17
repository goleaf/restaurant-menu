<?php

namespace App\Actions\ServicePoints;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Floor\RunFloorOperationAction;
use App\Actions\ServicePoints\Support\ServicePointMutationGuard;
use App\Enums\AuditLogAction;
use App\Enums\ServicePointStatus;
use App\Enums\ServicePointType;
use App\Models\Branch;
use App\Models\ServicePoint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use RuntimeException;

class CreateServicePointAction
{
    public function __construct(
        private readonly EnsureAreaNodeBelongsToBranchAction $ensureAreaNodeBelongsToBranch,
        private readonly ServicePointMutationGuard $guard,
        private readonly ValidateServicePointInputAction $validateInput,
        private readonly RecordAuditLogAction $audit,
        private readonly RunFloorOperationAction $operations,
    ) {}

    /**
     * @param  array{area_node_id: int|null, type: string, name: string, display_number: string|null, capacity: int, icon: string|null, is_active: bool}  $data
     */
    public function handle(Branch $branch, array $data, ?User $actor = null, ?string $requestId = null): ServicePoint
    {
        $authorize = static fn (User $user, Branch $currentBranch) => Gate::forUser($user)->authorize('create', [ServicePoint::class, $currentBranch]);
        $apply = function (User $user, Branch $currentBranch) use ($data): array {
            $data = $this->validateInput->handle($data);
            $this->ensureAreaNodeBelongsToBranch->handle($currentBranch->id, $data['area_node_id']);

            $servicePoint = $currentBranch->servicePoints()->make([
                'area_node_id' => $data['area_node_id'],
                'type' => ServicePointType::from($data['type']),
                'name' => $data['name'],
                'display_number' => $data['display_number'],
                'internal_code' => 'SP-'.Str::upper((string) Str::ulid()),
                'capacity' => $data['capacity'],
                'icon' => $data['icon'],
                'is_active' => $data['is_active'],
                'metadata' => [],
            ]);
            if (! $servicePoint->forceFill([
                'status' => ServicePointStatus::Free,
            ])->save()) {
                throw new RuntimeException('The service point could not be saved.');
            }
            $this->audit->handle(AuditLogAction::ServicePointChanged, 'service_point', $servicePoint->id,
                actorUser: $user, organizationId: $currentBranch->organization_id, branchId: $currentBranch->id,
                newValues: ['kind' => 'created', ...$servicePoint->only(['name', 'display_number', 'area_node_id', 'type', 'capacity', 'is_active'])]);

            return ['id' => $servicePoint->id];
        };
        if ($requestId !== null) {
            $result = $this->operations->handle($this->guard->actor($actor), $branch, $requestId, 'service_point_create', null, $data, $authorize, $apply);
        } else {
            $result = DB::transaction(function () use ($actor, $branch, $authorize, $apply): array {
                $user = $this->guard->actor($actor);
                $currentBranch = $this->guard->branch($branch->id);
                $authorize($user, $currentBranch);

                return $apply($user, $currentBranch);
            }, 3);
        }

        return ServicePoint::withTrashed()->where('branch_id', $branch->id)->whereKey($result['id'])->firstOrFail();
    }
}
