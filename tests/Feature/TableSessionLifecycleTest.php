<?php

use App\Actions\DraftOrders\SendDraftOrderToWaiterAction;
use App\Actions\TableSessions\CloseTableSessionAction;
use App\Actions\TableSessions\CreateGuestPendingTableSessionAction;
use App\Actions\TableSessions\LeaveTableSessionAction;
use App\Actions\TableSessions\OpenTableSessionForServicePointAction;
use App\Actions\TableSessions\RemoveTableSessionGuestAction;
use App\Actions\TableSessions\RequestBillForTableSessionAction;
use App\Actions\TableSessions\TransitionTableSessionStatusAction;
use App\Actions\Waiter\ConfirmDraftOrderByWaiterAction;
use App\Enums\DraftOrderStatus;
use App\Enums\OrderStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionJoinRequestStatus;
use App\Enums\TableSessionStatus;
use App\Enums\WaiterCallStatus;
use App\Livewire\PublicQr\GuestActions;
use App\Livewire\Waiter\TableDetail\Overview;
use App\Livewire\Waiter\TableSessionHistory;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;
use App\Models\User;
use App\Models\WaiterCall;
use App\Services\PublicQr\ActiveGuestAccessService;
use App\Services\PublicQr\GuestEntryQueryService;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('closed session and another table in the same branch cannot restore a guest identity', function () {
    [$organization, $branch, $servicePoint, $tableSession, $guest, $qrCode] = createLifecycleContext();
    $otherServicePoint = ServicePoint::factory()->for($branch)->create([
        'name' => 'Other table',
        'status' => ServicePointStatus::Occupied,
    ]);
    $otherQrCode = QrCode::factory()->for($otherServicePoint)->create([
        'public_token' => fake()->unique()->regexify('[A-Za-z0-9]{64}'),
        'short_code' => fake()->unique()->regexify('[A-Z0-9]{8}'),
        'status' => QrCodeStatus::Active,
    ]);

    $guestEntryQueries = app(GuestEntryQueryService::class);

    expect($guestEntryQueries->guestByToken($otherServicePoint, $guest->guest_token))->toBeNull();

    $tableSession->forceFill([
        'status' => TableSessionStatus::Closed,
        'ended_at' => now(),
    ])->save();

    request()->cookies->set(lifecycleGuestCookieName($qrCode), $guest->guest_token);

    expect(app(ActiveGuestAccessService::class)->findAuthorizedGuest(
        $qrCode->public_token,
        $tableSession->id,
        $guest->id,
    ))->toBeNull()
        ->and($guestEntryQueries->guestByToken($servicePoint, $guest->guest_token))->toBeNull()
        ->and($otherQrCode->service_point_id)->toBe($otherServicePoint->id)
        ->and($organization->id)->toBe($branch->organization_id);
});

test('guest can leave once and pending join requests expire when no approver remains', function () {
    [, , , $tableSession, $guest] = createLifecycleContext();
    $pendingRequest = TableSessionJoinRequest::factory()
        ->forTableSession($tableSession)
        ->pending()
        ->create();
    $draftOrder = DraftOrder::factory()->for($tableSession)->create([
        'status' => DraftOrderStatus::Draft,
    ]);
    $draftItem = DraftOrderItem::factory()->for($draftOrder)->for($guest, 'guest')->create();

    $leftGuest = app(LeaveTableSessionAction::class)->handle($guest, $guest->guest_token);
    $replayedGuest = app(LeaveTableSessionAction::class)->handle($guest, $guest->guest_token);

    expect($leftGuest->status)->toBe(TableSessionGuestStatus::Left)
        ->and($leftGuest->left_at)->not->toBeNull()
        ->and($replayedGuest->status)->toBe(TableSessionGuestStatus::Left)
        ->and($pendingRequest->fresh()->status)->toBe(TableSessionJoinRequestStatus::Expired)
        ->and($draftItem->fresh())->not->toBeNull()
        ->and($tableSession->fresh()->status)->toBe(TableSessionStatus::Active);
});

test('a departed table cannot be reclaimed until staff close and reopen it', function () {
    [$organization, , $servicePoint, $tableSession, $guest] = createLifecycleContext();
    $waiter = attachLifecycleStaff($organization, SystemRole::Waiter);
    $originalOpenerId = $guest->id;
    $tableSession->forceFill(['opened_by_guest_id' => $originalOpenerId])->save();
    app(LeaveTableSessionAction::class)->handle($guest, $guest->guest_token);

    $blockedResult = app(CreateGuestPendingTableSessionAction::class)->handle(
        $servicePoint,
        'Premature replacement',
        str_repeat('R', 64),
        'en',
    );

    $closedSession = app(CloseTableSessionAction::class)->handle($tableSession, $waiter);
    $newSession = app(OpenTableSessionForServicePointAction::class)->handle($servicePoint->fresh(), $waiter);
    $joinedResult = app(CreateGuestPendingTableSessionAction::class)->handle(
        $servicePoint->fresh(),
        'Replacement guest',
        str_repeat('N', 64),
        'en',
    );

    expect($blockedResult['state']->value)->toBe('active_session_exists')
        ->and($blockedResult['table_session']?->id)->toBe($tableSession->id)
        ->and($blockedResult['guest'])->toBeNull()
        ->and($blockedResult['join_request'])->toBeNull()
        ->and($closedSession->status)->toBe(TableSessionStatus::Closed)
        ->and($newSession->id)->not->toBe($tableSession->id)
        ->and($joinedResult['table_session']?->id)->toBe($newSession->id)
        ->and($joinedResult['guest']?->status)->toBe(TableSessionGuestStatus::Active)
        ->and($tableSession->fresh()->opened_by_guest_id)->toBe($originalOpenerId)
        ->and($tableSession->guests()->count())->toBe(1);
});

test('waiter director and restaurant admin can remove an active participant', function (SystemRole $systemRole) {
    [$organization, , , $tableSession, $guest] = createLifecycleContext();
    $staff = attachLifecycleStaff($organization, $systemRole);

    $removedGuest = app(RemoveTableSessionGuestAction::class)->handle($tableSession, $guest, $staff);
    $replayedGuest = app(RemoveTableSessionGuestAction::class)->handle($tableSession, $guest, $staff);

    expect($removedGuest->status)->toBe(TableSessionGuestStatus::Removed)
        ->and($removedGuest->left_at)->not->toBeNull()
        ->and($replayedGuest->status)->toBe(TableSessionGuestStatus::Removed);
})->with([
    'waiter' => SystemRole::Waiter,
    'director' => SystemRole::Director,
    'restaurant admin' => SystemRole::RestaurantAdmin,
]);

test('participant removal is tenant safe and closed sessions reject mutations', function () {
    [$organization, , , $tableSession, $guest] = createLifecycleContext();
    $otherOrganization = Organization::factory()->create();
    $foreignAdmin = attachLifecycleStaff($otherOrganization, SystemRole::RestaurantAdmin);

    expect(fn () => app(RemoveTableSessionGuestAction::class)->handle($tableSession, $guest, $foreignAdmin))
        ->toThrow(ValidationException::class);

    $tableSession->forceFill([
        'status' => TableSessionStatus::Closed,
        'ended_at' => now(),
    ])->save();

    $waiter = attachLifecycleStaff($organization, SystemRole::Waiter);

    expect(fn () => app(RemoveTableSessionGuestAction::class)->handle($tableSession, $guest, $waiter))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(LeaveTableSessionAction::class)->handle($guest, $guest->guest_token))
        ->toThrow(ValidationException::class);
});

test('requesting a bill requires all drafts submitted and all orders served', function () {
    [, , , $tableSession, $guest] = createLifecycleContext();
    $draftOrder = DraftOrder::factory()->for($tableSession)->create([
        'status' => DraftOrderStatus::Draft,
    ]);
    DraftOrderItem::factory()->for($draftOrder)->for($guest, 'guest')->create();

    expect(fn () => app(RequestBillForTableSessionAction::class)->handle($tableSession, $guest))
        ->toThrow(ValidationException::class);

    $draftOrder->forceFill(['status' => DraftOrderStatus::ConvertedToOrder])->save();
    Order::factory()->for($tableSession)->for($draftOrder, 'draftOrder')->create([
        'status' => OrderStatus::InProgress,
    ]);

    expect(fn () => app(RequestBillForTableSessionAction::class)->handle($tableSession, $guest))
        ->toThrow(ValidationException::class)
        ->and($tableSession->fresh()->status)->toBe(TableSessionStatus::Active);
});

test('first order moves the table through staff confirmation without allowing a duplicate session', function (SystemRole $staffRole) {
    [$organization, , $servicePoint, $tableSession, $guest] = createLifecycleContext();
    $staff = attachLifecycleStaff($organization, $staffRole);
    $draftOrder = DraftOrder::factory()->for($tableSession)->create([
        'status' => DraftOrderStatus::Draft,
    ]);
    DraftOrderItem::factory()->for($draftOrder)->for($guest, 'guest')->create([
        'menu_item_id' => null,
        'menu_item_variant_id' => null,
        'item_name' => 'Lifecycle item',
    ]);

    app(SendDraftOrderToWaiterAction::class)->handle($draftOrder, $guest);

    $blockedEntry = app(CreateGuestPendingTableSessionAction::class)->handle(
        $servicePoint,
        'Late guest',
        str_repeat('L', 64),
        'en',
    );

    expect($tableSession->fresh()->status)->toBe(TableSessionStatus::WaitingWaiterConfirmation)
        ->and($blockedEntry['table_session']?->id)->toBe($tableSession->id)
        ->and($blockedEntry['guest'])->toBeNull()
        ->and($blockedEntry['join_request'])->toBeNull()
        ->and(app(OpenTableSessionForServicePointAction::class)->handle($servicePoint->fresh(), $staff)->id)
        ->toBe($tableSession->id)
        ->and(TableSession::query()->where('service_point_id', $servicePoint->id)->count())->toBe(1);

    $order = app(ConfirmDraftOrderByWaiterAction::class)->handle($draftOrder->fresh(), $staff);

    expect($tableSession->fresh()->status)->toBe(TableSessionStatus::Active)
        ->and($order->status)->toBe(OrderStatus::SentToKitchenBar);
})->with([
    'waiter' => SystemRole::Waiter,
    'director' => SystemRole::Director,
    'restaurant admin' => SystemRole::RestaurantAdmin,
]);

test('table session state machine rejects backwards and terminal transitions', function () {
    [, , , $tableSession] = createLifecycleContext();
    $transition = app(TransitionTableSessionStatusAction::class);

    $transition->handle($tableSession, TableSessionStatus::WaitingWaiterConfirmation);

    expect($tableSession->fresh()->status)->toBe(TableSessionStatus::WaitingWaiterConfirmation)
        ->and(TableSessionStatus::WaitingWaiterConfirmation->allowsGuestParticipation())->toBeFalse()
        ->and(TableSessionStatus::WaitingWaiterConfirmation->allowsGuestViewing())->toBeTrue()
        ->and(TableSessionStatus::PaymentRequested->locksOrderChanges())->toBeTrue()
        ->and(TableSessionStatus::Paid->occupiesServicePoint())->toBeFalse();

    $transition->handle($tableSession->fresh(), TableSessionStatus::Active);
    $transition->handle($tableSession->fresh(), TableSessionStatus::PaymentRequested);

    expect(fn () => $transition->handle($tableSession->fresh(), TableSessionStatus::Active))
        ->toThrow(ValidationException::class);

    $transition->handle($tableSession->fresh(), TableSessionStatus::Paid);
    $transition->handle($tableSession->fresh(), TableSessionStatus::Closed);

    expect(fn () => $transition->handle($tableSession->fresh(), TableSessionStatus::Active))
        ->toThrow(ValidationException::class)
        ->and(fn () => $transition->handle($tableSession, TableSessionStatus::Active))
        ->toThrow(ValidationException::class)
        ->and($tableSession->fresh()->status)->toBe(TableSessionStatus::Closed);
});

test('authorized staff close a completed session cleanly and reopen the table', function (SystemRole $staffRole) {
    [$organization, , $servicePoint, $tableSession, $guest] = createLifecycleContext();
    $staff = attachLifecycleStaff($organization, $staffRole);
    $pendingRequest = TableSessionJoinRequest::factory()->forTableSession($tableSession)->pending()->create();
    $waiterCall = WaiterCall::factory()
        ->forServicePoint($servicePoint)
        ->forTableSession($tableSession)
        ->pending()
        ->create(['requested_by_guest_id' => $guest->id]);
    $draftOrder = DraftOrder::factory()->for($tableSession)->create([
        'status' => DraftOrderStatus::ConvertedToOrder,
    ]);
    $order = Order::factory()
        ->for($tableSession)
        ->for($draftOrder, 'draftOrder')
        ->create(['status' => OrderStatus::Served]);
    $orderItem = OrderItem::factory()->for($order)->for($guest, 'guest')->create();
    $tableSession->forceFill([
        'guest_invite_token_hash' => hash('sha256', 'temporary-invite'),
        'guest_invite_created_at' => now(),
        'guest_invite_expires_at' => now()->addHour(),
        'guest_invite_created_by_guest_id' => $guest->id,
    ])->save();

    $closedSession = app(CloseTableSessionAction::class)->handle($tableSession, $staff);
    $newSession = app(OpenTableSessionForServicePointAction::class)->handle($servicePoint->fresh(), $staff);

    expect($closedSession->status)->toBe(TableSessionStatus::Closed)
        ->and($guest->fresh()->status)->toBe(TableSessionGuestStatus::Left)
        ->and($pendingRequest->fresh()->status)->toBe(TableSessionJoinRequestStatus::Expired)
        ->and($waiterCall->fresh()->status)->toBe(WaiterCallStatus::Handled)
        ->and($closedSession->guest_invite_token_hash)->toBeNull()
        ->and($order->fresh()->status)->toBe(OrderStatus::Closed)
        ->and($orderItem->fresh())->not->toBeNull()
        ->and($newSession->id)->not->toBe($closedSession->id)
        ->and($newSession->status)->toBe(TableSessionStatus::Active);
})->with([
    'waiter' => SystemRole::Waiter,
    'director' => SystemRole::Director,
    'restaurant admin' => SystemRole::RestaurantAdmin,
]);

test('guest leave Livewire action invalidates browser identity and redirects to the permanent qr', function () {
    [, , , $tableSession, $guest, $qrCode] = createLifecycleContext();

    Livewire::withCookie(lifecycleGuestCookieName($qrCode), $guest->guest_token)
        ->test(GuestActions::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $guest->id,
            'publicToken' => $qrCode->public_token,
            'language' => 'en',
        ])
        ->call('leaveTable')
        ->assertRedirect(route('public.qr.show', ['token' => $qrCode->public_token]));

    expect($guest->fresh()->status)->toBe(TableSessionGuestStatus::Left)
        ->and(session('guest_entries.'.$qrCode->public_token))->toBeNull();
});

test('waiter removes a participant through the Livewire table overview', function () {
    [$organization, , , $tableSession, $guest] = createLifecycleContext();
    $waiter = attachLifecycleStaff($organization, SystemRole::Waiter);

    Livewire::actingAs($waiter)
        ->test(Overview::class, ['tableSessionId' => $tableSession->id])
        ->assertSet('overview.participants.guests.0.id', $guest->id)
        ->call('removeGuest', $guest->id)
        ->assertSet('overview.participants.guests.0.can_remove', false)
        ->assertSee(__('ui.waiter.table_detail.guest_removed'));

    expect($guest->fresh()->status)->toBe(TableSessionGuestStatus::Removed);
});

test('waiter history lists only closed sessions from authorized tenants', function () {
    [$organization, , , $tableSession, $guest] = createLifecycleContext();
    $waiter = attachLifecycleStaff($organization, SystemRole::Waiter);
    $tableSession->forceFill([
        'status' => TableSessionStatus::Closed,
        'ended_at' => now(),
    ])->save();
    $guest->forceFill([
        'status' => TableSessionGuestStatus::Left,
        'left_at' => now(),
    ])->save();

    [, , , $foreignSession] = createLifecycleContext();
    $foreignSession->forceFill([
        'status' => TableSessionStatus::Closed,
        'ended_at' => now()->addSecond(),
    ])->save();

    Livewire::actingAs($waiter)
        ->test(TableSessionHistory::class)
        ->assertSet('sessions.0.id', $tableSession->id)
        ->assertSee($tableSession->servicePoint->name)
        ->assertDontSee($foreignSession->servicePoint->name);
});

function createLifecycleContext(): array
{
    $organization = Organization::factory()->create(['name' => fake()->unique()->company()]);
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $servicePoint = ServicePoint::factory()->for($branch)->create([
        'status' => ServicePointStatus::Occupied,
        'is_active' => true,
    ]);
    $qrCode = QrCode::factory()->for($servicePoint)->create([
        'public_token' => fake()->unique()->regexify('[A-Za-z0-9]{64}'),
        'short_code' => fake()->unique()->regexify('[A-Z0-9]{8}'),
        'status' => QrCodeStatus::Active,
    ]);
    $tableSession = TableSession::factory()->forServicePoint($servicePoint)->active()->waiterOpened()->create();
    $guest = TableSessionGuest::factory()->for($tableSession)->active()->create([
        'guest_name' => 'Lifecycle guest',
    ]);

    return [$organization, $branch, $servicePoint, $tableSession, $guest, $qrCode];
}

function attachLifecycleStaff(Organization $organization, SystemRole $systemRole): User
{
    $user = User::factory()->create();
    $role = Role::query()->where('code', $systemRole->value)->firstOrFail();

    $organization->users()->syncWithoutDetachingOrFail([
        $user->id => [
            'role_id' => $role->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ],
    ]);

    return $user;
}

function lifecycleGuestCookieName(QrCode $qrCode): string
{
    return 'guest_token_'.substr(hash('sha256', $qrCode->public_token), 0, 24);
}
