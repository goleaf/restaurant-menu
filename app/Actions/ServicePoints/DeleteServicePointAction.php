<?php

declare(strict_types=1);

namespace App\Actions\ServicePoints;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\QrCodes\DisableQrCodeAction;
use App\Actions\ServicePoints\Support\ServicePointMutationGuard;
use App\Enums\AuditLogAction;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Models\Branch;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\Branches\ServicePointQueryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

final class DeleteServicePointAction
{
    public function __construct(
        private readonly DisableQrCodeAction $disableQrCode,
        private readonly RecordAuditLogAction $recordAuditLog,
        private readonly ServicePointMutationGuard $guard,
        private readonly ServicePointQueryService $queries,
    ) {}

    public function handle(User $actor, Branch $branch, ServicePoint $servicePoint, ?int $expectedVersion = null): void
    {
        DB::transaction(function () use ($actor, $branch, $servicePoint, $expectedVersion): void {
            $actor = $this->guard->actor($actor);
            $branch = $this->guard->branch($branch->id);
            $scopedServicePoint = $this->queries->mutationRow($branch, $servicePoint->id);
            $scopedServicePoint->setRelation('branch', $branch);
            Gate::forUser($actor)->authorize('delete', $scopedServicePoint);
            $this->guard->version($scopedServicePoint, $expectedVersion);
            $this->guard->idle($scopedServicePoint, 'servicePointDeletion');

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
                    'structure_version',
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
            if (! $scopedServicePoint->forceFill([
                'is_active' => false,
                'status' => ServicePointStatus::Closed,
            ])->save() || ! $scopedServicePoint->delete()) {
                throw new RuntimeException('The service point could not be archived.');
            }

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
        }, 3);
    }
}
