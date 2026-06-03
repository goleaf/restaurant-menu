<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\QrCodes\GenerateQrCodeForServicePointAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\QrCodeStatus;
use App\Enums\SystemPermission;
use App\Livewire\Organizations\Brands\Branches\Index as BranchesIndex;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\Index as ServicePointsIndex;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('qr generation action creates active permanent qr identity', function () {
    [$organization, , $branch, $manager] = createPrompt23Branch();
    grantPrompt23Permission($manager, $organization, SystemPermission::GenerateQr);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Table impossible label',
            'display_number' => 'TABLE-IMPOSSIBLE-LABEL',
        ]);

    $qrCode = (new GenerateQrCodeForServicePointAction)->handle($servicePoint, $manager);

    expect($qrCode->service_point_id)->toBe($servicePoint->id);
    expect($qrCode->created_by_user_id)->toBe($manager->id);
    expect($qrCode->status)->toBe(QrCodeStatus::Active);
    expect($qrCode->public_token)->toHaveLength(64);
    expect($qrCode->short_code)->toMatch('/^QR-[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{8}$/');
    expect($qrCode->publicPath())->toBe('/q/'.$qrCode->public_token);
    expect($qrCode->publicPath())->not->toContain($servicePoint->name);
    expect($qrCode->publicPath())->not->toContain((string) $servicePoint->display_number);
});

test('qr generation action returns existing active qr instead of creating another', function () {
    [, , $branch, $manager] = createPrompt23Branch();
    $servicePoint = ServicePoint::factory()->for($branch)->create();
    $action = new GenerateQrCodeForServicePointAction;

    $firstQrCode = $action->handle($servicePoint, $manager);
    $secondQrCode = $action->handle($servicePoint, $manager);

    expect($secondQrCode->id)->toBe($firstQrCode->id);
    expect(QrCode::query()->where('service_point_id', $servicePoint->id)->count())->toBe(1);
    expect(QrCode::query()
        ->where('service_point_id', $servicePoint->id)
        ->where('status', QrCodeStatus::Active->value)
        ->count())->toBe(1);
});

test('generated qr identity remains stable when service point is renamed or moved', function () {
    [, , $branch, $manager] = createPrompt23Branch();
    $firstArea = AreaNode::factory()->for($branch)->create(['name' => 'Main hall']);
    $secondArea = AreaNode::factory()->for($branch)->create(['name' => 'Terrace']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($firstArea)
        ->create([
            'name' => 'Table 18',
            'display_number' => '18',
        ]);
    $qrCode = (new GenerateQrCodeForServicePointAction)->handle($servicePoint, $manager);

    $originalToken = $qrCode->public_token;
    $originalShortCode = $qrCode->short_code;

    $servicePoint->update([
        'area_node_id' => $secondArea->id,
        'name' => 'Terrace table 18',
        'display_number' => 'T-18',
    ]);

    $qrCode->refresh();

    expect($qrCode->service_point_id)->toBe($servicePoint->id);
    expect($qrCode->public_token)->toBe($originalToken);
    expect($qrCode->short_code)->toBe($originalShortCode);
});

test('generate qr permission can access service points and create qr from ui', function () {
    [$organization, $brand, $branch, $manager] = createPrompt23Branch();
    grantPrompt23Permission($manager, $organization, SystemPermission::GenerateQr);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create(['name' => 'QR table']);

    Livewire::actingAs($manager)
        ->test(BranchesIndex::class, ['organization' => $organization, 'brand' => $brand])
        ->assertSet('canGenerateQr', true)
        ->assertSee('Service points');

    $this->actingAs($manager)
        ->get(route('organizations.brands.branches.service-points.index', [$organization, $brand, $branch]))
        ->assertOk();

    $component = Livewire::actingAs($manager)
        ->test(ServicePointsIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->assertSet('canGenerateQr', true)
        ->assertSet('canManageServicePoints', false)
        ->assertSet('canChangeServicePointStatus', false)
        ->assertSee('QR table')
        ->assertSee('Create QR')
        ->assertDontSee('Update status')
        ->call('generateQr', $servicePoint->id)
        ->assertHasNoErrors()
        ->assertSee('QR active');

    $qrCode = $servicePoint->fresh()->activeQrCode;

    expect($qrCode)->not->toBeNull();

    $component
        ->assertSee($qrCode->short_code)
        ->assertSee($qrCode->publicPath());
});

test('service point manager without generate qr permission cannot generate qr', function () {
    [$organization, $brand, $branch, $manager] = createPrompt23Branch();
    grantPrompt23Permission($manager, $organization, SystemPermission::ManageServicePoints);
    $servicePoint = ServicePoint::factory()->for($branch)->create(['name' => 'Managed table']);

    Livewire::actingAs($manager)
        ->test(ServicePointsIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->assertSet('canGenerateQr', false)
        ->assertSet('canManageServicePoints', true)
        ->assertDontSee('Create QR')
        ->call('generateQr', $servicePoint->id)
        ->assertForbidden();

    expect($servicePoint->fresh()->activeQrCode)->toBeNull();
});

test('show qr reveals existing active qr without creating another record', function () {
    [$organization, $brand, $branch, $manager] = createPrompt23Branch();
    grantPrompt23Permission($manager, $organization, SystemPermission::GenerateQr);
    $servicePoint = ServicePoint::factory()->for($branch)->create(['name' => 'Existing QR table']);
    $existingQrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'short_code' => 'QR-READY1',
            'public_token' => 'existing-public-token-for-show-action',
        ]);

    Livewire::actingAs($manager)
        ->test(ServicePointsIndex::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->assertSee('Show QR')
        ->call('showQr', $servicePoint->id)
        ->assertSee($existingQrCode->short_code)
        ->assertSee($existingQrCode->publicPath())
        ->call('generateQr', $servicePoint->id)
        ->assertSee($existingQrCode->short_code);

    expect(QrCode::query()->where('service_point_id', $servicePoint->id)->count())->toBe(1);
});

function createPrompt23Branch(): array
{
    $manager = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($manager, ['name' => 'QR Generation Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'QR Generation Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create(['name' => 'QR Generation Branch']);

    return [$organization, $brand, $branch, $manager->fresh()];
}

function grantPrompt23Permission(User $user, Organization $organization, SystemPermission $permission): void
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
