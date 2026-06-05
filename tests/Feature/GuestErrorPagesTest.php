<?php

use App\Enums\OrganizationSubscriptionStatus;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Livewire\PublicQr\Show as PublicQrShow;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use Livewire\Livewire;

test('guest error page is shown when qr token is not found', function () {
    $this->get(route('public.qr.show', ['token' => 'missingpublictoken'], false))
        ->assertOk()
        ->assertSee('data-component="guest-error-page"', false)
        ->assertSee('data-error-state="not_found"', false)
        ->assertSeeText('QR code not found')
        ->assertSeeText('Open start page');
});

test('guest error page is shown when qr code is disabled', function () {
    [$qrCode] = createPrompt86GuestErrorContext(qrStatus: QrCodeStatus::Disabled);

    $this->get(route('public.qr.show', ['token' => $qrCode->public_token], false))
        ->assertOk()
        ->assertSee('data-component="guest-error-page"', false)
        ->assertSee('data-error-state="disabled"', false)
        ->assertSeeText('QR code is temporarily disabled')
        ->assertSeeText('Try again');
});

test('guest error page is shown when service point is inactive', function () {
    [$qrCode, , $servicePoint] = createPrompt86GuestErrorContext();
    $servicePoint->update(['is_active' => false]);

    $this->get(route('public.qr.show', ['token' => $qrCode->public_token], false))
        ->assertOk()
        ->assertSee('data-component="guest-error-page"', false)
        ->assertSee('data-error-state="inactive_service_point"', false)
        ->assertSeeText('This place is temporarily unavailable')
        ->assertDontSeeText($servicePoint->name);
});

test('guest error page is shown when restaurant is temporarily unavailable', function () {
    [$qrCode, $organization] = createPrompt86GuestErrorContext();

    OrganizationSubscription::factory()
        ->for($organization)
        ->inactive()
        ->create([
            'status' => OrganizationSubscriptionStatus::Inactive,
        ]);

    Livewire::test(PublicQrShow::class, ['token' => $qrCode->public_token])
        ->assertSet('state', 'restaurant_unavailable')
        ->assertSee('data-component="guest-error-page"', false)
        ->assertSee('data-error-state="restaurant_unavailable"', false)
        ->assertSeeText('Restaurant is temporarily unavailable');
});

test('guest error page is shown when restored table session is closed', function () {
    [$qrCode, , $servicePoint] = createPrompt86GuestErrorContext();
    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->create([
            'status' => TableSessionStatus::Closed,
            'ended_at' => now(),
        ]);
    $guest = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Mira',
            'status' => TableSessionGuestStatus::Active,
        ]);

    Livewire::withCookie(prompt86GuestTokenCookieName($qrCode), $guest->guest_token)
        ->test(PublicQrShow::class, ['token' => $qrCode->public_token])
        ->assertSet('entryState', 'guest_blocked')
        ->assertSet('entryIssueCode', 'session_closed')
        ->assertSet('guestCanAddItems', false)
        ->assertSee('data-component="guest-error-page"', false)
        ->assertSee('data-error-state="session_closed"', false)
        ->assertSeeText('This table session is closed')
        ->assertSeeText('Return to QR page');
});

test('guest error page is shown when guest was rejected', function () {
    [$qrCode, , $servicePoint] = createPrompt86GuestErrorContext();
    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->create();
    $guest = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Rejected Guest',
            'status' => TableSessionGuestStatus::Rejected,
        ]);

    Livewire::withCookie(prompt86GuestTokenCookieName($qrCode), $guest->guest_token)
        ->test(PublicQrShow::class, ['token' => $qrCode->public_token])
        ->assertSet('entryState', 'guest_blocked')
        ->assertSet('entryIssueCode', 'guest_rejected')
        ->assertSet('guestCanAddItems', false)
        ->assertSee('data-component="guest-error-page"', false)
        ->assertSee('data-error-state="guest_rejected"', false)
        ->assertSeeText('Guest access was not approved')
        ->assertSeeText('Return to QR page');
});

test('guest error page is shown when invite link is stale', function () {
    [$qrCode] = createPrompt86GuestErrorContext();

    Livewire::withQueryParams(['invite' => str_repeat('A', 64)])
        ->test(PublicQrShow::class, ['token' => $qrCode->public_token])
        ->assertSet('hasCurrentInviteToken', true)
        ->set('guestName', 'Jonas')
        ->call('enterTable')
        ->assertSet('entryState', 'guest_invite_invalid')
        ->assertSet('entryIssueCode', 'invite_expired')
        ->assertSet('guestCanAddItems', false)
        ->assertSee('data-component="guest-error-page"', false)
        ->assertSee('data-error-state="invite_expired"', false)
        ->assertSeeText('Invite link has expired')
        ->assertSeeText('Return to QR page');
});

function createPrompt86GuestErrorContext(QrCodeStatus $qrStatus = QrCodeStatus::Active): array
{
    $organization = Organization::factory()->create(['name' => 'Prompt 86 Group']);
    $brand = Brand::factory()
        ->for($organization)
        ->create(['name' => 'Prompt 86 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 86 Branch',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
        ]);

    BranchSetting::factory()
        ->for($branch)
        ->create();

    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Prompt 86 Table',
            'status' => ServicePointStatus::Free,
            'is_active' => true,
        ]);
    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'public_token' => fake()->unique()->regexify('[A-Za-z0-9]{64}'),
            'short_code' => 'P86-'.fake()->unique()->bothify('####'),
            'status' => $qrStatus,
        ]);

    return [$qrCode, $organization, $servicePoint, $branch, $brand];
}

function prompt86GuestTokenCookieName(QrCode $qrCode): string
{
    return 'guest_token_'.substr(hash('sha256', $qrCode->public_token), 0, 24);
}
