<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\AreaNode;
use App\Models\AreaNodeWaiter;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\PermissionUserOverride;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Playwright\Client;
use Tests\Support\IsolatedBrowserIdentity;

test('one employee card preserves context through areas permissions role and scoped suspension with independent real sessions', function (): void {
    $this->withVite();
    IsolatedBrowserIdentity::configure();
    $this->seed(SystemPermissionsSeeder::class);
    $owner = User::factory()->create(['email' => 'employee.card.owner@example.test']);
    $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Bendra restorano komanda — Общая команда']);
    $branch = Branch::factory()->for($organization)->withDefaultSettings()->create(['name' => 'Šeimos restoranas — Семейный ресторан']);
    $otherBranch = Branch::factory()->for($organization)->withDefaultSettings()->create(['name' => 'Second inherited restaurant']);
    $area = AreaNode::factory()->forBranch($branch)->active()->create(['name' => 'Vidinė terasa — Внутренняя терраса']);
    $subject = User::factory()->create(['name' => 'Živilė Сотрудница', 'email' => 'employee.card.subject@example.test']);
    $member = OrganizationUser::factory()->forOrganization($organization)->forUser($subject)->forSystemRole(SystemRole::Waiter)->active()->create();
    $foreignMember = OrganizationUser::factory()->forUser($subject)->forSystemRole(SystemRole::Waiter)->active()->create();
    $foreignBranch = Branch::factory()->for($foreignMember->organization)->withDefaultSettings()->create(['name' => 'Independent organization restaurant']);
    $menuPermission = Permission::query()->where('code', SystemPermission::ManageMenu->value)->sole();
    $cookRole = Role::query()->where('code', SystemRole::Cook->value)->sole();
    $staffUrl = route('organizations.brands.branches.staff.index', [$organization, $branch->brand, $branch], false);
    $cardUrl = route('organizations.brands.branches.staff.show', [$organization, $branch->brand, $branch, $member], false);
    $waiterUrl = route('restaurant.waiter.dashboard', ['branch' => $branch->id], false);
    $foreignWaiterUrl = route('restaurant.waiter.dashboard', ['branch' => $foreignBranch->id], false);
    $menuUrl = route('organizations.brands.branches.menu.index', [$organization, $branch->brand, $branch], false);

    $employee = visit(route('login', absolute: false));
    $employee->fill('email', $subject->email)->fill('password', 'password')->click('@login-button')
        ->assertPathIs(route('dashboard', absolute: false));
    $employee->navigate($waiterUrl)->assertSee($branch->name);
    $administrator = visit(route('login', absolute: false));
    $administrator->fill('email', $owner->email)->fill('password', 'password')->click('@login-button')
        ->assertPathIs(route('dashboard', absolute: false));
    $administrator->navigate($staffUrl)->fill('input[name="filters.search"]', $subject->name)->wait(0.5);
    employeeCardClick($administrator, 'a[href*="/staff/members/'.$member->id.'"]');
    $administrator->assertPathIs($cardUrl)->assertSee($subject->name)->assertSee(__('team.card.inherited'))
        ->assertDontSee($foreignBranch->name);
    $administrator->assertScript('getComputedStyle(document.querySelector(".rm-team-card")).containerName === "team-card"');
    $accountCount = User::query()->count();
    $accountFingerprint = hash('sha256', json_encode($subject->fresh()->getAttributes(), JSON_THROW_ON_ERROR));

    employeeCardClick($administrator, '[data-team-section="access"]');
    employeeCardClick($administrator, 'button[wire\\:click="openExistingAssignment"]');
    employeeCardClick($administrator, 'form[wire\\:submit="previewExistingAssignment"] button[type="submit"]');
    $administrator->assertSee(__('team.card.first_assignment_warning'))->assertSee($otherBranch->name);
    expect(BranchUser::query()->where('user_id', $subject->id)->exists())->toBeFalse();
    employeeCardClick($administrator, 'button[wire\\:click="assignExistingMember"]');
    $assignment = BranchUser::query()->where('organization_id', $organization->id)->where('user_id', $subject->id)->sole();
    expect($assignment->branch_id)->toBe($branch->id);
    $employee->navigate(route('restaurant.waiter.dashboard', ['branch' => $otherBranch->id], false))->assertDontSee($otherBranch->name);
    $employee->navigate($waiterUrl)->assertSee($branch->name);

    employeeCardClick($administrator, '[data-team-section="areas"]');
    $administrator->assertQueryStringHas('section', 'areas')->check('input[type="checkbox"][value="'.$area->id.'"]');
    employeeCardClick($administrator, '[data-team-section="access"]');
    $administrator->assertSee(__('staff.workspace.unsaved_title'));
    employeeCardClick($administrator, 'button[\\@click="cancelNavigation"]');
    $administrator->assertQueryStringHas('section', 'areas')->assertChecked('input[type="checkbox"][value="'.$area->id.'"]');
    employeeCardClick($administrator, 'form[wire\\:submit="previewAreaAssignments"] button[type="submit"]');
    expect(AreaNodeWaiter::query()->where('user_id', $subject->id)->exists())->toBeFalse();
    employeeCardClick($administrator, 'button[wire\\:click="saveAreaAssignments"]');
    expect(AreaNodeWaiter::query()->where('user_id', $subject->id)->pluck('area_node_id')->all())->toBe([$area->id]);

    employeeCardClick($administrator, '[data-team-section="access"]');
    employeeCardClick($administrator, 'button[wire\\:click="openPermissions"]');
    $administrator->select('select[wire\\:model="permissionForm.states.'.$menuPermission->id.'"]', 'allow');
    employeeCardClick($administrator, 'button[wire\\:click="previewPermissions"]');
    expect(PermissionUserOverride::query()->where('user_id', $subject->id)->exists())->toBeFalse();
    employeeCardClick($administrator, 'button[wire\\:click="applyPermissions"]');
    expect(PermissionUserOverride::query()->where('user_id', $subject->id)->sole()->scope_key)->toBe('organization:'.$organization->id);
    $employee->navigate($menuUrl)->assertPathIs($menuUrl)->assertSee($branch->name);

    employeeCardClick($administrator, 'button[wire\\:click="openMember('.$assignment->id.', \'role\')"]');
    $administrator->assertSee(__('team.card.role_areas_warning'))
        ->select('select[name="memberForm.roleId"]', (string) $cookRole->id)
        ->fill('input[name="memberForm.reason"]', 'Switch this restaurant assignment to preparation');
    employeeCardClick($administrator, 'form[wire\\:submit="previewMemberChange"] button[type="submit"]');
    $administrator->assertSee(__('team.card.projected_permissions'))->assertSee(__('team.card.permissions_unchanged'))
        ->assertSee(__('team.card.role_areas_warning'));
    expect(AreaNodeWaiter::query()->where('user_id', $subject->id)->exists())->toBeTrue();
    employeeCardClick($administrator, 'button[wire\\:click="saveMember"]');
    expect($assignment->fresh()->role_id)->toBe($cookRole->id)
        ->and($member->fresh()->role_id)->toBe($member->role_id)
        ->and(AreaNodeWaiter::query()->where('user_id', $subject->id)->exists())->toBeFalse();

    employeeCardClick($administrator, 'button[wire\\:click="openMember('.$assignment->id.', \'status\')"]');
    $administrator->select('select[name="memberForm.status"]', 'suspended')->fill('input[name="memberForm.reason"]', 'Restaurant access review');
    employeeCardClick($administrator, 'form[wire\\:submit="previewMemberChange"] button[type="submit"]');
    employeeCardClick($administrator, 'button[wire\\:click="saveMember"]');
    expect($assignment->fresh()->status)->toBe(OrganizationUserStatus::Suspended)
        ->and($member->fresh()->status)->toBe(OrganizationUserStatus::Active)
        ->and($foreignMember->fresh()->status)->toBe(OrganizationUserStatus::Active);
    $employee->navigate($waiterUrl)->assertDontSee($branch->name);
    $employee->navigate($foreignWaiterUrl)->assertSee($foreignBranch->name);

    employeeCardClick($administrator, 'button[wire\\:click="openMember('.$assignment->id.', \'status\')"]');
    $administrator->select('select[name="memberForm.status"]', 'active')->fill('input[name="memberForm.reason"]', 'Restaurant access review complete');
    employeeCardClick($administrator, 'form[wire\\:submit="previewMemberChange"] button[type="submit"]');
    employeeCardClick($administrator, 'button[wire\\:click="saveMember"]');
    $employee->navigate($waiterUrl)->assertSee($branch->name);
    expect($assignment->fresh()->status)->toBe(OrganizationUserStatus::Active);

    employeeCardClick($administrator, 'button[wire\\:click="openAssignmentRemoval"]');
    $administrator->fill('input[wire\\:model="removalForm.reason"]', 'Restore inherited restaurant scope');
    $administrator->check('[wire\\:model="removalForm.confirmed"]');
    employeeCardClick($administrator, 'form[wire\\:submit="previewAssignmentRemoval"] button[type="submit"]');
    $administrator->assertSee(__('team.card.return_rule'))->assertSee(__('team.card.return_rule_help'))->assertSee($otherBranch->name);
    expect($assignment->fresh())->not->toBeNull();
    employeeCardClick($administrator, 'button[wire\\:click="removeAssignment"]');
    expect(BranchUser::query()->where('organization_id', $organization->id)->where('user_id', $subject->id)->exists())->toBeFalse()
        ->and($member->fresh()->status)->toBe(OrganizationUserStatus::Active)
        ->and($foreignMember->fresh()->status)->toBe(OrganizationUserStatus::Active)
        ->and(User::query()->count())->toBe($accountCount)
        ->and(hash('sha256', json_encode($subject->fresh()->getAttributes(), JSON_THROW_ON_ERROR)))->toBe($accountFingerprint);
    $employee->navigate(route('restaurant.waiter.dashboard', ['branch' => $otherBranch->id], false))->assertSee($otherBranch->name);
    $employee->navigate($foreignWaiterUrl)->assertSee($foreignBranch->name);

    employeeCardClick($administrator, '[data-team-section="history"]');
    $administrator->assertQueryStringHas('section', 'history')->assertSee('Restaurant access review complete')
        ->assertSee('Restore inherited restaurant scope');
    $administrator->script('history.back()');
    $administrator->wait(0.3)->assertQueryStringHas('section', 'access');
    $administrator->script('history.forward()');
    $administrator->wait(0.3)->assertQueryStringHas('section', 'history');
    $administrator->navigate($cardUrl.'?section=history')->assertSee('Restaurant access review complete');
    $administrator->assertScript('getComputedStyle(document.querySelector(".rm-team-card")).containerName === "team-card"');
    employeeCardClick($administrator, '[data-team-section="overview"]');
    foreach ([[320, 800], [390, 844], [768, 900], [1024, 900], [1440, 1000]] as [$width, $height]) {
        $administrator->resize($width, $height);
        expect($administrator->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
        $administrator->screenshot(false, 'employee-card-'.$width);
    }
    $administrator->script("document.documentElement.style.zoom = '2'");
    expect($administrator->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
    $administrator->script("document.documentElement.style.zoom = ''; document.documentElement.classList.add('dark')");
    $administrator->resize(390, 844)->screenshot(false, 'employee-card-dark');
    $administrator->navigate($cardUrl.'?search='.rawurlencode($subject->name));
    employeeCardClick($administrator, '.rm-team-card__identity a');
    $administrator->assertPathIs($staffUrl)->assertValue('input[name="filters.search"]', $subject->name);
    $administrator->assertNoJavaScriptErrors();
    $employee->assertNoJavaScriptErrors();
});

test('a permissions-only operator reaches the employee card and legacy link without staff editing controls', function (): void {
    $this->withVite();
    IsolatedBrowserIdentity::configure();
    $this->seed(SystemPermissionsSeeder::class);
    $operator = OrganizationUser::factory()->forSystemRole(SystemRole::Director)->active()->create();
    $organization = $operator->organization;
    $branch = Branch::factory()->for($organization)->withDefaultSettings()->create();
    $subject = OrganizationUser::factory()->forOrganization($organization)->forSystemRole(SystemRole::Waiter)->active()->create();
    $menuPermission = Permission::query()->where('code', SystemPermission::ManageMenu->value)->sole();
    PermissionUserOverride::factory()->forOrganization($organization)->forUser($operator->user)
        ->forPermission(Permission::query()->where('code', SystemPermission::ManageStaff->value)->sole())->denied()->create();
    $page = visit(route('login', absolute: false));
    $page->fill('email', $operator->user->email)->fill('password', 'password')->click('@login-button')
        ->assertPathIs(route('restaurant.dashboard', absolute: false));
    $page->navigate(route('organizations.brands.branches.staff.index', [$organization, $branch->brand, $branch], false));
    employeeCardClick($page, 'a[href*="/staff/members/'.$subject->id.'"]');
    employeeCardClick($page, '[data-team-section="access"]');
    $page->assertVisible('button[wire\\:click="openPermissions"]')->assertMissing('button[wire\\:click^="openMember"]');
    employeeCardClick($page, 'button[wire\\:click="openPermissions"]');
    $page->assertVisible('select[wire\\:model="permissionForm.states.'.$menuPermission->id.'"]');
    $page->select('select[wire\\:model="permissionForm.states.'.$menuPermission->id.'"]', 'allow');
    employeeCardOffline($page, true);
    employeeCardClick($page, '[data-staff-editor] button[x-on\\:click*="dismissEditor"]');
    employeeCardClick($page, 'button[\\@click="cancelNavigation"]');
    $page->assertValue('select[wire\\:model="permissionForm.states.'.$menuPermission->id.'"]', 'allow');
    employeeCardClick($page, '[data-staff-editor] button[x-on\\:click*="dismissEditor"]');
    employeeCardClick($page, 'button[\\@click="discardAndNavigate"]');
    $page->assertMissing('[data-staff-editor]');
    employeeCardOffline($page, false);
    employeeCardClick($page, '[data-team-section="overview"]');
    $page->assertAttribute('[data-team-section="overview"]', 'aria-current', 'page');
    expect(PermissionUserOverride::query()->where('user_id', $subject->user_id)->exists())->toBeFalse();
    $page->navigate(route('organizations.staff.permissions', [$organization, $subject->user], false))
        ->assertAttribute('[data-team-section="access"]', 'aria-current', 'page')->assertSee($subject->user->name)
        ->assertNoJavaScriptErrors();
});

function employeeCardClick(PendingAwaitablePage $page, string $selector): void
{
    $page->assertVisible($selector)->assertEnabled($selector)->click($selector)->wait(0.3);
}

function employeeCardOffline(PendingAwaitablePage $page, bool $offline): void
{
    $context = $page->page()->context();
    $guid = (new ReflectionProperty($context, 'guid'))->getValue($context);
    foreach (Client::instance()->execute($guid, 'setOffline', ['offline' => $offline]) as $message) {
    }
}
