<?php

use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionJoinRequestStatus;
use App\Livewire\PublicQr\Show as PublicQrShow;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

test('table sessions store a hidden guest invite token', function () {
    expect(Schema::hasColumns('table_sessions', [
        'guest_invite_token',
        'guest_invite_created_at',
        'guest_invite_created_by_guest_id',
    ]))->toBeTrue();
});

test('active guest can create an invite share link for current table session', function () {
    [$qrCode, , $tableSession, $activeGuest] = createGuestInviteShareContext();

    $component = Livewire::withCookie(guestInviteShareCookieName($qrCode), $activeGuest->guest_token)
        ->test(PublicQrShow::class, ['token' => $qrCode->public_token])
        ->assertSet('currentTableSessionId', $tableSession->id)
        ->assertSet('currentGuestId', $activeGuest->id)
        ->assertSeeText('Invite guest')
        ->call('createGuestInviteLink')
        ->assertSeeText('Invite link is ready.')
        ->assertSeeText('Copy link')
        ->assertSee('navigator.share', false);

    $inviteUrl = $component->get('guestInviteUrl');
    $tableSession->refresh();

    expect($tableSession->guest_invite_token)->not->toBeNull();
    expect(strlen($tableSession->guest_invite_token))->toBe(64);
    expect($tableSession->guest_invite_created_by_guest_id)->toBe($activeGuest->id);
    expect($tableSession->guest_invite_created_at)->not->toBeNull();
    expect($inviteUrl)->toContain('/q/'.$qrCode->public_token);
    expect($inviteUrl)->toContain('invite='.$tableSession->guest_invite_token);
});

test('guest invite link opens landing and creates a pending join request', function () {
    [$qrCode, , $tableSession, $activeGuest] = createGuestInviteShareContext();

    Livewire::withCookie(guestInviteShareCookieName($qrCode), $activeGuest->guest_token)
        ->test(PublicQrShow::class, ['token' => $qrCode->public_token])
        ->call('createGuestInviteLink');

    $inviteToken = $tableSession->fresh()->guest_invite_token;

    Livewire::withQueryParams(['invite' => $inviteToken])
        ->withCookie(guestInviteShareCookieName($qrCode), str_repeat('x', 64))
        ->test(PublicQrShow::class, ['token' => $qrCode->public_token])
        ->assertSet('state', 'ready')
        ->assertSet('currentInviteToken', $inviteToken)
        ->assertSeeText('Enter your name to ask to join this table.')
        ->set('guestName', '  Jonas  ')
        ->call('enterTable')
        ->assertHasNoErrors()
        ->assertSet('preparedGuestName', 'Jonas')
        ->assertSet('currentTableSessionId', $tableSession->id)
        ->assertSet('currentGuestId', null)
        ->assertSet('guestCanAddItems', false)
        ->assertSeeText('Request sent. Waiting for guests at the table.')
        ->assertSeeText('Request sent');

    $joinRequest = TableSessionJoinRequest::query()->firstOrFail();

    expect($joinRequest->table_session_id)->toBe($tableSession->id);
    expect($joinRequest->guest_name)->toBe('Jonas');
    expect($joinRequest->status)->toBe(TableSessionJoinRequestStatus::Pending);
    expect(TableSessionGuest::query()->where('guest_name', 'Jonas')->exists())->toBeFalse();
});

test('branch setting can disable guest invite links', function () {
    [$qrCode, , $tableSession, $activeGuest] = createGuestInviteShareContext(allowGuestInviteLinks: false);

    Livewire::withCookie(guestInviteShareCookieName($qrCode), $activeGuest->guest_token)
        ->test(PublicQrShow::class, ['token' => $qrCode->public_token])
        ->call('createGuestInviteLink')
        ->assertSet('guestInviteUrl', '')
        ->assertSeeText('Приглашения гостей по ссылке отключены для этого филиала.');

    expect($tableSession->fresh()->guest_invite_token)->toBeNull();
});

function createGuestInviteShareContext(bool $allowGuestInviteLinks = true): array
{
    $organization = Organization::factory()->create(['name' => 'Guest Invite Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Guest Invite Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Guest Invite Branch',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
        ]);
    BranchSetting::factory()
        ->for($branch)
        ->create(['allow_guest_invite_links' => $allowGuestInviteLinks]);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Guest Invite Table',
            'is_active' => true,
            'status' => ServicePointStatus::Free,
        ]);
    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'public_token' => 'guestinvite'.fake()->unique()->numerify('######'),
            'short_code' => 'QR-GI'.fake()->unique()->numerify('####'),
            'status' => QrCodeStatus::Active,
        ]);
    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->waiterOpened()
        ->create();
    $activeGuest = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Ana',
            'status' => TableSessionGuestStatus::Active,
        ]);

    return [$qrCode, $servicePoint, $tableSession, $activeGuest, $branch];
}

function guestInviteShareCookieName(QrCode $qrCode): string
{
    return 'guest_token_'.substr(hash('sha256', $qrCode->public_token), 0, 24);
}
