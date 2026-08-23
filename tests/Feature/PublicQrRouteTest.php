<?php

use App\Enums\QrCodeStatus;
use App\Enums\ServicePointType;
use App\Livewire\PublicQr\GuestEntry;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
});

test('public qr route opens guest landing for active qr code', function () {
    [$organization, $brand, $branch] = createPublicQrBranch();
    $branchLogoPath = 'media/organizations/'.$organization->id.'/brands/'.$brand->id.'/branches/'.$branch->id.'/logos/logo.png';
    Storage::disk('public')->put($branchLogoPath, 'logo');
    $branch->update(['logo_path' => $branchLogoPath]);

    $area = AreaNode::factory()->for($branch)->create(['name' => 'Main Hall']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($area)
        ->create([
            'type' => ServicePointType::Table,
            'name' => 'Window Table',
            'display_number' => '42',
            'is_active' => true,
        ]);
    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'public_token' => 'publictokenactive',
            'short_code' => 'QR-ACTIVE',
            'status' => QrCodeStatus::Active,
        ]);
    $url = route('public.qr.show', ['token' => $qrCode->public_token], false);

    expect($url)->toBe('/q/publictokenactive');
    expect($url)->not->toContain((string) $organization->id);
    expect($url)->not->toContain((string) $branch->id);
    expect($url)->not->toContain((string) $servicePoint->id);
    expect($url)->not->toContain((string) $servicePoint->display_number);

    $this->get($url)
        ->assertOk()
        ->assertSee('data-page="guest-qr-landing"', false)
        ->assertSee('/storage/'.$branchLogoPath, false)
        ->assertSeeText($brand->name)
        ->assertSeeText($branch->name)
        ->assertSeeText('Window Table')
        ->assertSeeText('42')
        ->assertSeeText('Main Hall')
        ->assertSeeText('QR-ACTIVE')
        ->assertSeeText('Your name')
        ->assertSeeText('Join table')
        ->assertDontSeeText('Guest session and menu will appear here in the next steps.');
});

test('public qr route uses current service point data after move and rename', function () {
    [, , $branch] = createPublicQrBranch();
    $firstArea = AreaNode::factory()->for($branch)->create(['name' => 'Old Hall']);
    $secondArea = AreaNode::factory()->for($branch)->create(['name' => 'Terrace']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($firstArea)
        ->create([
            'name' => 'Old Table Name',
            'is_active' => true,
        ]);
    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'public_token' => 'publictokenmoved',
            'short_code' => 'QR-MOVED',
            'status' => QrCodeStatus::Active,
        ]);

    $servicePoint->update([
        'area_node_id' => $secondArea->id,
        'name' => 'Terrace Table',
    ]);

    $this->get(route('public.qr.show', ['token' => $qrCode->public_token], false))
        ->assertOk()
        ->assertSeeText('Terrace Table')
        ->assertSeeText('Terrace')
        ->assertDontSeeText('Old Table Name')
        ->assertDontSeeText('Old Hall');

    expect($qrCode->fresh()->public_token)->toBe('publictokenmoved');
});

test('guest can enter name on qr landing without registration', function () {
    [, , $branch] = createPublicQrBranch();
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Guest Table',
            'is_active' => true,
        ]);
    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'public_token' => 'publictokenguestname',
            'short_code' => 'QR-GUEST',
            'status' => QrCodeStatus::Active,
        ]);

    Livewire::test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->assertSet('state', 'ready')
        ->set('guestName', '  Ana   Maria  ')
        ->call('enterTable')
        ->assertHasNoErrors()
        ->assertSet('preparedGuestName', 'Ana Maria')
        ->assertSeeText('Welcome, Ana Maria.');

    expect(QrCode::query()->count())->toBe(1);
    expect(ServicePoint::query()->count())->toBe(1);
});

test('guest name is required before entering table', function () {
    [, , $branch] = createPublicQrBranch();
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create(['is_active' => true]);
    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'public_token' => 'publictokenguestvalidation',
            'short_code' => 'QR-GVALD',
            'status' => QrCodeStatus::Active,
        ]);

    Livewire::test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->set('guestName', '')
        ->call('enterTable')
        ->assertHasErrors(['guestName' => 'required']);
});

test('public qr route shows disabled qr error', function () {
    [, , $branch] = createPublicQrBranch();
    $servicePoint = ServicePoint::factory()->for($branch)->create(['is_active' => true]);
    $qrCode = QrCode::factory()
        ->disabled()
        ->for($servicePoint)
        ->create([
            'public_token' => 'publictokendisabled',
            'short_code' => 'QR-DISABL',
        ]);

    $this->get(route('public.qr.show', ['token' => $qrCode->public_token], false))
        ->assertOk()
        ->assertSeeText('QR code is temporarily disabled')
        ->assertSeeText('Please ask the staff to help you with this place.')
        ->assertDontSeeText('Guest session and menu will appear here in the next steps.');
});

test('public qr route shows revoked qr error', function () {
    [, , $branch] = createPublicQrBranch();
    $servicePoint = ServicePoint::factory()->for($branch)->create(['is_active' => true]);
    $qrCode = QrCode::factory()
        ->revoked()
        ->for($servicePoint)
        ->create([
            'public_token' => 'publictokenrevoked',
            'short_code' => 'QR-REVOK',
        ]);

    $this->get(route('public.qr.show', ['token' => $qrCode->public_token], false))
        ->assertOk()
        ->assertSeeText('QR code is no longer active')
        ->assertSeeText('This QR code has been replaced. Please ask the staff for the current code.')
        ->assertDontSeeText('Guest session and menu will appear here in the next steps.');
});

test('public qr route shows inactive service point message', function () {
    [, , $branch] = createPublicQrBranch();
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Closed Table',
            'is_active' => false,
        ]);
    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'public_token' => 'publictokeninactive',
            'short_code' => 'QR-INACT',
            'status' => QrCodeStatus::Active,
        ]);

    $this->get(route('public.qr.show', ['token' => $qrCode->public_token], false))
        ->assertOk()
        ->assertSeeText('This place is temporarily unavailable')
        ->assertSeeText('Please ask the staff before ordering from this place.')
        ->assertDontSeeText('Closed Table');
});

test('public qr route shows not found message for unknown token', function () {
    $this->get(route('public.qr.show', ['token' => 'unknownpublictoken'], false))
        ->assertOk()
        ->assertSeeText('QR code not found')
        ->assertSeeText('Please ask the staff for a fresh QR code.');
});

function createPublicQrBranch(): array
{
    $organization = Organization::factory()->create(['name' => 'Public QR Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Bella Public']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Bella Old Town',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
        ]);

    return [$organization, $brand, $branch];
}
