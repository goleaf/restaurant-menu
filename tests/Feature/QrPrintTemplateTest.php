<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\QrCodeStatus;
use App\Enums\QrLabelPreset;
use App\Enums\ServicePointType;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\PrintPanel;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\QrPanel;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\Qr\Show as QrAdminShow;
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

test('qr print page requires generate qr permission', function () {
    [$organization, $brand, $branch, $servicePoint, $qrCode, $manager] = createPrompt26QrContext();
    $url = prompt26QrPrintUrl($organization, $brand, $branch, $servicePoint, $qrCode);
    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs($manager)->get($url)->assertForbidden();
    grantPrompt26Permission($manager, $organization, SystemPermission::GenerateQr);
    $this->actingAs($manager)->get($url)->assertRedirect(route('organizations.brands.branches.service-points.index', [
        $organization, $brand, $branch, 'point' => $servicePoint->id, 'panel' => 'print', 'qr_record' => $qrCode->id,
    ]));
});

test('qr print template defaults to sticker without table number or area', function () {
    [$organization, , $branch, $servicePoint, $qrCode, $manager] = createPrompt26QrContext();
    grantPrompt26Permission($manager, $organization, SystemPermission::GenerateQr);
    Livewire::actingAs($manager)->test(PrintPanel::class, ['branchId' => $branch->id, 'ids' => [$servicePoint->id], 'expectedQrIds' => [$servicePoint->id => $qrCode->id]])
        ->assertSet('form.printTableNumber', false)->assertDontSee('data:image/svg+xml;base64', false)
        ->call('preparePrint')->assertHasNoErrors()->assertSee('data-qr-preset="minimal"', false)
        ->assertSee($branch->name)->assertSee(__('qr.print.sticker_title'))->assertSee('data:image/svg+xml;base64', false)
        ->assertSee($qrCode->short_code)->assertDontSee(__('qr.labels.table').': 15')->assertDontSee('Main Hall');
});

test('qr print template offers design presets without printing mutable table text by default', function () {
    [$organization, , $branch, $point, $qr, $manager] = createPrompt26QrContext();
    grantPrompt26Permission($manager, $organization, SystemPermission::GenerateQr);
    $component = Livewire::actingAs($manager)->test(PrintPanel::class, ['branchId' => $branch->id, 'ids' => [$point->id]])
        ->assertSet('form.preset', 'minimal')->assertSee(['Minimal', 'Classic', 'Restaurant', 'Bar', 'Hotel', 'Premium']);
    foreach (QrLabelPreset::cases() as $preset) {
        $component->set('form.preset', $preset->value)->call('preparePrint')->assertHasNoErrors()
            ->assertSee('qr-sticker-preset-'.$preset->value, false)->assertSee('data-qr-preset="'.$preset->value.'"', false)
            ->assertSee($qr->short_code)->assertDontSee(__('qr.labels.table').': 15');
    }
});

test('qr print template can include table number with warning but still hides area', function () {
    [$organization, , $branch, $point, , $manager] = createPrompt26QrContext();
    grantPrompt26Permission($manager, $organization, SystemPermission::GenerateQr);
    Livewire::actingAs($manager)->test(PrintPanel::class, ['branchId' => $branch->id, 'ids' => [$point->id]])
        ->assertSet('form.printTableNumber', false)->set('form.printTableNumber', true)->call('preparePrint')->assertHasNoErrors()
        ->assertSee(__('qr.labels.table').': 15')->assertSee(__('floor.print.mutable_number_warning'))->assertDontSee('Main Hall');
});

test('qr admin page links to print template', function () {
    [$organization, , $branch, $point, $qr, $manager] = createPrompt26QrContext();
    grantPrompt26Permission($manager, $organization, SystemPermission::GenerateQr);
    Livewire::actingAs($manager)->test(QrPanel::class, ['branchId' => $branch->id, 'pointId' => $point->id])
        ->assertSee(__('qr.actions.print'))->call('requestPrint')->assertHasNoErrors()
        ->assertDispatched('floor-print-point', pointId: $point->id, qrId: $qr->id);
});

test('print table number setting does not change qr identity', function () {
    [$organization, , $branch, $point, $qr, $manager] = createPrompt26QrContext();
    grantPrompt26Permission($manager, $organization, SystemPermission::GenerateQr);
    $token = $qr->public_token;
    $shortCode = $qr->short_code;
    Livewire::actingAs($manager)->test(PrintPanel::class, ['branchId' => $branch->id, 'ids' => [$point->id]])
        ->set('form.printTableNumber', true)->set('form.preset', 'premium')->call('preparePrint')->assertHasNoErrors()
        ->set('form.printTableNumber', false)->set('form.preset', 'minimal')->call('preparePrint')->assertHasNoErrors();
    expect($qr->fresh()->status)->toBe(QrCodeStatus::Active)->and($qr->fresh()->public_token)->toBe($token)
        ->and($qr->fresh()->short_code)->toBe($shortCode)->and(QrCode::query()->where('service_point_id', $point->id)->count())->toBe(1);
});

function createPrompt26QrContext(): array
{
    $manager = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($manager, ['name' => 'QR Print Group']);
    $restrictedRole = Role::query()
        ->where('code', SystemRole::Waiter->value)
        ->firstOrFail();
    $membership = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $manager->id)
        ->firstOrFail();
    $membership->forceFill(['role_id' => $restrictedRole->id])->saveOrFail();

    $brand = Brand::factory()->for($organization)->create(['name' => 'Bella Print']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Bella Print Branch',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
        ]);
    $area = AreaNode::factory()->for($branch)->create(['name' => 'Main Hall']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($area)
        ->create([
            'type' => ServicePointType::Table,
            'name' => 'Window Table',
            'display_number' => '15',
            'capacity' => 2,
        ]);
    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'public_token' => 'prompt26publictoken'.fake()->unique()->bothify('####'),
            'short_code' => 'QR-P26'.fake()->unique()->bothify('####'),
            'status' => QrCodeStatus::Active,
            'created_by_user_id' => $manager->id,
        ]);

    return [$organization, $brand, $branch, $servicePoint, $qrCode, $manager->fresh()];
}

function grantPrompt26Permission(User $user, Organization $organization, SystemPermission $permission): void
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

function prompt26QrPrintUrl(
    Organization $organization,
    Brand $brand,
    Branch $branch,
    ServicePoint $servicePoint,
    QrCode $qrCode,
): string {
    return route('organizations.brands.branches.service-points.qr.print', [
        $organization,
        $brand,
        $branch,
        $servicePoint,
        $qrCode,
    ]);
}
