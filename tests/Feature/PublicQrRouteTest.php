<?php

use App\Enums\QrCodeStatus;
use App\Enums\ServicePointType;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;

test('public qr route opens guest landing for active qr code', function () {
    [$organization, $brand, $branch] = createPublicQrBranch();
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
        ->assertSeeText('Welcome')
        ->assertSeeText($brand->name)
        ->assertSeeText($branch->name)
        ->assertSeeText('Window Table')
        ->assertSeeText('Main Hall')
        ->assertSeeText('QR-ACTIVE')
        ->assertSeeText('Guest session and menu will appear here in the next steps.');
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
