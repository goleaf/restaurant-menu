<?php

declare(strict_types=1);

use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionGuestStatus;
use App\Livewire\PublicQr\DraftOrder;
use App\Livewire\PublicQr\DraftTotals;
use App\Livewire\PublicQr\GuestEntry;
use App\Livewire\PublicQr\JoinRequests;
use App\Livewire\PublicQr\Notifications;
use App\Livewire\PublicQr\OrderStatuses;
use App\Livewire\PublicQr\TableGuests;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use Livewire\Livewire;

test('active guest sees the guest table page shell', function () {
    [$qrCode, , $tableSession, $activeGuest] = createGuestTablePageShellContext();

    Livewire::withCookie(guestTablePageShellCookieName($qrCode), $activeGuest->guest_token)
        ->test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->assertSet('currentTableSessionId', $tableSession->id)
        ->assertSet('currentGuestId', $activeGuest->id)
        ->assertSet('guestCanAddItems', true)
        ->assertSee('data-page="guest-table-shell"', false)
        ->assertSee('data-guest-table-context', false)
        ->assertSee('data-guest-cart-actions', false)
        ->assertSeeText('Guest Table Branch')
        ->assertSeeText('Стол у окна')
        ->assertSeeText('Entry saved')
        ->assertSeeText('Invite guest')
        ->assertSeeText('Menu')
        ->assertSeeText(__('menu.guest.choose_items'))
        ->assertSee('data-component="guest-order-statuses"', false)
        ->assertSee('data-component="guest-draft-order"', false)
        ->assertSee('data-component="guest-draft-totals"', false)
        ->assertSeeText('Order status')
        ->assertSeeText('Cart')
        ->assertSeeText('Table total')
        ->assertSeeText('€0.00')
        ->assertDontSee('id="guest-name"', false);
});

test('guest table polling blocks use branch settings interval', function () {
    [$qrCode, $servicePoint, $tableSession, $activeGuest] = createGuestTablePageShellContext();

    BranchSetting::query()
        ->where('branch_id', $servicePoint->branch_id)
        ->update(['polling_interval_seconds' => 3]);

    Livewire::withCookie(guestTablePageShellCookieName($qrCode), $activeGuest->guest_token)
        ->test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->assertSet('landing.polling_interval_seconds', 3)
        ->assertSee('wire:poll.visible.3s="refreshGuests"', false)
        ->assertSee('wire:poll.visible.3s="refreshNotifications"', false)
        ->assertSee('wire:poll.visible.3s="refreshJoinRequests"', false)
        ->assertSee('wire:poll.visible.3s="refreshOrderStatuses"', false)
        ->assertSee('wire:poll.visible.3s="refreshDraft"', false)
        ->assertSee('wire:poll.visible.3s="refreshTotals"', false);

    Livewire::withCookie(guestTablePageShellCookieName($qrCode), $activeGuest->guest_token)
        ->test(DraftOrder::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $activeGuest->id,
            'currency' => 'EUR',
            'publicToken' => $qrCode->public_token,
            'pollingIntervalSeconds' => 3,
            'showControls' => false,
            'showTotals' => false,
            'showStatuses' => false,
        ])
        ->assertSee('wire:poll.visible.3s="refreshDraft"', false)
        ->assertDontSee('Order status')
        ->assertDontSee('Table total');

    Livewire::test(DraftTotals::class, [
        'tableSessionId' => $tableSession->id,
        'currentGuestId' => $activeGuest->id,
        'currency' => 'EUR',
        'publicToken' => $qrCode->public_token,
        'pollingIntervalSeconds' => 3,
        'language' => 'en',
    ])->assertSee('wire:poll.visible.3s="refreshTotals"', false);

    Livewire::withCookie(guestTablePageShellCookieName($qrCode), $activeGuest->guest_token)
        ->test(OrderStatuses::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $activeGuest->id,
            'publicToken' => $qrCode->public_token,
            'pollingIntervalSeconds' => 3,
        ])->assertSee('wire:poll.visible.3s="refreshOrderStatuses"', false);

    Livewire::test(Notifications::class, [
        'tableSessionId' => $tableSession->id,
        'currentGuestId' => $activeGuest->id,
        'publicToken' => $qrCode->public_token,
        'pollingIntervalSeconds' => 3,
    ])->assertSee('wire:poll.visible.3s="refreshNotifications"', false);
});

test('guest list is an isolated polling block with readable statuses', function () {
    [$qrCode, , $tableSession, $activeGuest] = createGuestTablePageShellContext();

    $activeGuest->update(['ready_at' => now()]);

    TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Mila',
            'status' => TableSessionGuestStatus::Left,
        ]);
    TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Zane',
            'status' => TableSessionGuestStatus::Removed,
        ]);

    $component = Livewire::withCookie(guestTablePageShellCookieName($qrCode), $activeGuest->guest_token)
        ->test(TableGuests::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $activeGuest->id,
            'publicToken' => $qrCode->public_token,
            'language' => 'en',
        ])
        ->assertSee('data-component="guest-table-guests"', false)
        ->assertSee('wire:poll.visible.1s="refreshGuests"', false)
        ->assertSeeText('At the table')
        ->assertSeeText('You')
        ->assertSeeText('Left')
        ->assertSeeText('Removed')
        ->assertSeeText('Ready')
        ->assertSeeText('Not ready')
        ->assertSet('guests.0.is_ready', true)
        ->assertSet('guests.1.is_ready', false);

    expect(collect($component->get('guests'))->pluck('guest_name')->all())
        ->toBe(['Ana', 'Mila', 'Zane']);

    TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Boris',
            'status' => TableSessionGuestStatus::Active,
        ]);

    $component->call('refreshGuests');

    expect(collect($component->get('guests'))->pluck('guest_name')->all())
        ->toBe(['Ana', 'Boris', 'Mila', 'Zane']);
});

test('isolated guest polling revokes table data when the current guest loses access', function (): void {
    [$qrCode, , $tableSession, $activeGuest] = createGuestTablePageShellContext();
    $cookieName = guestTablePageShellCookieName($qrCode);

    $guestList = Livewire::withCookie($cookieName, $activeGuest->guest_token)
        ->test(TableGuests::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $activeGuest->id,
            'publicToken' => $qrCode->public_token,
            'language' => 'en',
        ])
        ->assertSet('canRead', true)
        ->assertSet('guests.0.guest_name', 'Ana');

    $orderStatuses = Livewire::withCookie($cookieName, $activeGuest->guest_token)
        ->test(OrderStatuses::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $activeGuest->id,
            'publicToken' => $qrCode->public_token,
            'language' => 'en',
        ])
        ->assertSet('canRead', true)
        ->assertSet('tableSessionStatusValue', $tableSession->status->value);

    $activeGuest->forceFill([
        'status' => TableSessionGuestStatus::Removed,
        'left_at' => now(),
    ])->save();

    $guestList
        ->call('refreshGuests')
        ->assertSet('canRead', false)
        ->assertSet('guests', []);

    $orderStatuses
        ->call('refreshOrderStatuses')
        ->assertSet('canRead', false)
        ->assertSet('tableSessionStatusValue', null)
        ->assertSet('itemStatuses', []);
});

test('isolated guest polling rejects a qr from another table in the same restaurant', function (): void {
    [$qrCode, $servicePoint, $tableSession, $activeGuest] = createGuestTablePageShellContext();
    $foreignServicePoint = ServicePoint::factory()
        ->for($servicePoint->branch)
        ->create(['is_active' => true]);
    $foreignQrCode = QrCode::factory()
        ->for($foreignServicePoint)
        ->active()
        ->create();
    $foreignCookieName = guestTablePageShellCookieName($foreignQrCode);

    Livewire::withCookie($foreignCookieName, $activeGuest->guest_token)
        ->test(TableGuests::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $activeGuest->id,
            'publicToken' => $foreignQrCode->public_token,
            'language' => 'en',
        ])
        ->assertSet('canRead', false)
        ->assertSet('guests', [])
        ->assertDontSeeText('Ana');

    Livewire::withCookie($foreignCookieName, $activeGuest->guest_token)
        ->test(OrderStatuses::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $activeGuest->id,
            'publicToken' => $foreignQrCode->public_token,
            'language' => 'en',
        ])
        ->assertSet('canRead', false)
        ->assertSet('tableSessionStatusValue', null)
        ->assertSet('itemStatuses', []);

    expect($qrCode->service_point_id)->toBe($servicePoint->id);
});

test('guest list polling query count stays constant as the table grows', function (): void {
    [$qrCode, , $tableSession, $activeGuest] = createGuestTablePageShellContext();
    $component = Livewire::withCookie(
        guestTablePageShellCookieName($qrCode),
        $activeGuest->guest_token,
    )->test(TableGuests::class, [
        'tableSessionId' => $tableSession->id,
        'currentGuestId' => $activeGuest->id,
        'publicToken' => $qrCode->public_token,
        'language' => 'en',
    ]);
    $initialQueryCount = countDatabaseQueries(
        fn () => $component->call('refreshGuests'),
    );

    TableSessionGuest::factory()
        ->count(20)
        ->for($tableSession)
        ->create(['status' => TableSessionGuestStatus::Active]);

    $grownQueryCount = countDatabaseQueries(
        fn () => $component->call('refreshGuests'),
    );

    expect($initialQueryCount)->toBeLessThanOrEqual(6)
        ->and($grownQueryCount)->toBe($initialQueryCount);
});

test('join request block can use current guest session before browser cookie returns', function () {
    [$qrCode, , $tableSession, $activeGuest] = createGuestTablePageShellContext();

    session()->put('guest_entries.'.$qrCode->public_token, [
        'table_session_id' => $tableSession->id,
        'guest_id' => $activeGuest->id,
        'guest_token' => $activeGuest->guest_token,
    ]);

    Livewire::test(JoinRequests::class, [
        'tableSessionId' => $tableSession->id,
        'guestId' => $activeGuest->id,
        'publicToken' => $qrCode->public_token,
        'language' => 'en',
    ])
        ->assertSet('canModerate', true)
        ->assertSeeText('No new guests waiting.');
});

function createGuestTablePageShellContext(): array
{
    $organization = Organization::factory()->create(['name' => 'Guest Table Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Guest Table Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Guest Table Branch',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
            'currency' => 'EUR',
        ]);
    BranchSetting::factory()->for($branch)->create();
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Стол у окна',
            'is_active' => true,
            'status' => ServicePointStatus::Occupied,
        ]);
    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'public_token' => 'guesttableshell'.fake()->unique()->numerify('######'),
            'short_code' => 'QR-GT'.fake()->unique()->numerify('####'),
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

    return [$qrCode, $servicePoint, $tableSession, $activeGuest];
}

function guestTablePageShellCookieName(QrCode $qrCode): string
{
    return 'guest_token_'.substr(hash('sha256', $qrCode->public_token), 0, 24);
}
