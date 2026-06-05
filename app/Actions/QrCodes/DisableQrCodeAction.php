<?php

namespace App\Actions\QrCodes;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\QrCodeStatus;
use App\Models\Branch;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;

class DisableQrCodeAction
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    public function handle(QrCode $qrCode, ?User $disabledBy = null, ?string $reason = null): QrCode
    {
        if ($qrCode->status !== QrCodeStatus::Active) {
            return $qrCode;
        }

        $servicePoint = $this->findServicePoint($qrCode);
        $branch = $this->findBranch($servicePoint);
        $oldStatus = $qrCode->status;

        $qrCode->status = QrCodeStatus::Disabled;
        $qrCode->save();

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
