<?php

namespace App\Actions\Invitations;

use App\Enums\InvitationStatus;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateInvitationAction
{
    /**
     * @param  array{brand?: Brand|null, branch?: Branch|null, email?: string|null, phone?: string|null, invite_token?: string|null, invite_code?: string|null, expires_at?: CarbonInterface|null}  $data
     */
    public function handle(Organization $organization, Role $role, User $invitedBy, array $data): Invitation
    {
        $branch = $data['branch'] ?? null;
        $brand = $data['brand'] ?? null;

        $this->ensureScopeBelongsToOrganization($organization, $brand, $branch);

        return Invitation::query()->create([
            'organization_id' => $organization->id,
            'brand_id' => $brand?->id ?? $branch?->brand_id,
            'branch_id' => $branch?->id,
            'role_id' => $role->id,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'invite_token' => $data['invite_token'] ?? Str::random(64),
            'invite_code' => $data['invite_code'] ?? Str::upper(Str::random(8)),
            'expires_at' => $data['expires_at'] ?? now()->addDays(7),
            'status' => InvitationStatus::Pending,
            'invited_by_user_id' => $invitedBy->id,
        ]);
    }

    private function ensureScopeBelongsToOrganization(Organization $organization, ?Brand $brand, ?Branch $branch): void
    {
        if ($brand instanceof Brand && $brand->organization_id !== $organization->id) {
            throw new InvalidArgumentException('Invitation brand must belong to the selected organization.');
        }

        if (! $branch instanceof Branch) {
            return;
        }

        if ($branch->organization_id !== $organization->id) {
            throw new InvalidArgumentException('Invitation branch must belong to the selected organization.');
        }

        if ($brand instanceof Brand && $branch->brand_id !== $brand->id) {
            throw new InvalidArgumentException('Invitation branch must belong to the selected brand.');
        }
    }
}
