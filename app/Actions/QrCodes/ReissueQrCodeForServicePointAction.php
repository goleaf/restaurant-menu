<?php

namespace App\Actions\QrCodes;

use App\Enums\QrCodeStatus;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReissueQrCodeForServicePointAction
{
    public function __construct(
        private readonly GenerateQrCodeForServicePointAction $generateQrCode,
    ) {}

    public function handle(QrCode $qrCode, User $revokedBy): QrCode
    {
        return DB::transaction(function () use ($qrCode, $revokedBy): QrCode {
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

            return $this->generateQrCode->handle($servicePoint, $revokedBy);
        });
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
}
