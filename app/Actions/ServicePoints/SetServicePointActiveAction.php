<?php

declare(strict_types=1);

namespace App\Actions\ServicePoints;

use App\Models\ServicePoint;

final class SetServicePointActiveAction
{
    public function handle(ServicePoint $servicePoint, bool $isActive): ServicePoint
    {
        $servicePoint->updateOrFail(['is_active' => $isActive]);

        return $servicePoint;
    }
}
