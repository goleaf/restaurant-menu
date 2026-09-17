<?php

declare(strict_types=1);

namespace App\Actions\ServicePoints;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\ServicePoints\Support\ServicePointMutationGuard;
use App\Enums\AuditLogAction;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\Branches\ServicePointQueryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

final class SetServicePointActiveAction
{
    public function __construct(private readonly ServicePointMutationGuard $guard, private readonly ServicePointQueryService $queries, private readonly RecordAuditLogAction $audit) {}

    public function handle(ServicePoint $servicePoint, bool $isActive, ?User $actor = null, ?int $expectedVersion = null): ServicePoint
    {
        return DB::transaction(function () use ($servicePoint, $isActive, $actor, $expectedVersion): ServicePoint {
            $actor = $this->guard->actor($actor);
            $branch = $this->guard->branch($servicePoint->branch_id);
            $point = $this->queries->mutationRow($branch, $servicePoint->id);
            $point->setRelation('branch', $branch);
            Gate::forUser($actor)->authorize('update', $point);
            $this->guard->version($point, $expectedVersion);
            if (! $isActive) {
                $this->guard->idle($point);
            }
            if ($point->is_active === $isActive) {
                return $point;
            }
            $before = $point->is_active;
            if (! $point->fill(['is_active' => $isActive])->save()) {
                throw new RuntimeException('The service point could not be saved.');
            }
            $this->audit->handle(AuditLogAction::ServicePointChanged, 'service_point', $point->id, actorUser: $actor,
                organizationId: $branch->organization_id, branchId: $branch->id,
                oldValues: ['is_active' => $before], newValues: ['kind' => 'activity', 'is_active' => $isActive]);

            return $point;
        }, 3);
    }
}
