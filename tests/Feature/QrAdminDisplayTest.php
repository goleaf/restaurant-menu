<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\QrCodes\StoreQrCodeImageAction;
use App\Actions\ServicePoints\UpdateServicePointAction;
use App\Enums\AuditLogAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointType;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\QrPanel;
use App\Models\AreaNode;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\QrCode;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\QrCodeSvgRenderer;
use App\Support\LocalizedDateFormatter;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
    Storage::fake('public');
});

test('qr admin page requires generate qr permission and shows qr details', function () {
    [$organization, $brand, $branch, $servicePoint, $qrCode, $manager] = createPrompt25QrContext();
    $url = prompt25QrAdminUrl($organization, $brand, $branch, $servicePoint, $qrCode);

    $this->get($url)
        ->assertRedirect(route('login'));

    $this->actingAs($manager)
        ->get($url)
        ->assertForbidden();

    grantPrompt25Permission($manager, $organization, SystemPermission::GenerateQr);

    $publicUrl = route('public.qr.show', ['token' => $qrCode->public_token]);

    $this->actingAs($manager)->get($url)->assertRedirect(route('organizations.brands.branches.service-points.index', [
        $organization, $brand, $branch, 'panel' => 'qr', 'point' => $servicePoint->id, 'qr_record' => $qrCode->id,
    ]));
    Livewire::actingAs($manager)->test(QrPanel::class, ['branchId' => $branch->id, 'pointId' => $servicePoint->id])
        ->assertSeeText('QR code')->assertSeeText('Main Hall')->assertSeeText($servicePoint->name)->assertSeeText($qrCode->short_code)
        ->assertSeeText('Active')->assertSeeText(LocalizedDateFormatter::dateTime($qrCode->created_at))->assertSee($publicUrl)
        ->assertDontSee('data:image/svg+xml;base64', false)->assertSee(__('floor.qr.image_missing'));

    $publicPathSegments = explode('/', trim((string) parse_url($publicUrl, PHP_URL_PATH), '/'));

    expect($publicPathSegments)->toBe(['q', $qrCode->public_token]);
    expect($publicPathSegments[1])->not->toBe((string) $organization->id);
    expect($publicPathSegments[1])->not->toBe((string) $branch->id);
    expect($publicPathSegments[1])->not->toBe((string) $servicePoint->id);
    expect($publicPathSegments[1])->not->toBe((string) $servicePoint->display_number);
});

test('qr admin page shows current service point data after move and rename without changing qr identity', function () {
    [$organization, $brand, $branch, $servicePoint, $qrCode, $manager] = createPrompt25QrContext();
    grantPrompt25Permission($manager, $organization, SystemPermission::GenerateQr);
    grantPrompt25Permission($manager, $organization, SystemPermission::ManageServicePoints);
    $terrace = AreaNode::factory()->for($branch)->create(['name' => 'Terrace']);
    $oldToken = $qrCode->public_token;
    $oldShortCode = $qrCode->short_code;

    app(UpdateServicePointAction::class)->handle($servicePoint, [
        'area_node_id' => $terrace->id,
        'type' => ServicePointType::Table->value,
        'name' => 'Terrace Table 12',
        'display_number' => 'T-12',
        'capacity' => 4,
        'icon' => 'sparkles',
        'is_active' => true,
    ], $manager);

    Livewire::actingAs($manager)->test(QrPanel::class, ['branchId' => $branch->id, 'pointId' => $servicePoint->id])
        ->assertSeeText('Terrace Table 12')->assertSeeText('Terrace')->assertDontSeeText('Window Table')->assertDontSeeText('Main Hall')
        ->assertSee($oldToken)->assertSeeText($oldShortCode);

    $qrCode->refresh();

    expect($qrCode->public_token)->toBe($oldToken);
    expect($qrCode->short_code)->toBe($oldShortCode);
    expect(QrCode::query()->where('service_point_id', $servicePoint->id)->count())->toBe(1);
});

test('manager can download qr svg image from admin page', function () {
    [$organization, $brand, $branch, $servicePoint, $qrCode, $manager] = createPrompt25QrContext();
    grantPrompt25Permission($manager, $organization, SystemPermission::GenerateQr);
    $publicUrl = route('public.qr.show', ['token' => $qrCode->public_token]);
    $expectedSvg = app(QrCodeSvgRenderer::class)->render($publicUrl);

    Livewire::actingAs($manager)
        ->test(QrPanel::class, ['branchId' => $branch->id, 'pointId' => $servicePoint->id, 'qrId' => $qrCode->id])
        ->call('downloadQrImage')
        ->assertFileDownloaded(strtolower($qrCode->short_code).'.svg', $expectedSvg, 'image/svg+xml');
});

test('manager can disable qr and public route shows disabled message', function () {
    [$organization, $brand, $branch, $servicePoint, $qrCode, $manager] = createPrompt25QrContext();
    grantPrompt25Permission($manager, $organization, SystemPermission::GenerateQr);

    Livewire::actingAs($manager)
        ->test(QrPanel::class, ['branchId' => $branch->id, 'pointId' => $servicePoint->id, 'qrId' => $qrCode->id])
        ->set('form.reason', 'Printed sticker was placed at the wrong table.')
        ->call('prepareOperation', 'disable')->call('applyOperation')
        ->assertHasNoErrors()
        ->assertDispatched('floor-editor-saved')
        ->assertSee('Disabled');

    $qrCode->refresh();

    expect($qrCode->status)->toBe(QrCodeStatus::Disabled);
    expect($qrCode->active_service_point_id)->toBeNull();
    expect(AuditLog::query()
        ->where('action', AuditLogAction::QrDisabled->value)
        ->where('entity_type', 'qr_code')
        ->where('entity_id', $qrCode->id)
        ->exists())->toBeTrue();

    $this->get(route('public.qr.show', ['token' => $qrCode->public_token], false))
        ->assertOk()
        ->assertSeeText('QR code is temporarily disabled');
});

test('manager must explain qr disable and type short code before reissue', function () {
    [$organization, $brand, $branch, $servicePoint, $qrCode, $manager] = createPrompt25QrContext();
    grantPrompt25Permission($manager, $organization, SystemPermission::GenerateQr);

    $component = Livewire::actingAs($manager)
        ->test(QrPanel::class, ['branchId' => $branch->id, 'pointId' => $servicePoint->id, 'qrId' => $qrCode->id])
        ->call('prepareOperation', 'disable')->call('applyOperation')
        ->assertHasErrors(['form.reason']);

    expect($qrCode->fresh()->status)->toBe(QrCodeStatus::Active);

    Livewire::actingAs($manager)
        ->test(QrPanel::class, ['branchId' => $branch->id, 'pointId' => $servicePoint->id, 'qrId' => $qrCode->id])
        ->call('prepareOperation', 'reissue')
        ->assertSet('operation', 'reissue')
        ->set('form.confirmation', 'TEMPORARY')
        ->call('cancelOperation')
        ->assertSet('operation', '')
        ->assertSet('form.confirmation', '')
        ->call('prepareOperation', 'reissue')
        ->call('applyOperation')
        ->assertHasErrors(['form.confirmation'])
        ->set('form.confirmation', 'WRONG-CODE')
        ->call('applyOperation')
        ->assertHasErrors(['form.confirmation']);

    $component->assertSee(__('floor.qr.disable_warning'));

    expect($qrCode->fresh()->status)->toBe(QrCodeStatus::Active)
        ->and(QrCode::query()
            ->where('service_point_id', $servicePoint->id)
            ->where('status', QrCodeStatus::Active->value)
            ->count())->toBe(1);
});

test('manager can manually reissue qr after warning', function () {
    [$organization, $brand, $branch, $servicePoint, $qrCode, $manager] = createPrompt25QrContext();
    grantPrompt25Permission($manager, $organization, SystemPermission::GenerateQr);
    $oldToken = $qrCode->public_token;
    $oldShortCode = $qrCode->short_code;
    $storeQrCodeImage = app(StoreQrCodeImageAction::class);
    $oldImagePath = $storeQrCodeImage->handle($qrCode);

    Livewire::actingAs($manager)
        ->test(QrPanel::class, ['branchId' => $branch->id, 'pointId' => $servicePoint->id, 'qrId' => $qrCode->id])
        ->call('prepareOperation', 'reissue')
        ->assertSet('operation', 'reissue')
        ->assertSee(__('floor.qr.reissue_warning'))
        ->set('form.confirmation', $qrCode->short_code)
        ->call('applyOperation')
        ->assertSet('operation', '')->assertSet('pointId', $servicePoint->id);

    $qrCode->refresh();
    $newQrCode = QrCode::query()
        ->where('service_point_id', $servicePoint->id)
        ->where('status', QrCodeStatus::Active->value)
        ->firstOrFail();

    expect($qrCode->status)->toBe(QrCodeStatus::Revoked);
    expect($qrCode->revoked_at)->not->toBeNull();
    expect($qrCode->revoked_by_user_id)->toBe($manager->id);
    expect($newQrCode->id)->not->toBe($qrCode->id);
    expect($newQrCode->public_token)->not->toBe($oldToken);
    expect($newQrCode->short_code)->not->toBe($oldShortCode);
    expect(QrCode::query()
        ->where('service_point_id', $servicePoint->id)
        ->where('status', QrCodeStatus::Active->value)
        ->count())->toBe(1);
    Storage::disk('public')->assertMissing($oldImagePath);
    Storage::disk('public')->assertExists($storeQrCodeImage->pathFor($newQrCode));
    expect(Storage::disk('public')->allFiles('qr'))->toHaveCount(1);
});

test('ordinary service point editing does not reissue qr', function () {
    [$organization, , $branch, $servicePoint, $qrCode, $manager] = createPrompt25QrContext();
    grantPrompt25Permission($manager, $organization, SystemPermission::ManageServicePoints);
    $newArea = AreaNode::factory()->for($branch)->create(['name' => 'VIP Room']);
    $oldToken = $qrCode->public_token;
    $oldShortCode = $qrCode->short_code;

    app(UpdateServicePointAction::class)->handle($servicePoint, [
        'area_node_id' => $newArea->id,
        'type' => ServicePointType::Table->value,
        'name' => 'VIP Table 12',
        'display_number' => 'VIP-12',
        'capacity' => 6,
        'icon' => 'sparkles',
        'is_active' => true,
    ], $manager);

    $qrCode->refresh();

    expect($qrCode->status)->toBe(QrCodeStatus::Active);
    expect($qrCode->public_token)->toBe($oldToken);
    expect($qrCode->short_code)->toBe($oldShortCode);
    expect(QrCode::query()->where('service_point_id', $servicePoint->id)->count())->toBe(1);
});

function createPrompt25QrContext(): array
{
    $manager = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($manager, ['name' => 'QR Admin Group']);
    $restrictedRole = Role::query()
        ->where('code', SystemRole::Waiter->value)
        ->firstOrFail();
    $membership = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $manager->id)
        ->firstOrFail();
    $membership->forceFill(['role_id' => $restrictedRole->id])->saveOrFail();

    $brand = Brand::factory()->for($organization)->create(['name' => 'QR Admin Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'QR Admin Branch',
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
            'display_number' => 'WINDOW-TABLE-LABEL',
            'capacity' => 2,
        ]);
    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'public_token' => 'prompt25publictoken'.fake()->unique()->bothify('####'),
            'short_code' => 'QR-P25'.fake()->unique()->bothify('####'),
            'status' => QrCodeStatus::Active,
            'created_by_user_id' => $manager->id,
        ]);

    return [$organization, $brand, $branch, $servicePoint, $qrCode, $manager->fresh()];
}

function grantPrompt25Permission(User $user, Organization $organization, SystemPermission $permission): void
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

function prompt25QrAdminUrl(
    Organization $organization,
    Brand $brand,
    Branch $branch,
    ServicePoint $servicePoint,
    QrCode $qrCode,
): string {
    return route('organizations.brands.branches.service-points.qr.show', [
        $organization,
        $brand,
        $branch,
        $servicePoint,
        $qrCode,
    ]);
}
