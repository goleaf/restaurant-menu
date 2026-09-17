<?php

declare(strict_types=1);

namespace App\Services\Branches;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class FloorLegacyEntryQuery
{
    public function __construct(private FloorWorkspaceQuery $workspace) {}

    /** @param array<string, mixed> $state */
    public function destination(User $actor, Organization $organization, Brand $brand, Branch $branch, array $state = [], ?ServicePoint $point = null, ?QrCode $qr = null, ?string $ability = null): string
    {
        abort_unless($brand->organization_id === $organization->id && $branch->organization_id === $organization->id && $branch->brand_id === $brand->id, 404);
        $this->workspace->branch($actor, $branch->id);
        if ($ability !== null) { Gate::forUser($actor)->authorize($ability, $branch); }
        if ($point !== null || $qr !== null) {
            abort_unless($point !== null && $qr !== null && $point->branch_id === $branch->id && $qr->service_point_id === $point->id, 404);
            Gate::forUser($actor)->authorize('generateQr', $branch);
            $state = [...$state, 'point' => $point->id, 'qr_record' => $qr->id];
        }
        return route('organizations.brands.branches.service-points.index', [
            'organization' => $organization->id, 'brand' => $brand->id, 'branch' => $branch->id, ...$state,
        ]);
    }
}
