<?php

use App\Actions\Staff\UpdateOrganizationStaffRoleAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Staff\Index as BranchStaffIndex;
use App\Livewire\Organizations\Staff\Index as OrganizationStaffIndex;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\DB;
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
