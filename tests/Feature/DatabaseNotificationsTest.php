<?php

use App\Actions\Departments\UpdateDepartmentTicketItemStatusAction;
use App\Actions\DraftOrders\SendDraftOrderToWaiterAction;
use App\Actions\Notifications\MarkGuestNotificationsReadAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\TableSessions\CreateTableSessionJoinRequestAction;
use App\Actions\Waiter\ConfirmDraftOrderByWaiterAction;
use App\Actions\Waiter\RejectDraftOrderByWaiterAction;
use App\Enums\DraftOrderStatus;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\KitchenTicketStatus;
use App\Enums\OrderStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Livewire\Notifications\UnreadCount;
use App\Livewire\PublicQr\Notifications as GuestNotifications;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use App\Models\WaiterCall;
use App\Notifications\BillRequestedNotification;
use App\Notifications\KitchenItemReadyNotification;
use App\Notifications\WaiterCalledNotification;
use App\Services\Notifications\UserNotificationQueryService;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('new join request creates unread database notifications for active guests', function () {
    [, , , $tableSession] = createPrompt81NotificationContext();
    $ana = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Ana',
            'status' => TableSessionGuestStatus::Active,
        ]);
    $boris = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Boris',
            'status' => TableSessionGuestStatus::Active,
        ]);

    $guestCredential = str_repeat('M', 64);
    $joinRequest = app(CreateTableSessionJoinRequestAction::class)->handle($tableSession, '  Mira  ', $guestCredential);

    expect($joinRequest)->not->toBeNull()
        ->and($ana->unreadNotifications()->where('type', 'join_request_created')->count())->toBe(1)
        ->and($boris->unreadNotifications()->where('type', 'join_request_created')->count())->toBe(1)
        ->and(data_get($ana->unreadNotifications()->firstOrFail()->data, 'guest_name'))->toBe('Mira')
        ->and((int) data_get($ana->unreadNotifications()->firstOrFail()->data, 'join_request_id'))->toBe($joinRequest->id);

    $replayedJoinRequest = app(CreateTableSessionJoinRequestAction::class)
        ->handle($tableSession, 'Mira', $guestCredential);

    expect($replayedJoinRequest?->id)->toBe($joinRequest->id)
        ->and($ana->unreadNotifications()->where('type', 'join_request_created')->count())->toBe(1)
        ->and($boris->unreadNotifications()->where('type', 'join_request_created')->count())->toBe(1);

    Livewire::withCookie(prompt82GuestCookieName('prompt82token'), $ana->guest_token)
        ->test(GuestNotifications::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $ana->id,
            'publicToken' => 'prompt82token',
        ])
        ->assertSet('unreadCount', 1)
        ->assertSee(__('ui.livewire.publicqr.notifications.novyi_gost_zdet_podtverzdeniia_7813e12a'))
        ->assertSee('Mira');
});

test('guest notification read action marks only an allowed unread notification', function (): void {
    [, $branch, $servicePoint, $tableSession, $guest] = createPrompt81NotificationContext();
    $ticketItem = createPrompt81KitchenTicketItem($branch, $servicePoint, $tableSession, $guest);
    $guest->notify(new KitchenItemReadyNotification($ticketItem));
    $notification = $guest->unreadNotifications()
        ->where('type', 'kitchen_item_ready')
        ->firstOrFail();
    $action = app(MarkGuestNotificationsReadAction::class);

    expect($action->one($guest, $notification->id, ['draft_order_confirmed']))->toBeFalse()
        ->and($action->one($guest, $notification->id, ['kitchen_item_ready']))->toBeTrue()
        ->and($action->one($guest, $notification->id, ['kitchen_item_ready']))->toBeFalse()
        ->and($notification->fresh()->read_at)->not->toBeNull();
});

test('guest notification ui can mark one notification as read', function (): void {
    [, $branch, $servicePoint, $tableSession, $guest] = createPrompt81NotificationContext();
    $ticketItem = createPrompt81KitchenTicketItem($branch, $servicePoint, $tableSession, $guest);
    $guest->notify(new KitchenItemReadyNotification($ticketItem));
    $notification = $guest->unreadNotifications()
        ->where('type', 'kitchen_item_ready')
        ->firstOrFail();

    Livewire::withCookie(prompt82GuestCookieName('prompt82token'), $guest->guest_token)
        ->test(GuestNotifications::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $guest->id,
            'publicToken' => 'prompt82token',
        ])
        ->assertSet('unreadCount', 1)
        ->call('markNotificationRead', $notification->id)
        ->assertSet('unreadCount', 0)
        ->assertSet('notifications', []);

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('sent draft creates unread database notification for waiter and unread count polls it', function () {
    [$organization, , , $tableSession, $guest] = createPrompt81NotificationContext();
    $waiter = User::factory()->create(['name' => 'Prompt 81 Waiter']);
    attachPrompt81Staff($waiter, $organization, SystemRole::Waiter, [SystemPermission::ViewOrders]);
    $draftOrder = createPrompt81DraftOrder($tableSession, $guest);

    app(SendDraftOrderToWaiterAction::class)->handle($draftOrder, $guest);

    $notification = $waiter->unreadNotifications()
        ->where('type', 'draft_order_sent_to_waiter')
        ->firstOrFail();

    expect((int) data_get($notification->data, 'draft_order_id'))->toBe($draftOrder->id)
        ->and(data_get($notification->data, 'sent_by_guest_name'))->toBe('Ana');

    Livewire::actingAs($waiter)
        ->test(UnreadCount::class)
        ->assertSet('unreadCount', 1)
        ->assertSee(__('ui.notifications.unread_count.notifications'))
        ->assertDontSee(__('ui.livewire.notifications.unreadcount.novyi_zakaz'))
        ->call('openPanel')
        ->assertSee(__('ui.livewire.notifications.unreadcount.novyi_zakaz'))
        ->call('markAllRead')
        ->assertSet('unreadCount', 0);

    expect($waiter->unreadNotifications()->count())->toBe(0);
});

test('staff notification ui lists waiter events and can mark one notification read', function () {
    [$organization, $branch, $servicePoint, $tableSession, $guest] = createPrompt81NotificationContext();
    $waiter = User::factory()->create(['name' => 'Prompt 82 Panel Waiter']);
    attachPrompt81Staff($waiter, $organization, SystemRole::Waiter, [SystemPermission::ViewOrders]);
    $waiterCall = WaiterCall::factory()
        ->forServicePoint($servicePoint)
        ->forTableSession($tableSession)
        ->create(['requested_by_guest_id' => $guest->id]);
    $ticketItem = createPrompt81KitchenTicketItem($branch, $servicePoint, $tableSession, $guest);

    $waiter->notify(new WaiterCalledNotification($waiterCall));
    $waiter->notify(new BillRequestedNotification($tableSession, $guest));
    $waiter->notify(new KitchenItemReadyNotification($ticketItem));

    $waiterCallNotificationId = $waiter->unreadNotifications()
        ->where('type', 'waiter_called')
        ->firstOrFail()
        ->id;

    Livewire::actingAs($waiter)
        ->test(UnreadCount::class)
        ->assertSet('unreadCount', 3)
        ->call('openPanel')
        ->assertSee(__('ui.livewire.notifications.unreadcount.vyzov_oficianta'))
        ->assertSee(__('ui.livewire.notifications.unreadcount.prosba_sceta'))
        ->assertSee(__('ui.livewire.notifications.unreadcount.poziciia_gotova_d55866f3'))
        ->call('markNotificationRead', $waiterCallNotificationId)
        ->assertSet('unreadCount', 2);

    expect($waiter->unreadNotifications()->where('type', 'waiter_called')->count())->toBe(0)
        ->and($waiter->readNotifications()->where('type', 'waiter_called')->count())->toBe(1);
});

test('notification bell opens a bounded panel without reading notifications', function (): void {
    [$organization, , $servicePoint, $session, $guest] = createPrompt81NotificationContext();
    $waiter = User::factory()->create();
    attachPrompt81Staff($waiter, $organization, SystemRole::Waiter, [SystemPermission::ViewOrders]);
    $call = WaiterCall::factory()->forServicePoint($servicePoint)->forTableSession($session)->create(['requested_by_guest_id' => $guest->id]);
    foreach (range(1, 23) as $index) {
        $waiter->notify(new WaiterCalledNotification($call));
    }

    $component = Livewire::actingAs($waiter)->test(UnreadCount::class)
        ->assertSet('unreadCount', 23)
        ->assertSet('panelOpen', false)
        ->assertDontSee('Ana')
        ->assertSee('x-data="notificationPanel"', false)
        ->assertSee('x-on:click="$el.focus(); loadPanel()"', false)
        ->call('openPanel')
        ->assertSet('panelOpen', true)
        ->assertSee('Ana');

    expect(substr_count($component->html(), 'data-notification-item='))->toBe(20)
        ->and($waiter->unreadNotifications()->count())->toBe(23)
        ->and($component->snapshot['data'])->not->toHaveKey('notifications');

    $component->set('panelOpen', false)->call('refreshUnreadCount')->assertDontSee('Ana');
});

test('notification history pages in both directions without growing the rendered list or marking read', function (): void {
    $this->freezeTime();
    [$organization, , $point, $session, $guest] = createPrompt81NotificationContext();
    $waiter = User::factory()->create();
    attachPrompt81Staff($waiter, $organization, SystemRole::Waiter, [SystemPermission::ViewOrders]);
    $call = WaiterCall::factory()->forServicePoint($point)->forTableSession($session)->create(['requested_by_guest_id' => $guest->id]);
    foreach (range(1, 45) as $index) {
        $waiter->notify(new WaiterCalledNotification($call));
    }
    $ids = $waiter->notifications()->reorder()->orderByDesc('created_at')->orderByDesc('id')->limit(45)->pluck('id')->all();
    $component = Livewire::actingAs($waiter)->test(UnreadCount::class)->call('openPanel')
        ->assertViewHas('notifications', fn (array $items): bool => array_column($items, 'id') === array_slice($ids, 0, 20))
        ->call('browseHistory', 'older')
        ->assertViewHas('notifications', fn (array $items): bool => array_column($items, 'id') === array_slice($ids, 20, 20));

    expect($component->get('history.current'))->toBeString()
        ->and($component->get('history.current'))->not->toContain($ids[19])
        ->and($component->snapshot['data'])->not->toHaveKey('notifications')
        ->and(substr_count($component->html(), 'data-notification-item='))->toBe(20);

    $component->call('browseHistory', 'older')
        ->assertViewHas('notifications', fn (array $items): bool => array_column($items, 'id') === array_slice($ids, 40))
        ->assertSet('history.older', null)
        ->call('browseHistory', 'older')
        ->assertViewHas('notifications', fn (array $items): bool => array_column($items, 'id') === array_slice($ids, 40))
        ->call('browseHistory', 'newer')
        ->assertViewHas('notifications', fn (array $items): bool => array_column($items, 'id') === array_slice($ids, 20, 20))
        ->call('browseHistory', 'newer')
        ->assertViewHas('notifications', fn (array $items): bool => array_column($items, 'id') === array_slice($ids, 0, 20))
        ->assertSet('history.newer', null)
        ->assertSet('unreadCount', 45);
    expect($waiter->unreadNotifications()->count())->toBe(45);
});

test('new notification arrivals update the count without shifting an older history page', function (): void {
    [$organization, , $point, $session, $guest] = createPrompt81NotificationContext();
    $waiter = User::factory()->create();
    attachPrompt81Staff($waiter, $organization, SystemRole::Waiter, [SystemPermission::ViewOrders]);
    $call = WaiterCall::factory()->forServicePoint($point)->forTableSession($session)->create(['requested_by_guest_id' => $guest->id]);
    foreach (range(1, 25) as $index) {
        $waiter->notify(new WaiterCalledNotification($call));
    }
    $ids = $waiter->notifications()->reorder()->orderByDesc('created_at')->orderByDesc('id')->limit(25)->pluck('id')->all();
    $component = Livewire::actingAs($waiter)->test(UnreadCount::class)->call('openPanel')->call('browseHistory', 'older');
    $this->travel(1)->seconds();
    $waiter->notify(new WaiterCalledNotification($call));
    $latest = $waiter->notifications()->reorder()->orderByDesc('created_at')->firstOrFail();

    $component->call('refreshUnreadCount')->assertSet('unreadCount', 26)
        ->assertViewHas('notifications', fn (array $items): bool => array_column($items, 'id') === array_slice($ids, 20))
        ->call('markNotificationRead', $ids[20])->assertSet('unreadCount', 25)
        ->assertViewHas('notifications', fn (array $items): bool => array_column($items, 'id') === array_slice($ids, 20))
        ->call('browseHistory', 'latest')->assertSet('history.current', null)
        ->assertViewHas('notifications', fn (array $items): bool => count($items) === 20 && $items[0]['id'] === $latest->id)
        ->call('browseHistory', 'older')
        ->update(calls: [['method' => 'refreshUnreadCount', 'params' => [], 'path' => '']], updates: ['panelOpen' => false])
        ->assertSet('history', [])->assertDontSee('Ana')
        ->call('openPanel')->assertSet('history.current', null)
        ->assertViewHas('notifications', fn (array $items): bool => $items[0]['id'] === $latest->id);
});

test('notification history recovers from removed pages and discards revoked audiences', function (): void {
    [$organization, , $point, $session, $guest] = createPrompt81NotificationContext();
    $waiter = User::factory()->create();
    attachPrompt81Staff($waiter, $organization, SystemRole::Waiter, [SystemPermission::ViewOrders]);
    $call = WaiterCall::factory()->forServicePoint($point)->forTableSession($session)->create(['requested_by_guest_id' => $guest->id]);
    foreach (range(1, 25) as $index) {
        $waiter->notify(new WaiterCalledNotification($call));
    }
    $ids = $waiter->notifications()->reorder()->orderByDesc('created_at')->orderByDesc('id')->limit(25)->pluck('id')->all();
    $component = Livewire::actingAs($waiter)->test(UnreadCount::class)->call('openPanel')->call('browseHistory', 'older');
    $waiter->notifications()->whereIn('id', array_slice($ids, 20))->delete();
    $component->call('refreshUnreadCount')->assertSet('history.current', null)
        ->assertViewHas('notifications', fn (array $items): bool => array_column($items, 'id') === array_slice($ids, 0, 20));

    foreach (range(1, 5) as $index) {
        $waiter->notify(new WaiterCalledNotification($call));
    }
    $component->call('browseHistory', 'latest')->call('browseHistory', 'older');
    $organization->users()->updateExistingPivot($waiter->id, ['status' => OrganizationUserStatus::Suspended->value]);
    $component->call('refreshUnreadCount')->assertSet('unreadCount', 0)
        ->assertSet('history.current', null)->assertSet('history.older', null)->assertSet('history.newer', null)->assertDontSee('Ana');
    expect($waiter->unreadNotifications()->count())->toBe(25);

    Auth::login(User::factory()->create());
    $component->call('browseHistory', 'latest')->assertStatus(409)->assertDontSee('Ana');
});

test('notification history rejects forged navigation state and remains unloaded when closed', function (): void {
    $waiter = User::factory()->create();
    foreach ([null, true, 42, [], ['older'], '1', 'previous'] as $direction) {
        Livewire::actingAs($waiter)->test(UnreadCount::class)->call('browseHistory', $direction)->assertStatus(422);
    }
    $component = Livewire::actingAs($waiter)->test(UnreadCount::class)
        ->call('browseHistory', 'older')->assertSet('panelOpen', false)->assertSet('history', []);
    expect(fn () => $component->set('history', ['current' => 'forged']))->toThrow(CannotUpdateLockedPropertyException::class);
});

test('notification reads recheck ownership permission and branch access without changing restaurant work', function (): void {
    [$organization, $branch, $point, $session, $guest] = createPrompt81NotificationContext();
    $waiter = User::factory()->create();
    attachPrompt81Staff($waiter, $organization, SystemRole::Waiter, [SystemPermission::ViewOrders]);
    $call = WaiterCall::factory()->forServicePoint($point)->forTableSession($session)->create(['requested_by_guest_id' => $guest->id]);
    $other = User::factory()->create();
    $waiter->notify(new WaiterCalledNotification($call));
    $other->notify(new WaiterCalledNotification($call));
    $own = $waiter->unreadNotifications()->firstOrFail();
    $foreign = $other->unreadNotifications()->firstOrFail();
    $originalStatus = $call->status;
    $component = Livewire::actingAs($waiter)->test(UnreadCount::class)->call('openPanel');
    $component->call('markNotificationRead', $foreign->id)->assertSet('unreadCount', 1);
    expect($foreign->fresh()->read_at)->toBeNull();
    $component->call('markNotificationRead', $own->id)->assertSet('unreadCount', 0)
        ->call('markNotificationRead', $own->id)->assertSet('unreadCount', 0);
    expect($own->fresh()->read_at)->not->toBeNull()
        ->and($call->fresh()->status)->toBe($originalStatus);

    $waiter->notify(new WaiterCalledNotification($call));
    $organization->users()->updateExistingPivot($waiter->id, ['status' => OrganizationUserStatus::Suspended->value]);
    $component->call('refreshUnreadCount')->assertSet('unreadCount', 0)->assertDontSee('Ana')
        ->call('markAllRead')->assertSet('unreadCount', 0);
    expect($waiter->unreadNotifications()->count())->toBe(1);
});

test('notification destinations are re-resolved and ignore arbitrary payload URLs', function (): void {
    [$organization, , $point, $session, $guest] = createPrompt81NotificationContext();
    $waiter = User::factory()->create();
    attachPrompt81Staff($waiter, $organization, SystemRole::Waiter, [SystemPermission::ViewOrders]);
    $call = WaiterCall::factory()->forServicePoint($point)->forTableSession($session)->create(['requested_by_guest_id' => $guest->id]);
    $waiter->notify(new WaiterCalledNotification($call));
    $notification = $waiter->unreadNotifications()->firstOrFail();
    $notification->update(['data' => [...$notification->data, 'url' => 'https://outside.invalid/steal']]);
    Livewire::actingAs($waiter)->test(UnreadCount::class)->call('openPanel')->assertDontSee('outside.invalid')
        ->call('openNotification', $notification->id)
        ->assertRedirect(route('restaurant.waiter.tables.show', $session));
    expect($notification->fresh()->read_at)->toBeNull();

    $organization->users()->updateExistingPivot($waiter->id, ['status' => OrganizationUserStatus::Suspended->value]);
    Livewire::actingAs($waiter)->test(UnreadCount::class)->call('openNotification', $notification->id)->assertNoRedirect();
});

test('notification panels reject stale account actions and clear their prior audience', function (): void {
    [$organization, , $point, $session, $guest] = createPrompt81NotificationContext();
    $waiter = User::factory()->create();
    $replacement = User::factory()->create();
    attachPrompt81Staff($waiter, $organization, SystemRole::Waiter, [SystemPermission::ViewOrders]);
    attachPrompt81Staff($replacement, $organization, SystemRole::Waiter, [SystemPermission::ViewOrders]);
    $call = WaiterCall::factory()->forServicePoint($point)->forTableSession($session)->create(['requested_by_guest_id' => $guest->id]);
    $waiter->notify(new WaiterCalledNotification($call));
    $replacement->notify(new WaiterCalledNotification($call));
    $component = Livewire::actingAs($waiter)->test(UnreadCount::class)->call('openPanel')->assertSee('Ana');
    Auth::login($replacement);
    $component->call('markAllRead')->assertSet('unreadCount', 0)->assertSet('panelOpen', false)->assertDontSee('Ana');
    expect($waiter->unreadNotifications()->count())->toBe(1)
        ->and($replacement->unreadNotifications()->count())->toBe(1);
});

test('notification scope respects revoked branch assignments and explicit permission denies', function (): void {
    [$organization, $branch, $point, $session, $guest] = createPrompt81NotificationContext();
    $waiter = User::factory()->create();
    $role = attachPrompt81Staff($waiter, $organization, SystemRole::Waiter, [SystemPermission::ViewOrders]);
    $assignment = BranchUser::factory()->for($organization)->for($branch)->for($waiter)->create(['role_id' => $role->id, 'status' => OrganizationUserStatus::Active]);
    $call = WaiterCall::factory()->forServicePoint($point)->forTableSession($session)->create(['requested_by_guest_id' => $guest->id]);
    $waiter->notify(new WaiterCalledNotification($call));
    $notification = $waiter->unreadNotifications()->firstOrFail();
    $component = Livewire::actingAs($waiter)->test(UnreadCount::class)->assertSet('unreadCount', 1)->call('openPanel');
    $assignment->forceFill(['status' => OrganizationUserStatus::Removed])->save();
    $component->call('markNotificationRead', $notification->id)->assertSet('unreadCount', 0)->assertDontSee('Ana');
    expect($notification->fresh()->read_at)->toBeNull();
    $assignment->forceFill(['status' => OrganizationUserStatus::Active])->save();
    $permission = Permission::query()->where('code', SystemPermission::ViewOrders->value)->firstOrFail();
    $role->permissions()->updateExistingPivot($permission->id, ['enabled' => false]);
    $component->call('markAllRead')->assertSet('unreadCount', 0)->assertDontSee('Ana');
    expect($notification->fresh()->read_at)->toBeNull();
});

test('closed notification polling hydrates no messages and open detail queries stay constant', function (): void {
    [$organization, , $point, $session, $guest] = createPrompt81NotificationContext();
    $waiter = User::factory()->create();
    attachPrompt81Staff($waiter, $organization, SystemRole::Waiter, [SystemPermission::ViewOrders]);
    $call = WaiterCall::factory()->forServicePoint($point)->forTableSession($session)->create(['requested_by_guest_id' => $guest->id]);
    foreach (range(1, 25) as $index) {
        $waiter->notify(new WaiterCalledNotification($call));
    }
    $queries = app(UserNotificationQueryService::class);
    $closedQueries = countDatabaseQueries(function () use ($queries, $waiter): void {
        $snapshot = $queries->snapshot($waiter, false);
        expect($snapshot['count'])->toBe(25)->and($snapshot['notifications'])->toHaveCount(0);
    });
    $openQueries = countDatabaseQueries(function () use ($queries, $waiter): void {
        $snapshot = $queries->snapshot($waiter, true);
        expect($snapshot['notifications'])->toHaveCount(20)->and($snapshot['destinations'])->toHaveCount(20);
    });
    $firstPage = $queries->snapshot($waiter, true);
    $cursor = $firstPage['older_cursor'];
    request()->query->set('cursor', $cursor?->encode());
    expect($queries->snapshot($waiter, true)['notifications']->modelKeys())->toBe($firstPage['notifications']->modelKeys());
    $olderQueries = countDatabaseQueries(function () use ($queries, $waiter, $cursor): void {
        $snapshot = $queries->snapshot($waiter, true, $cursor);
        expect($snapshot['notifications'])->toHaveCount(5)->and($snapshot['destinations'])->toHaveCount(5);
    });
    expect($openQueries)->toBe($closedQueries + 2)->and($olderQueries)->toBe($openQueries);
});

test('notification destinations disappear when their table context is missing or mismatched', function (): void {
    [$organization, , $point, $session, $guest] = createPrompt81NotificationContext();
    $waiter = User::factory()->create();
    attachPrompt81Staff($waiter, $organization, SystemRole::Waiter, [SystemPermission::ViewOrders]);
    $call = WaiterCall::factory()->forServicePoint($point)->forTableSession($session)->create(['requested_by_guest_id' => $guest->id]);
    $waiter->notify(new WaiterCalledNotification($call));
    $notification = $waiter->unreadNotifications()->firstOrFail();
    foreach ([null, '1', 999999] as $missingPoint) {
        $notification->update(['data' => [...$notification->data, 'service_point_id' => $missingPoint]]);
        Livewire::actingAs($waiter)->test(UnreadCount::class)->call('openPanel')->assertDontSee(__('notifications.panel.open_table'))
            ->call('openNotification', $notification->id)->assertNoRedirect()->assertSet('destinationUnavailable', true);
    }
    expect($notification->fresh()->read_at)->toBeNull();
});

test('notification actions reject malformed browser identifiers', function (): void {
    $user = User::factory()->create();
    foreach ([null, true, 42, [], ['id' => 'forged'], 'not-a-uuid'] as $identifier) {
        foreach (['markNotificationRead', 'openNotification'] as $action) {
            Livewire::actingAs($user)->test(UnreadCount::class)->call($action, $identifier)->assertStatus(422);
        }
    }
});

test('stable closed notification polls update state without retransmitting the panel markup', function (): void {
    [$organization, , $point, $session, $guest] = createPrompt81NotificationContext();
    $waiter = User::factory()->create();
    attachPrompt81Staff($waiter, $organization, SystemRole::Waiter, [SystemPermission::ViewOrders]);
    $call = WaiterCall::factory()->forServicePoint($point)->forTableSession($session)->create(['requested_by_guest_id' => $guest->id]);
    $component = Livewire::actingAs($waiter)->test(UnreadCount::class)->assertSee('data-notification-trigger', false);
    $waiter->notify(new WaiterCalledNotification($call));
    $component->call('refreshUnreadCount')->assertSet('unreadCount', 1);
    expect($component->effects)->not->toHaveKey('html');
    $component->call('openPanel')->assertSee('Ana')->assertSet('detailsRendered', true);
    $component->update(calls: [['method' => 'refreshUnreadCount', 'params' => [], 'path' => '']], updates: ['panelOpen' => false])
        ->assertDontSee('Ana')->assertSet('detailsRendered', false);
    expect($component->effects)->toHaveKey('html');
    $component->call('refreshUnreadCount');
    expect($component->effects)->not->toHaveKey('html');
    $component->call('openPanel')->assertSee('Ana')->assertSet('detailsRendered', true);
});

test('notification fallback messages follow the current interface locale rather than the delivery locale', function (): void {
    [$organization, , $point, $session] = createPrompt81NotificationContext();
    $waiter = User::factory()->create();
    attachPrompt81Staff($waiter, $organization, SystemRole::Waiter, [SystemPermission::ViewOrders]);
    $call = WaiterCall::factory()->forServicePoint($point)->forTableSession($session)->create(['requested_by_guest_id' => null]);
    $waiter->notify(new WaiterCalledNotification($call));
    $notification = $waiter->unreadNotifications()->firstOrFail();
    $notification->update(['data' => [...$notification->data, 'message' => 'Old delivery language']]);
    foreach (['en', 'lt', 'ru'] as $locale) {
        $waiter->forceFill(['locale' => $locale])->save();
        app()->setLocale($locale);
        Livewire::actingAs($waiter)->test(UnreadCount::class)->call('openPanel')
            ->assertSee(__('ui.livewire.notifications.unreadcount.gost_zovet_oficianta'))->assertDontSee('Old delivery language');
    }
});

test('kitchen ready creates one unread database notification for waiter recipients', function () {
    [$organization, $branch, $servicePoint, $tableSession, $guest] = createPrompt81NotificationContext();
    $waiter = User::factory()->create(['name' => 'Prompt 81 Ready Waiter']);
    $cook = User::factory()->create(['name' => 'Prompt 81 Cook']);
    attachPrompt81Staff($waiter, $organization, SystemRole::Waiter, [SystemPermission::ViewOrders]);
    attachPrompt81Staff($cook, $organization, SystemRole::Cook);
    $ticketItem = createPrompt81KitchenTicketItem($branch, $servicePoint, $tableSession, $guest);

    app(UpdateDepartmentTicketItemStatusAction::class)->handle(
        itemId: $ticketItem->id,
        status: KitchenTicketItemStatus::Ready,
        user: $cook,
        departmentTypes: [],
        roleCodes: [SystemRole::HeadChef, SystemRole::Cook],
        permissionCodes: [SystemPermission::ViewKitchen],
    );

    expect($waiter->unreadNotifications()->where('type', 'kitchen_item_ready')->count())->toBe(1)
        ->and($guest->unreadNotifications()->where('type', 'kitchen_item_ready')->count())->toBe(1)
        ->and(data_get($waiter->unreadNotifications()->firstOrFail()->data, 'item_name'))->toBe('Prompt 81 Soup');

    app(UpdateDepartmentTicketItemStatusAction::class)->handle(
        itemId: $ticketItem->id,
        status: KitchenTicketItemStatus::Ready,
        user: $cook,
        departmentTypes: [],
        roleCodes: [SystemRole::HeadChef, SystemRole::Cook],
        permissionCodes: [SystemPermission::ViewKitchen],
    );

    expect($waiter->unreadNotifications()->where('type', 'kitchen_item_ready')->count())->toBe(1)
        ->and($guest->unreadNotifications()->where('type', 'kitchen_item_ready')->count())->toBe(1);
});

test('kitchen in progress creates guest notification and guest notification ui shows cooking and ready states', function () {
    [$organization, $branch, $servicePoint, $tableSession, $guest] = createPrompt81NotificationContext();
    $cook = User::factory()->create(['name' => 'Prompt 82 Cook']);
    attachPrompt81Staff($cook, $organization, SystemRole::Cook);
    $ticketItem = createPrompt81KitchenTicketItem($branch, $servicePoint, $tableSession, $guest);

    app(UpdateDepartmentTicketItemStatusAction::class)->handle(
        itemId: $ticketItem->id,
        status: KitchenTicketItemStatus::InProgress,
        user: $cook,
        departmentTypes: [],
        roleCodes: [SystemRole::HeadChef, SystemRole::Cook],
        permissionCodes: [SystemPermission::ViewKitchen],
    );

    expect($guest->unreadNotifications()->where('type', 'kitchen_item_cooking')->count())->toBe(1);

    Livewire::withCookie(prompt82GuestCookieName('prompt82token'), $guest->guest_token)
        ->test(GuestNotifications::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $guest->id,
            'publicToken' => 'prompt82token',
        ])
        ->assertSet('unreadCount', 1)
        ->assertSee(__('ui.livewire.publicqr.notifications.poziciia_gotovitsia_c07e0e57'))
        ->assertSee('Prompt 81 Soup');

    app(UpdateDepartmentTicketItemStatusAction::class)->handle(
        itemId: $ticketItem->id,
        status: KitchenTicketItemStatus::Ready,
        user: $cook,
        departmentTypes: [],
        roleCodes: [SystemRole::HeadChef, SystemRole::Cook],
        permissionCodes: [SystemPermission::ViewKitchen],
    );

    Livewire::withCookie(prompt82GuestCookieName('prompt82token'), $guest->guest_token)
        ->test(GuestNotifications::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $guest->id,
            'publicToken' => 'prompt82token',
        ])
        ->assertSet('unreadCount', 2)
        ->assertSee(__('ui.livewire.notifications.unreadcount.poziciia_gotova_d55866f3'))
        ->call('markAllRead')
        ->assertSet('unreadCount', 0);
});

test('rejected draft creates unread database notifications for active guests', function () {
    [$organization, , , $tableSession, $guest] = createPrompt81NotificationContext();
    $zara = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Zara',
            'status' => TableSessionGuestStatus::Active,
        ]);
    $waiter = User::factory()->create(['name' => 'Prompt 81 Reject Waiter']);
    attachPrompt81Staff($waiter, $organization, SystemRole::Waiter, [SystemPermission::ViewOrders, SystemPermission::ConfirmOrders]);
    $draftOrder = createPrompt81DraftOrder($tableSession, $guest);
    app(SendDraftOrderToWaiterAction::class)->handle($draftOrder, $guest);

    app(RejectDraftOrderByWaiterAction::class)->handle($draftOrder->fresh(), $waiter, 'Please remove the soup.');

    expect($guest->unreadNotifications()->where('type', 'draft_order_rejected')->count())->toBe(1)
        ->and($zara->unreadNotifications()->where('type', 'draft_order_rejected')->count())->toBe(1)
        ->and(data_get($guest->unreadNotifications()->where('type', 'draft_order_rejected')->firstOrFail()->data, 'rejection_reason'))->toBe('Please remove the soup.');

    Livewire::withCookie(prompt82GuestCookieName('prompt82token'), $guest->guest_token)
        ->test(GuestNotifications::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $guest->id,
            'publicToken' => 'prompt82token',
        ])
        ->assertSet('unreadCount', 1)
        ->assertSee(__('ui.livewire.publicqr.notifications.zakaz_otklonen'))
        ->assertSee('Please remove the soup.');
});

test('confirmed draft creates unread database notifications for active guests', function () {
    [$organization, , , $tableSession, $guest] = createPrompt81NotificationContext();
    $zara = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Zara',
            'status' => TableSessionGuestStatus::Active,
        ]);
    $waiter = User::factory()->create(['name' => 'Prompt 82 Confirm Waiter']);
    attachPrompt81Staff($waiter, $organization, SystemRole::Waiter, [SystemPermission::ViewOrders, SystemPermission::ConfirmOrders]);
    $draftOrder = createPrompt81DraftOrder($tableSession, $guest);
    app(SendDraftOrderToWaiterAction::class)->handle($draftOrder, $guest);

    $order = app(ConfirmDraftOrderByWaiterAction::class)->handle($draftOrder->fresh(), $waiter);

    expect($order->draft_order_id)->toBe($draftOrder->id)
        ->and($guest->unreadNotifications()->where('type', 'draft_order_confirmed')->count())->toBe(1)
        ->and($zara->unreadNotifications()->where('type', 'draft_order_confirmed')->count())->toBe(1);

    Livewire::withCookie(prompt82GuestCookieName('prompt82token'), $guest->guest_token)
        ->test(GuestNotifications::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $guest->id,
            'publicToken' => 'prompt82token',
        ])
        ->assertSet('unreadCount', 1)
        ->assertSee(__('ui.livewire.publicqr.notifications.zakaz_podtverzden'))
        ->assertSee(__('ui.livewire.publicqr.notifications.oficiant_podtverdil_zakaz'));
});

function createPrompt81NotificationContext(): array
{
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Prompt 81 Group']);
    $brand = Brand::factory()
        ->for($organization)
        ->create(['name' => 'Prompt 81 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 81 Branch',
            'currency' => 'EUR',
        ]);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Prompt 81 Table',
            'status' => ServicePointStatus::Occupied,
        ]);
    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->create(['status' => TableSessionStatus::Active]);
    $guest = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Ana',
            'status' => TableSessionGuestStatus::Active,
        ]);

    return [$organization, $branch, $servicePoint, $tableSession, $guest];
}

function createPrompt81DraftOrder(TableSession $tableSession, TableSessionGuest $guest): DraftOrder
{
    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create(['status' => DraftOrderStatus::Draft]);

    DraftOrderItem::factory()
        ->for($draftOrder, 'draftOrder')
        ->for($guest, 'guest')
        ->create([
            'item_name' => 'Prompt 81 Soup',
            'quantity' => 1,
            'unit_price_cents' => 850,
            'modifier_total_cents' => 0,
            'total_price_cents' => 850,
            'selected_modifiers' => [],
        ]);

    return $draftOrder;
}

function createPrompt81KitchenTicketItem(
    Branch $branch,
    ServicePoint $servicePoint,
    TableSession $tableSession,
    TableSessionGuest $guest,
): KitchenTicketItem {
    $department = KitchenDepartment::factory()
        ->for($branch)
        ->create([
            'type' => KitchenDepartmentType::Kitchen,
            'name' => 'Prompt 81 Kitchen',
            'is_active' => true,
        ]);
    $order = Order::factory()
        ->for($branch)
        ->for($servicePoint)
        ->for($tableSession)
        ->create([
            'status' => OrderStatus::SentToKitchenBar,
            'total_price_cents' => 850,
            'currency' => 'EUR',
        ]);
    $orderItem = OrderItem::factory()
        ->for($order)
        ->for($guest, 'guest')
        ->create([
            'item_name' => 'Prompt 81 Soup',
            'guest_name' => 'Ana',
            'quantity' => 1,
            'unit_price_cents' => 850,
            'modifier_total_cents' => 0,
            'total_price_cents' => 850,
            'selected_modifiers' => [],
        ]);
    $ticket = KitchenTicket::factory()
        ->for($order)
        ->create([
            'branch_id' => $branch->id,
            'service_point_id' => $servicePoint->id,
            'table_session_id' => $tableSession->id,
            'kitchen_department_id' => $department->id,
            'department_type' => KitchenDepartmentType::Kitchen->value,
            'department_name' => $department->name,
            'status' => KitchenTicketStatus::Sent,
        ]);

    return KitchenTicketItem::factory()
        ->for($ticket, 'kitchenTicket')
        ->for($orderItem, 'orderItem')
        ->create([
            'table_session_guest_id' => $guest->id,
            'menu_item_id' => null,
            'guest_name' => 'Ana',
            'item_name' => 'Prompt 81 Soup',
            'quantity' => 1,
            'status' => KitchenTicketItemStatus::New,
            'selected_modifiers' => [],
            'comment' => null,
        ]);
}

/**
 * @param  list<SystemPermission>  $permissions
 */
function attachPrompt81Staff(User $user, Organization $organization, SystemRole $roleCode, array $permissions = []): Role
{
    $role = Role::query()
        ->where('code', $roleCode->value)
        ->firstOrFail();

    foreach ($permissions as $permission) {
        $permissionModel = Permission::query()
            ->where('code', $permission->value)
            ->firstOrFail();

        $role->permissions()->updateExistingPivot($permissionModel->id, ['enabled' => true]);
    }

    $organization->users()->syncWithoutDetachingOrFail([
        $user->id => [
            'role_id' => $role->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ],
    ]);

    return $role;
}

function prompt82GuestCookieName(string $publicToken): string
{
    return 'guest_token_'.substr(hash('sha256', $publicToken), 0, 24);
}
