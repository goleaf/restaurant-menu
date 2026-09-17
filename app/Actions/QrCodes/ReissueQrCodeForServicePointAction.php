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

class ReissueQrCodeForServicePointAction
{
    public function __construct(
        private readonly GenerateQrCodeForServicePointAction $generateQrCode,
        private readonly StoreQrCodeImageAction $storeQrCodeImage,
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    public function handle(QrCode $qrCode, User $revokedBy, ?int $expectedVersion = null, bool $storeImage = true, ?string $reason = null): QrCode
    {
        $result = DB::transaction(function () use ($qrCode, $revokedBy, $expectedVersion, $reason): array {
            $currentQrCode = $this->reloadQrCode($qrCode);
            if ($expectedVersion !== null && $currentQrCode->structure_version !== $expectedVersion) {
                throw ValidationException::withMessages(['expectedVersion' => __('floor.validation.changed')]);
            }
            $servicePoint = $this->findServicePoint($currentQrCode);

            $activeQrCodes = $servicePoint
                ->qrCodes()
                ->select([
                    'id',
                    'service_point_id',
                    'public_token',
                    'short_code',
                    'status',
                    'structure_version',
                    'created_by_user_id',
                    'revoked_at',
                    'revoked_by_user_id',
                    'created_at',
                    'updated_at',
                ])
                ->where('status', QrCodeStatus::Active->value)
                ->lockForUpdate()
                ->get();

            if ($currentQrCode->status !== QrCodeStatus::Active && $activeQrCodes->isNotEmpty()) {
                return [
                    'new_qr_code' => $activeQrCodes->firstOrFail(),
                    'revoked_qr_codes' => $currentQrCode->status === QrCodeStatus::Revoked
                        ? collect([$currentQrCode])
                        : collect(),
                ];
            }

            foreach ($activeQrCodes as $activeQrCode) {
                $activeQrCode->status = QrCodeStatus::Revoked;
                $activeQrCode->revoked_at = now();
                $activeQrCode->revoked_by_user_id = $revokedBy->id;
                if (! $activeQrCode->save()) {
                    throw new RuntimeException('The QR revocation could not be saved.');
                }
            }

            $replacementQrCode = $this->generateQrCode->handle($servicePoint, $revokedBy, storeImage: false);

            $this->recordReissue($servicePoint, $activeQrCodes, $replacementQrCode, $revokedBy, $reason);

            return [
                'new_qr_code' => $replacementQrCode,
                'revoked_qr_codes' => $activeQrCodes,
            ];
        }, 5);

        if (! $storeImage) {
            return $result['new_qr_code'];
        }

        $this->storeQrCodeImage->handle($result['new_qr_code']);

        foreach ($result['revoked_qr_codes'] as $revokedQrCode) {
            if ($revokedQrCode->id !== $result['new_qr_code']->id) {
                $this->storeQrCodeImage->delete($revokedQrCode);
            }
        }

        return $result['new_qr_code'];
    }

    private function reloadQrCode(QrCode $qrCode): QrCode
    {
        return QrCode::query()
            ->select([
                'id',
                'service_point_id',
                'public_token',
                'short_code',
                'status',
                'structure_version',
                'created_by_user_id',
                'revoked_at',
                'revoked_by_user_id',
                'created_at',
                'updated_at',
            ])
            ->whereKey($qrCode->id)
            ->where('service_point_id', $qrCode->service_point_id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function findServicePoint(QrCode $qrCode): ServicePoint
    {
        return $qrCode
            ->servicePoint()
            ->select([
                'id',
                'branch_id',
                'area_node_id',
                'type',
                'name',
                'display_number',
                'internal_code',
                'capacity',
                'icon',
                'status',
                'structure_version',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->firstOrFail();
    }

    private function recordReissue(ServicePoint $servicePoint, iterable $revokedQrCodes, QrCode $newQrCode, User $revokedBy, ?string $reason): void
    {
        $branch = Branch::query()
            ->select(['id', 'organization_id'])
            ->whereKey($servicePoint->branch_id)
            ->first();

        $revoked = collect($revokedQrCodes)
            ->map(fn (QrCode $qrCode): array => [
                'id' => $qrCode->id,
                'short_code' => $qrCode->short_code,
                'status' => QrCodeStatus::Revoked->value,
            ])
            ->values()
            ->all();

        $this->recordAuditLog->handle(
            action: AuditLogAction::QrReissued,
            entityType: 'qr_code',
            entityId: $newQrCode->id,
            actorUser: $revokedBy,
            organizationId: $branch?->organization_id,
            branchId: $servicePoint->branch_id,
            oldValues: [
                'service_point_id' => $servicePoint->id,
                'revoked_qr_codes' => $revoked,
            ],
            newValues: [
                'service_point_id' => $servicePoint->id,
                'qr_code_id' => $newQrCode->id,
                'short_code' => $newQrCode->short_code,
                'status' => QrCodeStatus::Active->value,
                'reason' => filled($reason) ? trim($reason) : null,
            ],
        );
    }
}
