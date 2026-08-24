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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ReissueInvitationAction
{
    public function __construct(
        private readonly InvitationCredentialGenerator $credentials,
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    public function handle(User $actor, Organization $organization, Invitation $invitation): CreatedInvitation
    {
        Gate::forUser($actor)->authorize('manageStaff', $organization);

        return DB::transaction(function () use ($actor, $organization, $invitation): CreatedInvitation {
            $scopedInvitation = Invitation::query()
                ->select([
                    'id',
                    'organization_id',
                    'brand_id',
                    'branch_id',
                    'role_id',
                    'email',
                    'phone',
                    'invite_token_hash',
                    'invite_code_hash',
                    'expires_at',
                    'status',
                    'invited_by_user_id',
                    'accepted_by_user_id',
                    'accepted_at',
                    'created_at',
                    'updated_at',
                ])
                ->where('organization_id', $organization->id)
                ->whereKey($invitation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($scopedInvitation->status, [InvitationStatus::Pending, InvitationStatus::Expired], true)) {
                throw ValidationException::withMessages([
                    'invitation' => __('staff.errors.invitation_cannot_be_reissued'),
                ]);
            }

            $role = Role::query()
                ->select(['id', 'code', 'name', 'sort_order'])
                ->whereKey($scopedInvitation->role_id)
                ->firstOrFail();
            Gate::forUser($actor)->authorize('assign', [$role, $organization]);

            if ($scopedInvitation->brand_id !== null) {
                Brand::query()
                    ->select(['id', 'organization_id'])
                    ->where('organization_id', $organization->id)
                    ->whereKey($scopedInvitation->brand_id)
                    ->firstOrFail();
            }

            if ($scopedInvitation->branch_id !== null) {
                $branch = Branch::query()
                    ->where('organization_id', $organization->id)
                    ->where('brand_id', $scopedInvitation->brand_id)
                    ->whereKey($scopedInvitation->branch_id)
                    ->firstOrFail();
                Gate::forUser($actor)->authorize('manageStaff', $branch);
            }

            $credentials = $this->credentials->generate();
            $previousStatus = $scopedInvitation->status;
            $scopedInvitation->forceFill([
                'invite_token_hash' => $this->credentials->hash($credentials['token']),
                'invite_code_hash' => $this->credentials->hash($credentials['code']),
                'expires_at' => now()->addDays(7),
                'status' => InvitationStatus::Pending,
                'accepted_by_user_id' => null,
                'accepted_at' => null,
            ])->saveOrFail();

            $this->recordAuditLog->handle(
                action: AuditLogAction::InvitationReissued,
                entityType: 'invitation',
                entityId: $scopedInvitation->id,
                actorUser: $actor,
                organizationId: $organization->id,
                branchId: $scopedInvitation->branch_id,
                oldValues: [
                    'status' => $previousStatus->value,
                ],
                newValues: [
                    'status' => InvitationStatus::Pending->value,
                    'role_id' => $scopedInvitation->role_id,
                    'expires_at' => $scopedInvitation->expires_at,
                ],
            );

            return new CreatedInvitation(
                $scopedInvitation->refresh(),
                $credentials['token'],
                $credentials['code'],
            );
        }, 3);
    }
}
