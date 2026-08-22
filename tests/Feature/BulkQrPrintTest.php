<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\QrCodeStatus;
use App\Enums\QrLabelPreset;
use App\Enums\ServicePointType;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Index as BranchesIndex;
use App\Livewire\Organizations\Brands\Branches\Qr\BulkPrint;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\QrCode;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('bulk qr print page requires generate qr permission', function () {
    [$organization, $brand, $branch, , , , $manager] = createPrompt27QrContext();
    $url = prompt27BulkQrPrintUrl($organization, $brand, $branch);

    $this->get($url)
        ->assertRedirect(route('login'));

    $this->actingAs($manager)
        ->get($url)
        ->assertForbidden();

    grantPrompt27Permission($manager, $organization, SystemPermission::GenerateQr);

    $this->actingAs($manager)
        ->get($url)
        ->assertOk()
        ->assertSee('data-page="branch-bulk-qr-print"', false)
        ->assertSeeText('Bulk QR print')
        ->assertSeeText('Select all with QR');
});

test('bulk qr print filters service points by area', function () {
    [$organization, $brand, $branch, $mainHall, $terrace, $servicePoints, $manager] = createPrompt27QrContext();
    grantPrompt27Permission($manager, $organization, SystemPermission::GenerateQr);

    Livewire::actingAs($manager)
        ->test(BulkPrint::class, [
            'organization' => $organization,
            'brand' => $brand,
            'branch' => $branch,
        ])
        ->assertSee($mainHall->name)
        ->assertSee($terrace->name)
        ->assertSee($servicePoints['mainWithQr']->name)
        ->assertSee($servicePoints['mainWithoutQr']->name)
        ->set('areaNodeId', (string) $terrace->id)
        ->assertSee($servicePoints['terraceWithQr']->name)
        ->assertDontSee($servicePoints['mainWithQr']->name)
        ->set('areaNodeId', 'none')
        ->assertSee($servicePoints['noZoneWithQr']->name)
        ->assertDontSee($servicePoints['terraceWithQr']->name);
});

test('bulk qr print can select multiple existing eternal qr codes', function () {
    [$organization, $brand, $branch, , , $servicePoints, $manager] = createPrompt27QrContext();
    grantPrompt27Permission($manager, $organization, SystemPermission::GenerateQr);

    $component = Livewire::actingAs($manager)
        ->test(BulkPrint::class, [
            'organization' => $organization,
            'brand' => $brand,
            'branch' => $branch,
        ])
        ->call('selectAllVisible');

    expect($component->get('selectedServicePointIds'))->toEqualCanonicalizing([
        $servicePoints['mainWithQr']->id,
        $servicePoints['terraceWithQr']->id,
        $servicePoints['noZoneWithQr']->id,
    ]);

    $component
        ->assertSee('QR-P27MAIN')
        ->assertSee('QR-P27TERR')
        ->assertSee('QR-P27NOZN')
        ->assertSee('data:image/svg+xml;base64', false)
        ->assertDontSee('QR-P27MISS');
});

test('bulk qr print applies label design presets to selected stickers', function () {
    [$organization, $brand, $branch, , , , $manager] = createPrompt27QrContext();
    grantPrompt27Permission($manager, $organization, SystemPermission::GenerateQr);

    Livewire::actingAs($manager)
        ->test(BulkPrint::class, [
            'organization' => $organization,
            'brand' => $brand,
            'branch' => $branch,
        ])
        ->assertSet('preset', QrLabelPreset::Minimal->value)
        ->call('selectAllVisible')
        ->assertSee('data-preset="minimal"', false)
        ->assertSeeText('Minimal')
        ->assertSeeText('Classic')
        ->assertSeeText('Restaurant')
        ->assertSeeText('Bar')
        ->assertSeeText('Hotel')
        ->assertSeeText('Premium')
        ->set('preset', QrLabelPreset::Bar->value)
        ->assertSet('preset', QrLabelPreset::Bar->value)
        ->assertSee('qr-sticker-preset-bar', false)
        ->assertSee('data-preset="bar"', false)
        ->assertDontSee('Стол: 1');
});

test('bulk qr print offers and creates missing qr without duplicating active qr codes', function () {
    [$organization, $brand, $branch, , , $servicePoints, $manager] = createPrompt27QrContext();
    grantPrompt27Permission($manager, $organization, SystemPermission::GenerateQr);
    $missingServicePoint = $servicePoints['mainWithoutQr'];

    Livewire::actingAs($manager)
        ->test(BulkPrint::class, [
            'organization' => $organization,
            'brand' => $brand,
            'branch' => $branch,
        ])
        ->assertSee($missingServicePoint->name)
        ->assertSee(__('qr.actions.generate'))
        ->call('createQrForServicePoint', $missingServicePoint->id)
        ->assertSet('selectedServicePointIds', [$missingServicePoint->id]);

    expect(QrCode::query()
        ->where('service_point_id', $missingServicePoint->id)
        ->where('status', QrCodeStatus::Active->value)
        ->count())->toBe(1);

    Livewire::actingAs($manager)
        ->test(BulkPrint::class, [
            'organization' => $organization,
            'brand' => $brand,
            'branch' => $branch,
        ])
        ->call('createQrForServicePoint', $missingServicePoint->id);

    expect(QrCode::query()
        ->where('service_point_id', $missingServicePoint->id)
        ->where('status', QrCodeStatus::Active->value)
        ->count())->toBe(1);
});

test('branch list links users with generate qr permission to bulk print', function () {
    [$organization, $brand, $branch, , , , $manager] = createPrompt27QrContext();

    Livewire::actingAs($manager)
        ->test(BranchesIndex::class, [
            'organization' => $organization,
            'brand' => $brand,
        ])
        ->assertDontSee('Bulk QR print');

    grantPrompt27Permission($manager, $organization, SystemPermission::GenerateQr);

    Livewire::actingAs($manager->fresh())
        ->test(BranchesIndex::class, [
            'organization' => $organization,
            'brand' => $brand,
        ])
        ->assertSee('Bulk QR print')
        ->assertSee(prompt27BulkQrPrintUrl($organization, $brand, $branch), false);
});

function createPrompt27QrContext(): array
{
    $manager = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($manager, ['name' => 'QR Bulk Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Bella Bulk']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Bella Bulk Branch',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
        ]);

    $restrictedRole = Role::query()
        ->where('code', SystemRole::Waiter->value)
        ->firstOrFail();

    $manager->roles()->sync([$restrictedRole->id]);
    OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $manager->id)
        ->firstOrFail()
        ->forceFill(['role_id' => $restrictedRole->id])
        ->save();

    $mainHall = AreaNode::factory()
        ->for($branch)
        ->create(['name' => 'Main Hall', 'sort_order' => 1]);
    $terrace = AreaNode::factory()
        ->for($branch)
        ->create(['name' => 'Terrace', 'sort_order' => 2]);

    $mainWithQr = ServicePoint::factory()
        ->for($branch)
        ->for($mainHall)
        ->create([
            'type' => ServicePointType::Table,
            'name' => 'Main Table 1',
            'display_number' => '1',
        ]);
    $mainWithoutQr = ServicePoint::factory()
        ->for($branch)
        ->for($mainHall)
        ->create([
            'type' => ServicePointType::Table,
            'name' => 'Main Table 2',
            'display_number' => '2',
        ]);
    $terraceWithQr = ServicePoint::factory()
        ->for($branch)
        ->for($terrace)
        ->create([
            'type' => ServicePointType::Table,
            'name' => 'Terrace Table 3',
            'display_number' => '3',
        ]);
    $noZoneWithQr = ServicePoint::factory()
        ->for($branch)
        ->create([
            'type' => ServicePointType::BarSeat,
            'name' => 'Bar Seat 4',
            'display_number' => '4',
        ]);

    QrCode::factory()
        ->for($mainWithQr)
        ->create([
            'public_token' => 'prompt27maintoken'.fake()->unique()->bothify('####'),
            'short_code' => 'QR-P27MAIN',
            'status' => QrCodeStatus::Active,
            'created_by_user_id' => $manager->id,
        ]);
    QrCode::factory()
        ->for($terraceWithQr)
        ->create([
            'public_token' => 'prompt27terracetoken'.fake()->unique()->bothify('####'),
            'short_code' => 'QR-P27TERR',
            'status' => QrCodeStatus::Active,
            'created_by_user_id' => $manager->id,
        ]);
    QrCode::factory()
        ->for($noZoneWithQr)
        ->create([
            'public_token' => 'prompt27nozonetoken'.fake()->unique()->bothify('####'),
            'short_code' => 'QR-P27NOZN',
            'status' => QrCodeStatus::Active,
            'created_by_user_id' => $manager->id,
        ]);

    return [
        $organization,
        $brand,
        $branch,
        $mainHall,
        $terrace,
        [
            'mainWithQr' => $mainWithQr,
            'mainWithoutQr' => $mainWithoutQr,
            'terraceWithQr' => $terraceWithQr,
            'noZoneWithQr' => $noZoneWithQr,
        ],
        $manager->fresh(),
    ];
}

function grantPrompt27Permission(User $user, Organization $organization, SystemPermission $permission): void
{
    $membership = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $user->id)
        ->where('status', OrganizationUserStatus::Active->value)
        ->firstOrFail();
    $permissionModel = Permission::query()
        ->where('code', $permission->value)
        ->firstOrFail();

    $membership->role->permissions()->updateExistingPivot($permissionModel->id, ['enabled' => true]);
}

function prompt27BulkQrPrintUrl(Organization $organization, Brand $brand, Branch $branch): string
{
    return route('organizations.brands.branches.qr.print', [
        $organization,
        $brand,
        $branch,
    ]);
}
