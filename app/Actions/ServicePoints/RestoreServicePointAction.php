<?php

declare(strict_types=1);

namespace App\Actions\ServicePoints;

use App\Models\Branch;
use App\Models\ServicePoint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class RestoreServicePointAction
{
    public function handle(User $actor, Branch $branch, ServicePoint $servicePoint): void
    {
        DB::transaction(function () use ($actor, $branch, $servicePoint): void {
            $scopedServicePoint = $branch->servicePoints()
                ->withTrashed()
                ->select([
                    'service_points.id',
                    'service_points.branch_id',
                    'service_points.name',
                    'service_points.deleted_at',
                ])
                ->whereKey($servicePoint->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('restore', $scopedServicePoint);
            $scopedServicePoint->restore();
        });
    }
}
