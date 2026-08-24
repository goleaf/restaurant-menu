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
use Throwable;

class ReissueQrCodeForServicePointAction
{
    public function __construct(
        private readonly GenerateQrCodeForServicePointAction $generateQrCode,
        private readonly StoreQrCodeImageAction $storeQrCodeImage,
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    public function handle(QrCode $qrCode, User $revokedBy): QrCode
    {
        $replacementQrCode = null;

        try {
            $result = DB::transaction(function () use ($qrCode, $revokedBy, &$replacementQrCode): array {
                $servicePoint = $this->findServicePoint($qrCode);

                $activeQrCodes = $servicePoint
                    ->qrCodes()
                    ->select([
                        'id',
                        'service_point_id',
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
                    ->get();

                foreach ($activeQrCodes as $activeQrCode) {
                    $activeQrCode->status = QrCodeStatus::Revoked;
                    $activeQrCode->revoked_at = now();
                    $activeQrCode->revoked_by_user_id = $revokedBy->id;
                    $activeQrCode->save();
                }

                $replacementQrCode = $this->generateQrCode->handle($servicePoint, $revokedBy);

                $this->recordReissue($servicePoint, $activeQrCodes, $replacementQrCode, $revokedBy);

                return [
                    'new_qr_code' => $replacementQrCode,
                    'revoked_qr_codes' => $activeQrCodes,
                ];
            }, 5);
        } catch (Throwable $exception) {
            if ($replacementQrCode instanceof QrCode) {
                $this->storeQrCodeImage->delete($replacementQrCode);
            }

            throw $exception;
        }

        foreach ($result['revoked_qr_codes'] as $revokedQrCode) {
            if ($revokedQrCode->id !== $result['new_qr_code']->id) {
                $this->storeQrCodeImage->delete($revokedQrCode);
            }
        }

        return $result['new_qr_code'];
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
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->firstOrFail();
    }

    private function recordReissue(ServicePoint $servicePoint, iterable $revokedQrCodes, QrCode $newQrCode, User $revokedBy): void
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
            ],
        );
    }
}
