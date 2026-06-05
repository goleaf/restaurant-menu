<?php

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
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferTableSessionAction
{
    public function __construct(
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
        private readonly UpdateServicePointStatusAction $updateServicePointStatus,
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    public function handle(TableSession $tableSession, ServicePoint $targetServicePoint, User $transferredBy): TableSession
    {
        return DB::transaction(function () use ($tableSession, $targetServicePoint, $transferredBy): TableSession {
            $tableSession = $this->reloadTableSession($tableSession);
            $targetServicePoint = $this->reloadServicePoint($targetServicePoint);
            $currentServicePoint = $tableSession->servicePoint;

            $this->ensureCanTransfer($tableSession, $targetServicePoint, $transferredBy);

            $oldServicePointId = (int) $tableSession->service_point_id;
            $oldServicePointName = $currentServicePoint?->name;
            $oldServicePointStatus = $currentServicePoint instanceof ServicePoint
                ? $this->servicePointStatus($currentServicePoint)
                : null;
            $newServicePointName = $targetServicePoint->name;
            $transferredAt = now();
            $metadata = $this->metadataWithTransferHistory(
                metadata: (array) ($tableSession->metadata ?? []),
                oldServicePointId: $oldServicePointId,
                targetServicePointId: (int) $targetServicePoint->id,
                transferredByUserId: (int) $transferredBy->id,
                transferredAt: $transferredAt->toISOString(),
            );

            $tableSession->fill([
                'service_point_id' => $targetServicePoint->id,
                'metadata' => $metadata,
            ])->save();

            if ($currentServicePoint instanceof ServicePoint) {
                $this->updateServicePointStatus->handle($currentServicePoint, ServicePointStatus::Free);
            }

            $this->updateServicePointStatus->handle($targetServicePoint, ServicePointStatus::Occupied);

            $this->recordAuditLog->handle(
                action: AuditLogAction::TableSessionTransferred,
                entityType: 'table_session',
                entityId: $tableSession->id,
                actorUser: $transferredBy,
                organizationId: $tableSession->branch?->organization_id,
                branchId: $tableSession->branch_id,
                oldValues: [
                    'service_point_id' => $oldServicePointId,
                    'service_point_name' => $oldServicePointName,
                    'service_point_status' => $oldServicePointStatus,
                ],
                newValues: [
                    'service_point_id' => $targetServicePoint->id,
                    'service_point_name' => $newServicePointName,
                    'service_point_status' => ServicePointStatus::Occupied,
                    'transferred_by_user_id' => $transferredBy->id,
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

    private function ensureCanTransfer(TableSession $tableSession, ServicePoint $targetServicePoint, User $transferredBy): void
    {
        if (! $this->userCanTransfer($transferredBy, (int) $tableSession->branch_id)) {
            throw ValidationException::withMessages([
                'table_session_transfer' => __('У вас нет права переносить активную сессию стола.'),
            ]);
        }

        if ($this->sessionStatus($tableSession) !== TableSessionStatus::Active) {
            throw ValidationException::withMessages([
                'table_session_transfer' => __('Перенести можно только активную сессию стола.'),
            ]);
        }

        if ((int) $targetServicePoint->branch_id !== (int) $tableSession->branch_id) {
            throw ValidationException::withMessages([
                'transferTargetServicePointId' => __('Выберите свободное место в этом же филиале.'),
            ]);
        }

        if ((int) $targetServicePoint->id === (int) $tableSession->service_point_id) {
            throw ValidationException::withMessages([
                'transferTargetServicePointId' => __('Выберите другое свободное место.'),
            ]);
        }

        if (! $targetServicePoint->is_active) {
            throw ValidationException::withMessages([
                'transferTargetServicePointId' => __('Это место отключено. Выберите другое свободное место.'),
            ]);
        }

        if ($this->servicePointStatus($targetServicePoint) !== ServicePointStatus::Free
            || $this->targetHasOpenSession($targetServicePoint)) {
            throw ValidationException::withMessages([
                'transferTargetServicePointId' => __('Новое место уже занято или недоступно.'),
            ]);
        }
    }

    private function userCanTransfer(User $user, int $branchId): bool
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

    private function targetHasOpenSession(ServicePoint $targetServicePoint): bool
    {
        return TableSession::query()
            ->select(['id', 'branch_id', 'service_point_id', 'status'])
            ->where('branch_id', $targetServicePoint->branch_id)
            ->where('service_point_id', $targetServicePoint->id)
            ->whereIn('status', [
                TableSessionStatus::Pending->value,
                TableSessionStatus::Active->value,
                TableSessionStatus::WaitingWaiterConfirmation->value,
                TableSessionStatus::PaymentRequested->value,
            ])
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function metadataWithTransferHistory(
        array $metadata,
        int $oldServicePointId,
        int $targetServicePointId,
        int $transferredByUserId,
        string $transferredAt,
    ): array {
        $entry = [
            'from_service_point_id' => $oldServicePointId,
            'to_service_point_id' => $targetServicePointId,
            'transferred_by_user_id' => $transferredByUserId,
            'transferred_at' => $transferredAt,
        ];
        $transfers = (array) ($metadata['transfers'] ?? []);
        $transfers[] = $entry;

        $metadata['transfers'] = array_slice($transfers, -20);
        $metadata['last_transfer'] = $entry;

        return $metadata;
    }

    private function sessionStatus(TableSession $tableSession): TableSessionStatus
    {
        return $tableSession->status instanceof TableSessionStatus
            ? $tableSession->status
            : TableSessionStatus::from((string) $tableSession->status);
    }

    private function servicePointStatus(ServicePoint $servicePoint): ServicePointStatus
    {
        return $servicePoint->status instanceof ServicePointStatus
            ? $servicePoint->status
            : ServicePointStatus::from((string) $servicePoint->status);
    }
}
