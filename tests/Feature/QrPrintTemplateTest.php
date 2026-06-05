<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\QrCodeStatus;
use App\Enums\QrLabelPreset;
use App\Enums\ServicePointType;
use App\Enums\SystemPermission;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\Qr\PrintTemplate;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\Qr\Show as QrAdminShow;
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

test('qr print page requires generate qr permission', function () {
    [$organization, $brand, $branch, $servicePoint, $qrCode, $manager] = createPrompt26QrContext();
    $url = prompt26QrPrintUrl($organization, $brand, $branch, $servicePoint, $qrCode);

    $this->get($url)
        ->assertRedirect(route('login'));

    $this->actingAs($manager)
        ->get($url)
        ->assertForbidden();

    grantPrompt26Permission($manager, $organization, SystemPermission::GenerateQr);

    $this->actingAs($manager)
        ->get($url)
        ->assertOk()
        ->assertSeeText(__('qr.print.sticker_title'));
});

test('qr print template defaults to sticker without table number or area', function () {
    [$organization, $brand, $branch, $servicePoint, $qrCode, $manager] = createPrompt26QrContext();
    grantPrompt26Permission($manager, $organization, SystemPermission::GenerateQr);

    $this->actingAs($manager)
        ->get(prompt26QrPrintUrl($organization, $brand, $branch, $servicePoint, $qrCode))
        ->assertOk()
        ->assertSee('data-page="qr-print-template"', false)
        ->assertSee('data-preset="minimal"', false)
        ->assertSeeText($brand->name)
        ->assertSeeText(__('qr.print.sticker_title'))
        ->assertSee('data:image/svg+xml;base64', false)
        ->assertSeeText($qrCode->short_code)
        ->assertDontSeeText(__('qr.labels.table').': 15')
        ->assertDontSeeText('Main Hall')
        ->assertDontSeeText(__('qr.print.table_number_warning'));
});

test('qr print template offers design presets without printing mutable table text by default', function () {
    [$organization, $brand, $branch, $servicePoint, $qrCode, $manager] = createPrompt26QrContext();
    grantPrompt26Permission($manager, $organization, SystemPermission::GenerateQr);

    $component = Livewire::actingAs($manager)
        ->test(PrintTemplate::class, [
            'organization' => $organization,
            'brand' => $brand,
            'branch' => $branch,
            'servicePoint' => $servicePoint,
            'qrCode' => $qrCode,
        ])
        ->assertSet('preset', QrLabelPreset::Minimal->value)
        ->assertSee('data-preset="minimal"', false)
        ->assertSeeText('Minimal')
        ->assertSeeText('Classic')
        ->assertSeeText('Restaurant')
        ->assertSeeText('Bar')
        ->assertSeeText('Hotel')
        ->assertSeeText('Premium')
        ->assertDontSee(__('qr.labels.table').': 15');

    foreach (QrLabelPreset::cases() as $preset) {
        $component
            ->set('preset', $preset->value)
            ->assertSet('preset', $preset->value)
            ->assertSee('qr-sticker-preset-'.$preset->value, false)
            ->assertSee('data-preset="'.$preset->value.'"', false)
            ->assertSee($qrCode->short_code)
            ->assertDontSee(__('qr.labels.table').': 15');
    }
});

test('qr print template can include table number with warning but still hides area', function () {
    [$organization, $brand, $branch, $servicePoint, $qrCode, $manager] = createPrompt26QrContext();
    grantPrompt26Permission($manager, $organization, SystemPermission::GenerateQr);

    $this->actingAs($manager)
        ->get(prompt26QrPrintUrl($organization, $brand, $branch, $servicePoint, $qrCode).'?print_table_number=1')
        ->assertOk()
        ->assertSeeText(__('qr.labels.table').': 15')
        ->assertSeeText(__('qr.print.table_number_warning'))
        ->assertDontSeeText('Main Hall');

    Livewire::actingAs($manager)
        ->test(PrintTemplate::class, [
            'organization' => $organization,
            'brand' => $brand,
            'branch' => $branch,
            'servicePoint' => $servicePoint,
            'qrCode' => $qrCode,
        ])
        ->assertSet('printTableNumber', false)
        ->set('printTableNumber', true)
        ->assertSee(__('qr.labels.table').': 15')
        ->assertSee(__('qr.print.table_number_warning'))
        ->assertDontSee('Main Hall');
});

test('qr admin page links to print template', function () {
    [$organization, $brand, $branch, $servicePoint, $qrCode, $manager] = createPrompt26QrContext();
    grantPrompt26Permission($manager, $organization, SystemPermission::GenerateQr);

    Livewire::actingAs($manager)
        ->test(QrAdminShow::class, [
            'organization' => $organization,
            'brand' => $brand,
            'branch' => $branch,
            'servicePoint' => $servicePoint,
            'qrCode' => $qrCode,
        ])
        ->assertSee(__('qr.actions.print'))
        ->assertSee(prompt26QrPrintUrl($organization, $brand, $branch, $servicePoint, $qrCode), false);
});

test('print table number setting does not change qr identity', function () {
    [$organization, $brand, $branch, $servicePoint, $qrCode, $manager] = createPrompt26QrContext();
    grantPrompt26Permission($manager, $organization, SystemPermission::GenerateQr);
    $oldToken = $qrCode->public_token;
    $oldShortCode = $qrCode->short_code;

    Livewire::actingAs($manager)
        ->test(PrintTemplate::class, [
            'organization' => $organization,
            'brand' => $brand,
            'branch' => $branch,
            'servicePoint' => $servicePoint,
            'qrCode' => $qrCode,
        ])
        ->set('printTableNumber', true)
        ->set('printTableNumber', false)
        ->set('preset', QrLabelPreset::Premium->value)
        ->set('preset', QrLabelPreset::Minimal->value);

    $qrCode->refresh();

    expect($qrCode->status)->toBe(QrCodeStatus::Active);
    expect($qrCode->public_token)->toBe($oldToken);
    expect($qrCode->short_code)->toBe($oldShortCode);
    expect(QrCode::query()->where('service_point_id', $servicePoint->id)->count())->toBe(1);
});

function createPrompt26QrContext(): array
{
    $manager = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($manager, ['name' => 'QR Print Group']);
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
