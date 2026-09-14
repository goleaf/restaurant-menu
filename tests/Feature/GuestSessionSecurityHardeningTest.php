<?php

use App\Actions\DraftOrders\AddGuestDraftOrderItemAction;
use App\Actions\DraftOrders\DeleteGuestDraftOrderItemAction;
use App\Actions\DraftOrders\SendDraftOrderToWaiterAction;
use App\Actions\DraftOrders\UpdateGuestDraftOrderItemAction;
use App\Actions\PublicQr\EnsureGuestEntryRateLimitAction;
use App\Actions\TableSessions\CreateGuestPendingTableSessionAction;
use App\Enums\DraftOrderStatus;
use App\Enums\GuestTableEntryState;
use App\Enums\MenuStatus;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionJoinRequestStatus;
use App\Enums\TableSessionStatus;
use App\Livewire\PublicQr\GuestEntry;
use App\Livewire\PublicQr\Show as PublicQrShow;
use App\Models\AreaNode;
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
use App\Models\TableSessionServicePoint;
use App\Services\PublicQr\PublicQrQueryService;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('guest table presentation loads only missing service point relations', function (
    string $loadedRelations,
    bool $hasArea,
    int $expectedQueries,
): void {
    $area = AreaNode::factory()->create();
    $servicePoint = ServicePoint::factory()->create([
        'branch_id' => $area->branch_id,
        'area_node_id' => $hasArea ? $area->id : null,
    ]);
    $tableSession = TableSession::factory()->forServicePoint($servicePoint)->active()->create();

    if ($loadedRelations !== 'none') {
        $tableSession->load('servicePoint:id,branch_id,area_node_id,type,name,display_number,is_active');
    }

    if ($loadedRelations === 'all') {
        $tableSession->servicePoint->load('areaNode:id,branch_id,name');
    }

    $loadedServicePoint = $tableSession->getRelations()['servicePoint'] ?? null;
    $queries = app(PublicQrQueryService::class);
    $result = null;
    $queryCount = countDatabaseQueries(function () use ($queries, $tableSession, &$result): void {
        $result = $queries->servicePointForTableSession($tableSession);
    });

    expect($result)->toBeInstanceOf(ServicePoint::class)
        ->and($result->relationLoaded('areaNode'))->toBeTrue()
        ->and($queryCount)->toBe($expectedQueries);

    if ($loadedServicePoint instanceof ServicePoint) {
        expect($result)->toBe($loadedServicePoint);
    }

    $presentationQueries = countDatabaseQueries(function () use ($queries, $tableSession, $result, $servicePoint, $area, $hasArea): void {
        expect($result->id)->toBe($servicePoint->id)
            ->and($result->name)->toBe($servicePoint->name)
            ->and($result->display_number)->toBe($servicePoint->display_number)
            ->and($result->type)->toBe($servicePoint->type)
            ->and($result->areaNode?->id)->toBe($hasArea ? $area->id : null)
            ->and($result->areaNode?->name)->toBe($hasArea ? $area->name : null)
            ->and($queries->servicePointForTableSession($tableSession))->toBe($result);
    });

    expect($presentationQueries)->toBe(0);
})->with([
    'unloaded table and area' => ['none', true, 2],
    'loaded table with missing area' => ['table', true, 1],
    'loaded table and area' => ['all', true, 0],
    'unloaded table without an area' => ['none', false, 1],
    'loaded table without an area' => ['table', false, 0],
    'loaded table and null area' => ['all', false, 0],
]);

test('qr session lookups keep direct and transferred filters independent', function (
    string $origin,
    TableSessionStatus $status,
    bool $isVisible,
    int $expectedQueries,
): void {
    $servicePoint = ServicePoint::factory()->create(['is_active' => true]);
    $qrServicePoint = match ($origin) {
        'current' => $servicePoint,
        'foreign' => ServicePoint::factory()->create(['is_active' => true]),
        default => ServicePoint::factory()->create(['branch_id' => $servicePoint->branch_id, 'is_active' => true]),
    };
    $qrCode = QrCode::factory()->forServicePoint($qrServicePoint)->active()->create();
    $tableSession = TableSession::factory()->forServicePoint($servicePoint)->active()->create([
        'status' => $status,
        'ended_at' => $status->isTerminal() ? now() : null,
        'metadata' => in_array($origin, ['transferred', 'foreign'], true)
            ? ['transfers' => [['from_service_point_id' => $qrServicePoint->id]]]
            : [],
    ]);

    if (in_array($origin, ['merged', 'unlinked'], true)) {
        $linkFactory = TableSessionServicePoint::factory()
            ->forTableSessionAndServicePoint($tableSession, $qrServicePoint);

        if ($origin === 'unlinked') {
            $linkFactory = $linkFactory->unlinkedBy();
        }

        $linkFactory->create();
    }

    $result = null;
    $queryCount = countDatabaseQueries(function () use ($qrCode, $tableSession, &$result): void {
        $result = app(PublicQrQueryService::class)->activeTableSessionForQr($qrCode->public_token, $tableSession->id);
    });

    expect($queryCount)->toBe($expectedQueries)
        ->and($result?->id)->toBe($isVisible ? $tableSession->id : null);

    if ($isVisible) {
        expect($result->branch_id)->toBe($tableSession->branch_id)
            ->and($result->service_point_id)->toBe($servicePoint->id)
            ->and($result->status)->toBe($status)
            ->and($result->metadata)->toBe($tableSession->metadata);
    }
})->with([
    'current table' => ['current', TableSessionStatus::Active, true, 3],
    'actively merged table' => ['merged', TableSessionStatus::Active, true, 3],
    'transferred table fallback' => ['transferred', TableSessionStatus::Active, true, 4],
    'unrelated table in same branch' => ['unrelated', TableSessionStatus::Active, false, 4],
    'unlinked table' => ['unlinked', TableSessionStatus::Active, false, 4],
    'foreign branch despite transfer metadata' => ['foreign', TableSessionStatus::Active, false, 4],
    'closed current table' => ['current', TableSessionStatus::Closed, false, 4],
    'closed transferred table' => ['transferred', TableSessionStatus::Closed, false, 4],
    'cancelled transferred table' => ['transferred', TableSessionStatus::Cancelled, false, 4],
]);

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

test('guest entry attempts are rate limited without storing the raw qr token in the limiter key', function () {
    $qrToken = str_repeat('R', 64);
    $clientAddress = '203.0.113.25';
    $action = app(EnsureGuestEntryRateLimitAction::class);

    foreach (range(1, 10) as $attempt) {
        $action->handle($qrToken, $clientAddress);
    }

    expect(fn () => $action->handle($qrToken, $clientAddress))
        ->toThrow(ValidationException::class);
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
