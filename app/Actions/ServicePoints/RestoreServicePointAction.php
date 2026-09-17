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

final class RestoreServicePointAction
{
    public function __construct(private readonly ServicePointMutationGuard $guard, private readonly ServicePointQueryService $queries, private readonly RecordAuditLogAction $audit, private readonly DisableQrCodeAction $disableQrCode) {}

    public function handle(User $actor, Branch $branch, ServicePoint $servicePoint, ?int $expectedVersion = null): void
    {
        DB::transaction(function () use ($actor, $branch, $servicePoint, $expectedVersion): void {
            $actor = $this->guard->actor($actor);
            $branch = $this->guard->branch($branch->id);
            $scopedServicePoint = $this->queries->mutationRow($branch, $servicePoint->id, true);
            $scopedServicePoint->setRelation('branch', $branch);
            Gate::forUser($actor)->authorize('restore', $scopedServicePoint);
            $this->guard->version($scopedServicePoint, $expectedVersion);
            $scopedServicePoint->forceFill(['is_active' => false, 'status' => ServicePointStatus::Closed]);
            if (! $scopedServicePoint->restore()) {
                throw new RuntimeException('The service point could not be restored.');
            }
            $activeQrCode = $scopedServicePoint->qrCodes()->select(['id', 'service_point_id', 'structure_version'])
                ->where('status', QrCodeStatus::Active->value)->lockForUpdate()->first();
            if ($activeQrCode instanceof QrCode) {
                $this->disableQrCode->handle($activeQrCode, $actor, __('service_points.audit.deleted_qr_disabled'), $activeQrCode->structure_version);
            }
            $this->audit->handle(AuditLogAction::ServicePointChanged, 'service_point', $scopedServicePoint->id,
                actorUser: $actor, organizationId: $branch->organization_id, branchId: $branch->id,
                oldValues: ['archived' => true], newValues: ['kind' => 'restored', 'archived' => false]);
        }, 3);
    }
}
