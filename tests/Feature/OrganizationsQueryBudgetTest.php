<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Staff\Index as BranchStaffIndex;
use App\Livewire\Organizations\Index as OrganizationsIndex;
use App\Livewire\Organizations\Staff\Index as OrganizationStaffIndex;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use App\Services\Branches\BranchQueryService;
use App\Services\Organizations\BrandQueryService;
use App\Services\Organizations\OrganizationQueryService;
use App\Services\Staff\StaffQueryService;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Pagination\Paginator;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

test('organization results are bounded searchable and render only the requested page', function (): void {
    $user = User::factory()->create();
    $role = Role::query()->where('code', SystemRole::Owner->value)->firstOrFail();

    foreach (range(1, 31) as $number) {
        $organization = Organization::factory()
            ->for($user, 'owner')
            ->create(['name' => sprintf('Paged Organization %02d', $number)]);

        OrganizationUser::factory()
            ->forOrganization($organization)
            ->forUser($user)
            ->forRole($role)
            ->active()
            ->create();
    }

    Livewire::actingAs($user)
        ->test(OrganizationsIndex::class)
        ->assertSee('Paged Organization 01')
        ->assertSee('Paged Organization 15')
        ->assertDontSee('Paged Organization 16')
        ->call('setPage', 2, 'organizationsPage')
        ->assertSee('Paged Organization 16')
        ->assertDontSee('Paged Organization 01')
        ->set('search', 'Organization 31')
        ->assertSet('paginators.organizationsPage', 1)
        ->assertSee('Paged Organization 31')
        ->assertDontSee('Paged Organization 30');
});

test('brand and branch searches stay inside the selected tenant boundary', function (): void {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Scoped Group']);
    $otherOrganization = (new CreateOrganizationAction)->handle($otherOwner, ['name' => 'Other Group']);

    foreach (range(1, 31) as $number) {
        Brand::factory()->for($organization)->create(['name' => sprintf('Scoped Brand %02d', $number)]);
    }

    Brand::factory()->for($otherOrganization)->create(['name' => 'Scoped Brand 31 Outside']);

    $brandPaginator = app(BrandQueryService::class)
        ->paginateForOrganization($organization, 'Brand 31', 15);

    expect($brandPaginator)->toBeInstanceOf(Paginator::class)
        ->and($brandPaginator->getPageName())->toBe('brandsPage')
        ->and($brandPaginator->items())->toHaveCount(1)
        ->and($brandPaginator->items()[0]->organization_id)->toBe($organization->id)
        ->and($brandPaginator->items()[0]->name)->toBe('Scoped Brand 31');

    $brand = Brand::factory()->for($organization)->create(['name' => 'Branch Scope']);
    $otherBrand = Brand::factory()->for($organization)->create(['name' => 'Other Branch Scope']);

    foreach (range(1, 31) as $number) {
        Branch::factory()
            ->for($organization)
            ->for($brand)
            ->create(['name' => sprintf('Scoped Branch %02d', $number)]);
    }

    Branch::factory()
        ->for($organization)
        ->for($otherBrand)
        ->create(['name' => 'Scoped Branch 31 Outside']);

    $branchPaginator = app(BranchQueryService::class)
        ->paginateAccessibleForBrand($owner, $organization, $brand, 'Branch 31', 15);

    expect($branchPaginator)->toBeInstanceOf(Paginator::class)
        ->and($branchPaginator->getPageName())->toBe('branchesPage')
        ->and($branchPaginator->items())->toHaveCount(1)
        ->and($branchPaginator->items()[0]->brand_id)->toBe($brand->id)
        ->and($branchPaginator->items()[0]->name)->toBe('Scoped Branch 31');
});

test('branch staff and invitations use their own independent paginator names', function (): void {
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Branch Staff Pagination Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Branch Staff Brand']);
    $branch = Branch::factory()->for($organization)->for($brand)->create(['name' => 'Branch Staff Branch']);
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();

    foreach (range(1, 31) as $number) {
        BranchUser::factory()
            ->forBranch($branch)
            ->forRole($role)
            ->active()
            ->create([
                'user_id' => User::factory()->create([
                    'name' => sprintf('Branch Paged Staff %02d', $number),
                    'email' => sprintf('branch-paged-staff-%02d@example.test', $number),
                ])->id,
                'assigned_by_user_id' => $owner->id,
            ]);

        Invitation::factory()
            ->forOrganization($organization)
            ->forRole($role)
            ->pending()
            ->create([
                'brand_id' => $brand->id,
                'branch_id' => $branch->id,
                'invited_by_user_id' => $owner->id,
                'email' => sprintf('branch-paged-invitation-%02d@example.test', $number),
            ]);
    }

    Livewire::actingAs($owner)
        ->test(BranchStaffIndex::class, [
            'organization' => $organization,
            'brand' => $brand,
            'branch' => $branch,
        ])
        ->call('setPage', 2, 'branchStaffPage')
        ->call('setPage', 2, 'branchInvitationsPage')
        ->assertSet('paginators.branchStaffPage', 2)
        ->assertSet('paginators.branchInvitationsPage', 2)
        ->set('staffSearch', 'Branch Paged Staff 31')
        ->assertSet('paginators.branchStaffPage', 1)
        ->assertSet('paginators.branchInvitationsPage', 2)
        ->set('invitationSearch', 'invitation-31')
        ->assertSet('paginators.branchInvitationsPage', 1)
        ->assertSee('branch-paged-invitation-31@example.test')
        ->assertDontSee('branch-paged-invitation-30@example.test');
});

test('organization staff and invitations use independent named paginators', function (): void {
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Staff Pagination Group']);
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();

    foreach (range(1, 31) as $number) {
        OrganizationUser::factory()
            ->forOrganization($organization)
            ->forRole($role)
            ->active()
            ->create([
                'user_id' => User::factory()->create([
                    'name' => sprintf('Paged Staff %02d', $number),
                    'email' => sprintf('paged-staff-%02d@example.test', $number),
                ])->id,
            ]);

        Invitation::factory()
            ->forOrganization($organization)
            ->forRole($role)
            ->pending()
            ->create([
                'brand_id' => null,
                'branch_id' => null,
                'invited_by_user_id' => $owner->id,
                'email' => sprintf('paged-invitation-%02d@example.test', $number),
            ]);
    }

    Livewire::actingAs($owner)
        ->test(OrganizationStaffIndex::class, ['organization' => $organization])
        ->call('setPage', 2, 'organizationStaffPage')
        ->call('setPage', 2, 'organizationInvitationsPage')
        ->assertSet('paginators.organizationStaffPage', 2)
        ->assertSet('paginators.organizationInvitationsPage', 2)
        ->set('staffSearch', 'Paged Staff 31')
        ->assertSet('paginators.organizationStaffPage', 1)
        ->assertSet('paginators.organizationInvitationsPage', 2)
        ->set('invitationSearch', 'invitation-31')
        ->assertSet('paginators.organizationInvitationsPage', 1)
        ->assertSee('paged-invitation-31@example.test')
        ->assertDontSee('paged-invitation-30@example.test');
});

test('query counts remain bounded and rendered relationships are eager loaded', function (): void {
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Budget Group']);
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();

    foreach (range(1, 31) as $number) {
        OrganizationUser::factory()
            ->forOrganization($organization)
            ->forRole($role)
            ->active()
            ->create([
                'user_id' => User::factory()->create([
                    'name' => sprintf('Budget Staff %02d', $number),
                    'email' => sprintf('budget-staff-%02d@example.test', $number),
                ])->id,
            ]);
    }

    $staffQueries = app(StaffQueryService::class);
    $queryCount = countDatabaseQueries(function () use ($staffQueries, $organization): void {
        $members = $staffQueries->paginateOrganizationMembers($organization, '', 15);

        expect($members->getPageName())->toBe('organizationStaffPage')
            ->and($members->items())->toHaveCount(15);

        foreach ($members as $member) {
            expect($member->relationLoaded('user'))->toBeTrue()
                ->and($member->relationLoaded('role'))->toBeTrue();
        }
    });

    expect($queryCount)->toBeLessThanOrEqual(3);

    $organizationQueries = app(OrganizationQueryService::class);
    $organizationQueryCount = countDatabaseQueries(
        function () use ($organizationQueries, $owner): void {
            expect($organizationQueries->paginateAccessibleTo($owner, '', 15)->getPageName())
                ->toBe('organizationsPage');
        },
    );

    expect($organizationQueryCount)->toBeLessThanOrEqual(2);
});
