<?php

declare(strict_types=1);

namespace App\Actions\ServicePoints;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\QrCodes\DisableQrCodeAction;
use App\Enums\AuditLogAction;
use App\Enums\BusinessRuleCode;
use App\Enums\OrderStatus;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Branch;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class DeleteServicePointAction
{
    public function __construct(
        private readonly DisableQrCodeAction $disableQrCode,
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    public function handle(User $actor, Branch $branch, ServicePoint $servicePoint): void
    {
        DB::transaction(function () use ($actor, $branch, $servicePoint): void {
            $scopedServicePoint = $branch->servicePoints()
                ->select([
                    'service_points.id',
                    'service_points.branch_id',
                    'service_points.area_node_id',
                    'service_points.name',
                    'service_points.internal_code',
                    'service_points.status',
                    'service_points.is_active',
                ])
                ->withExists([
                    'activeTableSession',
                    'activeTableSessionServicePointLinks',
                    'orders as active_order_exists' => fn ($query) => $query
                        ->whereIn('status', OrderStatus::activeValues()),
                ])
                ->whereKey($servicePoint->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('delete', $scopedServicePoint);

            if (
                (bool) $scopedServicePoint->getAttribute('active_table_session_exists')
                || (bool) $scopedServicePoint->getAttribute('active_table_session_service_point_links_exists')
            ) {
                throw BusinessRuleViolation::for(
                    BusinessRuleCode::ServicePointHasActiveSession,
                    'servicePointDeletion',
                    context: [
                        'service_point_id' => $scopedServicePoint->id,
                        'branch_id' => $branch->id,
                    ],
                );
            }

            if ((bool) $scopedServicePoint->getAttribute('active_order_exists')) {
                throw BusinessRuleViolation::for(
                    BusinessRuleCode::StructureHasActiveOrder,
                    'servicePointDeletion',
                    context: [
                        'service_point_id' => $scopedServicePoint->id,
                        'branch_id' => $branch->id,
                    ],
                );
            }

            $activeQrCode = $scopedServicePoint->qrCodes()
                ->select([
                    'id',
                    'service_point_id',
                    'active_service_point_id',
                    'public_token',
                    'short_code',
                    'status',
                    'created_by_user_id',
                    'revoked_at',
                    'revoked_by_user_id',
                    'created_at',
                    'updated_at',
                ])
                ->where('status', QrCodeStatus::Active->value)
                ->lockForUpdate()
                ->first();

            if ($activeQrCode instanceof QrCode) {
                $this->disableQrCode->handle(
                    $activeQrCode,
                    $actor,
                    __('service_points.audit.deleted_qr_disabled'),
                );
            }

            $previousStatus = $scopedServicePoint->status;
            $wasActive = $scopedServicePoint->is_active;
            $scopedServicePoint->forceFill([
                'is_active' => false,
                'status' => ServicePointStatus::Closed,
            ])->saveOrFail();
            $scopedServicePoint->deleteOrFail();

            $this->recordAuditLog->handle(
                action: AuditLogAction::ServicePointDeleted,
                entityType: 'service_point',
                entityId: $scopedServicePoint->id,
                actorUser: $actor,
                organizationId: (int) $branch->organization_id,
                branchId: $branch->id,
                oldValues: [
                    'name' => $scopedServicePoint->name,
                    'internal_code' => $scopedServicePoint->internal_code,
                    'status' => $previousStatus->value,
                    'is_active' => $wasActive,
                ],
                newValues: [
                    'status' => ServicePointStatus::Closed->value,
                    'is_active' => false,
                    'deleted' => true,
                ],
            );
        });
    }
}
