<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Actions\Branches\DeleteBranchAction;
use App\Actions\Branches\RestoreBranchAction;
use App\Actions\Brands\DeleteBrandAction;
use App\Actions\Brands\RestoreBrandAction;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class ChangeStructureLifecycleAction
{
    public function __construct(
        private DeleteOrganizationAction $deleteOrganization, private RestoreOrganizationAction $restoreOrganization,
        private DeleteBrandAction $deleteBrand, private RestoreBrandAction $restoreBrand,
        private DeleteBranchAction $deleteBranch, private RestoreBranchAction $restoreBranch,
    ) {}

    public function handle(User $actor, Organization|Brand|Branch $resource, string $fingerprint, string $confirmation, bool $restore): void
    {
        DB::transaction(function () use ($actor, $resource, $fingerprint, $confirmation, $restore): void {
            $actor = User::query()->whereKey($actor->id)->firstOrFail();
            $current = $resource->newQuery()->withTrashed()->whereKey($resource->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize($current->trashed() ? 'restore' : 'delete', $current);
            if ($current->trashed() !== $restore || ! hash_equals($fingerprint, $current->identityFingerprint()) || $current->name !== $confirmation) {
                throw ValidationException::withMessages(['confirmation' => __('center.conflict')]);
            }
            if ($current instanceof Organization) {
                if ($restore) {
                    $this->restoreOrganization->handle($actor, $current);
                } else {
                    $this->deleteOrganization->handle($actor, $current);
                }
            } elseif ($current instanceof Brand) {
                $parent = Organization::query()->whereKey($current->organization_id)->firstOrFail();
                if ($restore) {
                    $this->restoreBrand->handle($actor, $parent, $current);
                } else {
                    $this->deleteBrand->handle($actor, $parent, $current);
                }
            } else {
                $parent = Organization::query()->whereKey($current->organization_id)->firstOrFail();
                $brand = Brand::query()->where('organization_id', $parent->id)->whereKey($current->brand_id)->firstOrFail();
                if ($restore) {
                    $this->restoreBranch->handle($actor, $parent, $brand, $current);
                    if ($current->refresh()->forceFill(['is_active' => false])->save() !== true) {
                        throw new \RuntimeException('The restored restaurant could not be deactivated.');
                    }
                } else {
                    $this->deleteBranch->handle($actor, $parent, $brand, $current);
                }
            }
        }, attempts: 3);
    }
}
