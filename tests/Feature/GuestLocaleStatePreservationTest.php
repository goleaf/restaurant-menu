<?php

declare(strict_types=1);

use App\Actions\PublicQr\BuildGuestEntryContextAction;
use App\Enums\TableSessionGuestStatus;
use App\Livewire\PublicQr\GuestEntry;
use App\Livewire\PublicQr\GuestMenu;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use Livewire\Attributes\Reactive;
use Livewire\Livewire;

test('guest locale events cannot persist after guest access is revoked', function (TableSessionGuestStatus $status): void {
    [$qrCode, , $servicePoint] = guestLocalePreservationContext();
    $tableSession = TableSession::factory()->forServicePoint($servicePoint)->active()->create();
    $guest = TableSessionGuest::factory()->for($tableSession)->active()->create(['locale' => 'lt']);
    session()->put('guest_entries.'.$qrCode->public_token, [
        'table_session_id' => $tableSession->id,
        'guest_id' => $guest->id,
        'guest_token' => $guest->guest_token,
    ]);
    $component = Livewire::test(GuestEntry::class, ['token' => $qrCode->public_token, 'language' => 'lt'])
        ->assertSet('currentGuestId', $guest->id);
    $guest->forceFill(['status' => $status])->saveOrFail();
    expect($guest->fresh()->status)->toBe($status);

    $component->dispatch('guest-locale-updated', language: 'ru')->assertOk();

    expect($guest->fresh()->locale)->toBe('lt');
})->with([
    'removed' => TableSessionGuestStatus::Removed,
    'left' => TableSessionGuestStatus::Left,
    'rejected' => TableSessionGuestStatus::Rejected,
    'pending approval' => TableSessionGuestStatus::PendingApproval,
]);

test('guest locale events persist for the matching active guest', function (): void {
    [$qrCode, , $servicePoint] = guestLocalePreservationContext();
    $tableSession = TableSession::factory()->forServicePoint($servicePoint)->active()->create();
    $guest = TableSessionGuest::factory()->for($tableSession)->active()->create(['locale' => 'lt']);
    session()->put('guest_entries.'.$qrCode->public_token, [
        'table_session_id' => $tableSession->id,
        'guest_id' => $guest->id,
        'guest_token' => $guest->guest_token,
    ]);

    Livewire::test(GuestEntry::class, ['token' => $qrCode->public_token, 'language' => 'lt'])
        ->dispatch('guest-locale-updated', language: 'ru')
        ->assertSet('currentGuestId', $guest->id)
        ->assertSet('currentTableSessionId', $tableSession->id)
        ->assertSet('entryMessage', __('guest.table.entry_saved', [], 'ru'));

    expect($guest->fresh()->locale)->toBe('ru');
});

test('anonymous locale updates refresh prepared landing text without losing an entered name', function (): void {
    [$qrCode, , $servicePoint] = guestLocalePreservationContext();
    $component = Livewire::test(GuestEntry::class, ['token' => $qrCode->public_token, 'language' => 'lt'])
        ->set('guestName', 'Unsubmitted guest name');
    $originalMessage = $component->get('message');
    $expected = app(BuildGuestEntryContextAction::class)->handle($qrCode->public_token, 'ru', true, false);
    expect($expected['message'])->not->toBe($originalMessage);
    expect($expected['landing']['service_point_type'])->toBe(__(
        'reports.service_point_types.'.$servicePoint->type->value,
        [],
        'ru',
    ));

    $component->dispatch('guest-locale-updated', language: 'ru')
        ->assertSet('language', 'ru')
        ->assertSet('guestName', 'Unsubmitted guest name')
        ->assertSet('currentGuestId', null)
        ->assertSet('message', $expected['message'])
        ->assertSee($expected['message']);

    foreach (['opening_status_label', 'opening_status_detail', 'public_description', 'service_point_type'] as $field) {
        expect($component->get('landing')[$field])->toBe($expected['landing'][$field]);
    }
});

test('locale events retain the existing nested guest menu component identity', function (): void {
    [$qrCode, , $servicePoint] = guestLocalePreservationContext();
    $tableSession = TableSession::factory()->forServicePoint($servicePoint)->active()->create();
    $guest = TableSessionGuest::factory()->for($tableSession)->active()->create(['locale' => 'lt']);
    session()->put('guest_entries.'.$qrCode->public_token, [
        'table_session_id' => $tableSession->id,
        'guest_id' => $guest->id,
        'guest_token' => $guest->guest_token,
    ]);
    $component = Livewire::test(GuestEntry::class, ['token' => $qrCode->public_token, 'language' => 'lt']);
    $before = array_filter($component->snapshot['memo']['children'], fn (string $key): bool => str_starts_with($key, 'guest-menu-'), ARRAY_FILTER_USE_KEY);
    expect($before)->toHaveCount(1);

    $component->dispatch('guest-locale-updated', language: 'ru');
    $after = array_filter($component->snapshot['memo']['children'], fn (string $key): bool => str_starts_with($key, 'guest-menu-'), ARRAY_FILTER_USE_KEY);

    expect(array_values($after))->toBe(array_values($before));
});

test('guest menu receives locale events without discarding an open item draft or search', function (): void {
    [, $branch] = guestLocalePreservationContext();
    $menu = Menu::factory()->for($branch)->active()->create();
    $category = MenuCategory::factory()->for($menu)->active()->create();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['name' => 'Locale dish']);

    Livewire::test(GuestMenu::class, ['branchId' => $branch->id, 'language' => 'lt'])
        ->call('openItem', $item->id)
        ->assertSet('selectedItemId', $item->id)
        ->set('itemComment', 'Keep this unfinished comment')
        ->set('search', 'Locale')
        ->dispatch('guest-locale-updated', language: 'ru')
        ->assertSet('language', 'ru')
        ->assertSet('selectedItemId', $item->id)
        ->assertSet('itemComment', 'Keep this unfinished comment')
        ->assertSet('search', 'Locale');
});

test('stable guest menu accepts localized parent opening text as a reactive presentation property', function (): void {
    [$qrCode, $branch] = guestLocalePreservationContext();
    $menu = Menu::factory()->for($branch)->active()->create();
    $category = MenuCategory::factory()->for($menu)->active()->create();
    MenuItem::factory()->for($menu)->for($category, 'category')->create(['name' => 'Closed venue dish']);
    $branch->forceFill(['is_temporarily_closed' => true, 'temporary_closed_reason' => null])->saveOrFail();
    $component = Livewire::test(GuestEntry::class, ['token' => $qrCode->public_token, 'language' => 'lt']);
    $lithuanianDetail = $component->get('landing')['opening_status_detail'];

    $component->dispatch('guest-locale-updated', language: 'ru');
    $russianDetail = $component->get('landing')['opening_status_detail'];
    expect($russianDetail)->not->toBe($lithuanianDetail);
    expect((new ReflectionProperty(GuestMenu::class, 'branchOpeningStatusMessage'))->getAttributes(Reactive::class))->toHaveCount(1);

    Livewire::test(GuestMenu::class, [
        'branchId' => $branch->id,
        'language' => 'ru',
        'branchCanAcceptOrders' => false,
        'branchOpeningStatusMessage' => $russianDetail,
    ])->assertSee('Closed venue dish')->assertSee($russianDetail)->assertDontSee($lithuanianDetail);
});

/** @return array{QrCode, Branch, ServicePoint} */
function guestLocalePreservationContext(): array
{
    $branch = Branch::factory()->create(['public_description' => null]);
    BranchSetting::factory()->for($branch)->create(['default_language' => 'lt']);
    $servicePoint = ServicePoint::factory()->for($branch)->create(['is_active' => true]);
    $qrCode = QrCode::factory()->forServicePoint($servicePoint)->active()->create();

    return [$qrCode, $branch, $servicePoint];
}
