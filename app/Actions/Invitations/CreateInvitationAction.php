<?php

declare(strict_types=1);

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
    private const INVITE_TOKEN_LENGTH = 64;

    private const INVITE_CODE_LENGTH = 8;

    /**
     * @param  array{brand?: Brand|null, branch?: Branch|null, email?: string|null, phone?: string|null, invite_token?: string|null, invite_code?: string|null, expires_at?: CarbonInterface|null}  $data
     */
    public function handle(Organization $organization, Role $role, User $invitedBy, array $data): CreatedInvitation
    {
        $branch = $data['branch'] ?? null;
        $brand = $data['brand'] ?? null;

        $this->ensureScopeBelongsToOrganization($organization, $brand, $branch);

        $token = $this->inviteToken($data['invite_token'] ?? null);
        $code = $this->inviteCode($data['invite_code'] ?? null);
        $email = isset($data['email'])
            ? Str::lower(trim($data['email']))
            : null;
        $phone = isset($data['phone'])
            ? trim($data['phone'])
            : null;

        $invitation = new Invitation;
        $invitation->forceFill([
            'organization_id' => $organization->id,
            'brand_id' => $brand->id ?? $branch?->brand_id,
            'branch_id' => $branch?->id,
            'role_id' => $role->id,
            'email' => $email === '' ? null : $email,
            'phone' => $phone === '' ? null : $phone,
            'invite_token' => null,
            'invite_code' => null,
            'invite_token_hash' => self::credentialHash($token),
            'invite_code_hash' => self::credentialHash($code),
            'expires_at' => $data['expires_at'] ?? now()->addDays(7),
            'status' => InvitationStatus::Pending,
            'invited_by_user_id' => $invitedBy->id,
        ])->save();

        return new CreatedInvitation($invitation, $token, $code);
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

    private function inviteToken(?string $token): string
    {
        if ($token !== null) {
            $token = trim($token);

            if (strlen($token) !== self::INVITE_TOKEN_LENGTH || ! ctype_alnum($token)) {
                throw new InvalidArgumentException('Invitation token must be a 64 character random token.');
            }

            return $token;
        }

        do {
            $token = Str::random(self::INVITE_TOKEN_LENGTH);
        } while (Invitation::query()->where('invite_token_hash', self::credentialHash($token))->exists());

        return $token;
    }

    private function inviteCode(?string $code): string
    {
        if ($code !== null) {
            return Str::upper(trim($code));
        }

        do {
            $code = Str::upper(Str::random(self::INVITE_CODE_LENGTH));
        } while (Invitation::query()->where('invite_code_hash', self::credentialHash($code))->exists());

        return $code;
    }

    private static function credentialHash(string $credential): string
    {
        return hash('sha256', $credential);
    }
}
