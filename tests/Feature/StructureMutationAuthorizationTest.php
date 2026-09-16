<?php

declare(strict_types=1);

use App\Actions\Branches\CreateBranchAction;
use App\Actions\Branches\UpdateBranchAction;
use App\Actions\Brands\CreateBrandAction;
use App\Actions\Brands\UpdateBrandAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Organizations\UpdateOrganizationAction;
use App\Enums\OrganizationUserStatus;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

test('structure mutations independently authorize their explicit actor', function (string $operation): void {
    [$owner, $organization, $brand, $branch] = structureMutationAuthorizationContext();
    $outsider = User::factory()->create();

    expect(fn () => runStructureMutation($operation, $outsider, $organization, $brand, $branch))
        ->toThrow(AuthorizationException::class);

    expect($organization->fresh()->name)->toBe('Original organization')
        ->and($brand->fresh()->name)->toBe('Original brand')
        ->and($branch->fresh()->name)->toBe('Original branch');
})->with(['update organization', 'create brand', 'update brand', 'create branch', 'update branch']);

test('structure mutations preserve authorized operation without browser authentication', function (string $operation): void {
    [$owner, $organization, $brand, $branch] = structureMutationAuthorizationContext();

    expect(auth()->check())->toBeFalse();

    $result = runStructureMutation($operation, $owner, $organization, $brand, $branch);

    expect($result->name)->toBe('Changed name');
})->with(['update organization', 'create brand', 'update brand', 'create branch', 'update branch']);

test('structure mutations recheck suspended memberships', function (string $operation): void {
    [$owner, $organization, $brand, $branch] = structureMutationAuthorizationContext();
    $organization->memberships()->where('user_id', $owner->id)->firstOrFail()
        ->forceFill(['status' => OrganizationUserStatus::Suspended])->save();

    expect(fn () => runStructureMutation($operation, $owner, $organization, $brand, $branch))
        ->toThrow(AuthorizationException::class);
})->with(['update organization', 'create brand', 'update brand', 'create branch', 'update branch']);

test('structure update ignores unrelated dirty parent and media attributes', function (): void {
    [$owner, $organization, $brand, $branch] = structureMutationAuthorizationContext();
    $foreign = Brand::factory()->create();
    $organization->logo_path = 'media/foreign.jpg';
    $brand->organization_id = $foreign->organization_id;
    $branch->brand_id = $foreign->id;

    runStructureMutation('update organization', $owner, $organization, $brand, $branch);
    runStructureMutation('update brand', $owner, $organization, $brand, $branch);
    runStructureMutation('update branch', $owner, $organization, $brand, $branch);

    expect($organization->fresh()->logo_path)->toBeNull()
        ->and($brand->fresh()->organization_id)->toBe($organization->id)
        ->and($branch->fresh()->brand_id)->toBe($brand->id);
});

/** @return array{User, Organization, Brand, Branch} */
function structureMutationAuthorizationContext(): array
{
    $owner = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Original organization']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Original brand']);
    $branch = Branch::factory()->for($organization)->for($brand)->create(['name' => 'Original branch']);

    return [$owner, $organization, $brand, $branch];
}

function runStructureMutation(string $operation, User $actor, Organization $organization, Brand $brand, Branch $branch): Organization|Brand|Branch
{
    $data = ['name' => 'Changed name'];
    $branchData = [...$data, 'address' => '1 Main St', 'city' => 'Vilnius', 'country' => 'LT', 'timezone' => 'Europe/Vilnius', 'currency' => 'EUR', 'is_active' => true];

    return match ($operation) {
        'update organization' => app(UpdateOrganizationAction::class)->handle($organization, $data, $actor),
        'create brand' => app(CreateBrandAction::class)->handle($organization, $data, $actor),
        'update brand' => app(UpdateBrandAction::class)->handle($brand, $data, $actor),
        'create branch' => app(CreateBranchAction::class)->handle($brand, $branchData, $actor),
        'update branch' => app(UpdateBranchAction::class)->handle($branch, $branchData, $actor),
    };
}
