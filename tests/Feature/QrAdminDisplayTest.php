<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\ServicePoints\UpdateServicePointAction;
use App\Enums\AuditLogAction;
use App\Enums\DangerousAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointType;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\Qr\Show as QrAdminShow;
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
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
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

    $this->actingAs($manager)
        ->get($url)
        ->assertOk()
        ->assertSeeText('QR code')
        ->assertSeeText($branch->name)
        ->assertSeeText('Main Hall')
        ->assertSeeText($servicePoint->name)
        ->assertSeeText($qrCode->short_code)
        ->assertSeeText('Active')
        ->assertSeeText(DangerousAction::DisableQr->title())
        ->assertSeeText(DangerousAction::ReissueQr->title())
        ->assertSeeText(DangerousAction::ReissueQr->consequence())
        ->assertSee($publicUrl)
        ->assertSee('data:image/svg+xml;base64', false)
        ->assertSeeText($qrCode->created_at->format('Y-m-d H:i'));

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
    ]);

    $this->actingAs($manager)
        ->get(prompt25QrAdminUrl($organization, $brand, $branch, $servicePoint, $qrCode))
        ->assertOk()
        ->assertSeeText('Terrace Table 12')
        ->assertSeeText('Terrace')
        ->assertDontSeeText('Window Table')
        ->assertDontSeeText('Main Hall')
        ->assertSee($oldToken)
        ->assertSeeText($oldShortCode);

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
        ->test(QrAdminShow::class, [
            'organization' => $organization,
            'brand' => $brand,
            'branch' => $branch,
            'servicePoint' => $servicePoint,
            'qrCode' => $qrCode,
        ])
        ->call('downloadQrImage')
        ->assertFileDownloaded(strtolower($qrCode->short_code).'.svg', $expectedSvg, 'image/svg+xml');
});

test('manager can disable qr and public route shows disabled message', function () {
    [$organization, $brand, $branch, $servicePoint, $qrCode, $manager] = createPrompt25QrContext();
    grantPrompt25Permission($manager, $organization, SystemPermission::GenerateQr);

    Livewire::actingAs($manager)
        ->test(QrAdminShow::class, [
            'organization' => $organization,
            'brand' => $brand,
            'branch' => $branch,
            'servicePoint' => $servicePoint,
            'qrCode' => $qrCode,
        ])
        ->set('qrDisableReason', 'Printed sticker was placed at the wrong table.')
        ->call('disableQr')
        ->assertHasNoErrors()
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
        ->test(QrAdminShow::class, [
            'organization' => $organization,
            'brand' => $brand,
            'branch' => $branch,
            'servicePoint' => $servicePoint,
            'qrCode' => $qrCode,
        ])
        ->call('disableQr')
        ->assertHasErrors(['qrDisableReason']);

    expect($qrCode->fresh()->status)->toBe(QrCodeStatus::Active);

    Livewire::actingAs($manager)
        ->test(QrAdminShow::class, [
            'organization' => $organization,
            'brand' => $brand,
            'branch' => $branch,
            'servicePoint' => $servicePoint,
            'qrCode' => $qrCode,
        ])
        ->call('confirmReissue')
        ->assertSet('confirmingReissue', true)
        ->set('qrReissueConfirmation', 'TEMPORARY')
        ->call('cancelReissue')
        ->assertSet('confirmingReissue', false)
        ->assertSet('qrReissueConfirmation', '')
        ->call('confirmReissue')
        ->call('reissueQr')
        ->assertHasErrors(['qrReissueConfirmation'])
        ->set('qrReissueConfirmation', 'WRONG-CODE')
        ->call('reissueQr')
        ->assertHasErrors(['qrReissueConfirmation']);

    expect($component->instance()->dangerousAction(DangerousAction::DisableQr->value))
        ->toBe(DangerousAction::DisableQr);

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

    Livewire::actingAs($manager)
        ->test(QrAdminShow::class, [
            'organization' => $organization,
            'brand' => $brand,
            'branch' => $branch,
            'servicePoint' => $servicePoint,
            'qrCode' => $qrCode,
        ])
        ->call('confirmReissue')
        ->assertSet('confirmingReissue', true)
        ->assertSee(DangerousAction::ReissueQr->consequence())
        ->set('qrReissueConfirmation', $qrCode->short_code)
        ->call('reissueQr')
        ->assertRedirect();

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
});

test('ordinary service point editing does not reissue qr', function () {
    [, , $branch, $servicePoint, $qrCode] = createPrompt25QrContext();
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
    ]);

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
