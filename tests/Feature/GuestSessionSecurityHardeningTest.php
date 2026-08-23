<?php

use App\Actions\DraftOrders\AddGuestDraftOrderItemAction;
use App\Actions\DraftOrders\DeleteGuestDraftOrderItemAction;
use App\Actions\DraftOrders\SendDraftOrderToWaiterAction;
use App\Actions\DraftOrders\UpdateGuestDraftOrderItemAction;
use App\Actions\TableSessions\CreateGuestPendingTableSessionAction;
use App\Enums\DraftOrderStatus;
use App\Enums\GuestTableEntryState;
use App\Enums\MenuStatus;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionJoinRequestStatus;
use App\Livewire\PublicQr\GuestEntry;
use App\Livewire\PublicQr\Show as PublicQrShow;
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
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('guest-created session action rejects inactive service points', function () {
    [, , $servicePoint] = createPrompt85SecurityContext(withTableSession: false);

    $servicePoint->update(['is_active' => false]);

    $result = app(CreateGuestPendingTableSessionAction::class)->handle($servicePoint->fresh(), '  Mira  ');

    expect($result['state'])->toBe(GuestTableEntryState::ServicePointUnavailable)
        ->and(TableSession::query()->exists())->toBeFalse()
        ->and(TableSessionGuest::query()->exists())->toBeFalse();
});

test('guest draft actions reject inactive service points even with a valid active guest', function () {
    [, , $servicePoint, $tableSession, $guest, $menuItem] = createPrompt85SecurityContext();

    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create(['status' => DraftOrderStatus::Draft]);
    $draftOrderItem = DraftOrderItem::factory()
        ->for($draftOrder)
        ->for($guest, 'guest')
        ->for($menuItem, 'menuItem')
        ->create(['item_name' => 'Security soup']);

    $servicePoint->update(['is_active' => false]);

    expect(fn () => app(AddGuestDraftOrderItemAction::class)->handle(
        tableSession: $tableSession,
        guest: $guest,
        menuItem: $menuItem,
        selectedModifierOptions: [],
    ))->toThrow(ValidationException::class);

    expect(fn () => app(UpdateGuestDraftOrderItemAction::class)->handle(
        draftOrderItem: $draftOrderItem,
        guest: $guest,
        quantity: 2,
        selectedModifierOptions: [],
        comment: 'Still blocked',
    ))->toThrow(ValidationException::class);

    expect(fn () => app(DeleteGuestDraftOrderItemAction::class)->handle($draftOrderItem, $guest))
        ->toThrow(ValidationException::class);

    expect(fn () => app(SendDraftOrderToWaiterAction::class)->handle($draftOrder, $guest))
        ->toThrow(ValidationException::class);

    expect($draftOrder->fresh()->status)->toBe(DraftOrderStatus::Draft)
        ->and(DraftOrderItem::query()->whereKey($draftOrderItem->id)->exists())->toBeTrue();
});

test('rejected guest cannot add draft items through backend action', function () {
    [, , , $tableSession, $guest, $menuItem] = createPrompt85SecurityContext();

    $guest->forceFill(['status' => TableSessionGuestStatus::Rejected])->save();

    expect(fn () => app(AddGuestDraftOrderItemAction::class)->handle(
        tableSession: $tableSession,
        guest: $guest,
        menuItem: $menuItem,
        selectedModifierOptions: [],
    ))->toThrow(ValidationException::class);

    expect(DraftOrder::query()->exists())->toBeFalse()
        ->and(DraftOrderItem::query()->exists())->toBeFalse();
});

test('expired join request restore is blocked and marked expired', function () {
    [$qrCode, , , $tableSession] = createPrompt85SecurityContext();
    $joinRequest = TableSessionJoinRequest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Jonas',
            'status' => TableSessionJoinRequestStatus::Pending,
            'expires_at' => now()->subMinute(),
        ]);

    Livewire::withCookie(prompt85GuestTokenCookieName($qrCode), $joinRequest->guest_token)
        ->test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->assertSet('state', 'ready')
        ->assertSet('currentJoinRequestId', $joinRequest->id)
        ->assertSet('guestCanAddItems', false)
        ->assertSet('entryState', 'join_request_blocked')
        ->assertSeeText(__('guest.table.join_request_expired'));

    expect($joinRequest->fresh()->status)->toBe(TableSessionJoinRequestStatus::Expired)
        ->and(TableSessionGuest::query()->where('guest_token', $joinRequest->guest_token)->exists())->toBeFalse();
});

test('disabled qr shows a safe error and cannot open guest ordering', function () {
    [$qrCode] = createPrompt85SecurityContext(withTableSession: false);

    $qrCode->forceFill(['status' => QrCodeStatus::Disabled])->save();

    Livewire::test(PublicQrShow::class, ['token' => $qrCode->public_token])
        ->assertSet('state', 'disabled')
        ->assertSeeText('QR code is temporarily disabled');

    Livewire::test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->set('guestName', 'Ana')
        ->call('enterTable')
        ->assertSet('currentTableSessionId', null)
        ->assertSet('guestCanAddItems', false);

    expect(TableSession::query()->exists())->toBeFalse()
        ->and(TableSessionGuest::query()->exists())->toBeFalse();
});

function createPrompt85SecurityContext(bool $withTableSession = true): array
{
    $organization = Organization::factory()->create(['name' => 'Prompt 85 Group']);
    $brand = Brand::factory()
        ->for($organization)
        ->create(['name' => 'Prompt 85 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 85 Branch',
            'currency' => 'EUR',
        ]);

    BranchSetting::factory()
        ->for($branch)
        ->create();

    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Prompt 85 Table',
            'status' => ServicePointStatus::Occupied,
            'is_active' => true,
        ]);
    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'public_token' => fake()->unique()->regexify('[A-Za-z0-9]{64}'),
            'short_code' => 'P85-'.fake()->unique()->bothify('####'),
            'status' => QrCodeStatus::Active,
        ]);
    $menuItem = createPrompt85MenuItem($branch);

    if (! $withTableSession) {
        return [$qrCode, $branch, $servicePoint, null, null, $menuItem];
    }

    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->waiterOpened()
        ->create();
    $guest = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Ana',
            'status' => TableSessionGuestStatus::Active,
        ]);

    return [$qrCode, $branch, $servicePoint, $tableSession, $guest, $menuItem];
}

function createPrompt85MenuItem(Branch $branch): MenuItem
{
    $menu = Menu::factory()
        ->for($branch)
        ->create([
            'name' => 'Prompt 85 Menu',
            'status' => MenuStatus::Active,
        ]);
    $category = MenuCategory::factory()
        ->for($menu)
        ->create([
            'name' => 'Security category',
            'is_active' => true,
        ]);

    return MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create([
            'name' => 'Security soup',
            'price_cents' => 650,
            'is_available' => true,
        ]);
}

function prompt85GuestTokenCookieName(QrCode $qrCode): string
{
    return 'guest_token_'.substr(hash('sha256', $qrCode->public_token), 0, 24);
}
