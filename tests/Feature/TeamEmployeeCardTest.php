<?php

use App\Enums\AuditLogAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Staff\Show;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->actor = User::factory()->create();
    $this->organization = Organization::factory()->create();
    OrganizationUser::factory()->forOrganization($this->organization)->for($this->actor)->forSystemRole(SystemRole::Owner)->active()->create();
    $this->brand = Brand::factory()->for($this->organization)->create();
    $this->branch = Branch::factory()->for($this->organization)->for($this->brand)->create();
    $this->member = OrganizationUser::factory()->forOrganization($this->organization)->forSystemRole(SystemRole::Waiter)->active()->create();
});

function teamCardParameters($test): array
{
    return ['organization' => $test->organization, 'brand' => $test->brand, 'branch' => $test->branch, 'member' => $test->member];
}

test('employee card resolves inherited access without materializing assignments', function (): void {
    Livewire::actingAs($this->actor)->test(Show::class, teamCardParameters($this))
        ->assertSee($this->member->user->name)->assertViewHas('card', fn (array $card): bool => $card['mode'] === 'inherited' && $card['branch_accessible'])
        ->assertSet('section', 'overview');
    expect(BranchUser::query()->where('user_id', $this->member->user_id)->exists())->toBeFalse();
});

test('employee card applies one scoped status operation and preserves another organization', function (): void {
    $assignment = BranchUser::factory()->forBranch($this->branch)->forUser($this->member->user)->active()->create();
    $other = OrganizationUser::factory()->for($this->member->user)->forSystemRole(SystemRole::Waiter)->active()->create();
    $component = Livewire::actingAs($this->actor)->test(Show::class, teamCardParameters($this))
        ->call('selectSection', 'access')->call('openMember', $assignment->id, 'status')
        ->set('memberForm.status', 'suspended')->set('memberForm.reason', 'Access is suspended for this restaurant.')
        ->call('previewMemberChange')->call('saveMember')->assertHasNoErrors();
    expect($assignment->fresh()->status)->toBe(OrganizationUserStatus::Suspended)
        ->and($this->member->fresh()->status)->toBe(OrganizationUserStatus::Active)
        ->and($other->fresh()->status)->toBe(OrganizationUserStatus::Active)
        ->and($this->member->user->fresh()->canAccessBranch($this->branch))->toBeFalse();
    $component->assertSee($this->member->user->name);
});

test('employee card rejects foreign membership and cannot edit another employee through public methods', function (): void {
    $foreign = OrganizationUser::factory()->forSystemRole(SystemRole::Waiter)->active()->create();
    Livewire::actingAs($this->actor)->test(Show::class, [...teamCardParameters($this), 'member' => $foreign])->assertNotFound();
    $assignment = BranchUser::factory()->forBranch($this->branch)->active()->create();
    Livewire::actingAs($this->actor)->test(Show::class, teamCardParameters($this))
        ->call('openMember', $assignment->id)->assertNotFound();
});

test('employee card explicitly previews returning the last assignment to organization rules', function (): void {
    $otherBranch = Branch::factory()->for($this->organization)->for($this->brand)->create();
    $assignment = BranchUser::factory()->forBranch($this->branch)->forUser($this->member->user)->active()->create();
    expect($this->member->user->fresh()->canAccessBranch($otherBranch))->toBeFalse();

    $card = Livewire::actingAs($this->actor)->test(Show::class, teamCardParameters($this))
        ->call('openAssignmentRemoval')->assertSet('editor', 'remove-assignment')
        ->set('removalForm.reason', 'Return this colleague to organization restaurant rules.')
        ->set('removalForm.confirmed', true)->call('previewAssignmentRemoval')
        ->assertSet('preview.operation', 'return_to_organization');
    expect($assignment->fresh())->not->toBeNull();
    $card->call('removeAssignment')->assertHasNoErrors()->assertSet('editor', '');
    expect($assignment->fresh())->toBeNull()
        ->and($this->member->user->fresh()->canAccessBranch($otherBranch))->toBeTrue();
});

test('assignment removal keeps localized invalid confirmation in the same card', function (string $locale): void {
    $this->actor->update(['locale' => $locale]);
    app()->setLocale($locale);
    $assignment = BranchUser::factory()->forBranch($this->branch)->forUser($this->member->user)->active()->create();
    $card = Livewire::actingAs($this->actor)->test(Show::class, teamCardParameters($this))
        ->call('openAssignmentRemoval')->set('removalForm.reason', 'x')->call('previewAssignmentRemoval')
        ->assertSet('section', 'access')->assertSet('editor', 'remove-assignment')
        ->assertHasErrors(['removalForm.reason' => 'min', 'removalForm.confirmed' => 'accepted']);
    expect($card->errors()->first('removalForm.reason'))->toBe(__('validation.min.string', ['attribute' => __('validation.attributes.reason'), 'min' => 3]))
        ->and($card->errors()->first('removalForm.confirmed'))->toBe(__('validation.accepted', ['attribute' => __('team.card.confirm_scope')]))
        ->and($assignment->fresh())->not->toBeNull();
})->with(['en', 'lt', 'ru']);

test('organization employee history does not relabel inaccessible archived branch events as organization events', function (): void {
    BranchUser::factory()->forBranch($this->branch)->forUser($this->actor)->active()->create();
    AuditLog::factory()->create(['organization_id' => $this->organization->id, 'branch_id' => $this->branch->id,
        'action' => AuditLogAction::StaffRoleChanged, 'entity_type' => 'organization_user', 'entity_id' => $this->member->id,
        'new_values' => ['reason' => 'Archived branch scope must remain private.']]);
    $this->branch->delete();
    Livewire::actingAs($this->actor)->withQueryParams(['section' => 'history'])
        ->test(Show::class, ['organization' => $this->organization, 'member' => $this->member])
        ->assertViewHas('historyRows', fn (array $rows): bool => $rows === []);
});
