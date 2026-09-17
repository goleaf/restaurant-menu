<?php

namespace App\Actions\QrCodes;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\QrCodeStatus;
use App\Models\Branch;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DisableQrCodeAction
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    public function handle(QrCode $qrCode, ?User $disabledBy = null, ?string $reason = null, ?int $expectedVersion = null): QrCode
    {
        return DB::transaction(function () use ($qrCode, $disabledBy, $reason, $expectedVersion): QrCode {
            $qrCode = QrCode::query()->select([
                'id', 'service_point_id', 'public_token', 'short_code', 'status', 'structure_version',
            ])->whereKey($qrCode->id)->where('service_point_id', $qrCode->service_point_id)->lockForUpdate()->firstOrFail();
            if ($expectedVersion !== null && $qrCode->structure_version !== $expectedVersion) {
                throw ValidationException::withMessages(['expectedVersion' => __('floor.validation.changed')]);
            }
            if ($qrCode->status !== QrCodeStatus::Active) {
                return $qrCode;
            }

            $servicePoint = $this->findServicePoint($qrCode);
            $branch = $this->findBranch($servicePoint);
            $oldStatus = $qrCode->status;

            $qrCode->status = QrCodeStatus::Disabled;
            if (! $qrCode->save()) {
                throw new RuntimeException('The QR status could not be saved.');
            }

            $this->recordAuditLog->handle(
                action: AuditLogAction::QrDisabled,
                entityType: 'qr_code',
                entityId: $qrCode->id,
                actorUser: $disabledBy,
                organizationId: $branch?->organization_id,
                branchId: $servicePoint?->branch_id,
                oldValues: [
                    'status' => $oldStatus,
                    'short_code' => $qrCode->short_code,
                ],
                newValues: [
                    'status' => QrCodeStatus::Disabled,
                    'short_code' => $qrCode->short_code,
                    'reason' => $this->normalizeReason($reason),
                ],
            );

            return $qrCode;
        }, 5);
    }

    private function findServicePoint(QrCode $qrCode): ?ServicePoint
    {
        return $qrCode->servicePoint()
            ->select(['id', 'branch_id'])
            ->first();
    }

    private function findBranch(?ServicePoint $servicePoint): ?Branch
    {
        if (! $servicePoint instanceof ServicePoint) {
            return null;
        }

        return Branch::query()
            ->select(['id', 'organization_id'])
            ->whereKey($servicePoint->branch_id)
            ->first();
    }

    private function normalizeReason(?string $reason): ?string
    {
        $normalized = trim((string) $reason);

        return $normalized === '' ? null : mb_substr($normalized, 0, 500);
    }
}
