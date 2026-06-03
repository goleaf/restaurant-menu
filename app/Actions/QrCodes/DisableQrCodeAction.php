<?php

namespace App\Actions\QrCodes;

use App\Enums\QrCodeStatus;
use App\Models\QrCode;

class DisableQrCodeAction
{
    public function handle(QrCode $qrCode): QrCode
    {
        if ($qrCode->status !== QrCodeStatus::Active) {
            return $qrCode;
        }

        $qrCode->status = QrCodeStatus::Disabled;
        $qrCode->save();

        return $qrCode;
    }
}
