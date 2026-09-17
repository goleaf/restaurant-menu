<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Enums\AuditLogAction;
use App\Enums\InvitationStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\PermissionOverrideState;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Navigation\WorkspaceAccessQuery;
use App\Support\LocalizedDateFormatter;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;

final class TeamCardQueryService
{
    public function __construct(private readonly WorkspaceAccessQuery $workspaceAccess) {}

    /** @return list<array{label:string,allowed:bool}> */
    public function capabilities(User $subject, Branch $branch): array
    {
        $destinations = $this->workspaceAccess->destinations($subject);
        $groups = [
            'team.card.service' => ['waiter'], 'permissions.groups.menu' => ['menu', 'availability'],
            'permissions.groups.departments' => ['kitchen', 'bar'], 'permissions.groups.reports' => ['reports', 'report_view'],
            'permissions.groups.staff' => ['team'], 'permissions.groups.restaurant' => ['settings'],
        ];
        $rows = [];
        foreach ($groups as $label => $keys) {
            $allowed = false;
            foreach ($keys as $key) {
                $allowed = $allowed || in_array($branch->id, $destinations[$key] ?? [], true);
            }
            $rows[] = ['label' => __($label), 'allowed' => $allowed];
        }

        return $rows;
    }

    public function member(Organization $organization, int $id): OrganizationUser
    {
        $member = OrganizationUser::query()->select(['id', 'organization_id', 'user_id', 'role_id', 'status', 'access_version'])
            ->with(['organization:id,name,is_active', 'user:id,name,email', 'user.roles:id,code', 'role:id,code,name,sort_order'])
            ->where('organization_id', $organization->id)->whereKey($id)->first();
        abort_unless($member instanceof OrganizationUser, 404);

        return $member;
    }

    public function memberForUser(Organization $organization, int $userId): OrganizationUser
    {
        $id = OrganizationUser::query()->where('organization_id', $organization->id)->where('user_id', $userId)->value('id');
        abort_if($id === null, 404);

        return $this->member($organization, (int) $id);
    }

    public function assignment(OrganizationUser $member, Branch $branch): ?BranchUser
    {
        return BranchUser::query()->select(['id', 'organization_id', 'branch_id', 'user_id', 'role_id', 'status', 'access_version'])
            ->with(['role:id,code,name,sort_order', 'user:id,name,email'])
            ->where('organization_id', $member->organization_id)->where('branch_id', $branch->id)->where('user_id', $member->user_id)->first();
    }

    /** @return array<string, mixed> */
    public function overview(User $actor, Organization $organization, OrganizationUser $member, ?Branch $branch): array
    {
        Gate::forUser($actor)->authorize('viewCard', [$member, $branch]);
        $assignment = $branch === null ? null : $this->assignment($member, $branch);
        $explicit = BranchUser::query()->where('organization_id', $organization->id)->where('user_id', $member->user_id)->exists();
        $canManage = $actor->id !== $member->user_id && ! $member->user->isSuperadmin()
            && Gate::forUser($actor)->allows('manageStaff', $branch ?? $organization)
            && Gate::forUser($actor)->allows('assign', [($assignment ?? $member)->role, $organization]);
        $subjectIds = $member->user->accessibleBranchIdsForOrganization($organization)->all();
        $actorIds = $actor->accessibleBranchIdsForOrganization($organization)->all();
        $branches = Branch::query()->select(['id', 'organization_id', 'brand_id', 'name'])->where('organization_id', $organization->id)
            ->whereIn('id', $actorIds)->orderBy('name')->orderBy('id')->simplePaginate(15, pageName: 'memberBranchesPage')->withQueryString();
        $assignments = BranchUser::query()->select(['id', 'branch_id', 'role_id', 'status'])->with('role:id,code,name,sort_order')
            ->where('organization_id', $organization->id)->where('user_id', $member->user_id)->whereIn('branch_id', $branches->getCollection()->modelKeys())->get()->keyBy('branch_id');
        $rows = $branches->getCollection()->map(function (Branch $item) use ($assignments, $subjectIds, $member): array {
            $record = $assignments->get($item->id);

            return ['id' => $item->id, 'name' => $item->name, 'accessible' => in_array($item->id, $subjectIds, true),
                'role' => $record?->role->code->localizedLabel(), 'status' => $record?->status->localizedLabel(),
                'url' => $this->url($member, $item)];
        })->all();
        $tasks = $branch !== null && Gate::forUser($actor)->allows('openTable', $branch)
            ? TableSession::query()->where('branch_id', $branch->id)->where('opened_by_user_id', $member->user_id)->whereNull('ended_at')->count() : null;

        return ['self_edit_blocked' => $actor->id === $member->user_id, 'protected_account' => $member->user->isSuperadmin(), 'id' => $member->id, 'user_id' => $member->user_id, 'name' => $member->user->name, 'email' => $member->user->email,
            'organization_role' => $member->role->code->localizedLabel(), 'organization_status' => $member->status->localizedLabel(),
            'branch_role' => $assignment?->role->code->localizedLabel(), 'branch_status' => $assignment?->status->localizedLabel(),
            'mode' => $explicit ? 'explicit' : 'inherited', 'branch_accessible' => $branch !== null && in_array($branch->id, $subjectIds, true),
            'membership_id' => $assignment->id ?? ($branch === null ? $member->id : null), 'can_manage' => $canManage,
            'can_remove' => $canManage && $assignment !== null && $member->status === OrganizationUserStatus::Active,
            'can_assign' => $canManage && $branch !== null && $assignment === null && $member->status === OrganizationUserStatus::Active,
            'can_areas' => $canManage && $assignment?->role->code === SystemRole::Waiter && $assignment->status === OrganizationUserStatus::Active && $member->status === OrganizationUserStatus::Active,
            'can_permissions' => ! $member->user->isSuperadmin() && Gate::forUser($actor)->allows('managePermissions', $member),
            'can_history' => Gate::forUser($actor)->allows('viewHistory', [$member, $branch]),
            'organization_url' => Gate::forUser($actor)->allows('viewTeam', $organization) ? $this->url($member) : null,
            'branches' => $rows, 'branches_paginator' => $branches, 'open_tasks' => $tasks];
    }

    /** @return Paginator<int, AuditLog> */
    public function history(User $actor, OrganizationUser $member, ?Branch $branch): Paginator
    {
        Gate::forUser($actor)->authorize('viewHistory', [$member, $branch]);
        $branchIds = Branch::query()->where('organization_id', $member->organization_id)
            ->whereIn('id', $actor->accessibleBranchIdsForOrganization($member->organization_id))->pluck('id')->all();
        $assignments = BranchUser::query()->select('id')->where('organization_id', $member->organization_id)->where('user_id', $member->user_id);
        $invitations = Invitation::query()->select('id')->where('organization_id', $member->organization_id)->where('accepted_by_user_id', $member->user_id);

        return AuditLog::query()->select(['id', 'organization_id', 'branch_id', 'user_id', 'action', 'entity_type', 'entity_id', 'old_values', 'new_values', 'created_at'])
            ->with(['user:id,name', 'branch:id,name'])->where('organization_id', $member->organization_id)
            ->whereIn('action', [AuditLogAction::StaffPermissionChanged, AuditLogAction::StaffRoleChanged, AuditLogAction::StaffDeactivated, AuditLogAction::StaffReactivated, AuditLogAction::InvitationCreated, AuditLogAction::InvitationReissued, AuditLogAction::InvitationAccepted, AuditLogAction::InvitationCancelled])
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhereIn('branch_id', $branch === null ? $branchIds : [$branch->id]))
            ->where(fn ($query) => $query
                ->where(fn ($scope) => $scope->where('entity_type', 'organization_user')->where('entity_id', $member->id))
                ->orWhere(fn ($scope) => $scope->where('entity_type', 'branch_user')
                    ->where(fn ($identity) => $identity->whereIn('entity_id', $assignments)
                        ->orWhere('old_values->staff_user_id', $member->user_id)->orWhere('new_values->staff_user_id', $member->user_id)))
                ->orWhere(fn ($scope) => $scope->where('entity_type', 'staff_permission')->where('entity_id', $member->user_id))
                ->orWhere(fn ($scope) => $scope->where('entity_type', 'invitation')
                    ->where(fn ($identity) => $identity->whereIn('entity_id', $invitations)
                        ->orWhere(fn ($cancelled) => $cancelled->where('action', AuditLogAction::InvitationCancelled)->where('new_values->staff_user_id', $member->user_id)))))
            ->orderByDesc('id')->simplePaginate(20, pageName: 'memberHistoryPage')->withQueryString();
    }

    /** @return array<string, mixed> */
    public function historyRow(AuditLog $event): array
    {
        $safe = function (?array $values) use ($event): array {
            $result = [];
            foreach (['role', 'status', 'state', 'reason', 'permission_code', 'branch_access_mode'] as $key) {
                $value = $values[$key] ?? null;
                if (! is_string($value) || $value === '') {
                    continue;
                }
                $result[] = match ($key) {
                    'role' => SystemRole::tryFrom($value)?->localizedLabel() ?? $value,
                    'branch_access_mode' => $value === 'organization' ? __('team.card.inherited') : __('team.card.explicit'),
                    'reason' => $event->entity_type === 'invitation' && $event->action === AuditLogAction::InvitationCancelled && in_array($value, ['membership_suspended', 'branch_assignment_removed'], true) ? __('team.card.invitation_stopped') : $value,
                    'status' => $event->entity_type === 'invitation' ? (InvitationStatus::tryFrom($value)?->localizedLabel() ?? $value) : (OrganizationUserStatus::tryFrom($value)?->localizedLabel() ?? $value),
                    'state' => ($state = PermissionOverrideState::tryFrom($value)) === null ? '' : __($state->summaryLabelKey()),
                    'permission_code' => ($permission = SystemPermission::tryFrom($value)) === null ? '' : __($permission->uiLabelKey()),
                };
            }
            if (isset($values['area_node_ids']) && is_array($values['area_node_ids'])) {
                $result[] = $values['area_node_ids'] === [] ? __('staff.workspace.coverage_all') : __('staff.workspace.coverage_count', ['count' => count($values['area_node_ids'])]);
            }

            return $result;
        };

        return ['id' => $event->id, 'actor' => $event->user?->name, 'date' => LocalizedDateFormatter::dateTime($event->created_at),
            'scope' => $event->branch->name ?? __('team.card.organization_scope'), 'action' => match (true) {
                $event->action === AuditLogAction::StaffPermissionChanged && $event->entity_type === 'branch_user' && ($event->new_values['scope'] ?? null) === 'areas' => __('team.card.areas_changed'),
                $event->action === AuditLogAction::StaffPermissionChanged && $event->entity_type === 'organization_user' && ($event->new_values['scope'] ?? null) === 'branch_assignment_removed' => __('team.card.assignment_removed'),
                default => $event->action->label(),
            },
            'before' => $safe($event->old_values), 'after' => $safe($event->new_values)];
    }

    /** @param array<string, mixed> $query */
    public function url(OrganizationUser $member, ?Branch $branch = null, string $section = 'overview', array $query = []): string
    {
        return route($branch === null ? 'organizations.staff.show' : 'organizations.brands.branches.staff.show', [
            'organization' => $member->organization_id, ...($branch === null ? [] : ['brand' => $branch->brand_id, 'branch' => $branch->id]),
            'member' => $member->id, ...$query, 'section' => $section,
        ]);
    }
}
