<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Staff\SetUserPermissionOverrideAction;
use App\Actions\Staff\UpdateBranchStaffRoleAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\OrganizationSubscriptionStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\PermissionOverrideState;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Staff\Index;
use App\Livewire\Organizations\Staff\Show as StaffPermissions;
use App\Models\AreaNode;
use App\Models\AreaNodeWaiter;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\PermissionUserOverride;
use App\Models\Role;
use App\Models\User;
use App\Services\Staff\StaffQueryService;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('opening an assignment preserves an unsaved permission draft and its dirty baseline', function (): void {
    [$manager, $organization] = createCardDraftOrganization();
    grantCardDraftPermission($manager, $organization, SystemPermission::ManagePermissions);
    grantCardDraftPermission($manager, $organization, SystemPermission::ManageStaff);
    $staff = createCardDraftStaffMember($organization, SystemRole::Waiter);
    $member = OrganizationUser::query()->where('organization_id', $organization->id)->where('user_id', $staff->id)->firstOrFail();
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $permission = Permission::query()->where('code', SystemPermission::ManageMenu->value)->firstOrFail();
    $component = Livewire::actingAs($manager)->test(StaffPermissions::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch, 'member' => $member])
        ->call('openPermissions');
    $baseline = $component->get('originalEditor');
    $component->set('permissionForm.states.'.$permission->id, 'allow')->call('openExistingAssignment')
        ->assertSet('editor', 'permissions')->assertSet('originalEditor', $baseline)
        ->assertSet('permissionForm.states.'.$permission->id, 'allow')
        ->assertSee(__('staff.workspace.unsaved'));
    expect($staff->permissionOverrides($organization->id)->exists())->toBeFalse();
});

test('permission preview conflicts preserve draft state and use the form field prefix', function (): void {
    [$manager, $organization] = createCardDraftOrganization();
    grantCardDraftPermission($manager, $organization, SystemPermission::ManagePermissions);
    $staff = createCardDraftStaffMember($organization, SystemRole::Waiter);
    $permission = Permission::query()->where('code', SystemPermission::ManageMenu->value)->firstOrFail();
    $component = cardDraftPermissions($manager, $organization, $staff)->call('openPermissions')
        ->set('permissionForm.states.'.$permission->id, 'allow');
    app(SetUserPermissionOverrideAction::class)->handle($staff, $permission, PermissionOverrideState::Deny, $manager, $organization->id);
    $component->call('previewPermissions')->assertHasErrors(['permissionForm.states'])
        ->assertSet('permissionForm.states.'.$permission->id, 'allow');
});

function createCardDraftOrganization(): array
{
    $manager = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($manager, ['name' => 'Permission Overrides Group']);
    $role = Role::query()->where('code', SystemRole::ShiftManager->value)->firstOrFail();

    $manager->roles()->sync([$role->id]);
    OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $manager->id)
        ->firstOrFail()
        ->forceFill(['role_id' => $role->id])
        ->save();

    return [$manager->fresh(), $organization];
}

function createCardDraftStaffMember(Organization $organization, SystemRole $role): User
{
    $user = User::factory()->create();
    $roleModel = Role::query()->where('code', $role->value)->firstOrFail();

    $user->roles()->syncWithoutDetachingOrFail([$roleModel->id]);

    $organization->users()->syncWithoutDetachingOrFail([
        $user->id => [
            'role_id' => $roleModel->id,
            'status' => OrganizationUserStatus::Active,
            'joined_at' => now(),
        ],
    ]);

    return $user;
}

function grantCardDraftPermission(User $user, Organization $organization, SystemPermission $permission): void
{
    $membership = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $user->id)
        ->firstOrFail();
    $permissionModel = Permission::query()
        ->where('code', $permission->value)
        ->firstOrFail();

    $membership->role->permissions()->updateExistingPivot($permissionModel->id, ['enabled' => true]);
}

function cardDraftPermissions(User $actor, Organization $organization, User $subject): Testable
{
    $member = OrganizationUser::query()->where('organization_id', $organization->id)->where('user_id', $subject->id)->firstOrFail();

    return Livewire::actingAs($actor)->test(StaffPermissions::class, ['organization' => $organization, 'member' => $member])->call('selectSection', 'access');
}

test('restaurant staff lists inherited people once without materializing assignments or leaking another organization', function (): void {
    [$manager, $organization] = createCardDraftOrganization();
    grantCardDraftPermission($manager, $organization, SystemPermission::ManageStaff);
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $otherBranch = Branch::factory()->for($organization)->for($brand)->create();
    $inherited = OrganizationUser::factory()->forOrganization($organization)->forSystemRole(SystemRole::Waiter)->active()->create();
    $assigned = OrganizationUser::factory()->forOrganization($organization)->forSystemRole(SystemRole::Waiter)->active()->create();
    BranchUser::factory()->forBranch($branch)->forUser($assigned->user)->forRole($assigned->role)->create(['status' => OrganizationUserStatus::Suspended]);
    $elsewhere = OrganizationUser::factory()->forOrganization($organization)->forSystemRole(SystemRole::Waiter)->active()->create();
    BranchUser::factory()->forBranch($otherBranch)->forUser($elsewhere->user)->forRole($elsewhere->role)->active()->create();
    $foreign = OrganizationUser::factory()->forSystemRole(SystemRole::Waiter)->active()->create();
    $count = BranchUser::query()->count();
    $component = Livewire::actingAs($manager)->test(App\Livewire\Organizations\Brands\Branches\Staff\Index::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch]);
    $rows = collect($component->viewData('memberRows'))->keyBy('user_id');
    expect($rows->keys()->all())->toContain($inherited->user_id, $assigned->user_id)
        ->not->toContain($elsewhere->user_id, $foreign->user_id)
        ->and($rows[$inherited->user_id]['coverage'])->toBe(__('team.card.inherited'))
        ->and($rows[$assigned->user_id]['localized_status'])->toContain(OrganizationUserStatus::Suspended->localizedLabel())
        ->and(BranchUser::query()->count())->toBe($count);
});

test('accepted invitation cards use the persisted subject and restore invitation filters', function (): void {
    [$manager, $organization] = createCardDraftOrganization();
    grantCardDraftPermission($manager, $organization, SystemPermission::ManageStaff);
    User::factory()->count(3)->create();
    $member = OrganizationUser::factory()->forOrganization($organization)->forSystemRole(SystemRole::Waiter)->active()->create();
    $oldContact = 'old-accepted-contact@example.test';
    User::factory()->create(['email' => $oldContact]);
    $invitation = Invitation::factory()->forOrganization($organization)->forRole($member->role)->acceptedBy($member->user)
        ->create(['email' => $oldContact, 'invited_by_user_id' => $manager->id]);
    $component = Livewire::actingAs($manager)->test(Index::class, ['organization' => $organization])
        ->call('selectSection', 'invitations')->set('filters.status', 'accepted')->set('filters.search', $oldContact)->set('filters.sort', 'name');
    $row = collect($component->viewData('invitationRows'))->firstWhere('id', $invitation->id);
    expect($row['card_url'])->toContain('/members/'.$member->id)->not->toContain('/members/'.$member->user_id.'?');
    parse_str(parse_url($row['card_url'], PHP_URL_QUERY), $query);
    expect($query)->toMatchArray(['from' => 'invitations', 'search' => $oldContact, 'status' => 'accepted', 'sort' => 'name']);
    $card = Livewire::withQueryParams([...$query, 'listPage' => '4'])->actingAs($manager)->test(StaffPermissions::class, ['organization' => $organization, 'member' => $member]);
    $card->assertSee($member->user->name)->assertSet('returnSection', 'invitations')->assertHasNoErrors();
    parse_str(parse_url($card->viewData('listUrl'), PHP_URL_QUERY), $returnQuery);
    expect($returnQuery)->toMatchArray(['section' => 'invitations', 'status' => 'accepted', 'search' => $oldContact, 'sort' => 'name', 'organizationInvitationsPage' => '4']);
});

test('staff list query and visible row counts stay bounded as employees grow', function (): void {
    [$manager, $organization] = createCardDraftOrganization();
    grantCardDraftPermission($manager, $organization, SystemPermission::ManageStaff);
    OrganizationUser::factory()->count(20)->forOrganization($organization)->forSystemRole(SystemRole::Waiter)->active()->create();
    $component = Livewire::actingAs($manager)->test(Index::class, ['organization' => $organization]);
    $before = countDatabaseQueries(fn () => $component->call('$refresh'));
    OrganizationUser::factory()->count(40)->forOrganization($organization)->forSystemRole(SystemRole::Waiter)->active()->create();
    $after = countDatabaseQueries(fn () => $component->call('$refresh'));
    expect($after)->toBe($before)
        ->and($component->viewData('memberRows'))->toHaveCount(15)
        ->and($component->snapshot['data'])->not->toHaveKeys(['permissionRows', 'permissionForm', 'areaAssignments']);
});

test('restaurant coverage includes a waiter with inherited restaurant access', function (): void {
    [$manager, $organization] = createCardDraftOrganization();
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $waiter = OrganizationUser::factory()->forOrganization($organization)->forSystemRole(SystemRole::Waiter)->active()->create();
    AreaNode::factory()->for($branch)->create(['is_active' => true]);
    $coverage = app(StaffQueryService::class)->coverageOverview($branch);
    expect($waiter->user->canAccessBranch($branch))->toBeTrue()
        ->and($coverage['unrestricted_count'])->toBe(2)
        ->and($coverage['rows'][0]['assigned_count'])->toBe(2); // The manager has service access too.
});

test('restaurant coverage applies organization permission overrides to the selected branch waiter role', function (): void {
    [$manager, $organization] = createCardDraftOrganization();
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $waiter = OrganizationUser::factory()->forOrganization($organization)->forSystemRole(SystemRole::Waiter)->active()->create();
    $cook = OrganizationUser::factory()->forOrganization($organization)->forSystemRole(SystemRole::Cook)->active()->create();
    $waiterRole = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    BranchUser::factory()->forBranch($branch)->forUser($cook->user)->forRole($waiterRole)->active()->create();
    $viewOrders = Permission::query()->where('code', SystemPermission::ViewOrders->value)->firstOrFail();
    PermissionUserOverride::factory()->forUser($waiter->user)->forPermission($viewOrders)->forOrganization($organization)->denied()->create();
    AreaNode::factory()->for($branch)->create(['is_active' => true]);
    $queries = app(StaffQueryService::class);
    expect($queries->coverageOverview($branch)['unrestricted_count'])->toBe(1);
    PermissionUserOverride::factory()->forUser($cook->user)->forPermission($viewOrders)->forOrganization($organization)->allowed()->create();
    expect($queries->coverageOverview($branch)['unrestricted_count'])->toBe(2);
    $cook->forceFill(['status' => OrganizationUserStatus::Suspended])->save();
    expect($queries->coverageOverview($branch)['unrestricted_count'])->toBe(1);
});

test('archived last area assignments remain restrictions in the coverage overview', function (): void {
    [$manager, $organization] = createCardDraftOrganization();
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $member = OrganizationUser::factory()->forOrganization($organization)->forSystemRole(SystemRole::Waiter)->active()->create();
    BranchUser::factory()->forBranch($branch)->forUser($member->user)->forRole($member->role)->active()->create();
    $archived = AreaNode::factory()->for($branch)->create(['is_active' => true]);
    $other = AreaNode::factory()->for($branch)->create(['is_active' => true]);
    AreaNodeWaiter::factory()->create(['organization_id' => $organization->id, 'branch_id' => $branch->id, 'area_node_id' => $archived->id, 'user_id' => $member->user_id]);
    $archived->delete();
    $coverage = app(StaffQueryService::class)->coverageOverview($branch);
    expect($coverage['unrestricted_count'])->toBe(1)
        ->and(array_column($coverage['rows'], 'id'))->toBe([$other->id])
        ->and($coverage['rows'][0]['assigned_count'])->toBe(1); // The archived restriction remains; only the manager covers this area.
});

test('coverage preserves conservative legacy permission semantics and subscription suspension', function (): void {
    [$manager, $organization] = createCardDraftOrganization();
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $member = OrganizationUser::factory()->forOrganization($organization)->forSystemRole(SystemRole::Cook)->active()->create();
    $waiterRole = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    BranchUser::factory()->forBranch($branch)->forUser($member->user)->forRole($waiterRole)->active()->create();
    $viewOrders = Permission::query()->where('code', SystemPermission::ViewOrders->value)->firstOrFail();
    PermissionUserOverride::factory()->forUser($member->user)->forPermission($viewOrders)->allowed()->create();
    $queries = app(StaffQueryService::class);
    expect($queries->coverageOverview($branch)['unrestricted_count'])->toBe(2);
    OrganizationUser::factory()->forUser($member->user)->forSystemRole(SystemRole::Cook)->active()->create();
    expect($queries->coverageOverview($branch)['unrestricted_count'])->toBe(1);
    PermissionUserOverride::factory()->forUser($member->user)->forPermission($viewOrders)->forOrganization($organization)->allowed()->create();
    expect($queries->coverageOverview($branch)['unrestricted_count'])->toBe(2);
    $organization->subscription()->firstOrFail()->forceFill(['status' => OrganizationSubscriptionStatus::Inactive])->save();
    expect($queries->coverageOverview($branch)['unrestricted_count'])->toBe(0);
});

test('coverage page keeps bounded queries and payload while combining inherited and assigned staff', function (): void {
    [$manager, $organization] = createCardDraftOrganization();
    grantCardDraftPermission($manager, $organization, SystemPermission::ManageStaff);
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $members = OrganizationUser::factory()->count(30)->forOrganization($organization)->forSystemRole(SystemRole::Waiter)->active()->create();
    foreach ($members->take(10) as $member) {
        BranchUser::factory()->forBranch($branch)->forUser($member->user)->forRole($member->role)->active()->create();
    }
    AreaNode::factory()->count(10)->for($branch)->create(['is_active' => true]);
    $component = Livewire::actingAs($manager)->test(App\Livewire\Organizations\Brands\Branches\Staff\Index::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->call('selectSection', 'assignments');
    $queryCount = countDatabaseQueries(fn () => $component->call('$refresh'));
    $htmlBytes = strlen($component->html());
    $snapshotBytes = strlen(json_encode($component->snapshot));
    expect($component->viewData('coverageOverview')['unrestricted_count'])->toBe(31)
        ->and($queryCount)->toBeLessThan(100)
        ->and($htmlBytes)->toBeLessThan(200000)
        ->and($snapshotBytes)->toBeLessThan(20000);
});

test('coverage follows service access after a restaurant role change and returns typed participant links', function (): void {
    [$manager, $organization] = createCardDraftOrganization();
    grantCardDraftPermission($manager, $organization, SystemPermission::ManageStaff);
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    User::factory()->count(3)->create();
    $member = OrganizationUser::factory()->forOrganization($organization)->forSystemRole(SystemRole::Waiter)->active()->create();
    $assignment = BranchUser::factory()->forBranch($branch)->forUser($member->user)->forRole($member->role)->active()->create();
    $area = AreaNode::factory()->for($branch)->create(['is_active' => true]);
    AreaNodeWaiter::factory()->create(['organization_id' => $organization->id, 'branch_id' => $branch->id, 'area_node_id' => $area->id, 'user_id' => $member->user_id]);
    $cook = Role::query()->where('code', SystemRole::Cook->value)->firstOrFail();
    app(UpdateBranchStaffRoleAction::class)->handle($manager, $branch, $assignment, $cook, 'Restaurant assignment changed.');
    expect(app(ResolveWaiterAccessibleBranchIdsAction::class)->handle($member->user)->contains($branch->id))->toBeTrue();
    $coverage = app(StaffQueryService::class)->coverageOverview($branch);
    expect($coverage['unrestricted_count'])->toBe(2)
        ->and($coverage['rows'][0]['assigned_count'])->toBe(2)
        ->and(collect($coverage['unrestricted_members'])->firstWhere('id', $member->id))->toBe(['id' => $member->id, 'name' => $member->user->name]);
    expect(AreaNodeWaiter::query()->where('user_id', $member->user_id)->exists())->toBeFalse();
});

test('coverage includes separately allowed non waiter staff and excludes suspended or differently assigned service staff', function (): void {
    [$manager, $organization] = createCardDraftOrganization();
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $otherBranch = Branch::factory()->for($organization)->for($brand)->create();
    $cook = OrganizationUser::factory()->forOrganization($organization)->forSystemRole(SystemRole::Cook)->active()->create();
    $permission = Permission::query()->where('code', SystemPermission::ViewOrders->value)->firstOrFail();
    PermissionUserOverride::factory()->forUser($cook->user)->forPermission($permission)->forOrganization($organization)->allowed()->create();
    $suspended = OrganizationUser::factory()->forOrganization($organization)->forSystemRole(SystemRole::Waiter)->active()->create();
    BranchUser::factory()->forBranch($branch)->forUser($suspended->user)->forRole($suspended->role)->create(['status' => OrganizationUserStatus::Suspended]);
    $elsewhere = OrganizationUser::factory()->forOrganization($organization)->forSystemRole(SystemRole::Waiter)->active()->create();
    BranchUser::factory()->forBranch($otherBranch)->forUser($elsewhere->user)->forRole($elsewhere->role)->active()->create();
    $area = AreaNode::factory()->for($branch)->create(['is_active' => true]);
    AreaNodeWaiter::factory()->create(['organization_id' => $organization->id, 'branch_id' => $branch->id, 'area_node_id' => $area->id, 'user_id' => $cook->user_id]);
    $coverage = app(StaffQueryService::class)->coverageOverview($branch);
    expect($coverage['unrestricted_count'])->toBe(1)
        ->and($coverage['rows'][0]['assigned_count'])->toBe(2)
        ->and($coverage['rows'][0]['waiter_members'])->toBe([['id' => $cook->id, 'name' => $cook->user->name]])
        ->and(app(ResolveWaiterAccessibleBranchIdsAction::class)->handle($cook->user)->contains($branch->id))->toBeTrue()
        ->and(app(ResolveWaiterAccessibleBranchIdsAction::class)->handle($suspended->user)->contains($branch->id))->toBeFalse()
        ->and(app(ResolveWaiterAccessibleBranchIdsAction::class)->handle($elsewhere->user)->contains($branch->id))->toBeFalse();
});

test('coverage links stay bounded and preserve distinct membership identities for equal names', function (): void {
    [$manager, $organization] = createCardDraftOrganization();
    $manager->forceFill(['name' => 'ZZZ Manager'])->save();
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $area = AreaNode::factory()->for($branch)->create(['is_active' => true]);
    User::factory()->count(3)->create();
    $restricted = OrganizationUser::factory()->count(4)->forOrganization($organization)->forSystemRole(SystemRole::Waiter)->active()
        ->state(['user_id' => User::factory()->state(['name' => 'Equal Name'])])->create();
    foreach ($restricted as $member) {
        AreaNodeWaiter::factory()->create(['organization_id' => $organization->id, 'branch_id' => $branch->id, 'area_node_id' => $area->id, 'user_id' => $member->user_id]);
    }
    $unrestricted = OrganizationUser::factory()->count(4)->forOrganization($organization)->forSystemRole(SystemRole::Waiter)->active()
        ->state(['user_id' => User::factory()->state(['name' => 'Equal Name'])])->create();
    $coverage = app(StaffQueryService::class)->coverageOverview($branch);
    expect($coverage['unrestricted_count'])->toBe(5)
        ->and($coverage['rows'][0]['assigned_count'])->toBe(9)
        ->and(array_column($coverage['unrestricted_members'], 'id'))->toBe($unrestricted->take(3)->modelKeys())
        ->and(array_column($coverage['rows'][0]['waiter_members'], 'id'))->toBe($restricted->take(3)->modelKeys())
        ->and($coverage['unrestricted_names'])->toBe(['Equal Name', 'Equal Name', 'Equal Name'])
        ->and($coverage['rows'][0]['waiter_names'])->toBe(['Equal Name', 'Equal Name', 'Equal Name']);
});
