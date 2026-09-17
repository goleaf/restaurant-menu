<?php

declare(strict_types=1);

use App\Actions\Staff\SetUserPermissionOverrideAction;
use App\Actions\Staff\UpdateBranchStaffRoleAction;
use App\Enums\AuditLogAction;
use App\Enums\InvitationStatus;
use App\Enums\PermissionOverrideState;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Staff\Index;
use App\Livewire\Organizations\Staff\Show;
use App\Models\AreaNode;
use App\Models\AreaNodeWaiter;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Invitation;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\PermissionUserOverride;
use App\Models\Role;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Assert;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->branch = Branch::factory()->create();
    $this->organization = $this->branch->organization;
    $this->owner = OrganizationUser::factory()->forOrganization($this->organization)->forSystemRole(SystemRole::Owner)->active()->create()->user;
    $this->member = OrganizationUser::factory()->forOrganization($this->organization)->forSystemRole(SystemRole::Waiter)->active()->create();
    $this->assignment = BranchUser::factory()->forBranch($this->branch)->forUser($this->member->user)->forRole($this->member->role)->active()->create();
    $this->parameters = ['organization' => $this->organization, 'member' => $this->member, 'brand' => $this->branch->brand, 'branch' => $this->branch];
});

test('employee role and status errors use complete local language and preserve the selected card', function (string $locale): void {
    $this->owner->update(['locale' => $locale]);
    app()->setLocale($locale);
    $card = Livewire::actingAs($this->owner)->withQueryParams(['section' => 'access'])->test(Show::class, $this->parameters);
    foreach ([
        ['roleId', '', 'required', 'staff.role', []],
        ['roleId', false, 'numeric', 'staff.role', []],
        ['roleId', 1.5, 'integer', 'staff.role', []],
        ['roleId', 999999, 'in', 'staff.role', []],
        ['status', 'unknown', 'in', 'staff.workspace.status', []],
        ['reason', '', 'required', 'validation.attributes.reason', []],
        ['reason', ['not a string'], 'string', 'validation.attributes.reason', []],
        ['reason', 'xx', 'min.string', 'validation.attributes.reason', ['min' => 3]],
        ['reason', str_repeat('x', 501), 'max.string', 'validation.attributes.reason', ['max' => 500]],
    ] as [$field, $value, $rule, $attribute, $parameters]) {
        $card->call('discardEditor')->call('openMember', $this->assignment->id, 'role')
            ->set('memberForm.reason', 'A valid unchanged draft')->set('memberForm.'.$field, $value)->call('previewMemberChange')
            ->assertHasErrors('memberForm.'.$field)->assertSet('editor', 'member')->assertSet('section', 'access')
            ->assertSet('membershipId', $this->member->id)->assertSet('memberForm.'.$field, $value);
        teamValidationMessage($card, 'memberForm.'.$field, $rule, $attribute, $locale, $parameters);
    }
    expect($this->assignment->fresh()->access_version)->toBe(0);
})->with(['en', 'lt', 'ru']);

test('area editor errors localize list types duplicates ids and search without broadening stored coverage', function (string $locale): void {
    $this->owner->update(['locale' => $locale]);
    app()->setLocale($locale);
    $area = AreaNode::factory()->forBranch($this->branch)->active()->create();
    $card = Livewire::actingAs($this->owner)->withQueryParams(['section' => 'areas'])->test(Show::class, $this->parameters);
    foreach ([
        ['areaIds', 'all', 'areaIds', 'array', 'staff.workspace.area_coverage', []],
        ['areaIds', [false], 'areaIds.0', 'numeric', 'staff.workspace.area_coverage', []],
        ['areaIds', ['1.5'], 'areaIds.0', 'integer', 'staff.workspace.area_coverage', []],
        ['areaIds', [0], 'areaIds.0', 'min.numeric', 'staff.workspace.area_coverage', ['min' => 1]],
        ['areaIds', [$area->id, $area->id], 'areaIds.0', 'distinct', 'staff.workspace.area_coverage', []],
        ['areaIds', range(1, 501), 'areaIds', 'max.array', 'staff.workspace.area_coverage', ['max' => 500]],
        ['search', ['nested'], 'search', 'string', 'validation.attributes.search', []],
        ['search', str_repeat('x', 121), 'search', 'max.string', 'validation.attributes.search', ['max' => 120]],
    ] as [$field, $value, $errorField, $rule, $attribute, $parameters]) {
        $card->call('discardEditor')->call('openAssignments', $this->assignment->id)
            ->set('assignmentForm.'.$field, $value)->call('previewAreaAssignments')->assertHasErrors('assignmentForm.'.$errorField)
            ->assertSet('editor', 'areas')->assertSet('section', 'areas')->assertSet('assignmentForm.'.$field, $value);
        teamValidationMessage($card, 'assignmentForm.'.$errorField, $rule, $attribute, $locale, $parameters);
    }
    expect($this->assignment->fresh()->access_version)->toBe(0);
})->with(['en', 'lt', 'ru']);

test('invitation creation form emits localized messages for every recipient role and expiry boundary', function (string $locale): void {
    $this->owner->update(['locale' => $locale]);
    app()->setLocale($locale);
    $center = Livewire::actingAs($this->owner)->test(Index::class, ['organization' => $this->organization]);
    foreach ([
        ['email', '', 'required', 'ui.auth.forgot_password.email_address', []],
        ['email', 'not-an-email', 'email', 'ui.auth.forgot_password.email_address', []],
        ['email', str_repeat('a', 256).'@example.test', 'max.string', 'ui.auth.forgot_password.email_address', ['max' => 255]],
        ['phone', ['nested'], 'string', 'validation.attributes.phone', []],
        ['phone', str_repeat('1', 41), 'max.string', 'validation.attributes.phone', ['max' => 40]],
        ['roleId', '', 'required', 'staff.role', []],
        ['roleId', false, 'numeric', 'staff.role', []],
        ['roleId', 999999, 'in', 'staff.role', []],
        ['expiresInDays', '', 'required', 'staff.fields.invitation_expiry_days', []],
        ['expiresInDays', false, 'numeric', 'staff.fields.invitation_expiry_days', []],
        ['expiresInDays', 1.5, 'integer', 'staff.fields.invitation_expiry_days', []],
        ['expiresInDays', 0, 'between.numeric', 'staff.fields.invitation_expiry_days', ['min' => 1, 'max' => 30]],
        ['expiresInDays', 31, 'between.numeric', 'staff.fields.invitation_expiry_days', ['min' => 1, 'max' => 30]],
    ] as [$field, $value, $rule, $attribute, $parameters]) {
        $center->call('discardEditor')->call('openInvitation')->set('invitationForm.email', 'fictional.recipient@example.test')
            ->set('invitationForm.roleId', $this->member->role_id)->set('invitationForm.expiresInDays', 7)
            ->set('invitationForm.'.$field, $value)->call('previewInvitation')->assertHasErrors('invitationForm.'.$field)
            ->assertSet('editor', 'invite');
        teamValidationMessage($center, 'invitationForm.'.$field, $rule, $attribute, $locale, $parameters);
    }
    expect(Invitation::query()->count())->toBe(0);
})->with(['en', 'lt', 'ru']);

test('permission draft errors and critical confirmation stay localized in the active employee card', function (string $locale): void {
    $this->owner->update(['locale' => $locale]);
    app()->setLocale($locale);
    $permission = Permission::query()->where('code', SystemPermission::ManageStaff->value)->sole();
    $card = Livewire::actingAs($this->owner)->test(Show::class, $this->parameters);
    foreach ([
        [[], 'permissionForm.states', 'required'],
        ['allow', 'permissionForm.states', 'array'],
        [[$permission->id => false], 'permissionForm.states.'.$permission->id, 'string'],
        [[$permission->id => 'unknown'], 'permissionForm.states.'.$permission->id, 'in'],
    ] as [$value, $field, $rule]) {
        $card->call('discardEditor')->call('openPermissions')->set('permissionForm.states', $value)->call('previewPermissions')
            ->assertHasErrors($field)->assertSet('editor', 'permissions')->assertSet('section', 'access')->assertSet('permissionForm.states', $value);
        teamValidationMessage($card, $field, $rule, 'permissions.labels.manage_permissions', $locale);
    }
    $card->call('discardEditor')->call('openPermissions')->set('permissionForm.states.'.$permission->id, 'deny')
        ->call('previewPermissions')->assertHasErrors(['permissionForm.reason' => 'required', 'permissionForm.confirmed' => 'accepted']);
    teamValidationMessage($card, 'permissionForm.reason', 'required', 'permissions.forms.change_reason', $locale);
    teamValidationMessage($card, 'permissionForm.confirmed', 'accepted', 'permissions.draft.confirm', $locale);
    $card->set('permissionForm.reason', str_repeat('x', 501))->set('permissionForm.confirmed', true)->call('previewPermissions')
        ->assertHasErrors(['permissionForm.reason' => 'max']);
    teamValidationMessage($card, 'permissionForm.reason', 'max.string', 'permissions.forms.change_reason', $locale, ['max' => 500]);
    expect(PermissionUserOverride::query()->where('user_id', $this->member->user_id)->count())->toBe(0);
})->with(['en', 'lt', 'ru']);

/** @param array<string,int|string> $parameters */
function teamValidationMessage(Testable $component, string $field, string $rule, string $attributeKey, string $locale, array $parameters = []): void
{
    $key = 'validation.'.$rule;
    expect(app('translator')->hasForLocale($key, $locale))->toBeTrue()
        ->and(app('translator')->hasForLocale($attributeKey, $locale))->toBeTrue();
    $expected = __($key, ['attribute' => __($attributeKey, [], $locale), ...$parameters], $locale);
    Assert::assertContains($expected, $component->errors()->get($field), json_encode($component->errors()->get($field), JSON_UNESCAPED_UNICODE));
    foreach ($component->errors()->get($field) as $message) {
        expect($message)->not->toContain('validation.', ':attribute', ':min', ':max', 'memberForm', 'assignmentForm', 'permissionForm', 'invitationForm', 'role id', 'area ids');
        if ($locale !== 'en') {
            expect($message)->not->toBe(__($key, ['attribute' => __($attributeKey, [], 'en'), ...$parameters], 'en'));
        }
    }
}

test('existing colleague identifier errors remain localized on the selected employee card', function (string $locale): void {
    $this->owner->update(['locale' => $locale]);
    app()->setLocale($locale);
    $card = Livewire::actingAs($this->owner)->withQueryParams(['section' => 'access'])->test(Show::class, $this->parameters);
    foreach ([['', 'required', []], [false, 'numeric', []], [0, 'min.numeric', ['min' => 1]], [1.5, 'integer', []]] as [$value, $rule, $parameters]) {
        $card->call('discardEditor')->call('openExistingAssignment')->set('memberForm.organizationMemberId', $value)
            ->call('previewExistingAssignment')->assertHasErrors('memberForm.organizationMemberId')
            ->assertSet('membershipId', $this->member->id)->assertSet('editor', 'assign');
        teamValidationMessage($card, 'memberForm.organizationMemberId', $rule, 'staff.workspace.existing_colleague', $locale, $parameters);
    }
})->with(['en', 'lt', 'ru']);

test('employee history localizes invitation lifecycle values and excludes unrelated audit actions', function (string $locale): void {
    $this->owner->update(['locale' => $locale]);
    app()->setLocale($locale);
    $invitation = Invitation::factory()->forOrganization($this->organization)->forRole($this->member->role)->acceptedBy($this->member->user)->create();
    $created = AuditLog::factory()->create([
        'organization_id' => $this->organization->id, 'branch_id' => null, 'user_id' => $this->owner->id,
        'action' => AuditLogAction::InvitationCreated, 'entity_type' => 'invitation', 'entity_id' => $invitation->id,
        'old_values' => null, 'new_values' => ['status' => 'pending', 'role' => SystemRole::Waiter->value],
    ]);
    $accepted = AuditLog::factory()->create([
        'organization_id' => $this->organization->id, 'branch_id' => null, 'user_id' => $this->owner->id,
        'action' => AuditLogAction::InvitationAccepted, 'entity_type' => 'invitation', 'entity_id' => $invitation->id,
        'old_values' => ['status' => 'pending'], 'new_values' => ['status' => 'accepted', 'invite_token' => 'do-not-render-secret'],
    ]);
    $unrelated = AuditLog::factory()->create([
        'organization_id' => $this->organization->id, 'branch_id' => null, 'user_id' => $this->owner->id,
        'action' => AuditLogAction::PaymentRecorded, 'entity_type' => 'organization_user', 'entity_id' => $this->member->id,
    ]);
    $card = Livewire::actingAs($this->owner)->withQueryParams(['section' => 'history'])->test(Show::class, $this->parameters)->assertDontSee('do-not-render-secret');
    $rows = collect($card->viewData('historyRows'))->keyBy('id');
    expect($rows->keys()->all())->toContain($created->id, $accepted->id)->not->toContain($unrelated->id)
        ->and($rows[$created->id]['action'])->toBe(__('audit.actions.invitation_created'))
        ->and($rows[$created->id]['after'])->toContain(InvitationStatus::Pending->localizedLabel(), SystemRole::Waiter->localizedLabel())
        ->and($rows[$accepted->id]['before'])->toContain(InvitationStatus::Pending->localizedLabel())
        ->and($rows[$accepted->id]['after'])->toContain(InvitationStatus::Accepted->localizedLabel());
})->with(['en', 'lt', 'ru']);

test('stale role domain errors stay localized and prefixed without discarding the card draft', function (string $locale): void {
    $this->owner->update(['locale' => $locale]);
    app()->setLocale($locale);
    $cook = Role::query()->where('code', SystemRole::Cook->value)->sole();
    $bartender = Role::query()->where('code', SystemRole::Bartender->value)->sole();
    $card = Livewire::actingAs($this->owner)->withQueryParams(['section' => 'access'])->test(Show::class, $this->parameters)
        ->call('openMember', $this->assignment->id, 'role')->set('memberForm.roleId', $cook->id)
        ->set('memberForm.reason', 'Keep this entered explanation')->call('previewMemberChange');
    app(UpdateBranchStaffRoleAction::class)->handle($this->owner, $this->branch, $this->assignment, $bartender, 'Another approved edit.', 0);
    $card->call('saveMember')->assertHasErrors('memberForm.roleId')->assertSet('section', 'access')
        ->assertSet('memberForm.roleId', $cook->id)->assertSet('memberForm.reason', 'Keep this entered explanation');
    teamDomainMessage($card, 'memberForm.roleId', 'staff.errors.stale_membership', $locale);
    expect($this->assignment->fresh()->role_id)->toBe($bartender->id);
})->with(['en', 'lt', 'ru']);

test('foreign area rejection stays localized in the selected area card without leaking its name', function (string $locale): void {
    $this->owner->update(['locale' => $locale]);
    app()->setLocale($locale);
    $foreign = AreaNode::factory()->active()->create(['name' => 'PRIVATE FOREIGN AREA']);
    $card = Livewire::actingAs($this->owner)->withQueryParams(['section' => 'areas'])->test(Show::class, $this->parameters)
        ->set('assignmentForm.areaIds', [$foreign->id])->call('previewAreaAssignments')->call('saveAreaAssignments')
        ->assertHasErrors('assignmentForm.areaIds')->assertSet('section', 'areas')->assertSet('assignmentForm.areaIds', [$foreign->id])
        ->assertDontSee($foreign->name);
    teamDomainMessage($card, 'assignmentForm.areaIds', 'staff.errors.zone_unavailable', $locale);
    expect(AreaNodeWaiter::query()->where('user_id', $this->member->user_id)->count())->toBe(0);
})->with(['en', 'lt', 'ru']);

test('stale permission domain errors use the draft field and local language after another administrator saves', function (string $locale): void {
    $this->owner->update(['locale' => $locale]);
    app()->setLocale($locale);
    $permission = Permission::query()->where('code', SystemPermission::ManageMenu->value)->sole();
    $card = Livewire::actingAs($this->owner)->test(Show::class, $this->parameters)->call('openPermissions')
        ->set('permissionForm.states.'.$permission->id, 'allow');
    app(SetUserPermissionOverrideAction::class)->handle($this->member->user, $permission,
        PermissionOverrideState::Deny, $this->owner, $this->organization->id);
    $card->call('previewPermissions')->assertHasErrors('permissionForm.states')->assertSet('section', 'access')
        ->assertSet('permissionForm.states.'.$permission->id, 'allow');
    teamDomainMessage($card, 'permissionForm.states', 'permissions.errors.stale_draft', $locale);
})->with(['en', 'lt', 'ru']);

function teamDomainMessage(Testable $component, string $field, string $key, string $locale): void
{
    expect(app('translator')->hasForLocale($key, $locale))->toBeTrue()
        ->and($component->errors()->get($field))->toContain(__($key, [], $locale));
    if ($locale !== 'en') {
        expect(__($key, [], $locale))->not->toBe(__($key, [], 'en'));
    }
}
