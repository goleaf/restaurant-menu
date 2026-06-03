<?php

namespace App\Actions\QrCodes;

use App\Enums\QrCodeStatus;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use LogicException;

class GenerateQrCodeForServicePointAction
{
    private const PUBLIC_TOKEN_LENGTH = 64;

    private const SHORT_CODE_LENGTH = 8;

    private const SHORT_CODE_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public function handle(ServicePoint $servicePoint, ?User $createdBy = null): QrCode
    {
        $activeQrCode = $this->activeQrCodeFor($servicePoint);

        if ($activeQrCode instanceof QrCode) {
            return $activeQrCode;
        }

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            try {
                return $servicePoint->qrCodes()->create([
                    'public_token' => Str::random(self::PUBLIC_TOKEN_LENGTH),
                    'short_code' => $this->generateShortCode(),
                    'status' => QrCodeStatus::Active,
                    'created_by_user_id' => $createdBy?->id,
                ]);
            } catch (QueryException $exception) {
                $activeQrCode = $this->activeQrCodeFor($servicePoint);

                if ($activeQrCode instanceof QrCode) {
                    return $activeQrCode;
                }

                if ($attempt === 10) {
                    throw $exception;
                }
            }
        }

        throw new LogicException('Unable to generate a QR code.');
    }

    private function activeQrCodeFor(ServicePoint $servicePoint): ?QrCode
    {
        return $servicePoint
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
            ->first();
    }

    private function generateShortCode(): string
    {
        $characters = '';
        $maxIndex = strlen(self::SHORT_CODE_ALPHABET) - 1;

        for ($index = 0; $index < self::SHORT_CODE_LENGTH; $index++) {
            $characters .= self::SHORT_CODE_ALPHABET[random_int(0, $maxIndex)];
        }

        return 'QR-'.$characters;
    }
}
