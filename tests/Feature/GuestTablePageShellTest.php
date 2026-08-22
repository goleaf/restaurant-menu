<?php

declare(strict_types=1);

use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionGuestStatus;
use App\Livewire\PublicQr\DraftOrder;
use App\Livewire\PublicQr\DraftTotals;
use App\Livewire\PublicQr\JoinRequests;
use App\Livewire\PublicQr\Notifications;
use App\Livewire\PublicQr\OrderStatuses;
use App\Livewire\PublicQr\Show as PublicQrShow;
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
        ->test(PublicQrShow::class, ['token' => $qrCode->public_token])
        ->assertSet('currentTableSessionId', $tableSession->id)
        ->assertSet('currentGuestId', $activeGuest->id)
        ->assertSet('guestCanAddItems', true)
        ->assertSee('data-page="guest-table-shell"', false)
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
        ->assertSeeText('0.00 EUR')
        ->assertDontSee('id="guest-name"', false);
});

test('guest table polling blocks use branch settings interval', function () {
    [$qrCode, $servicePoint, $tableSession, $activeGuest] = createGuestTablePageShellContext();

    BranchSetting::query()
        ->where('branch_id', $servicePoint->branch_id)
        ->update(['polling_interval_seconds' => 3]);

    Livewire::withCookie(guestTablePageShellCookieName($qrCode), $activeGuest->guest_token)
        ->test(PublicQrShow::class, ['token' => $qrCode->public_token])
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

    Livewire::test(OrderStatuses::class, [
        'tableSessionId' => $tableSession->id,
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
    [, , $tableSession, $activeGuest] = createGuestTablePageShellContext();

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

    $component = Livewire::test(TableGuests::class, [
        'tableSessionId' => $tableSession->id,
        'currentGuestId' => $activeGuest->id,
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
