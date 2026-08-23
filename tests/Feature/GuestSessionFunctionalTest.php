<?php

use App\Enums\GuestTableEntryState;
use App\Enums\MenuStatus;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionJoinRequestStatus;
use App\Enums\TableSessionSource;
use App\Enums\TableSessionStatus;
use App\Livewire\PublicQr\GuestEntry;
use App\Livewire\PublicQr\GuestMenu;
use App\Livewire\PublicQr\JoinRequests;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;
use Illuminate\Support\Facades\Cookie;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

test('first guest enters by qr name and receives guest token for a new table session', function () {
    [$qrCode, , $servicePoint] = createPrompt353GuestQrContext();

    $this->get(route('public.qr.show', ['token' => $qrCode->public_token], false))
        ->assertOk()
        ->assertSee('data-page="guest-qr-landing"', false)
        ->assertSeeText('Prompt 353 Table');

    Livewire::test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->assertSet('state', 'ready')
        ->set('guestName', '  Ana   Maria  ')
        ->call('enterTable')
        ->assertHasNoErrors()
        ->assertSet('preparedGuestName', 'Ana Maria')
        ->assertSet('entryState', GuestTableEntryState::PendingSessionCreated->value)
        ->assertSet('guestCanViewTable', true)
        ->assertSet('guestCanAddItems', true)
        ->assertSee('data-page="guest-table-shell"', false)
        ->assertSeeText('Welcome, Ana Maria.');

    $tableSession = TableSession::query()->firstOrFail();
    $guest = TableSessionGuest::query()->firstOrFail();
    $queuedGuestCookie = prompt353QueuedGuestCookie();

    expect($tableSession->branch_id)->toBe($servicePoint->branch_id)
        ->and($tableSession->service_point_id)->toBe($servicePoint->id)
        ->and($tableSession->status)->toBe(TableSessionStatus::Pending)
        ->and($tableSession->source)->toBe(TableSessionSource::GuestCreated)
        ->and($tableSession->opened_by_guest_id)->toBe($guest->id)
        ->and($tableSession->pending_service_point_id)->toBe($servicePoint->id)
        ->and($guest->table_session_id)->toBe($tableSession->id)
        ->and($guest->guest_name)->toBe('Ana Maria')
        ->and($guest->status)->toBe(TableSessionGuestStatus::Active)
        ->and($guest->guest_token)->not->toBe('')
        ->and(strlen($guest->guest_token))->toBe(64)
        ->and($queuedGuestCookie)->toBeInstanceOf(SymfonyCookie::class)
        ->and($queuedGuestCookie->getValue())->toBe($guest->guest_token)
        ->and(session('guest_entries.'.$qrCode->public_token.'.guest_token'))->toBe($guest->guest_token);
});

test('second guest on an existing active session creates join request without table access', function () {
    [$qrCode, , $tableSession] = createPrompt353ActiveTableContext();

    Livewire::test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->set('guestName', 'Boris')
        ->call('enterTable')
        ->assertHasNoErrors()
        ->assertSet('entryState', GuestTableEntryState::JoinRequestCreated->value)
        ->assertSet('currentTableSessionId', $tableSession->id)
        ->assertSet('currentGuestId', null)
        ->assertSet('guestCanViewTable', false)
        ->assertSet('guestCanAddItems', false)
        ->assertDontSee('data-page="guest-table-shell"', false)
        ->assertSeeText('Request sent');

    $joinRequest = TableSessionJoinRequest::query()->firstOrFail();
    $queuedGuestCookie = prompt353QueuedGuestCookie();

    expect($joinRequest->table_session_id)->toBe($tableSession->id)
        ->and($joinRequest->guest_name)->toBe('Boris')
        ->and($joinRequest->status)->toBe(TableSessionJoinRequestStatus::Pending)
        ->and($joinRequest->guest_token)->not->toBe('')
        ->and(strlen($joinRequest->guest_token))->toBe(64)
        ->and(TableSessionGuest::query()->where('guest_name', 'Boris')->exists())->toBeFalse()
        ->and($queuedGuestCookie)->toBeInstanceOf(SymfonyCookie::class)
        ->and($queuedGuestCookie->getValue())->toBe($joinRequest->guest_token);
});

test('active guest approves join request and approved guest sees table page', function () {
    [$qrCode, , $tableSession, $activeGuest] = createPrompt353ActiveTableContext();
    $joinRequest = TableSessionJoinRequest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Lina',
            'status' => TableSessionJoinRequestStatus::Pending,
        ]);

    Livewire::withCookie(prompt353GuestCookieName($qrCode), $activeGuest->guest_token)
        ->test(JoinRequests::class, [
            'tableSessionId' => $tableSession->id,
            'guestId' => $activeGuest->id,
            'publicToken' => $qrCode->public_token,
            'language' => 'en',
        ])
        ->assertSet('canModerate', true)
        ->assertSeeText('Lina')
        ->call('approve', $joinRequest->id)
        ->assertSeeText('Guest approved.');

    $approvedGuest = TableSessionGuest::query()
        ->where('guest_token', $joinRequest->guest_token)
        ->firstOrFail();

    Livewire::withCookie(prompt353GuestCookieName($qrCode), $joinRequest->guest_token)
        ->test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->assertSet('currentTableSessionId', $tableSession->id)
        ->assertSet('currentGuestId', $approvedGuest->id)
        ->assertSet('guestCanViewTable', true)
        ->assertSet('guestCanAddItems', true)
        ->assertSee('data-page="guest-table-shell"', false)
        ->assertSeeText('Entry saved');

    expect($approvedGuest->guest_name)->toBe('Lina')
        ->and($approvedGuest->status)->toBe(TableSessionGuestStatus::Active)
        ->and($joinRequest->fresh()->status)->toBe(TableSessionJoinRequestStatus::Approved)
        ->and($joinRequest->fresh()->approved_by_guest_id)->toBe($activeGuest->id);
});

test('active guest rejects join request and rejected guest cannot add items', function () {
    [$qrCode, $branch, $tableSession, $activeGuest] = createPrompt353ActiveTableContext();
    $menuItem = createPrompt353MenuItem($branch);
    $joinRequest = TableSessionJoinRequest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Nina',
            'status' => TableSessionJoinRequestStatus::Pending,
        ]);

    $waitingGuest = Livewire::withCookie(prompt353GuestCookieName($qrCode), $joinRequest->guest_token)
        ->test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->assertSet('currentJoinRequestId', $joinRequest->id)
        ->assertSet('guestCanAddItems', false);

    Livewire::withCookie(prompt353GuestCookieName($qrCode), $activeGuest->guest_token)
        ->test(JoinRequests::class, [
            'tableSessionId' => $tableSession->id,
            'guestId' => $activeGuest->id,
            'publicToken' => $qrCode->public_token,
            'language' => 'en',
        ])
        ->call('reject', $joinRequest->id)
        ->assertSeeText('Guest rejected.');

    $waitingGuest
        ->call('refreshJoinRequestStatus')
        ->assertSet('entryState', 'join_request_blocked')
        ->assertSet('currentGuestId', null)
        ->assertSet('guestCanViewTable', false)
        ->assertSet('guestCanAddItems', false)
        ->assertDontSee('data-page="guest-table-shell"', false)
        ->assertSeeText('Request closed');

    Livewire::withCookie(prompt353GuestCookieName($qrCode), $joinRequest->guest_token)
        ->test(GuestMenu::class, [
            'branchId' => $branch->id,
            'currency' => 'EUR',
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => 0,
            'publicToken' => $qrCode->public_token,
            'guestCanAddItems' => false,
        ])
        ->set('selectedItemId', $menuItem->id)
        ->call('saveConfiguredItem')
        ->assertHasErrors(['guest']);

    expect($joinRequest->fresh()->status)->toBe(TableSessionJoinRequestStatus::Rejected)
        ->and($joinRequest->fresh()->rejected_by_guest_id)->toBe($activeGuest->id)
        ->and(TableSessionGuest::query()->where('guest_token', $joinRequest->guest_token)->exists())->toBeFalse()
        ->and(DraftOrder::query()->exists())->toBeFalse()
        ->and(DraftOrderItem::query()->exists())->toBeFalse();
});

test('active guest token refresh restores session without another approval', function () {
    [$qrCode, , $tableSession, $activeGuest] = createPrompt353ActiveTableContext();

    Livewire::withCookie(prompt353GuestCookieName($qrCode), $activeGuest->guest_token)
        ->test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->assertSet('state', 'ready')
        ->assertSet('preparedGuestName', $activeGuest->guest_name)
        ->assertSet('currentTableSessionId', $tableSession->id)
        ->assertSet('currentGuestId', $activeGuest->id)
        ->assertSet('currentJoinRequestId', null)
        ->assertSet('entryState', 'guest_restored')
        ->assertSet('guestCanViewTable', true)
        ->assertSet('guestCanAddItems', true)
        ->assertSee('data-page="guest-table-shell"', false)
        ->assertSeeText('Entry saved');

    expect(TableSessionJoinRequest::query()->exists())->toBeFalse()
        ->and(TableSessionGuest::query()->count())->toBe(1);
});

test('closed session blocks old guest actions and fresh qr scan can start a new session', function () {
    [$qrCode, $branch, $tableSession, $activeGuest] = createPrompt353ActiveTableContext();
    $menuItem = createPrompt353MenuItem($branch);

    $tableSession->forceFill([
        'status' => TableSessionStatus::Closed,
        'ended_at' => now(),
    ])->save();

    Livewire::withCookie(prompt353GuestCookieName($qrCode), $activeGuest->guest_token)
        ->test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->assertSet('currentTableSessionId', $tableSession->id)
        ->assertSet('currentGuestId', $activeGuest->id)
        ->assertSet('entryState', 'guest_blocked')
        ->assertSet('entryIssueCode', 'session_closed')
        ->assertSet('guestCanViewTable', false)
        ->assertSet('guestCanAddItems', false)
        ->assertDontSee('data-page="guest-table-shell"', false)
        ->assertSeeText('This table session is closed');

    Livewire::withCookie(prompt353GuestCookieName($qrCode), $activeGuest->guest_token)
        ->test(GuestMenu::class, [
            'branchId' => $branch->id,
            'currency' => 'EUR',
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $activeGuest->id,
            'publicToken' => $qrCode->public_token,
            'guestCanAddItems' => false,
        ])
        ->set('selectedItemId', $menuItem->id)
        ->call('saveConfiguredItem')
        ->assertHasErrors(['guest']);

    session()->forget('guest_entries.'.$qrCode->public_token);

    Livewire::withCookie(prompt353GuestCookieName($qrCode), 'fresh-scan-without-old-guest-token')
        ->test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->set('guestName', 'Fresh Guest')
        ->call('enterTable')
        ->assertSet('entryState', GuestTableEntryState::PendingSessionCreated->value)
        ->assertSet('guestCanViewTable', true)
        ->assertSet('guestCanAddItems', true);

    $newTableSession = TableSession::query()
        ->whereKeyNot($tableSession->id)
        ->firstOrFail();
    $newGuest = TableSessionGuest::query()
        ->where('guest_name', 'Fresh Guest')
        ->firstOrFail();

    expect($tableSession->fresh()->status)->toBe(TableSessionStatus::Closed)
        ->and($newTableSession->service_point_id)->toBe($tableSession->service_point_id)
        ->and($newTableSession->status)->toBe(TableSessionStatus::Pending)
        ->and($newTableSession->opened_by_guest_id)->toBe($newGuest->id)
        ->and($newGuest->status)->toBe(TableSessionGuestStatus::Active)
        ->and(DraftOrder::query()->exists())->toBeFalse()
        ->and(DraftOrderItem::query()->exists())->toBeFalse();
});

function createPrompt353GuestQrContext(): array
{
    $organization = Organization::factory()->create(['name' => 'Prompt 353 Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Prompt 353 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 353 Branch',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
            'currency' => 'EUR',
        ]);

    BranchSetting::factory()
        ->for($branch)
        ->create(['allow_guest_created_sessions' => true]);

    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Prompt 353 Table',
            'status' => ServicePointStatus::Free,
            'is_active' => true,
        ]);
    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'public_token' => 'prompt353'.fake()->unique()->bothify('????####'),
            'short_code' => 'QR-353'.fake()->unique()->numerify('###'),
            'status' => QrCodeStatus::Active,
        ]);

    return [$qrCode, $branch, $servicePoint];
}

function createPrompt353ActiveTableContext(): array
{
    [$qrCode, $branch, $servicePoint] = createPrompt353GuestQrContext();
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

    return [$qrCode, $branch, $tableSession, $activeGuest];
}

function createPrompt353MenuItem(Branch $branch): MenuItem
{
    $menu = Menu::factory()
        ->for($branch)
        ->create([
            'name' => 'Prompt 353 Menu',
            'status' => MenuStatus::Active,
        ]);
    $category = MenuCategory::factory()
        ->for($menu)
        ->create([
            'name' => 'Prompt 353 Category',
            'is_active' => true,
        ]);

    return MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create([
            'name' => 'Prompt 353 Soup',
            'price_cents' => 750,
            'is_available' => true,
        ]);
}

function prompt353GuestCookieName(QrCode $qrCode): string
{
    return 'guest_token_'.substr(hash('sha256', $qrCode->public_token), 0, 24);
}

function prompt353QueuedGuestCookie(): ?SymfonyCookie
{
    return collect(Cookie::getQueuedCookies())
        ->first(fn (SymfonyCookie $cookie): bool => str_starts_with($cookie->getName(), 'guest_token_'));
}
