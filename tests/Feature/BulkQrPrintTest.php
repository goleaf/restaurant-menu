<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointType;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\Index as FloorIndex;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\PrintPanel;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\SelectionOperations;
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
    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs($manager)->get($url)->assertForbidden();
    grantPrompt27Permission($manager, $organization, SystemPermission::GenerateQr);
    $this->actingAs($manager)->get($url)->assertRedirect(route('organizations.brands.branches.service-points.index', [$organization, $brand, $branch, 'zone' => 'all']));
});

test('bulk qr print filters service points by area', function () {
    [$organization, $brand, $branch, $mainHall, $terrace, $points, $manager] = createPrompt27QrContext();
    grantPrompt27Permission($manager, $organization, SystemPermission::GenerateQr);
    Livewire::actingAs($manager)->test(FloorIndex::class, compact('organization', 'brand', 'branch'))
        ->assertSee($mainHall->name)->assertSee($terrace->name)->assertSee($points['mainWithQr']->name)->assertSee($points['mainWithoutQr']->name)
        ->set('filters.area', (string) $terrace->id)->assertSee($points['terraceWithQr']->name)->assertDontSee($points['mainWithQr']->name)
        ->set('filters.area', 'none')->assertSee($points['noZoneWithQr']->name)->assertDontSee($points['terraceWithQr']->name);
});

test('bulk qr print can select multiple existing eternal qr codes', function () {
    [$organization, $brand, $branch, , , $points, $manager] = createPrompt27QrContext();
    grantPrompt27Permission($manager, $organization, SystemPermission::GenerateQr);
    $list = Livewire::actingAs($manager)->test(FloorIndex::class, compact('organization', 'brand', 'branch'))
        ->set('filters.qr', 'with')->call('selectPage');
    $expected = [$points['mainWithQr']->id, $points['terraceWithQr']->id, $points['noZoneWithQr']->id];
    expect($list->get('selectedIds'))->toEqualCanonicalizing($expected);
    $list->call('selectPage');
    expect($list->get('selectedIds'))->toEqualCanonicalizing($expected);
    Livewire::actingAs($manager)->test(PrintPanel::class, ['branchId' => $branch->id, 'ids' => $list->get('selectedIds')])
        ->call('preparePrint')->assertHasNoErrors()->assertSee(['QR-P27MAIN', 'QR-P27TERR', 'QR-P27NOZN'])
        ->assertSee('data:image/svg+xml;base64', false)->assertDontSee('QR-P27MISS');
    $list->call('clearSelection')->assertSet('selectedIds', []);
});

test('bulk qr print applies label design presets to selected stickers', function () {
    [$organization, , $branch, , , $points, $manager] = createPrompt27QrContext();
    grantPrompt27Permission($manager, $organization, SystemPermission::GenerateQr);
    Livewire::actingAs($manager)->test(PrintPanel::class, ['branchId' => $branch->id, 'ids' => [$points['mainWithQr']->id]])
        ->assertSet('form.preset', 'minimal')->assertSee(['Minimal', 'Classic', 'Restaurant', 'Bar', 'Hotel', 'Premium'])
        ->call('preparePrint')->assertSee('data-qr-preset="minimal"', false)
        ->set('form.preset', 'bar')->call('preparePrint')->assertHasNoErrors()->assertSet('form.preset', 'bar')
        ->assertSee('qr-sticker-preset-bar', false)->assertSee('data-qr-preset="bar"', false)->assertDontSee('Стол: 1');
});

test('bulk qr print offers and creates missing qr without duplicating active qr codes', function () {
    [$organization, , $branch, , , $points, $manager] = createPrompt27QrContext();
    grantPrompt27Permission($manager, $organization, SystemPermission::GenerateQr);
    $missing = $points['mainWithoutQr'];
    $selected = [$missing->id, $points['mainWithQr']->id];
    $component = Livewire::actingAs($manager)->test(SelectionOperations::class, ['branchId' => $branch->id, 'ids' => $selected, 'operation' => 'generate'])
        ->assertSee($missing->name)->call('generateNext')->assertHasNoErrors()->assertSet('finished', true);
    expect($component->get('completedIds'))->toEqualCanonicalizing($selected);
    $component->call('generateNext')->assertHasNoErrors();
    expect(QrCode::query()->where('service_point_id', $missing->id)->where('status', QrCodeStatus::Active)->count())->toBe(1)
        ->and(QrCode::query()->where('service_point_id', $points['mainWithQr']->id)->count())->toBe(1);
});

test('floor selection opens canonical printing only with generate qr permission', function (): void {
    [$organization, $brand, $branch, , , $points, $manager] = createPrompt27QrContext();
    Livewire::actingAs($manager)->test(PrintPanel::class, ['branchId' => $branch->id, 'ids' => [$points['mainWithQr']->id]])->assertForbidden();
    grantPrompt27Permission($manager, $organization, SystemPermission::GenerateQr);
    Livewire::actingAs($manager->fresh())->test(FloorIndex::class, compact('organization', 'brand', 'branch'))
        ->call('selectPoint', $points['mainWithQr']->id)->assertSee(__('floor.print_selected'))
        ->call('openSelection', 'print')->assertSet('panel', 'print');
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
