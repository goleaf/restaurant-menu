<?php

use App\Actions\Staff\UpdateOrganizationStaffRoleAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Staff\Index as BranchStaffIndex;
use App\Livewire\Organizations\Staff\Index as OrganizationStaffIndex;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
    $this->owner = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->membership = OrganizationUser::factory()->forOrganization($this->organization)->for($this->owner)->forSystemRole(SystemRole::Owner)->active()->create();
    $this->brand = Brand::factory()->for($this->organization)->create();
    $this->branch = Branch::factory()->for($this->organization)->for($this->brand)->create();
});

test('only the selected staff section is queried and full assignment maps never serialize', function () {
    DB::enableQueryLog();
    $component = Livewire::actingAs($this->owner)->test(BranchStaffIndex::class, ['organization' => $this->organization, 'brand' => $this->brand, 'branch' => $this->branch]);
    $queries = collect(DB::getQueryLog())->pluck('query')->implode(' ');
    DB::disableQueryLog();
    expect($queries)->not->toContain('from "invitations"');
    expect($component->snapshot['data'])->not->toHaveKeys(['areaAssignments', 'manualName', 'manualEmail', 'lastInviteLink', 'lastInviteCode']);
    expect(method_exists(OrganizationStaffIndex::class, 'addManualStaffMember'))->toBeFalse()
        ->and(method_exists(BranchStaffIndex::class, 'addManualStaffMember'))->toBeFalse();
});

test('invitation preview does not create identity and its bearer is response only', function () {
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    $users = User::query()->count();
    $members = OrganizationUser::query()->count();
    $component = Livewire::actingAs($this->owner)->test(OrganizationStaffIndex::class, ['organization' => $this->organization])
        ->call('openInvitation')->set('invitationForm.email', 'team-preview@example.test')
        ->set('invitationForm.roleId', $role->id)->call('previewInvitation');
    expect(Invitation::query()->count())->toBe(0);
    $component->call('createInviteLink')->assertHasNoErrors();
    expect(Invitation::query()->count())->toBe(1)->and(User::query()->count())->toBe($users)->and(OrganizationUser::query()->count())->toBe($members);
    $link = $component->viewData('createdInvitationLink');
    expect($link)->toBeString()->and(json_encode($component->snapshot))->not->toContain($link);
    $component->call('$refresh')->assertViewHas('createdInvitationLink', null);
});

test('plain refresh revokes team visibility after organization membership suspension', function () {
    $component = Livewire::actingAs($this->owner)->test(OrganizationStaffIndex::class, ['organization' => $this->organization]);
    $this->membership->forceFill(['status' => OrganizationUserStatus::Suspended])->save();
    $component->call('$refresh')->assertForbidden();
});

test('staff filter changes reset only the staff paginator and reject malformed URL filters', function () {
    $component = Livewire::actingAs($this->owner)->test(OrganizationStaffIndex::class, ['organization' => $this->organization])
        ->call('setPage', 3, 'organizationInvitationsPage')->call('setPage', 2, 'organizationStaffPage')
        ->set('filters.search', 'person');
    expect($component->get('paginators.organizationStaffPage'))->toBe(1)
        ->and($component->get('paginators.organizationInvitationsPage'))->toBe(3);
    $component->set('filters.sort', 'unsafe')->assertHasErrors('filters.sort');
});

test('changing invitation details after preview requires a new review and creates nothing', function () {
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    Livewire::actingAs($this->owner)->test(OrganizationStaffIndex::class, ['organization' => $this->organization])
        ->call('openInvitation')->set('invitationForm.email', 'first@example.test')->set('invitationForm.roleId', $role->id)
        ->call('previewInvitation')->set('invitationForm.email', 'second@example.test')->call('createInviteLink')
        ->assertHasErrors('preview')->assertSet('invitationForm.email', 'second@example.test');
    expect(Invitation::query()->count())->toBe(0);
});

test('member access conflicts retain the draft and never overwrite a newer role', function () {
    $waiter = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    $cook = Role::query()->where('code', SystemRole::Cook->value)->firstOrFail();
    $bartender = Role::query()->where('code', SystemRole::Bartender->value)->firstOrFail();
    $member = OrganizationUser::factory()->forOrganization($this->organization)->forRole($waiter)->active()->create();
    $component = Livewire::actingAs($this->owner)->test(OrganizationStaffIndex::class, ['organization' => $this->organization])
        ->call('openMember', $member->id)->set('memberForm.roleId', $cook->id)->set('memberForm.reason', 'Change preparation responsibilities.')
        ->call('previewMemberChange');
    app(UpdateOrganizationStaffRoleAction::class)->handle($this->owner, $this->organization, $member, $bartender, 'Another administrator assigned bar duties.');
    $component->call('saveMember')->assertHasErrors('editingRoleId')->assertSet('editor', 'member')
        ->assertSet('memberForm.roleId', $cook->id)->assertSet('memberForm.reason', 'Change preparation responsibilities.');
    expect($member->fresh()->role_id)->toBe($bartender->id);
});

test('dirty member and invitation drafts prevent section changes and browser history replacement', function () {
    $component = Livewire::actingAs($this->owner)->test(OrganizationStaffIndex::class, ['organization' => $this->organization])
        ->call('openInvitation')->set('invitationForm.email', 'retained@example.test')
        ->call('selectSection', 'invitations')->assertSet('section', 'employees')->assertSet('invitationForm.email', 'retained@example.test');
    $component->set('section', 'invitations')->assertSet('section', 'employees')->assertSet('editor', 'invite');
    $component->call('discardEditor')->call('selectSection', 'invitations')->assertSet('section', 'invitations');
});

test('effective invitation filters use the exact expiry boundary without a scheduler', function () {
    $this->freezeTime();
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    Invitation::factory()->forOrganization($this->organization)->pending()->create(['role_id' => $role->id, 'email' => 'boundary@example.test', 'expires_at' => now()]);
    Invitation::factory()->forOrganization($this->organization)->pending()->create(['role_id' => $role->id, 'email' => 'future@example.test', 'expires_at' => now()->addMinute()]);
    $component = Livewire::actingAs($this->owner)->test(OrganizationStaffIndex::class, ['organization' => $this->organization])
        ->call('selectSection', 'invitations')->set('filters.status', 'expired')
        ->assertSee('boundary@example.test')->assertDontSee('future@example.test');
    $component->set('filters.status', 'pending')->assertSee('future@example.test')->assertDontSee('boundary@example.test');
});

test('malformed member identifiers cannot select a truncated valid membership', function (mixed $id) {
    Livewire::actingAs($this->owner)->test(OrganizationStaffIndex::class, ['organization' => $this->organization])
        ->call('openMember', $id)->assertHasErrors('member')->assertSet('editingMembershipId', null);
})->with([2.5, true, '2junk', [['id' => 2]]]);

test('member cards link to authorized organization permissions and hide self links', function () {
    $member = OrganizationUser::factory()->forOrganization($this->organization)->forSystemRole(SystemRole::Waiter)->active()->create();
    $component = Livewire::actingAs($this->owner)->test(OrganizationStaffIndex::class, ['organization' => $this->organization]);
    $rows = collect($component->viewData('memberRows'))->keyBy('id');
    expect($rows[$member->id]['permissions_url'])->toBe(route('organizations.staff.permissions', ['organization' => $this->organization->id, 'staffMember' => $member->user_id]))
        ->and($rows[$this->membership->id]['permissions_url'])->toBeNull();
});

test('invitation summary aggregates effective states with the same tenant search and role scope', function () {
    $this->freezeTime();
    $waiter = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    $cook = Role::query()->where('code', SystemRole::Cook->value)->firstOrFail();
    Invitation::factory()->forOrganization($this->organization)->pending()->create(['role_id' => $waiter->id, 'email' => 'team-pending@example.test', 'expires_at' => now()->addDay()]);
    Invitation::factory()->forOrganization($this->organization)->pending()->create(['role_id' => $waiter->id, 'email' => 'team-expired@example.test', 'expires_at' => now()]);
    Invitation::factory()->forOrganization($this->organization)->pending()->create(['role_id' => $cook->id, 'email' => 'team-cook@example.test']);
    Invitation::factory()->forOrganization($this->organization)->pending()->create(['brand_id' => $this->brand->id, 'branch_id' => $this->branch->id, 'role_id' => $waiter->id, 'email' => 'team-branch@example.test']);
    Invitation::factory()->forOrganization($this->organization)->pending()->create(['role_id' => $waiter->id, 'email' => 'other@example.test']);
    $component = Livewire::actingAs($this->owner)->test(OrganizationStaffIndex::class, ['organization' => $this->organization])
        ->call('selectSection', 'invitations')->set('filters.search', 'team-')->set('filters.role', $waiter->code->value);
    $summary = collect($component->viewData('invitationSummary'))->keyBy('status');
    expect($summary['pending']['count'])->toBe(1)->and($summary['expired']['count'])->toBe(1)->and($summary['accepted']['count'])->toBe(0);
    $component->set('filters.status', 'expired')->assertSee('team-expired@example.test')->assertDontSee('team-pending@example.test');
});

test('removed manual provisioning cannot be called through either Livewire workspace', function (bool $branchScope) {
    $component = Livewire::actingAs($this->owner)->test($branchScope ? BranchStaffIndex::class : OrganizationStaffIndex::class,
        $branchScope ? ['organization' => $this->organization, 'brand' => $this->brand, 'branch' => $this->branch] : ['organization' => $this->organization]);
    $users = User::query()->count();
    $members = OrganizationUser::query()->count();
    expect(fn () => $component->call('addManualStaffMember'))->toThrow(MethodNotFoundException::class);
    expect(User::query()->count())->toBe($users)->and(OrganizationUser::query()->count())->toBe($members);
})->with([false, true]);

test('creating an invitation from a filtered employee list clears the incompatible status', function () {
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    Livewire::actingAs($this->owner)->test(OrganizationStaffIndex::class, ['organization' => $this->organization])
        ->set('filters.status', 'active')->call('openInvitation')->set('invitationForm.email', 'filtered@example.test')
        ->set('invitationForm.roleId', $role->id)->call('previewInvitation')->call('createInviteLink')
        ->assertHasNoErrors()->assertSet('section', 'invitations')->assertSet('filters.status', '')
        ->assertSee('filtered@example.test');
});

test('invitation preview explains enabled role defaults without exposing disabled or foreign grants', function () {
    $waiter = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    $enabled = Permission::query()->where('code', SystemPermission::ViewOrders->value)->firstOrFail();
    $disabled = Permission::query()->where('code', SystemPermission::ManageStaff->value)->firstOrFail();
    $waiter->permissions()->updateExistingPivot($enabled->id, ['enabled' => true]);
    $waiter->permissions()->updateExistingPivot($disabled->id, ['enabled' => false]);
    $component = Livewire::actingAs($this->owner)->test(OrganizationStaffIndex::class, ['organization' => $this->organization])
        ->call('openInvitation')->set('invitationForm.email', 'defaults@example.test')->set('invitationForm.roleId', $waiter->id)
        ->call('previewInvitation');
    expect($component->get('preview.role_defaults'))->toContain(__(SystemPermission::ViewOrders->uiLabelKey()))
        ->not->toContain(__(SystemPermission::ManageStaff->uiLabelKey()));
    expect(Invitation::query()->count())->toBe(0);
});

test('existing branch assignment candidates exclude self higher equal suspended and global administrators', function () {
    $admin = User::factory()->create();
    $adminRole = Role::query()->where('code', SystemRole::RestaurantAdmin->value)->firstOrFail();
    OrganizationUser::factory()->forOrganization($this->organization)->forUser($admin)->forRole($adminRole)->active()->create();
    $equal = OrganizationUser::factory()->forOrganization($this->organization)->forRole($adminRole)->active()->create();
    $lower = OrganizationUser::factory()->forOrganization($this->organization)->forSystemRole(SystemRole::Waiter)->active()->create();
    $suspended = OrganizationUser::factory()->forOrganization($this->organization)->forSystemRole(SystemRole::Waiter)->suspended()->create();
    $globalAdmin = OrganizationUser::factory()->forOrganization($this->organization)->forSystemRole(SystemRole::Waiter)->active()->create();
    $globalAdmin->user->roles()->attach(Role::query()->where('code', SystemRole::Superadmin->value)->firstOrFail());
    $component = Livewire::actingAs($admin)->test(BranchStaffIndex::class, ['organization' => $this->organization, 'brand' => $this->brand, 'branch' => $this->branch])
        ->call('openExistingAssignment');
    expect(array_column($component->viewData('candidateRows'), 'id'))->toBe([$lower->id]);
});
