<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\InvitationStatus;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CreateInvitationAction
{
    public function __construct(
        private readonly InvitationCredentialGenerator $credentials,
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    /**
     * @param  array{brand?: Brand|null, branch?: Branch|null, email?: string|null, phone?: string|null, invite_token?: string|null, invite_code?: string|null, expires_at?: CarbonInterface|null}  $data
     */
    public function handle(Organization $organization, Role $role, User $invitedBy, array $data): CreatedInvitation
    {
        $branch = $data['branch'] ?? null;
        $brand = $data['brand'] ?? null;

        Gate::forUser($invitedBy)->authorize('manageStaff', $organization);
        $this->ensureScopeBelongsToOrganization($organization, $brand, $branch);

        if ($branch instanceof Branch) {
            Gate::forUser($invitedBy)->authorize('manageStaff', $branch);
        }

        Gate::forUser($invitedBy)->authorize('assign', [$role, $organization]);

        $credentials = $this->credentials->generate(
            $data['invite_token'] ?? null,
            $data['invite_code'] ?? null,
        );
        $email = isset($data['email'])
            ? Str::lower(trim($data['email']))
            : null;
        $phone = isset($data['phone'])
            ? trim($data['phone'])
            : null;

        if (! is_string($email) || $email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Invitation recipient email is required.');
        }

        return DB::transaction(function () use ($organization, $brand, $branch, $role, $invitedBy, $data, $email, $phone, $credentials): CreatedInvitation {
            $invitation = new Invitation;
            $invitation->forceFill([
                'organization_id' => $organization->id,
                'brand_id' => $brand->id ?? $branch?->brand_id,
                'branch_id' => $branch?->id,
                'role_id' => $role->id,
                'email' => $email,
                'phone' => $phone === '' ? null : $phone,
                'invite_token_hash' => $this->credentials->hash($credentials['token']),
                'invite_code_hash' => $this->credentials->hash($credentials['code']),
                'expires_at' => $data['expires_at'] ?? now()->addDays(7),
                'status' => InvitationStatus::Pending,
                'invited_by_user_id' => $invitedBy->id,
            ])->saveOrFail();

            $this->recordAuditLog->handle(
                action: AuditLogAction::InvitationCreated,
                entityType: 'invitation',
                entityId: $invitation->id,
                actorUser: $invitedBy,
                organizationId: $organization->id,
                branchId: $invitation->branch_id,
                newValues: [
                    'status' => InvitationStatus::Pending->value,
                    'role_id' => $role->id,
                    'email' => $email,
                    'phone' => $phone === '' ? null : $phone,
                    'expires_at' => $invitation->expires_at,
                ],
            );

            return new CreatedInvitation($invitation, $credentials['token'], $credentials['code']);
        }, 3);
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
