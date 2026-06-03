<?php

use App\Enums\GuestTableEntryState;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionSource;
use App\Enums\TableSessionStatus;
use App\Livewire\PublicQr\Show as PublicQrShow;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use Illuminate\Support\Facades\Cookie;
use Livewire\Livewire;

test('first guest creates pending table session and active session guest from qr landing', function () {
    [$qrCode, $servicePoint] = createGuestPendingQrContext();

    Livewire::test(PublicQrShow::class, ['token' => $qrCode->public_token])
        ->assertSet('state', 'ready')
        ->set('guestName', '  Ana   Maria  ')
        ->call('enterTable')
        ->assertHasNoErrors()
        ->assertSet('preparedGuestName', 'Ana Maria')
        ->assertSet('entryState', GuestTableEntryState::PendingSessionCreated->value)
        ->assertSeeText('Добро пожаловать, Ana Maria.')
        ->assertSeeText('Стол ожидает подтверждения официанта.');

    $tableSession = TableSession::query()->firstOrFail();
    $guest = TableSessionGuest::query()->firstOrFail();

    expect($tableSession->branch_id)->toBe($servicePoint->branch_id);
    expect($tableSession->service_point_id)->toBe($servicePoint->id);
    expect($tableSession->status)->toBe(TableSessionStatus::Pending);
    expect($tableSession->source)->toBe(TableSessionSource::GuestCreated);
    expect($tableSession->started_at)->not->toBeNull();
    expect($tableSession->opened_by_user_id)->toBeNull();
    expect($tableSession->opened_by_guest_id)->toBe($guest->id);
    expect($tableSession->active_service_point_id)->toBeNull();
    expect($tableSession->pending_service_point_id)->toBe($servicePoint->id);
    expect($guest->table_session_id)->toBe($tableSession->id);
    expect($guest->guest_name)->toBe('Ana Maria');
    expect($guest->guest_token)->not->toBeNull();
    expect(strlen($guest->guest_token))->toBe(64);
    expect($guest->status)->toBe(TableSessionGuestStatus::Active);
    expect($guest->joined_at)->not->toBeNull();
    expect($servicePoint->fresh()->status)->toBe(ServicePointStatus::Free);

    $queuedGuestCookie = collect(Cookie::getQueuedCookies())
        ->first(fn (Symfony\Component\HttpFoundation\Cookie $cookie): bool => str_starts_with($cookie->getName(), 'guest_token_'));

    expect($queuedGuestCookie)->not->toBeNull();
    expect($queuedGuestCookie->getValue())->toBe($guest->guest_token);
});

test('guest-created sessions setting can block first guest session creation', function () {
    [$qrCode] = createGuestPendingQrContext(allowGuestCreatedSessions: false);

    Livewire::test(PublicQrShow::class, ['token' => $qrCode->public_token])
        ->set('guestName', 'Mila')
        ->call('enterTable')
        ->assertHasNoErrors()
        ->assertSet('entryState', GuestTableEntryState::GuestCreatedSessionsDisabled->value)
        ->assertSet('currentTableSessionId', null)
        ->assertSet('currentGuestId', null)
        ->assertSeeText('Открытие стола гостем отключено.');

    expect(TableSession::query()->exists())->toBeFalse();
    expect(TableSessionGuest::query()->exists())->toBeFalse();
});

test('guest entering table does not create pending session when active session already exists', function () {
    [$qrCode, $servicePoint] = createGuestPendingQrContext();
    $activeTableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->waiterOpened()
        ->create();

    Livewire::test(PublicQrShow::class, ['token' => $qrCode->public_token])
        ->set('guestName', 'Jonas')
        ->call('enterTable')
        ->assertHasNoErrors()
        ->assertSet('entryState', GuestTableEntryState::ActiveSessionExists->value)
        ->assertSet('currentTableSessionId', $activeTableSession->id)
        ->assertSet('currentGuestId', null)
        ->assertSeeText('Стол уже открыт.');

    expect(TableSession::query()->count())->toBe(1);
    expect(TableSessionGuest::query()->exists())->toBeFalse();
});

test('guest entering again does not create duplicate pending session or guest', function () {
    [$qrCode] = createGuestPendingQrContext();

    Livewire::test(PublicQrShow::class, ['token' => $qrCode->public_token])
        ->set('guestName', 'Lina')
        ->call('enterTable')
        ->assertSet('entryState', GuestTableEntryState::PendingSessionCreated->value)
        ->call('enterTable')
        ->assertSet('entryState', GuestTableEntryState::PendingSessionCreated->value);

    expect(TableSession::query()->count())->toBe(1);
    expect(TableSessionGuest::query()->count())->toBe(1);
});

test('fresh guest landing sees existing pending session without creating another first guest', function () {
    [$qrCode] = createGuestPendingQrContext();

    Livewire::test(PublicQrShow::class, ['token' => $qrCode->public_token])
        ->set('guestName', 'First')
        ->call('enterTable')
        ->assertSet('entryState', GuestTableEntryState::PendingSessionCreated->value);

    Livewire::test(PublicQrShow::class, ['token' => $qrCode->public_token])
        ->set('guestName', 'Second')
        ->call('enterTable')
        ->assertSet('entryState', GuestTableEntryState::PendingSessionExists->value)
        ->assertSet('currentGuestId', null)
        ->assertSeeText('Стол уже ожидает подтверждения официанта.');

    expect(TableSession::query()->count())->toBe(1);
    expect(TableSessionGuest::query()->count())->toBe(1);
});

function createGuestPendingQrContext(bool $allowGuestCreatedSessions = true): array
{
    $organization = Organization::factory()->create(['name' => 'Guest Pending Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Guest Pending Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Guest Pending Branch',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
        ]);
    BranchSetting::factory()
        ->for($branch)
        ->create(['allow_guest_created_sessions' => $allowGuestCreatedSessions]);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Guest Pending Table',
            'is_active' => true,
            'status' => ServicePointStatus::Free,
        ]);
    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'public_token' => 'guestpending'.fake()->unique()->numerify('######'),
            'short_code' => 'QR-GP'.fake()->unique()->numerify('####'),
            'status' => QrCodeStatus::Active,
        ]);

    return [$qrCode, $servicePoint, $branch];
}
