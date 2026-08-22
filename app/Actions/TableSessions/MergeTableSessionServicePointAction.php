<?php

declare(strict_types=1);

namespace App\Actions\TableSessions;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\ServicePoints\UpdateServicePointStatusAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\AuditLogAction;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\TableSessionStatus;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionServicePoint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MergeTableSessionServicePointAction
{
    public function __construct(
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
        private readonly UpdateServicePointStatusAction $updateServicePointStatus,
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    public function handle(TableSession $tableSession, ServicePoint $servicePointToLink, User $linkedBy): TableSession
    {
        return DB::transaction(function () use ($tableSession, $servicePointToLink, $linkedBy): TableSession {
            $tableSession = $this->reloadTableSession($tableSession);
            $servicePointToLink = $this->reloadServicePoint($servicePointToLink);

            $this->ensureCanMerge($tableSession, $servicePointToLink, $linkedBy);

            $link = TableSessionServicePoint::query()->create([
                'table_session_id' => $tableSession->id,
                'service_point_id' => $servicePointToLink->id,
                'linked_by_user_id' => $linkedBy->id,
                'linked_at' => now(),
            ]);

            $metadata = $this->metadataWithMergeHistory(
                metadata: (array) ($tableSession->metadata ?? []),
                linkedServicePointId: (int) $servicePointToLink->id,
                linkedByUserId: (int) $linkedBy->id,
                linkedAt: $link->linked_at->toISOString(),
            );

            $tableSession->fill(['metadata' => $metadata])->save();
            $this->updateServicePointStatus->handle($servicePointToLink, ServicePointStatus::Occupied);

            $this->recordAuditLog->handle(
                action: AuditLogAction::TableSessionServicePointLinked,
                entityType: 'table_session',
                entityId: $tableSession->id,
                actorUser: $linkedBy,
                organizationId: $tableSession->branch->organization_id,
                branchId: $tableSession->branch_id,
                oldValues: [
                    'service_point_status' => ServicePointStatus::Free,
                ],
                newValues: [
                    'linked_service_point_id' => $servicePointToLink->id,
                    'linked_service_point_name' => $servicePointToLink->name,
                    'service_point_status' => ServicePointStatus::Occupied,
                    'linked_by_user_id' => $linkedBy->id,
                ],
            );

            return $tableSession->refresh();
        });
    }

    private function reloadTableSession(TableSession $tableSession): TableSession
    {
        return TableSession::query()
            ->select([
                'id',
                'branch_id',
                'service_point_id',
                'active_service_point_id',
                'pending_service_point_id',
                'status',
                'metadata',
            ])
            ->with([
                'branch:id,organization_id',
                'servicePoint' => fn ($query) => $query->select([
                    'id',
                    'branch_id',
                    'name',
                    'display_number',
                    'status',
                ]),
            ])
            ->whereKey($tableSession->id)
            ->firstOrFail();
    }

    private function reloadServicePoint(ServicePoint $servicePoint): ServicePoint
    {
        return ServicePoint::query()
            ->select(['id', 'branch_id', 'area_node_id', 'name', 'display_number', 'status', 'is_active'])
            ->whereKey($servicePoint->id)
            ->firstOrFail();
    }

    private function ensureCanMerge(TableSession $tableSession, ServicePoint $servicePointToLink, User $linkedBy): void
    {
        if (! $this->userCanMerge($linkedBy, (int) $tableSession->branch_id)) {
            throw ValidationException::withMessages([
                'table_session_merge' => __('ui.actions.tablesessions.mergetablesessionservicepointaction.u_vas_net_prav'),
            ]);
        }

        if ($this->sessionStatus($tableSession) !== TableSessionStatus::Active) {
            throw ValidationException::withMessages([
                'table_session_merge' => __('ui.actions.tablesessions.mergetablesessionservicepointaction.obieedinit_moz'),
            ]);
        }

        if ((int) $servicePointToLink->branch_id !== (int) $tableSession->branch_id) {
            throw ValidationException::withMessages([
                'mergeTargetServicePointId' => __('ui.actions.tablesessions.mergetablesessionservicepointaction.vyberite_svobo'),
            ]);
        }

        if ((int) $servicePointToLink->id === (int) $tableSession->service_point_id) {
            throw ValidationException::withMessages([
                'mergeTargetServicePointId' => __('ui.actions.tablesessions.mergetablesessionservicepointaction.eto_uze_osnovn'),
            ]);
        }

        if (! $servicePointToLink->is_active) {
            throw ValidationException::withMessages([
                'mergeTargetServicePointId' => __('ui.actions.tablesessions.mergetablesessionservicepointaction.eto_mesto_otkl'),
            ]);
        }

        if ($this->servicePointStatus($servicePointToLink) !== ServicePointStatus::Free
            || $this->targetHasOpenSession($servicePointToLink)
            || $this->targetIsAlreadyLinked($servicePointToLink)) {
            throw ValidationException::withMessages([
                'mergeTargetServicePointId' => __('ui.actions.tablesessions.mergetablesessionservicepointaction.eto_mesto_uze'),
            ]);
        }
    }

    private function userCanMerge(User $user, int $branchId): bool
    {
        $viewOrderBranchIds = $this->resolveAccessibleBranchIds
            ->handle($user, SystemPermission::ViewOrders);
        $confirmOrderBranchIds = $this->resolveAccessibleBranchIds
            ->handle($user, SystemPermission::ConfirmOrders);

        return $viewOrderBranchIds
            ->merge($confirmOrderBranchIds)
            ->unique()
            ->contains($branchId);
    }

    private function targetHasOpenSession(ServicePoint $servicePoint): bool
    {
        return TableSession::query()
            ->select(['id', 'branch_id', 'service_point_id', 'status'])
            ->where('branch_id', $servicePoint->branch_id)
            ->where('service_point_id', $servicePoint->id)
            ->whereIn('status', [
                TableSessionStatus::Pending->value,
                TableSessionStatus::Active->value,
                TableSessionStatus::WaitingWaiterConfirmation->value,
                TableSessionStatus::PaymentRequested->value,
            ])
            ->exists();
    }

    private function targetIsAlreadyLinked(ServicePoint $servicePoint): bool
    {
        return TableSessionServicePoint::query()
            ->select(['id', 'service_point_id', 'unlinked_at'])
            ->active()
            ->where('service_point_id', $servicePoint->id)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function metadataWithMergeHistory(
        array $metadata,
        int $linkedServicePointId,
        int $linkedByUserId,
        string $linkedAt,
    ): array {
        $entry = [
            'linked_service_point_id' => $linkedServicePointId,
            'linked_by_user_id' => $linkedByUserId,
            'linked_at' => $linkedAt,
        ];
        $merges = (array) ($metadata['merged_service_points'] ?? []);
        $merges[] = $entry;

        $metadata['merged_service_points'] = array_slice($merges, -20);
        $metadata['last_merged_service_point'] = $entry;

        return $metadata;
    }

    private function sessionStatus(TableSession $tableSession): TableSessionStatus
    {
        return $tableSession->status;
    }

    private function servicePointStatus(ServicePoint $servicePoint): ServicePointStatus
    {
        return $servicePoint->status;
    }
}
