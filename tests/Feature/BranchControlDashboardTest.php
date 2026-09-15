<?php

use App\Actions\Dashboard\BuildRestaurantDashboardAction;
use App\Actions\Dashboard\SaveDashboardOrderingAction;
use App\Actions\Kitchen\ResolveKitchenAccessibleDepartmentIdsAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\KitchenDepartmentType;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Restaurant\Dashboard;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\KitchenDepartment;
use App\Models\OrganizationUser;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
    $this->branch = Branch::factory()->create(['name' => 'Vilnius control', 'timezone' => 'Europe/Vilnius']);
    $this->owner = User::factory()->create();
    $this->membership = OrganizationUser::factory()->forOrganization($this->branch->organization)
        ->forUser($this->owner)->forSystemRole(SystemRole::Owner)->create();
});

test('branch control automatically selects the only branch and keeps context out of the snapshot', function () {
    Livewire::actingAs($this->owner)->test(Dashboard::class)
        ->assertSet('selectedBranchId', (string) $this->branch->id)
        ->assertSee('Vilnius control');
});

test('multiple branches default to an explicit overview without a first branch action', function () {
    Branch::factory()->for($this->branch->organization)->for($this->branch->brand)->create();
    $payload = app(BuildRestaurantDashboardAction::class)->handle($this->owner)['dashboard'];
    expect($payload['selected_branch'])->toBeNull();
    expect(collect($payload['main_links'])->firstWhere('key', 'Waiter screen')['href'])->toBeNull();
    foreach ($payload['main_links'] as $link) {
        if ($link['requires_branch']) {
            expect($link['href'])->toBeNull();
        }
    }
});

test('branch control rejects malformed and foreign branch selections before using them', function (mixed $selection) {
    expect(fn () => app(BuildRestaurantDashboardAction::class)->handle($this->owner, $selection))
        ->toThrow(ValidationException::class);
})->with([true, false, '1garbage', '1.0', '1e0', '-1', [['1']], '999999999999999999999999999']);

test('branch control removes revoked context on an ordinary refresh', function () {
    $component = Livewire::actingAs($this->owner)->test(Dashboard::class)->assertSee('Vilnius control');
    $this->membership->forceFill(['status' => OrganizationUserStatus::Suspended])->save();
    $component->call('$refresh')->assertDontSee('Vilnius control')->assertSet('canAccessRestaurantDashboard', false);
});

test('branch control URL dates are bounded and foreign identifiers cannot select a branch', function () {
    $foreign = Branch::factory()->create();
    Livewire::actingAs($this->owner)->withQueryParams(['branch' => (string) $foreign->id])->test(Dashboard::class)
        ->assertDontSee($foreign->name)->assertHasErrors('selectedBranchId');
    Livewire::actingAs($this->owner)->test(Dashboard::class)
        ->set('periodDraft', 'custom')->set('dateFromDraft', '2026-01-01')->set('dateToDraft', '2026-03-01')
        ->call('applyPeriod')->assertHasErrors();
});

test('ordering pause is explicit replayable and reauthorizes a stale screen', function () {
    $action = app(SaveDashboardOrderingAction::class);
    $action->handle($this->owner, $this->branch->id, true, 'Private event', null);
    $action->handle($this->owner, $this->branch->id, true, 'Private event', null);
    expect($this->branch->refresh()->is_temporarily_closed)->toBeTrue();
    $this->membership->forceFill(['status' => OrganizationUserStatus::Suspended])->save();
    expect(fn () => $action->handle($this->owner, $this->branch->id, false, null, null))
        ->toThrow(AuthorizationException::class);
    expect($this->branch->refresh()->is_temporarily_closed)->toBeTrue();
});

test('the report shortcut preserves the selected branch and exact custom range', function () {
    $dashboard = app(BuildRestaurantDashboardAction::class)->handle($this->owner, $this->branch->id, 'custom', '2026-06-01', '2026-06-07')['dashboard'];
    expect(collect($dashboard['main_links'])->firstWhere('key', 'reports.title')['href'])
        ->toBe(route('restaurant.dashboard', ['branch' => $this->branch->id, 'period' => 'custom:2026-06-01:2026-06-07']).'#reports');
});

test('branch shortcuts use the selected interface language', function (string $locale) {
    app()->setLocale($locale);
    $dashboard = app(BuildRestaurantDashboardAction::class)->handle($this->owner, $this->branch->id)['dashboard'];
    $labels = array_column($dashboard['main_links'], 'label', 'key');
    expect($labels['Menu'])->toBe(__('navigation.menu'))
        ->and($labels['Tables'])->toBe(__('reports.exports.tables'))
        ->and($labels['Waiter screen'])->toBe(__('navigation.waiter'))
        ->and($labels['Kitchen'])->toBe(__('navigation.kitchen'))
        ->and($labels['QR lookup'])->toBe(__('qr.lookup.title'));
})->with(['lt', 'ru']);

test('revoking another organization clears its branch option on plain refresh', function () {
    $this->seed(SystemPermissionsSeeder::class);
    $user = User::factory()->create();
    $a = Branch::factory()->create(['name' => 'Allowed A']);
    $b = Branch::factory()->create(['name' => 'Revoked B secret']);
    OrganizationUser::factory()->forOrganization($a->organization)->for($user)->forSystemRole(SystemRole::Owner)->active()->create();
    $membership = OrganizationUser::factory()->forOrganization($b->organization)->for($user)->forSystemRole(SystemRole::Owner)->active()->create();
    $component = Livewire::actingAs($user)->withQueryParams(['branch' => (string) $a->id])->test(App\Livewire\Waiter\Dashboard::class);
    $component->assertSee('Revoked B secret');
    $membership->forceFill(['status' => OrganizationUserStatus::Suspended])->save();
    $component->call('$refresh')->assertDontSee('Revoked B secret');
});

test('all branch overview rejects suspended last branch assignment', function () {
    $this->seed(SystemPermissionsSeeder::class);
    $user = User::factory()->create();
    $a = Branch::factory()->create(['name' => 'Blocked assigned A']);
    $b = Branch::factory()->for($a->organization)->for($a->brand)->create(['name' => 'Never assigned B']);
    OrganizationUser::factory()->forOrganization($a->organization)->for($user)->forSystemRole(SystemRole::Owner)->active()->create();
    BranchUser::factory()->forBranch($a)->forUser($user)->suspended()->create();
    expect($user->canAccessBranch($a))->toBeFalse()->and($user->canAccessBranch($b))->toBeFalse();
    $payload = app(BuildRestaurantDashboardAction::class)->handle($user);
    expect($payload['has_access'])->toBeFalse()->and($payload['dashboard'])->toBeNull();
});

test('restrict inactive assignments per organization without blocking other memberships', function (OrganizationUserStatus $status) {
    $this->seed(SystemPermissionsSeeder::class);
    $user = User::factory()->create();
    $a = Branch::factory()->create();
    $b = Branch::factory()->create();
    foreach ([$a, $b] as $branch) {
        OrganizationUser::factory()->forOrganization($branch->organization)->for($user)->forSystemRole(SystemRole::Owner)->active()->create();
    }
    BranchUser::factory()->forBranch($a)->forUser($user)->create(['status' => $status]);
    $resolver = app(ResolveWaiterAccessibleBranchIdsAction::class);
    expect($resolver->handle($user, SystemPermission::ViewReports)->all())->toBe([$b->id]);
    expect($resolver->handleMany($user, [SystemPermission::ViewReports])[SystemPermission::ViewReports->value]->all())->toBe([$b->id]);
    expect($resolver->authorizedBranchQuery($user)->pluck('id')->all())->toBe([$b->id]);
})->with([OrganizationUserStatus::Suspended, OrganizationUserStatus::Removed, OrganizationUserStatus::Invited]);
test('kitchen role path respects branch revocation and preserves unrelated organization', function () {
    $this->seed(SystemPermissionsSeeder::class);
    $user = User::factory()->create();
    $a = Branch::factory()->create();
    $b = Branch::factory()->create();
    foreach ([$a, $b] as $branch) {
        OrganizationUser::factory()->forOrganization($branch->organization)->for($user)->forSystemRole(SystemRole::Cook)->active()->create();
    }
    BranchUser::factory()->forBranch($a)->forUser($user)->suspended()->create();
    $ka = KitchenDepartment::factory()->for($a)->create(['type' => KitchenDepartmentType::Kitchen, 'is_active' => true]);
    $kb = KitchenDepartment::factory()->for($b)->create(['type' => KitchenDepartmentType::Kitchen, 'is_active' => true]);
    expect(app(ResolveKitchenAccessibleDepartmentIdsAction::class)->handle($user)->all())->toBe([$kb->id]);
});
