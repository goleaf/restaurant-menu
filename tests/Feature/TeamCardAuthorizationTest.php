<?php

declare(strict_types=1);

use App\Enums\AuditLogAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Staff\Index as BranchStaffIndex;
use App\Livewire\Organizations\Staff\Show;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\PermissionUserOverride;
use App\Models\Role;
use App\Models\User;
use App\Services\Navigation\WorkspaceAccessQuery;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->organization = Organization::factory()->create();
    $this->ownerMember = OrganizationUser::factory()->forOrganization($this->organization)
        ->forSystemRole(SystemRole::Owner)->active()->create();
    $this->owner = $this->ownerMember->user;
    $this->branch = Branch::factory()->for($this->organization)->create();
    $this->subject = OrganizationUser::factory()->forOrganization($this->organization)
        ->forSystemRole(SystemRole::Waiter)->active()->create();
    $this->parameters = ['organization' => $this->organization, 'member' => $this->subject];
    $this->branchParameters = [...$this->parameters, 'brand' => $this->branch->brand, 'branch' => $this->branch];
});

test('a permissions-only administrator can find the team and open an employee without staff mutation rights', function (): void {
    $administrator = OrganizationUser::factory()->forOrganization($this->organization)
        ->forSystemRole(SystemRole::Director)->active()->create()->user;
    foreach ([SystemPermission::ManageStaff->value => false, SystemPermission::ManagePermissions->value => true] as $code => $enabled) {
        PermissionUserOverride::factory()->forOrganization($this->organization)->forUser($administrator)
            ->forPermission(Permission::query()->where('code', $code)->sole())->create(['enabled' => $enabled]);
    }

    expect(Gate::forUser($administrator)->allows('manageStaff', $this->branch))->toBeFalse()
        ->and(app(WorkspaceAccessQuery::class)->destinations($administrator)['team'])->toContain($this->branch->id);
    $this->actingAs($administrator)->get(route('organizations.staff.index', $this->organization))->assertOk();
    $this->get(route('organizations.brands.branches.staff.index', [$this->organization, $this->branch->brand, $this->branch]))->assertOk();
    $card = Livewire::actingAs($administrator)->withQueryParams(['section' => 'access'])->test(Show::class, $this->branchParameters)
        ->assertSet('membershipId', $this->subject->id)->call('openPermissions')->assertSet('editor', 'permissions');
    expect($card->viewData('card'))->toMatchArray(['can_permissions' => true, 'can_manage' => false]);
    $card->call('openMember', $this->subject->id, 'status')->assertForbidden();
    expect($this->subject->fresh()->status)->toBe(OrganizationUserStatus::Active);
});

test('employee cards deny unrelated capability holders and foreign organizations', function (): void {
    $this->actingAs($this->subject->user)->get(route('organizations.staff.show', $this->parameters))->assertForbidden();
    $foreign = OrganizationUser::factory()->forSystemRole(SystemRole::Waiter)->active()->create();
    $this->actingAs($this->owner)->get(route('organizations.staff.show', [
        'organization' => $this->organization, 'member' => $foreign,
    ]))->assertNotFound();
});

test('a restaurant card rejects a foreign restaurant even for an administrator of both organizations', function (): void {
    $foreign = Organization::factory()->create();
    OrganizationUser::factory()->forOrganization($foreign)->forUser($this->owner)->forSystemRole(SystemRole::Owner)->active()->create();
    $foreignBranch = Branch::factory()->for($foreign)->create();

    $this->actingAs($this->owner)->get(route('organizations.brands.branches.staff.show', [
        'organization' => $this->organization, 'brand' => $foreignBranch->brand, 'branch' => $foreignBranch, 'member' => $this->subject,
    ]))->assertNotFound();
});

test('a card cannot swap its locked organization membership or mutate another selected employee', function (): void {
    $other = OrganizationUser::factory()->forOrganization($this->organization)->forSystemRole(SystemRole::Waiter)->active()->create();
    $component = Livewire::actingAs($this->owner)->test(Show::class, $this->parameters);
    expect(fn () => $component->set('membershipId', $other->id))->toThrow(CannotUpdateLockedPropertyException::class);

    Livewire::actingAs($this->owner)->test(Show::class, $this->parameters)
        ->call('openMember', $other->id, 'status')->assertNotFound();
    expect($this->subject->fresh()->status)->toBe(OrganizationUserStatus::Active)
        ->and($other->fresh()->status)->toBe(OrganizationUserStatus::Active);
});

test('the legacy permission address resolves a user to their membership in the requested organization', function (): void {
    User::factory()->count(3)->create();
    $person = User::factory()->create();
    $member = OrganizationUser::factory()->forOrganization($this->organization)->forUser($person)
        ->forSystemRole(SystemRole::Waiter)->active()->create();
    $foreign = OrganizationUser::factory()->forUser($person)->forSystemRole(SystemRole::Cook)->active()->create();
    expect($member->id)->not->toBe($person->id);

    $this->actingAs($this->owner)->get(route('organizations.staff.permissions', [
        'organization' => $this->organization, 'staffMember' => $person,
    ]))->assertOk()->assertSee($person->name)->assertSeeLivewire(Show::class);
    Livewire::actingAs($this->owner)->test(Show::class, ['organization' => $this->organization, 'staffMember' => $person])
        ->assertSet('membershipId', $member->id)->assertSet('section', 'access');
    expect($member->fresh()->role_id)->not->toBe($foreign->role_id);
});

test('a card explains the selected employee permissions instead of the administrators or branch role defaults', function (): void {
    BranchUser::factory()->forBranch($this->branch)->forUser($this->subject->user)
        ->forRole($this->ownerMember->role)->active()->create();
    expect(Gate::forUser($this->owner)->allows('manageMenu', $this->branch))->toBeTrue()
        ->and(Gate::forUser($this->subject->user)->allows('manageMenu', $this->branch))->toBeFalse();
    $component = Livewire::actingAs($this->owner)->withQueryParams(['section' => 'access'])->test(Show::class, $this->branchParameters);
    $menu = collect($component->viewData('permissionRows'))->firstWhere('code', SystemPermission::ManageMenu->value);
    expect($menu)->toMatchArray(['role_default' => false, 'effective_allowed' => false]);
});

test('inherited branch access is visible without creating a restaurant assignment', function (): void {
    $component = Livewire::actingAs($this->owner)->test(Show::class, $this->branchParameters);
    expect($component->viewData('card'))->toMatchArray([
        'mode' => 'inherited', 'branch_accessible' => true, 'membership_id' => null, 'can_assign' => true,
    ])->and(BranchUser::query()->where('user_id', $this->subject->user_id)->exists())->toBeFalse();
});

test('card restaurant details never expose the persons independent organization or inaccessible restaurants', function (): void {
    $foreignMember = OrganizationUser::factory()->forUser($this->subject->user)->forSystemRole(SystemRole::Cook)->active()->create();
    $foreignBranch = Branch::factory()->for($foreignMember->organization)->create(['name' => 'PRIVATE OTHER ORGANIZATION']);
    $hiddenBranch = Branch::factory()->for($this->organization)->create(['name' => 'PRIVATE OTHER RESTAURANT']);
    BranchUser::factory()->forBranch($this->branch)->forUser($this->owner)->forRole($this->ownerMember->role)->active()->create();

    $card = Livewire::actingAs($this->owner)->test(Show::class, $this->branchParameters)->viewData('card');
    expect(array_column($card['branches'], 'name'))->toBe([$this->branch->name])
        ->not->toContain($hiddenBranch->name, $foreignBranch->name);
});

test('a restaurant status form retains its own scope after another tab changes the restaurant preference', function (): void {
    $otherBranch = Branch::factory()->for($this->organization)->create();
    $assignment = BranchUser::factory()->forBranch($this->branch)->forUser($this->subject->user)->forRole($this->subject->role)->active()->create();
    $other = BranchUser::factory()->forBranch($otherBranch)->forUser($this->subject->user)->forRole($this->subject->role)->active()->create();
    $component = Livewire::actingAs($this->owner)->test(Show::class, $this->branchParameters)
        ->call('openMember', $assignment->id, 'status')->set('memberForm.status', 'suspended')
        ->set('memberForm.reason', 'Review this restaurant access')->call('previewMemberChange');
    session()->put('workspace.preference', ['actor' => $this->owner->id, 'branch' => $otherBranch->id, 'destination' => 'team']);
    $component->call('saveMember')->assertHasNoErrors();

    expect($assignment->fresh()->status)->toBe(OrganizationUserStatus::Suspended)
        ->and($other->fresh()->status)->toBe(OrganizationUserStatus::Active)
        ->and($this->subject->fresh()->status)->toBe(OrganizationUserStatus::Active);
});

test('employee card history requires its own permission and matches typed employee entities', function (): void {
    $included = AuditLog::factory()->create([
        'organization_id' => $this->organization->id, 'branch_id' => $this->branch->id, 'user_id' => $this->owner->id,
        'action' => AuditLogAction::StaffPermissionChanged, 'entity_type' => 'staff_permission', 'entity_id' => $this->subject->user_id,
        'old_values' => ['state' => 'default'], 'new_values' => ['state' => 'deny'],
    ]);
    $unrelated = AuditLog::factory()->create([
        'organization_id' => $this->organization->id, 'branch_id' => $this->branch->id, 'user_id' => $this->owner->id,
        'entity_type' => 'menu_item', 'entity_id' => $this->subject->user_id,
    ]);
    $component = Livewire::actingAs($this->owner)->withQueryParams(['section' => 'history'])->test(Show::class, $this->branchParameters);
    expect(array_column($component->viewData('historyRows'), 'id'))->toContain($included->id)->not->toContain($unrelated->id);

    PermissionUserOverride::factory()->forOrganization($this->organization)->forUser($this->owner)
        ->forPermission(Permission::query()->where('code', SystemPermission::ViewAuditLog->value)->sole())->denied()->create();
    $component->call('$refresh')->assertViewHas('historyRows', []);
    expect($component->viewData('card')['can_history'])->toBeFalse();
});

test('viewing a managers own card does not authorize self editing', function (): void {
    $component = Livewire::actingAs($this->owner)->test(Show::class, [
        'organization' => $this->organization, 'member' => $this->ownerMember,
    ]);
    expect($component->viewData('card'))->toMatchArray(['can_manage' => false, 'can_permissions' => false]);
    $component->call('openPermissions')->assertForbidden();
});

test('opening a restaurant assignment never resets a dirty permission draft baseline', function (): void {
    $permission = Permission::query()->where('code', SystemPermission::ManageMenu->value)->sole();
    $component = Livewire::actingAs($this->owner)->test(Show::class, $this->branchParameters)->call('openPermissions');
    $baseline = $component->get('originalEditor');
    $component->set('permissionForm.states.'.$permission->id, 'allow')->call('openExistingAssignment')
        ->assertSet('editor', 'permissions')->assertSet('originalEditor', $baseline)
        ->call('selectSection', 'overview')->assertSet('section', 'access')
        ->assertSet('permissionForm.states.'.$permission->id, 'allow');
});

test('critical permission validation remains attached to the employee card form', function (): void {
    $permission = Permission::query()->where('code', SystemPermission::ManageStaff->value)->sole();
    Livewire::actingAs($this->owner)->test(Show::class, $this->branchParameters)->call('openPermissions')
        ->set('permissionForm.states.'.$permission->id, 'deny')->call('previewPermissions')->call('applyPermissions')
        ->assertHasErrors(['permissionForm.reason' => 'required', 'permissionForm.confirmed' => 'accepted'])
        ->assertSet('editor', 'permissions')->assertSet('section', 'access')
        ->assertSet('permissionForm.states.'.$permission->id, 'deny');
    expect(PermissionUserOverride::query()->where('user_id', $this->subject->user_id)->exists())->toBeFalse();
});

test('restaurant employee filters identify their organizational scope while showing a different restaurant assignment', function (): void {
    $cook = Role::query()->where('code', SystemRole::Cook->value)->sole();
    BranchUser::factory()->forBranch($this->branch)->forUser($this->subject->user)->forRole($cook)->suspended()->create();
    $component = Livewire::actingAs($this->owner)->test(BranchStaffIndex::class, [
        'organization' => $this->organization, 'brand' => $this->branch->brand, 'branch' => $this->branch,
    ])->set('filters.role', SystemRole::Waiter->value)->set('filters.status', OrganizationUserStatus::Active->value)
        ->assertSee(__('team.card.organization_role'))->assertSee(__('team.card.branch_list_scope'));

    expect(array_column($component->viewData('memberRows'), 'user_id'))->toContain($this->subject->user_id);
    $row = collect($component->viewData('memberRows'))->firstWhere('user_id', $this->subject->user_id);
    expect($row['role_label'])->toContain(SystemRole::Waiter->localizedLabel(), SystemRole::Cook->localizedLabel())
        ->and($row['localized_status'])->toContain(OrganizationUserStatus::Active->localizedLabel(), OrganizationUserStatus::Suspended->localizedLabel());
    $component->set('filters.role', SystemRole::Cook->value);
    expect(array_column($component->viewData('memberRows'), 'user_id'))->not->toContain($this->subject->user_id);
});
