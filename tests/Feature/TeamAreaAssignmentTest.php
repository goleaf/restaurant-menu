<?php

use App\Actions\Staff\SyncWaiterAreaAssignmentsAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Staff\Index;
use App\Models\AreaNode;
use App\Models\AreaNodeWaiter;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use App\Services\Staff\StaffQueryService;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
    $this->branch = Branch::factory()->create();
    $this->owner = User::factory()->create();
    OrganizationUser::factory()->forOrganization($this->branch->organization)->for($this->owner)->forSystemRole(SystemRole::Owner)->active()->create();
    $this->waiter = User::factory()->create();
    $organizationMember = OrganizationUser::factory()->forOrganization($this->branch->organization)->for($this->waiter)->forSystemRole(SystemRole::Waiter)->active()->create();
    $this->member = BranchUser::factory()->forBranch($this->branch)->forUser($this->waiter)->forRole($organizationMember->role)->active()->create();
    $this->area = AreaNode::factory()->forBranch($this->branch)->active()->create();
});

test('assignment fingerprints reject overlapping edits without replacing the first selection', function () {
    $original = app(StaffQueryService::class)->assignmentSnapshot($this->branch, $this->member);
    $action = app(SyncWaiterAreaAssignmentsAction::class);
    $action->handle($this->branch, $this->member, $this->owner, [$this->area->id], $original['fingerprint']);
    expect(fn () => $action->handle($this->branch, $this->member, $this->owner, [], $original['fingerprint']))->toThrow(ValidationException::class);
    expect(AreaNodeWaiter::query()->where('user_id', $this->waiter->id)->pluck('area_node_id')->all())->toBe([$this->area->id]);
});

test('malformed area selections cannot silently become unrestricted coverage', function (mixed $invalid) {
    $action = app(SyncWaiterAreaAssignmentsAction::class);
    $action->handle($this->branch, $this->member, $this->owner, [$this->area->id]);
    expect(fn () => $action->handle($this->branch, $this->member, $this->owner, [$invalid]))->toThrow(ValidationException::class);
    expect(AreaNodeWaiter::query()->where('user_id', $this->waiter->id)->count())->toBe(1);
})->with([true, '1junk', -1, ['nested']]);

test('a stale active membership cannot assign areas after suspension', function () {
    $this->member->fresh()->forceFill(['status' => OrganizationUserStatus::Suspended])->save();
    expect(fn () => app(SyncWaiterAreaAssignmentsAction::class)->handle($this->branch, $this->member, $this->owner, [$this->area->id]))->toThrow(AuthorizationException::class);
    expect(AreaNodeWaiter::query()->count())->toBe(0);
});

test('the focused assignment editor previews one batch and preserves draft on conflict', function () {
    $second = AreaNode::factory()->forBranch($this->branch)->active()->create();
    $component = Livewire\Livewire::actingAs($this->owner)->test(Index::class, ['organization' => $this->branch->organization, 'brand' => $this->branch->brand, 'branch' => $this->branch])
        ->call('openAssignments', $this->member->id)->set('assignmentForm.areaIds', [(string) $second->id])->call('previewAreaAssignments')
        ->assertViewHas('areasAdded', 1);
    expect(AreaNodeWaiter::query()->count())->toBe(0);
    app(SyncWaiterAreaAssignmentsAction::class)->handle($this->branch, $this->member, $this->owner, [$this->area->id]);
    $component->call('saveAreaAssignments')->assertHasErrors('assignmentForm.areaIds')->assertSet('assignmentForm.areaIds', [(string) $second->id]);
    expect(AreaNodeWaiter::query()->pluck('area_node_id')->all())->toBe([$this->area->id]);
    $component->call('refreshAssignments', true)->assertSet('assignmentForm.areaIds', [(string) $second->id])
        ->call('previewAreaAssignments')->assertViewHas('areasAdded', 1)->assertViewHas('areasRemoved', 1)
        ->call('saveAreaAssignments')->assertHasNoErrors();
    expect(AreaNodeWaiter::query()->pluck('area_node_id')->all())->toBe([$second->id]);
});

test('archiving an area after preview rejects the batch and preserves existing assignments', function () {
    $action = app(SyncWaiterAreaAssignmentsAction::class);
    $action->handle($this->branch, $this->member, $this->owner, [$this->area->id]);
    $snapshot = app(StaffQueryService::class)->assignmentSnapshot($this->branch, $this->member);
    $this->area->delete();
    expect(fn () => $action->handle($this->branch, $this->member, $this->owner, [$this->area->id], $snapshot['fingerprint']))->toThrow(ValidationException::class);
    expect(AreaNodeWaiter::query()->count())->toBe(1);
    $action->handle($this->branch, $this->member, $this->owner, [], $snapshot['fingerprint']);
    expect(AreaNodeWaiter::query()->count())->toBe(0);
});

test('area assignment cannot alter a global superadmin through a lower tenant role', function () {
    $role = Role::query()->where('code', SystemRole::Superadmin->value)->firstOrFail();
    $this->waiter->roles()->sync([$role->id]);
    expect(fn () => app(SyncWaiterAreaAssignmentsAction::class)->handle($this->branch, $this->member, $this->owner, [$this->area->id]))->toThrow(AuthorizationException::class);
    expect(AreaNodeWaiter::query()->count())->toBe(0);
});

test('ordinary assignment refresh keeps the unsaved selected areas', function () {
    Livewire\Livewire::actingAs($this->owner)->test(Index::class, ['organization' => $this->branch->organization, 'brand' => $this->branch->brand, 'branch' => $this->branch])
        ->call('openAssignments', $this->member->id)->set('assignmentForm.areaIds', [(string) $this->area->id])
        ->call('refreshAssignments')->assertSet('assignmentForm.areaIds', [(string) $this->area->id]);
    expect(AreaNodeWaiter::query()->count())->toBe(0);
});

test('coverage overview matches exact selected areas and unrestricted active waiters', function () {
    $child = AreaNode::factory()->forBranch($this->branch)->withParent($this->area)->create();
    $inactive = AreaNode::factory()->forBranch($this->branch)->inactive()->create();
    app(SyncWaiterAreaAssignmentsAction::class)->handle($this->branch, $this->member, $this->owner, [$this->area->id]);
    $unrestricted = User::factory()->create();
    $organizationMembership = OrganizationUser::factory()->forOrganization($this->branch->organization)->for($unrestricted)->forSystemRole(SystemRole::Waiter)->active()->create();
    BranchUser::factory()->forBranch($this->branch)->forUser($unrestricted)->forRole($organizationMembership->role)->active()->create();
    $overview = app(StaffQueryService::class)->coverageOverview($this->branch);
    $rows = collect($overview['rows'])->keyBy('id');
    expect($overview['unrestricted_count'])->toBe(1)
        ->and($rows[$this->area->id]['assigned_count'])->toBe(2)
        ->and($rows[$child->id]['assigned_count'])->toBe(1)
        ->and($rows[$this->area->id]['waiter_names'])->toContain($this->waiter->name)
        ->and($rows->has($inactive->id))->toBeFalse();
    $organizationMembership->forceFill(['status' => OrganizationUserStatus::Suspended])->save();
    $overview = app(StaffQueryService::class)->coverageOverview($this->branch);
    expect($overview['unrestricted_count'])->toBe(0);
});

test('area review shows named selected current added and removed assignments across search pages', function () {
    $added = AreaNode::factory()->forBranch($this->branch)->withParent($this->area)->create(['name' => 'Quiet terrace']);
    app(SyncWaiterAreaAssignmentsAction::class)->handle($this->branch, $this->member, $this->owner, [$this->area->id]);
    $component = Livewire\Livewire::actingAs($this->owner)->test(Index::class, ['organization' => $this->branch->organization, 'brand' => $this->branch->brand, 'branch' => $this->branch])
        ->call('openAssignments', $this->member->id)->set('assignmentForm.areaIds', [(string) $added->id])
        ->set('assignmentForm.search', 'No matching area')->call('previewAreaAssignments');
    $review = $component->viewData('areaEditor');
    expect($review['groups'])->toBe([])
        ->and(array_column($review['current'], 'id'))->toBe([$this->area->id])
        ->and(array_column($review['selected'], 'id'))->toBe([$added->id])
        ->and(array_column($review['added'], 'id'))->toBe([$added->id])
        ->and(array_column($review['removed'], 'id'))->toBe([$this->area->id])
        ->and($review['added'][0]['label'])->toContain($this->area->name, 'Quiet terrace');
});

test('area conflict comparison preserves the draft and displays the newly saved names', function () {
    $mine = AreaNode::factory()->forBranch($this->branch)->create(['name' => 'My proposed zone']);
    $theirs = AreaNode::factory()->forBranch($this->branch)->create(['name' => 'Recently assigned zone']);
    $component = Livewire\Livewire::actingAs($this->owner)->test(Index::class, ['organization' => $this->branch->organization, 'brand' => $this->branch->brand, 'branch' => $this->branch])
        ->call('openAssignments', $this->member->id)->set('assignmentForm.areaIds', [(string) $mine->id])->call('previewAreaAssignments');
    app(SyncWaiterAreaAssignmentsAction::class)->handle($this->branch, $this->member, $this->owner, [$theirs->id]);
    $component->call('saveAreaAssignments')->assertHasErrors('assignmentForm.areaIds')->call('refreshAssignments', true);
    $review = $component->viewData('areaEditor');
    expect(array_column($review['current'], 'id'))->toBe([$theirs->id])
        ->and(array_column($review['selected'], 'id'))->toBe([$mine->id]);
    $component->call('saveAreaAssignments')->assertHasErrors('preview');
    expect(AreaNodeWaiter::query()->pluck('area_node_id')->all())->toBe([$theirs->id]);
});

test('a forged foreign area is rejected as a complete batch without changing saved selection', function () {
    $foreign = AreaNode::factory()->create();
    $action = app(SyncWaiterAreaAssignmentsAction::class);
    $action->handle($this->branch, $this->member, $this->owner, [$this->area->id]);
    $snapshot = app(StaffQueryService::class)->assignmentSnapshot($this->branch, $this->member);
    expect(fn () => $action->handle($this->branch, $this->member, $this->owner, [$foreign->id], $snapshot['fingerprint']))->toThrow(ValidationException::class);
    expect(AreaNodeWaiter::query()->where('user_id', $this->waiter->id)->pluck('area_node_id')->all())->toBe([$this->area->id]);
    $review = app(StaffQueryService::class)->areaEditor($this->branch, [$foreign->id], '');
    expect($review['unavailable'][0]['label'])->not->toContain($foreign->name)
        ->and($review['unavailable'][0]['available'])->toBeFalse();
});

test('rejected assignment persistence rolls back removed areas and preserves the original fingerprint', function () {
    $replacement = AreaNode::factory()->forBranch($this->branch)->create();
    $action = app(SyncWaiterAreaAssignmentsAction::class);
    $action->handle($this->branch, $this->member, $this->owner, [$this->area->id]);
    $before = app(StaffQueryService::class)->assignmentSnapshot($this->branch, $this->member);
    AreaNodeWaiter::saving(fn (AreaNodeWaiter $assignment): bool => $assignment->area_node_id !== $replacement->id);
    expect(fn () => $action->handle($this->branch, $this->member, $this->owner, [$replacement->id], $before['fingerprint']))->toThrow(RuntimeException::class);
    expect(app(StaffQueryService::class)->assignmentSnapshot($this->branch, $this->member))->toBe($before);
});
