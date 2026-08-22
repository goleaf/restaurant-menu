<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

test('organization routes reject a brand owned by another organization', function (): void {
    $user = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($user, ['name' => 'Scoped routes']);
    $foreignOrganization = app(CreateOrganizationAction::class)->handle($user, ['name' => 'Foreign routes']);
    $foreignBrand = Brand::factory()->for($foreignOrganization)->create();

    $this->actingAs($user->fresh())
        ->get(route('organizations.brands.branches.index', [$organization, $foreignBrand]))
        ->assertNotFound();
});

test('brand routes reject a branch owned by another brand', function (): void {
    [$user, $organization, $brand, , , $foreignBranch] = scopedRouteBindingContext();

    $this->actingAs($user)
        ->get(route('organizations.brands.branches.menu.index', [$organization, $brand, $foreignBranch]))
        ->assertNotFound();
});

test('branch routes reject a service point owned by another branch', function (): void {
    [$user, $organization, $brand, $branch, , $foreignBranch] = scopedRouteBindingContext();
    $foreignServicePoint = ServicePoint::factory()->for($foreignBranch)->create();
    $foreignQrCode = QrCode::factory()->for($foreignServicePoint)->create();

    $this->actingAs($user)
        ->get(route('organizations.brands.branches.service-points.qr.show', [
            $organization,
            $brand,
            $branch,
            $foreignServicePoint,
            $foreignQrCode,
        ]))
        ->assertNotFound();
});

test('service point routes reject a qr code owned by another service point', function (): void {
    [$user, $organization, $brand, $branch] = scopedRouteBindingContext();
    $servicePoint = ServicePoint::factory()->for($branch)->create();
    $foreignServicePoint = ServicePoint::factory()->for($branch)->create();
    $foreignQrCode = QrCode::factory()->for($foreignServicePoint)->create();

    $this->actingAs($user)
        ->get(route('organizations.brands.branches.service-points.qr.show', [
            $organization,
            $brand,
            $branch,
            $servicePoint,
            $foreignQrCode,
        ]))
        ->assertNotFound();
});

/**
 * @return array{User, Organization, Brand, Branch, Brand, Branch}
 */
function scopedRouteBindingContext(): array
{
    $user = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($user, ['name' => 'Scoped routes']);
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $foreignBrand = Brand::factory()->for($organization)->create();
    $foreignBranch = Branch::factory()->for($organization)->for($foreignBrand)->create();

    return [$user->fresh(), $organization, $brand, $branch, $foreignBrand, $foreignBranch];
}
