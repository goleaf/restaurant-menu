<?php

use App\Actions\AreaNodes\CreateAreaNodeAction;
use App\Actions\Branches\CreateBranchAction;
use App\Actions\Brands\CreateBrandAction;
use App\Actions\Onboarding\CreateStarterMenuAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\QrCodes\GenerateQrCodeForServicePointAction;
use App\Actions\ServicePoints\CreateServicePointAction;
use App\Enums\AreaNodeType;
use App\Enums\DraftOrderStatus;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\ManualPaymentMethod;
use App\Enums\OrderStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\ServicePointType;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionJoinRequestStatus;
use App\Enums\TableSessionSource;
use App\Enums\TableSessionStatus;
use App\Livewire\Bar\Dashboard as BarDashboard;
use App\Livewire\Kitchen\Dashboard as KitchenDashboard;
use App\Livewire\PublicQr\DraftOrder as GuestDraftOrder;
use App\Livewire\PublicQr\GuestActions;
use App\Livewire\PublicQr\GuestEntry;
use App\Livewire\PublicQr\GuestMenu;
use App\Livewire\PublicQr\JoinRequests;
use App\Livewire\PublicQr\Show as PublicQrShow;
use App\Livewire\Waiter\TableDetail\DraftReview as WaiterDraftReview;
use App\Livewire\Waiter\TableDetail\OrderFulfilment as WaiterOrderFulfilment;
use App\Livewire\Waiter\TableDetail\Payment as WaiterPayment;
use App\Models\DraftOrder;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\QrCode;
use App\Models\Role;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('first vertical slice works from registration to closed table session', function () {
    $this
        ->post(route('register.store'), [
            'name' => 'Vertical Owner',
            'email' => 'vertical-owner@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    $owner = User::query()
        ->select(['id', 'name', 'email'])
        ->where('email', 'vertical-owner@example.test')
        ->firstOrFail();

    $organization = app(CreateOrganizationAction::class)->handle($owner, [
        'name' => 'Vertical Food Group',
    ]);
    $brand = app(CreateBrandAction::class)->handle($organization, [
        'name' => 'Vertical Bistro',
    ]);
    $branch = app(CreateBranchAction::class)->handle($brand, [
        'name' => 'Vertical Bistro Old Town',
        'address' => 'Pilies 1',
        'city' => 'Vilnius',
        'country' => 'Lithuania',
        'timezone' => 'Europe/Vilnius',
        'currency' => 'EUR',
        'is_active' => true,
    ]);
    $areaNode = app(CreateAreaNodeAction::class)->handle($branch, [
        'parent_id' => null,
        'type' => AreaNodeType::Hall->value,
        'name' => 'Main Hall',
        'icon' => 'door-open',
        'sort_order' => 10,
        'is_active' => true,
    ]);
    $servicePoint = app(CreateServicePointAction::class)->handle($branch, [
        'area_node_id' => $areaNode->id,
        'type' => ServicePointType::Table->value,
        'name' => 'Terrace Table 1',
        'display_number' => 'T1',
        'capacity' => 4,
        'icon' => 'table-2',
        'is_active' => true,
    ]);

    $qrCode = app(GenerateQrCodeForServicePointAction::class)->handle($servicePoint, $owner);
    $starterMenu = app(CreateStarterMenuAction::class)->handle($branch, [
        'menu_name' => 'Vertical Menu',
        'category_name' => 'Main',
        'item_name' => 'Vertical Pizza',
        'item_price' => '12.00',
    ]);
    $pizza = $starterMenu['item']->refresh();
    $barItem = createVerticalSliceBarItem($branch->id, $starterMenu['category']->id);

    [$waiter, $cook, $bartender] = createVerticalSliceStaff($organization);
    $cookieName = verticalSliceGuestCookieName($qrCode);

    expect($qrCode->public_token)->toHaveLength(64)
        ->and($qrCode->short_code)->toStartWith('QR-')
        ->and($qrCode->status)->toBe(QrCodeStatus::Active);

    expect(explode('/', trim(route('public.qr.show', ['token' => $qrCode->public_token], false), '/')))
        ->toBe(['q', $qrCode->public_token]);

    $this
        ->get(route('public.qr.show', ['token' => $qrCode->public_token], false))
        ->assertOk()
        ->assertSeeText('Vertical Bistro')
        ->assertSeeText('Terrace Table 1');

    Livewire::test(PublicQrShow::class, ['token' => $qrCode->public_token])
        ->assertSet('state', 'ready');

    Livewire::test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->set('guestName', '  Ana  ')
        ->call('enterTable')
        ->assertHasNoErrors()
        ->assertSet('entryState', 'pending_session_created')
        ->assertSeeText('Welcome, Ana.');

    $tableSession = TableSession::query()
        ->select(['id', 'branch_id', 'service_point_id', 'status', 'source', 'opened_by_guest_id'])
        ->where('service_point_id', $servicePoint->id)
        ->firstOrFail();
    $firstGuest = TableSessionGuest::query()
        ->select(['id', 'table_session_id', 'guest_name', 'guest_token', 'status'])
        ->where('table_session_id', $tableSession->id)
        ->where('guest_name', 'Ana')
        ->firstOrFail();

    expect($tableSession->status)->toBe(TableSessionStatus::Pending)
        ->and($tableSession->source)->toBe(TableSessionSource::GuestCreated)
        ->and($tableSession->opened_by_guest_id)->toBe($firstGuest->id)
        ->and($firstGuest->status)->toBe(TableSessionGuestStatus::Active)
        ->and($firstGuest->guest_token)->toHaveLength(64)
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::Free);

    Livewire::withCookie($cookieName, $firstGuest->guest_token)
        ->test(GuestActions::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $firstGuest->id,
            'publicToken' => $qrCode->public_token,
            'language' => 'en',
            'venueName' => 'Vertical Bistro',
        ])
        ->call('createGuestInviteLink')
        ->assertHasNoErrors()
        ->assertSeeText('Invite link is ready.');

    $inviteToken = $tableSession->fresh()->guest_invite_token;

    expect($inviteToken)->toBeString()
        ->and($inviteToken)->toHaveLength(64);

    session()->forget('guest_entries.'.$qrCode->public_token);

    Livewire::withQueryParams(['invite' => $inviteToken])
        ->withCookie($cookieName, str_repeat('x', 64))
        ->test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->assertSet('hasCurrentInviteToken', true)
        ->set('guestName', 'Boris')
        ->call('enterTable')
        ->assertHasNoErrors()
        ->assertSet('entryState', 'join_request_created')
        ->assertSeeText('Request sent');

    $joinRequest = TableSessionJoinRequest::query()
        ->select(['id', 'table_session_id', 'guest_name', 'guest_token', 'status'])
        ->where('table_session_id', $tableSession->id)
        ->where('guest_name', 'Boris')
        ->firstOrFail();

    expect($joinRequest->status)->toBe(TableSessionJoinRequestStatus::Pending)
        ->and($joinRequest->guest_token)->toHaveLength(64);

    Livewire::withCookie($cookieName, $firstGuest->guest_token)
        ->test(JoinRequests::class, [
            'tableSessionId' => $tableSession->id,
            'guestId' => $firstGuest->id,
            'publicToken' => $qrCode->public_token,
            'language' => 'en',
        ])
        ->assertSet('canModerate', true)
        ->assertSeeText('Boris')
        ->call('approve', $joinRequest->id)
        ->assertHasNoErrors()
        ->assertSeeText('Guest approved.');

    $secondGuest = TableSessionGuest::query()
        ->select(['id', 'table_session_id', 'guest_name', 'guest_token', 'status'])
        ->where('table_session_id', $tableSession->id)
        ->where('guest_name', 'Boris')
        ->firstOrFail();

    expect($secondGuest->status)->toBe(TableSessionGuestStatus::Active)
        ->and($joinRequest->fresh()->status)->toBe(TableSessionJoinRequestStatus::Approved);

    addVerticalSliceGuestItem($qrCode, $tableSession, $firstGuest, $pizza, 'No onions');
    addVerticalSliceGuestItem($qrCode, $tableSession, $secondGuest, $barItem, 'No ice');

    $draftOrder = DraftOrder::query()
        ->select(['id', 'table_session_id', 'status'])
        ->where('table_session_id', $tableSession->id)
        ->firstOrFail();

    expect($draftOrder->status)->toBe(DraftOrderStatus::Draft)
        ->and($draftOrder->items()->count())->toBe(2);

    Livewire::withCookie($cookieName, $firstGuest->guest_token)
        ->test(GuestDraftOrder::class, verticalSliceDraftProps($qrCode, $tableSession, $firstGuest))
        ->assertSet('guestSections.0.guest_name', 'Ana')
        ->assertSet('guestSections.0.total', '12.00')
        ->assertSet('guestSections.1.guest_name', 'Boris')
        ->assertSet('guestSections.1.total', '4.50')
        ->assertSet('tableTotalAmount', '16.50')
        ->assertSee('Vertical Pizza')
        ->assertSee('Vertical Lemonade')
        ->call('sendDraftToWaiter', true)
        ->assertHasNoErrors()
        ->assertSee('Order sent to the waiter.');

    expect($draftOrder->fresh()->status)->toBe(DraftOrderStatus::SentToWaiter)
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::HasNewOrder)
        ->and($waiter->notifications()->count())->toBe(1);

    Livewire::actingAs($waiter)
        ->test(WaiterDraftReview::class, ['tableSessionId' => $tableSession->id])
        ->assertSet('draftReview.status_value', DraftOrderStatus::SentToWaiter->value)
        ->assertSee('Vertical Pizza')
        ->assertSee('Vertical Lemonade')
        ->call('confirmDraft')
        ->assertHasNoErrors()
        ->assertSee(__('ui.livewire.waiter.tabledetail.zakaz_podtverzden_oficiantom_kuxnia_i_bar_po'));

    Livewire::actingAs($waiter)
        ->test(WaiterOrderFulfilment::class, ['tableSessionId' => $tableSession->id])
        ->call('sendOrderToKitchenBar')
        ->assertHasNoErrors()
        ->assertSee(__('ui.livewire.waiter.tabledetail.zakaz_otpravlen_na_kuxniu_bar_gosti_uvidiat'));

    $order = Order::query()
        ->select(['id', 'draft_order_id', 'status', 'total_price'])
        ->where('draft_order_id', $draftOrder->id)
        ->firstOrFail();

    expect($order->status)->toBe(OrderStatus::SentToKitchenBar)
        ->and($order->total_price)->toBe('16.50')
        ->and(KitchenTicket::query()->where('order_id', $order->id)->count())->toBe(2)
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::Cooking);

    $kitchenTicketItem = verticalSliceTicketItem($order, KitchenDepartmentType::Kitchen);
    $barTicketItem = verticalSliceTicketItem($order, KitchenDepartmentType::Bar);

    Livewire::actingAs($cook)
        ->test(KitchenDashboard::class)
        ->assertSee('Vertical Pizza')
        ->call('setItemStatus', $kitchenTicketItem->id, KitchenTicketItemStatus::InProgress->value)
        ->assertHasNoErrors()
        ->call('setItemStatus', $kitchenTicketItem->id, KitchenTicketItemStatus::Ready->value)
        ->assertHasNoErrors()
        ->assertSee('Ready');

    Livewire::actingAs($bartender)
        ->test(BarDashboard::class)
        ->assertSee('Vertical Lemonade')
        ->call('setItemStatus', $barTicketItem->id, KitchenTicketItemStatus::Ready->value)
        ->assertHasNoErrors()
        ->assertSee('Ready');

    expect($order->fresh()->status)->toBe(OrderStatus::Ready)
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::ReadyToServe);

    Livewire::actingAs($waiter)
        ->test(WaiterOrderFulfilment::class, ['tableSessionId' => $tableSession->id])
        ->assertSet('orderFulfilment.ready_ticket_item_count', 2)
        ->assertSee('Mark served')
        ->call('markTicketItemServed', $kitchenTicketItem->id)
        ->assertHasNoErrors()
        ->call('markTicketItemServed', $barTicketItem->id)
        ->assertHasNoErrors()
        ->assertSet('orderFulfilment.order_status_value', OrderStatus::Served->value)
        ->assertSee('Served');

    expect($order->fresh()->status)->toBe(OrderStatus::Served)
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::Occupied);

    Livewire::withCookie($cookieName, $firstGuest->guest_token)
        ->test(GuestDraftOrder::class, verticalSliceDraftProps($qrCode, $tableSession, $firstGuest))
        ->assertSet('confirmedOrdersTotalAmount', '16.50')
        ->assertSet('tableTotalAmount', '16.50')
        ->call('requestBill')
        ->assertHasNoErrors()
        ->assertSee('The waiter has been asked to bring the bill.');

    expect($tableSession->fresh()->status)->toBe(TableSessionStatus::PaymentRequested)
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::PaymentRequested);

    Livewire::actingAs($waiter)
        ->test(WaiterPayment::class, ['tableSessionId' => $tableSession->id])
        ->assertSet('payment.remaining_total', '16.50 EUR')
        ->set('paymentMethod', ManualPaymentMethod::CardTerminal->value)
        ->call('recordTablePayment')
        ->assertHasNoErrors()
        ->assertSee(__('payments.messages.payment_recorded'))
        ->call('closePaidSession')
        ->assertHasNoErrors()
        ->assertSee(__('payments.messages.session_closed'));

    expect($tableSession->fresh()->status)->toBe(TableSessionStatus::Closed)
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::Free)
        ->and($qrCode->fresh()->public_token)->toBe($qrCode->public_token)
        ->and($qrCode->fresh()->status)->toBe(QrCodeStatus::Active);
});

function createVerticalSliceBarItem(int $branchId, int $categoryId): MenuItem
{
    $barDepartment = KitchenDepartment::query()
        ->select(['id', 'branch_id', 'type', 'is_active'])
        ->where('branch_id', $branchId)
        ->where('type', KitchenDepartmentType::Bar->value)
        ->where('is_active', true)
        ->firstOrFail();
    $category = MenuCategory::query()
        ->select(['id', 'menu_id'])
        ->whereKey($categoryId)
        ->firstOrFail();

    return $category
        ->menu
        ->items()
        ->create([
            'category_id' => $category->id,
            'kitchen_department_id' => $barDepartment->id,
            'name' => 'Vertical Lemonade',
            'description' => null,
            'price' => '4.50',
            'is_available' => true,
            'sort_order' => 20,
        ])
        ->refresh();
}

/**
 * @return array{0: User, 1: User, 2: User}
 */
function createVerticalSliceStaff(Organization $organization): array
{
    $waiter = User::factory()->create([
        'name' => 'Vertical Waiter',
        'email' => 'vertical-waiter@example.test',
    ]);
    $cook = User::factory()->create([
        'name' => 'Vertical Cook',
        'email' => 'vertical-cook@example.test',
    ]);
    $bartender = User::factory()->create([
        'name' => 'Vertical Bartender',
        'email' => 'vertical-bartender@example.test',
    ]);

    attachVerticalSliceStaff($waiter, $organization, SystemRole::Waiter, [
        SystemPermission::ViewOrders,
        SystemPermission::ConfirmOrders,
        SystemPermission::SendToKitchen,
        SystemPermission::ManagePayments,
        SystemPermission::CloseTableSessions,
    ]);
    attachVerticalSliceStaff($cook, $organization, SystemRole::Cook);
    attachVerticalSliceStaff($bartender, $organization, SystemRole::Bartender);

    return [$waiter, $cook, $bartender];
}

/**
 * @param  list<SystemPermission>  $permissions
 */
function attachVerticalSliceStaff(
    User $user,
    Organization $organization,
    SystemRole $roleCode,
    array $permissions = [],
): Role {
    $role = Role::query()
        ->where('code', $roleCode->value)
        ->firstOrFail();

    foreach ($permissions as $permission) {
        $permissionModel = Permission::query()
            ->where('code', $permission->value)
            ->firstOrFail();

        $role->permissions()->updateExistingPivot($permissionModel->id, ['enabled' => true]);
    }

    $user->roles()->syncWithoutDetaching([$role->id]);
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

function addVerticalSliceGuestItem(
    QrCode $qrCode,
    TableSession $tableSession,
    TableSessionGuest $guest,
    MenuItem $menuItem,
    string $comment,
): void {
    Livewire::withCookie(verticalSliceGuestCookieName($qrCode), $guest->guest_token)
        ->test(GuestMenu::class, [
            'branchId' => $tableSession->branch_id,
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $guest->id,
            'publicToken' => $qrCode->public_token,
            'guestCanAddItems' => true,
            'currency' => 'EUR',
        ])
        ->assertSee($menuItem->name)
        ->call('openItem', $menuItem->id)
        ->set('itemComment', $comment)
        ->call('saveConfiguredItem')
        ->assertHasNoErrors()
        ->assertSee('Item added to the shared order.');
}

/**
 * @return array<string, mixed>
 */
function verticalSliceDraftProps(QrCode $qrCode, TableSession $tableSession, TableSessionGuest $guest): array
{
    return [
        'tableSessionId' => $tableSession->id,
        'currentGuestId' => $guest->id,
        'currency' => 'EUR',
        'publicToken' => $qrCode->public_token,
        'language' => 'en',
    ];
}

function verticalSliceTicketItem(Order $order, KitchenDepartmentType $departmentType): KitchenTicketItem
{
    return KitchenTicketItem::query()
        ->select(['id', 'kitchen_ticket_id', 'status', 'served_at', 'served_by_user_id'])
        ->whereHas('kitchenTicket', function ($query) use ($order, $departmentType): void {
            $query
                ->where('order_id', $order->id)
                ->where('department_type', $departmentType->value);
        })
        ->firstOrFail();
}

function verticalSliceGuestCookieName(QrCode $qrCode): string
{
    return 'guest_token_'.substr(hash('sha256', $qrCode->public_token), 0, 24);
}
