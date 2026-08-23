<?php

use App\Actions\QrCodes\GenerateQrCodeForServicePointAction;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointType;
use App\Livewire\PublicQr\GuestEntry;
use App\Livewire\PublicQr\Show as PublicQrShow;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use Livewire\Livewire;

test('service point receives one active permanent qr identity', function () {
    [, , , $servicePoint] = createPrompt352QrServicePoint();

    $firstQrCode = app(GenerateQrCodeForServicePointAction::class)->handle($servicePoint);
    $secondQrCode = app(GenerateQrCodeForServicePointAction::class)->handle($servicePoint);

    expect($firstQrCode->status)->toBe(QrCodeStatus::Active)
        ->and($firstQrCode->service_point_id)->toBe($servicePoint->id)
        ->and($firstQrCode->public_token)->not->toBe('')
        ->and($firstQrCode->public_token)->not->toBe((string) $servicePoint->id)
        ->and($firstQrCode->short_code)->not->toBe('')
        ->and($secondQrCode->id)->toBe($firstQrCode->id)
        ->and(QrCode::query()->where('service_point_id', $servicePoint->id)->where('status', QrCodeStatus::Active->value)->count())->toBe(1)
        ->and(QrCode::query()->where('service_point_id', $servicePoint->id)->count())->toBe(1);
});

test('public qr opens guest landing with current service point zone and branch data', function () {
    [, , $branch, $servicePoint, $areaNode, $qrCode] = createPrompt352ActiveQr([
        'branch' => [
            'name' => 'Hidden Legal Branch Name',
            'public_name' => 'Bella QR Terrace',
            'address' => 'Pilies 10',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
        ],
        'area' => ['name' => 'Atrium Zone'],
        'service_point' => [
            'name' => 'Window Table',
            'display_number' => '42',
        ],
        'qr' => [
            'public_token' => 'prompt352landingtoken',
            'short_code' => 'QR-P352A',
        ],
    ]);

    $this->get(route('public.qr.show', ['token' => $qrCode->public_token], false))
        ->assertOk()
        ->assertSee('data-page="guest-qr-landing"', false)
        ->assertSeeText('Bella QR Terrace')
        ->assertSeeText('Window Table')
        ->assertSeeText('42')
        ->assertSeeText('Atrium Zone')
        ->assertSeeText('Pilies 10, Vilnius, Lithuania')
        ->assertSeeText('QR-P352A')
        ->assertDontSeeText('Hidden Legal Branch Name');

    expect($qrCode->fresh()->service_point_id)->toBe($servicePoint->id)
        ->and($servicePoint->fresh()->area_node_id)->toBe($areaNode->id)
        ->and($branch->fresh()->publicDisplayName())->toBe('Bella QR Terrace');
});

test('same qr keeps working after service point rename and shows new name', function () {
    [, , , $servicePoint, , $qrCode] = createPrompt352ActiveQr([
        'area' => ['name' => 'Main Hall'],
        'service_point' => ['name' => 'Old Table Name'],
        'qr' => [
            'public_token' => 'prompt352renametoken',
            'short_code' => 'QR-P352B',
        ],
    ]);

    $originalToken = $qrCode->public_token;
    $originalShortCode = $qrCode->short_code;

    $servicePoint->update(['name' => 'Renamed Window Table']);

    $this->get(route('public.qr.show', ['token' => $originalToken], false))
        ->assertOk()
        ->assertSee('data-page="guest-qr-landing"', false)
        ->assertSeeText('Renamed Window Table')
        ->assertSeeText('Main Hall')
        ->assertDontSeeText('Old Table Name');

    expect($qrCode->fresh()->public_token)->toBe($originalToken)
        ->and($qrCode->fresh()->short_code)->toBe($originalShortCode);
});

test('same qr keeps working after service point moves to another area', function () {
    [, , $branch, $servicePoint, , $qrCode] = createPrompt352ActiveQr([
        'area' => ['name' => 'Old Hall'],
        'service_point' => ['name' => 'Movable Table'],
        'qr' => [
            'public_token' => 'prompt352movetoken',
            'short_code' => 'QR-P352C',
        ],
    ]);
    $newArea = AreaNode::factory()
        ->for($branch)
        ->create(['name' => 'Garden Room']);

    $originalToken = $qrCode->public_token;
    $originalShortCode = $qrCode->short_code;

    $servicePoint->update(['area_node_id' => $newArea->id]);

    $this->get(route('public.qr.show', ['token' => $originalToken], false))
        ->assertOk()
        ->assertSee('data-page="guest-qr-landing"', false)
        ->assertSeeText('Movable Table')
        ->assertSeeText('Garden Room')
        ->assertDontSeeText('Old Hall');

    expect($qrCode->fresh()->public_token)->toBe($originalToken)
        ->and($qrCode->fresh()->short_code)->toBe($originalShortCode)
        ->and($qrCode->fresh()->service_point_id)->toBe($servicePoint->id);
});

test('disabled and revoked qr codes show friendly errors and revoked qr does not create session', function () {
    [, , , $disabledServicePoint, , $disabledQrCode] = createPrompt352ActiveQr([
        'service_point' => ['name' => 'Disabled Table'],
        'qr' => [
            'public_token' => 'prompt352disabledtoken',
            'short_code' => 'QR-P352D',
            'status' => QrCodeStatus::Disabled,
        ],
    ]);
    [, , , $revokedServicePoint, , $revokedQrCode] = createPrompt352ActiveQr([
        'service_point' => ['name' => 'Revoked Table'],
        'qr' => [
            'public_token' => 'prompt352revokedtoken',
            'short_code' => 'QR-P352R',
            'status' => QrCodeStatus::Revoked,
            'revoked_at' => now(),
        ],
    ]);

    $this->get(route('public.qr.show', ['token' => $disabledQrCode->public_token], false))
        ->assertOk()
        ->assertSeeText('QR code is temporarily disabled')
        ->assertSeeText('Please ask the staff to help you with this place.')
        ->assertDontSeeText($disabledServicePoint->name);

    $this->get(route('public.qr.show', ['token' => $revokedQrCode->public_token], false))
        ->assertOk()
        ->assertSeeText('QR code is no longer active')
        ->assertSeeText('This QR code has been replaced. Please ask the staff for the current code.')
        ->assertDontSeeText($revokedServicePoint->name);

    Livewire::test(PublicQrShow::class, ['token' => $revokedQrCode->public_token])
        ->assertSet('state', 'revoked');

    Livewire::test(GuestEntry::class, ['token' => $revokedQrCode->public_token])
        ->set('guestName', 'Ana')
        ->call('enterTable')
        ->assertSet('state', 'revoked');

    expect(TableSession::query()->count())->toBe(0);
});

test('invalid qr token shows safe friendly error without technical details or database ids', function () {
    $organization = Organization::factory()->create([
        'id' => 987650,
        'name' => 'Hidden Invalid Token Group',
    ]);
    $brand = Brand::factory()
        ->for($organization)
        ->create([
            'id' => 987651,
            'name' => 'Hidden Invalid Token Brand',
        ]);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'id' => 987652,
            'name' => 'Hidden Invalid Token Branch',
        ]);
    $areaNode = AreaNode::factory()
        ->for($branch)
        ->create([
            'id' => 987653,
            'name' => 'Hidden Invalid Token Zone',
        ]);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($areaNode)
        ->create([
            'id' => 987654,
            'name' => 'Hidden Invalid Token Table',
        ]);

    $this->get(route('public.qr.show', ['token' => 'invalidtokenwithoutids'], false))
        ->assertOk()
        ->assertSeeText('QR code not found')
        ->assertSeeText('Please ask the staff for a fresh QR code.')
        ->assertDontSeeText('Exception')
        ->assertDontSeeText('SQL')
        ->assertDontSeeText('qr_codes')
        ->assertDontSeeText('service_points')
        ->assertDontSeeText('987650')
        ->assertDontSeeText('987651')
        ->assertDontSeeText('987652')
        ->assertDontSeeText('987653')
        ->assertDontSeeText('987654')
        ->assertDontSeeText($organization->name)
        ->assertDontSeeText($brand->name)
        ->assertDontSeeText($branch->name)
        ->assertDontSeeText($areaNode->name)
        ->assertDontSeeText($servicePoint->name);

    expect(TableSession::query()->count())->toBe(0);
});

function createPrompt352QrServicePoint(array $branchOverrides = [], array $areaOverrides = [], array $servicePointOverrides = []): array
{
    $organization = Organization::factory()->create(['name' => 'Prompt 352 QR Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Prompt 352 QR Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create(array_merge([
            'name' => 'Prompt 352 QR Branch',
            'address' => 'Gedimino 1',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
        ], $branchOverrides));
    $areaNode = AreaNode::factory()
        ->for($branch)
        ->create(array_merge([
            'name' => 'Prompt 352 Main Zone',
        ], $areaOverrides));
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($areaNode)
        ->create(array_merge([
            'type' => ServicePointType::Table,
            'name' => 'Prompt 352 Table',
            'display_number' => '7',
            'is_active' => true,
        ], $servicePointOverrides));

    return [$organization, $brand, $branch, $servicePoint, $areaNode];
}

function createPrompt352ActiveQr(array $overrides = []): array
{
    [$organization, $brand, $branch, $servicePoint, $areaNode] = createPrompt352QrServicePoint(
        $overrides['branch'] ?? [],
        $overrides['area'] ?? [],
        $overrides['service_point'] ?? [],
    );

    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create(array_merge([
            'public_token' => 'prompt352token'.strtolower(fake()->bothify('????####')),
            'short_code' => 'QR-P352X',
            'status' => QrCodeStatus::Active,
        ], $overrides['qr'] ?? []));

    return [$organization, $brand, $branch, $servicePoint, $areaNode, $qrCode];
}
