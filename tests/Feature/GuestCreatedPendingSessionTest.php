<?php

use App\Enums\GuestTableEntryState;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionJoinRequestStatus;
use App\Enums\TableSessionSource;
use App\Enums\TableSessionStatus;
use App\Livewire\PublicQr\GuestEntry;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;
use Illuminate\Support\Facades\Cookie;
use Livewire\Livewire;

test('first guest creates pending table session and active session guest from qr landing', function () {
    [$qrCode, $servicePoint] = createGuestPendingQrContext();

    Livewire::test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->assertSet('state', 'ready')
        ->set('guestName', '  Ana   Maria  ')
        ->call('enterTable')
        ->assertHasNoErrors()
        ->assertSet('preparedGuestName', 'Ana Maria')
        ->assertSet('entryState', GuestTableEntryState::PendingSessionCreated->value)
        ->assertSeeText('Welcome, Ana Maria.')
        ->assertSeeText('Table opened. You can start choosing.');

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

test('guest token cookie restores table session after page refresh', function () {
    [$qrCode] = createGuestPendingQrContext();

    Livewire::test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->set('guestName', 'Lina')
        ->call('enterTable')
        ->assertSet('entryState', GuestTableEntryState::PendingSessionCreated->value);

    $tableSession = TableSession::query()->firstOrFail();
    $guest = TableSessionGuest::query()->firstOrFail();

    Livewire::withCookie(guestTokenCookieName($qrCode), $guest->guest_token)
        ->test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->assertSet('state', 'ready')
        ->assertSet('preparedGuestName', 'Lina')
        ->assertSet('currentTableSessionId', $tableSession->id)
        ->assertSet('currentGuestId', $guest->id)
        ->assertSet('guestCanAddItems', true)
        ->assertSet('entryState', 'guest_restored')
        ->assertSeeText('Entry saved.')
        ->assertSeeText('Entry saved');

    $this
        ->withCookie(guestTokenCookieName($qrCode), $guest->guest_token)
        ->get(route('public.qr.show', ['token' => $qrCode->public_token], false))
        ->assertOk()
        ->assertSeeText('Entry saved.')
        ->assertSeeText('Entry saved');
});

test('guest token restore shows message when table session is closed', function () {
    [$qrCode, $servicePoint] = createGuestPendingQrContext();
    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->create([
            'status' => TableSessionStatus::Closed,
            'started_at' => now()->subHour(),
            'ended_at' => now(),
        ]);
    $guest = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Nina',
            'status' => TableSessionGuestStatus::Active,
        ]);

    $tableSession->forceFill(['opened_by_guest_id' => $guest->id])->save();

    Livewire::withCookie(guestTokenCookieName($qrCode), $guest->guest_token)
        ->test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->assertSet('currentTableSessionId', $tableSession->id)
        ->assertSet('currentGuestId', $guest->id)
        ->assertSet('guestCanAddItems', false)
        ->assertSet('entryState', 'guest_blocked')
        ->assertSeeText('This table session is closed.');
});

test('blocked guest statuses cannot add items after token restore', function (TableSessionGuestStatus $status, string $message) {
    [$qrCode, $servicePoint] = createGuestPendingQrContext();
    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->create();
    $guest = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Mila',
            'status' => $status,
        ]);

    Livewire::withCookie(guestTokenCookieName($qrCode), $guest->guest_token)
        ->test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->assertSet('currentTableSessionId', $tableSession->id)
        ->assertSet('currentGuestId', $guest->id)
        ->assertSet('guestCanAddItems', false)
        ->assertSet('entryState', 'guest_blocked')
        ->assertSeeText($message);
})->with([
    'rejected' => [TableSessionGuestStatus::Rejected, 'Your request to join this table was rejected.'],
    'removed' => [TableSessionGuestStatus::Removed, 'You are no longer active at this table.'],
]);

test('guest-created sessions setting can block first guest session creation', function () {
    [$qrCode] = createGuestPendingQrContext(allowGuestCreatedSessions: false);

    Livewire::test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->set('guestName', 'Mila')
        ->call('enterTable')
        ->assertHasNoErrors()
        ->assertSet('entryState', GuestTableEntryState::GuestCreatedSessionsDisabled->value)
        ->assertSet('currentTableSessionId', null)
        ->assertSet('currentGuestId', null)
        ->assertSeeText('Guests cannot start a new table session right now.');

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

    Livewire::test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->set('guestName', 'Jonas')
        ->call('enterTable')
        ->assertHasNoErrors()
        ->assertSet('entryState', GuestTableEntryState::ActiveSessionExists->value)
        ->assertSet('currentTableSessionId', $activeTableSession->id)
        ->assertSet('currentGuestId', null)
        ->assertSeeText('There is already an active table session.');

    expect(TableSession::query()->count())->toBe(1);
    expect(TableSessionGuest::query()->exists())->toBeFalse();
    expect(TableSessionJoinRequest::query()->exists())->toBeFalse();
});

test('guest entering active session with active guests creates pending join request', function () {
    [$qrCode, $servicePoint] = createGuestPendingQrContext();
    $activeTableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->waiterOpened()
        ->create();
    TableSessionGuest::factory()
        ->for($activeTableSession)
        ->create(['guest_name' => 'Ana']);

    Livewire::test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->set('guestName', 'Jonas')
        ->call('enterTable')
        ->assertHasNoErrors()
        ->assertSet('entryState', GuestTableEntryState::JoinRequestCreated->value)
        ->assertSet('currentTableSessionId', $activeTableSession->id)
        ->assertSet('currentGuestId', null)
        ->assertSeeText('Request sent. Waiting for guests at the table.')
        ->assertSeeText('Request sent');

    $joinRequest = TableSessionJoinRequest::query()->firstOrFail();

    expect(TableSession::query()->count())->toBe(1);
    expect(TableSessionGuest::query()->count())->toBe(1);
    expect($joinRequest->table_session_id)->toBe($activeTableSession->id);
    expect($joinRequest->guest_name)->toBe('Jonas');
    expect($joinRequest->guest_token)->not->toBeNull();
    expect(strlen($joinRequest->guest_token))->toBe(64);
    expect($joinRequest->status)->toBe(TableSessionJoinRequestStatus::Pending);
    expect($joinRequest->expires_at)->not->toBeNull();

    $queuedGuestCookie = collect(Cookie::getQueuedCookies())
        ->first(fn (Symfony\Component\HttpFoundation\Cookie $cookie): bool => str_starts_with($cookie->getName(), 'guest_token_')
            && $cookie->getValue() === $joinRequest->guest_token);

    expect($queuedGuestCookie)->not->toBeNull();
});

test('guest sees duplicate name warning before join request is created', function () {
    [$qrCode, $servicePoint] = createGuestPendingQrContext();
    $activeTableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->waiterOpened()
        ->create();
    TableSessionGuest::factory()
        ->for($activeTableSession)
        ->create([
            'guest_name' => 'Анна',
            'status' => TableSessionGuestStatus::Active,
        ]);

    Livewire::test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->set('guestName', '  Анна  ')
        ->call('enterTable')
        ->assertHasNoErrors()
        ->assertSet('hasGuestNameConflict', true)
        ->assertSet('guestNameConflictExistingName', 'Анна')
        ->assertSet('guestNameSuggestions', ['Анна 2', 'Анна К.'])
        ->assertSet('currentJoinRequestId', null)
        ->assertSeeText('There is already a guest named Анна at this table.')
        ->assertSeeText('Анна 2')
        ->assertSeeText('Анна К.')
        ->assertSeeText('Enter as Анна');

    expect(TableSessionJoinRequest::query()->exists())->toBeFalse();
    expect(TableSessionGuest::query()->count())->toBe(1);
});

test('guest can choose suggested display name for duplicate table name', function () {
    [$qrCode, $servicePoint] = createGuestPendingQrContext();
    $activeTableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->waiterOpened()
        ->create();
    TableSessionGuest::factory()
        ->for($activeTableSession)
        ->create([
            'guest_name' => 'Анна',
            'status' => TableSessionGuestStatus::Active,
        ]);

    Livewire::test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->set('guestName', 'Анна')
        ->call('enterTable')
        ->assertSet('hasGuestNameConflict', true)
        ->call('chooseGuestNameSuggestion', 0)
        ->assertSet('guestName', 'Анна 2')
        ->assertSet('hasGuestNameConflict', false)
        ->call('enterTable')
        ->assertSet('entryState', GuestTableEntryState::JoinRequestCreated->value)
        ->assertSet('currentTableSessionId', $activeTableSession->id)
        ->assertSet('currentGuestId', null)
        ->assertSet('hasGuestNameConflict', false);

    $joinRequest = TableSessionJoinRequest::query()->firstOrFail();

    expect($joinRequest->table_session_id)->toBe($activeTableSession->id);
    expect($joinRequest->guest_name)->toBe('Анна 2');
});

test('guest can continue with duplicate display name when it is intentional', function () {
    [$qrCode, $servicePoint] = createGuestPendingQrContext();
    $activeTableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->waiterOpened()
        ->create();
    TableSessionGuest::factory()
        ->for($activeTableSession)
        ->create([
            'guest_name' => 'Анна',
            'status' => TableSessionGuestStatus::Active,
        ]);

    Livewire::test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->set('guestName', 'Анна')
        ->call('enterTable')
        ->assertSet('hasGuestNameConflict', true)
        ->call('continueWithDuplicateGuestName')
        ->assertSet('entryState', GuestTableEntryState::JoinRequestCreated->value)
        ->assertSet('currentTableSessionId', $activeTableSession->id)
        ->assertSet('currentGuestId', null)
        ->assertSet('hasGuestNameConflict', false);

    $joinRequest = TableSessionJoinRequest::query()->firstOrFail();

    expect($joinRequest->table_session_id)->toBe($activeTableSession->id);
    expect($joinRequest->guest_name)->toBe('Анна');
});

test('guest entering again does not create duplicate pending session or guest', function () {
    [$qrCode] = createGuestPendingQrContext();

    Livewire::test(GuestEntry::class, ['token' => $qrCode->public_token])
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

    Livewire::test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->set('guestName', 'First')
        ->call('enterTable')
        ->assertSet('entryState', GuestTableEntryState::PendingSessionCreated->value);

    Livewire::test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->set('guestName', 'Second')
        ->call('enterTable')
        ->assertSet('entryState', GuestTableEntryState::JoinRequestCreated->value)
        ->assertSet('currentGuestId', null)
        ->assertSeeText('Request sent. Waiting for guests at the table.');

    expect(TableSession::query()->count())->toBe(1);
    expect(TableSessionGuest::query()->count())->toBe(1);
    expect(TableSessionJoinRequest::query()->count())->toBe(1);
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

function guestTokenCookieName(QrCode $qrCode): string
{
    return 'guest_token_'.substr(hash('sha256', $qrCode->public_token), 0, 24);
}
