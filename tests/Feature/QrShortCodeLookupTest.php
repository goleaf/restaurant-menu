<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointType;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\QrCodes\ShortCodeLookup;
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

test('qr short code lookup requires authentication and generate qr permission', function () {
    [$organization, , , , , $manager] = createPrompt109QrLookupContext();

    $this->get(route('restaurant.qr-lookup.index'))
        ->assertRedirect(route('login'));

    $this->actingAs($manager)
        ->get(route('restaurant.qr-lookup.index'))
        ->assertForbidden();

    grantPrompt109Permission($manager, $organization, SystemPermission::GenerateQr);

    $this->actingAs($manager)
        ->get(route('restaurant.qr-lookup.index'))
        ->assertOk()
        ->assertSeeText('QR lookup')
        ->assertSeeText('Short code');
});

test('manager can find qr by printed short code without changing qr identity', function () {
    [$organization, $brand, $branch, $servicePoint, $qrCode, $manager] = createPrompt109QrLookupContext();
    grantPrompt109Permission($manager, $organization, SystemPermission::GenerateQr);
    $oldToken = $qrCode->public_token;
    $oldShortCode = $qrCode->short_code;
    $publicUrl = route('public.qr.show', ['token' => $qrCode->public_token]);

    Livewire::actingAs($manager)
        ->test(ShortCodeLookup::class)
        ->set('shortCode', '  '.strtolower($qrCode->short_code).'  ')
        ->call('search')
        ->assertHasNoErrors()
        ->assertSet('shortCode', $qrCode->short_code)
        ->assertSee($branch->name)
        ->assertSee('Lookup Hall')
        ->assertSee($servicePoint->name)
        ->assertSee('Active')
        ->assertSee($publicUrl)
        ->assertSee(route('organizations.brands.branches.service-points.qr.show', [
            $organization,
            $brand,
            $branch,
            $servicePoint,
            $qrCode,
        ]));

    $qrCode->refresh();

    expect($qrCode->public_token)->toBe($oldToken);
    expect($qrCode->short_code)->toBe($oldShortCode);
    expect(QrCode::query()->where('service_point_id', $servicePoint->id)->count())->toBe(1);
});

test('qr short code lookup is scoped to accessible branches', function () {
    [$organization, , $branch, , $qrCode, $manager] = createPrompt109QrLookupContext();
    grantPrompt109Permission($manager, $organization, SystemPermission::GenerateQr);
    [, , $otherBranch, , $otherQrCode] = createPrompt109QrLookupContext(prefix: 'OTHER');

    expect($otherBranch->id)->not->toBe($branch->id);

    Livewire::actingAs($manager)
        ->test(ShortCodeLookup::class)
        ->set('shortCode', $otherQrCode->short_code)
        ->call('search')
        ->assertHasNoErrors()
        ->assertSee('No QR code was found for accessible branches.')
        ->assertDontSee($otherQrCode->public_token)
        ->assertDontSee($otherBranch->name);

    $qrCode->refresh();
    $otherQrCode->refresh();

    expect($qrCode->status)->toBe(QrCodeStatus::Active);
    expect($otherQrCode->status)->toBe(QrCodeStatus::Active);
});

test('manager can disable qr from short code lookup', function () {
    [$organization, , , , $qrCode, $manager] = createPrompt109QrLookupContext();
    grantPrompt109Permission($manager, $organization, SystemPermission::GenerateQr);

    Livewire::actingAs($manager)
        ->test(ShortCodeLookup::class)
        ->set('shortCode', $qrCode->short_code)
        ->call('search')
        ->set('qrDisableReason', 'Printed sticker is damaged.')
        ->call('disableQr')
        ->assertHasNoErrors()
        ->assertSee('Disabled');

    $qrCode->refresh();

    expect($qrCode->status)->toBe(QrCodeStatus::Disabled);
    expect($qrCode->active_service_point_id)->toBeNull();
});

test('manager can reissue qr from short code lookup after warning', function () {
    [$organization, , , $servicePoint, $qrCode, $manager] = createPrompt109QrLookupContext();
    grantPrompt109Permission($manager, $organization, SystemPermission::GenerateQr);
    $oldToken = $qrCode->public_token;
    $oldShortCode = $qrCode->short_code;

    Livewire::actingAs($manager)
        ->test(ShortCodeLookup::class)
        ->set('shortCode', $qrCode->short_code)
        ->call('search')
        ->call('confirmReissue')
        ->assertSet('confirmingReissue', true)
        ->assertSee('The current public URL will stop working')
        ->set('qrReissueConfirmation', 'TEMPORARY')
        ->call('cancelReissue')
        ->assertSet('confirmingReissue', false)
        ->assertSet('qrReissueConfirmation', '')
        ->call('confirmReissue')
        ->set('qrReissueConfirmation', $qrCode->short_code)
        ->call('reissueQr')
        ->assertHasNoErrors()
        ->assertSet('confirmingReissue', false)
        ->assertNotSet('shortCode', $oldShortCode);

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

function createPrompt109QrLookupContext(string $prefix = 'P109'): array
{
    $manager = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($manager, ['name' => $prefix.' QR Lookup Group']);
    $brand = Brand::factory()
        ->for($organization)
        ->create(['name' => $prefix.' QR Lookup Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => $prefix.' QR Lookup Branch',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
        ]);
    $area = AreaNode::factory()
        ->for($branch)
        ->create(['name' => 'Lookup Hall']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($area)
        ->create([
            'type' => ServicePointType::Table,
            'name' => $prefix.' Lookup Table',
            'display_number' => $prefix.'-TABLE',
            'capacity' => 2,
        ]);
    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'public_token' => strtolower($prefix).'publictoken'.fake()->unique()->bothify('####'),
            'short_code' => 'QR-'.$prefix.fake()->unique()->bothify('####'),
            'status' => QrCodeStatus::Active,
            'created_by_user_id' => $manager->id,
        ]);

    $restrictedRole = Role::query()
        ->where('code', SystemRole::Cook->value)
        ->firstOrFail();

    OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $manager->id)
        ->firstOrFail()
        ->forceFill(['role_id' => $restrictedRole->id])
        ->save();

    return [$organization, $brand, $branch, $servicePoint, $qrCode, $manager->fresh()];
}

function grantPrompt109Permission(User $user, Organization $organization, SystemPermission $permission): void
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
