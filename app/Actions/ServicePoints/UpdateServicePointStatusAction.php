<?php

namespace App\Actions\ServicePoints;

use App\Enums\ServicePointStatus;
use App\Models\ServicePoint;

class UpdateServicePointStatusAction
{
    public function handle(ServicePoint $servicePoint, ServicePointStatus|string $status): ServicePoint
    {
        $servicePointStatus = $status instanceof ServicePointStatus
            ? $status
            : ServicePointStatus::from($status);

        $servicePoint->fill([
            'status' => $servicePointStatus,
        ]);

        $servicePoint->save();

        return $servicePoint;
    }
}
