<?php

use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionGuestStatus;
use App\Livewire\PublicQr\JoinRequests;
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
        ->assertSeeText('Вход сохранён')
        ->assertSeeText('Пригласить гостя')
        ->assertSeeText('Меню')
        ->assertSeeText('Выбор блюд')
        ->assertSee('data-component="guest-draft-order"', false)
        ->assertSeeText('Общий заказ')
        ->assertSeeText('Корзина')
        ->assertSeeText('Общая сумма')
        ->assertSeeText('0.00 EUR')
        ->assertDontSee('id="guest-name"', false);
});

test('guest list is an isolated polling block with readable statuses', function () {
    [, , $tableSession, $activeGuest] = createGuestTablePageShellContext();
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
    ])
        ->assertSee('data-component="guest-table-guests"', false)
        ->assertSee('wire:poll.1s="refreshGuests"', false)
        ->assertSeeText('За столом')
        ->assertSeeText('Вы')
        ->assertSeeText('Ушёл')
        ->assertSeeText('Удалён');

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
    ])
        ->assertSet('canModerate', true)
        ->assertSeeText('Новых гостей на подтверждение нет.');
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
