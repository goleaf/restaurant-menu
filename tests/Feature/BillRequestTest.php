<?php

use App\Actions\TableSessions\OpenTableSessionForServicePointAction;
use App\Enums\DraftOrderStatus;
use App\Enums\OrderStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Livewire\PublicQr\DraftOrder as GuestDraftOrder;
use App\Livewire\Waiter\Dashboard as WaiterDashboard;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\QrCode;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('active guest can request bill and waiter sees the request', function () {
    [$qrCode, $servicePoint, $tableSession, $ana, $waiter] = createPrompt66BillRequestContext();

    $component = Livewire::withCookie(prompt66GuestCookieName($qrCode), $ana->guest_token)
        ->test(GuestDraftOrder::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $ana->id,
            'currency' => 'EUR',
            'publicToken' => $qrCode->public_token,
        ])
        ->assertSet('tableTotalAmount', '37.50')
        ->assertSet('guestSections.0.guest_name', 'Ana')
        ->assertSet('guestSections.0.confirmed_total', '20.00')
        ->assertSet('guestSections.0.draft_total', '5.50')
        ->assertSet('guestSections.0.total', '25.50')
        ->assertSet('guestSections.1.guest_name', 'Boris')
        ->assertSet('guestSections.1.confirmed_total', '12.00')
        ->assertSet('guestSections.1.total', '12.00')
        ->assertSet('canRequestBill', true)
        ->assertSee('Попросить счёт')
        ->call('requestBill')
        ->assertSet('billRequested', true)
        ->assertSet('canRequestBill', false)
        ->assertSee('Счёт запрошен')
        ->assertSee('37.50 EUR');

    expect($tableSession->fresh()->status)->toBe(TableSessionStatus::PaymentRequested)
        ->and($tableSession->fresh()->active_service_point_id)->toBe($servicePoint->id)
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::PaymentRequested)
        ->and($waiter->notifications()->count())->toBe(1)
        ->and((int) data_get($waiter->notifications()->firstOrFail()->data, 'table_session_id'))->toBe($tableSession->id)
        ->and(data_get($waiter->notifications()->firstOrFail()->data, 'guest_name'))->toBe('Ana');

    $component->call('requestBill');

    expect($waiter->notifications()->count())->toBe(1);

    $reopenedSession = app(OpenTableSessionForServicePointAction::class)
        ->handle($servicePoint->fresh(), $waiter);

    expect($reopenedSession->id)->toBe($tableSession->id)
        ->and($reopenedSession->status)->toBe(TableSessionStatus::PaymentRequested)
        ->and(TableSession::query()->where('service_point_id', $servicePoint->id)->count())->toBe(1);

    Livewire::actingAs($waiter)
        ->test(WaiterDashboard::class)
        ->assertSet('billRequestCount', 1)
        ->assertSee('Bill requests')
        ->assertSee('Счётный стол')
        ->assertSee('Bill requested');
});

test('non active guest cannot request bill from an old token', function () {
    [$qrCode, $servicePoint, $tableSession, $ana, $waiter] = createPrompt66BillRequestContext();

    $ana->update(['status' => TableSessionGuestStatus::Removed]);

    Livewire::withCookie(prompt66GuestCookieName($qrCode), $ana->guest_token)
        ->test(GuestDraftOrder::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $ana->id,
            'currency' => 'EUR',
            'publicToken' => $qrCode->public_token,
        ])
        ->call('requestBill')
        ->assertHasErrors(['bill_request']);

    expect($tableSession->fresh()->status)->toBe(TableSessionStatus::Active)
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::Occupied)
        ->and($waiter->notifications()->exists())->toBeFalse();
});

function createPrompt66BillRequestContext(): array
{
    $organization = Organization::factory()->create(['name' => 'Prompt 66 Group']);
    $brand = Brand::factory()
        ->for($organization)
        ->create(['name' => 'Prompt 66 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 66 Branch',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
            'currency' => 'EUR',
        ]);
    BranchSetting::factory()->for($branch)->create();

    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Счётный стол',
            'is_active' => true,
            'status' => ServicePointStatus::Occupied,
        ]);

    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'public_token' => 'prompt66'.fake()->unique()->bothify('????????????????'),
            'short_code' => 'P66-'.fake()->unique()->numerify('####'),
            'status' => QrCodeStatus::Active,
        ]);

    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->waiterOpened()
        ->create();

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

    $convertedDraft = DraftOrder::factory()
        ->for($tableSession)
        ->create(['status' => DraftOrderStatus::ConvertedToOrder]);
    $order = Order::factory()
        ->for($branch)
        ->for($servicePoint)
        ->for($tableSession)
        ->for($convertedDraft, 'draftOrder')
        ->create([
            'status' => OrderStatus::Served,
            'total_price' => '32.00',
            'currency' => 'EUR',
        ]);

    OrderItem::factory()
        ->for($order)
        ->create([
            'table_session_guest_id' => $ana->id,
            'guest_name' => 'Ana',
            'item_name' => 'Dinner',
            'unit_price' => '20.00',
            'total_price' => '20.00',
        ]);
    OrderItem::factory()
        ->for($order)
        ->create([
            'table_session_guest_id' => $boris->id,
            'guest_name' => 'Boris',
            'item_name' => 'Dessert',
            'unit_price' => '12.00',
            'total_price' => '12.00',
        ]);

    $openDraft = DraftOrder::factory()
        ->for($tableSession)
        ->create(['status' => DraftOrderStatus::Draft]);
    DraftOrderItem::factory()
        ->for($openDraft)
        ->create([
            'table_session_guest_id' => $ana->id,
            'item_name' => 'Tea',
            'unit_price' => '5.50',
            'total_price' => '5.50',
        ]);

    $waiter = User::factory()->create([
        'name' => 'Prompt 66 Waiter',
        'email' => 'prompt66-waiter@example.test',
    ]);

    attachPrompt66Waiter($waiter, $organization);

    return [$qrCode, $servicePoint, $tableSession, $ana, $waiter];
}

function attachPrompt66Waiter(User $user, Organization $organization): Role
{
    $waiterRole = Role::query()
        ->where('code', SystemRole::Waiter->value)
        ->firstOrFail();
    $viewOrders = Permission::query()
        ->where('code', SystemPermission::ViewOrders->value)
        ->firstOrFail();

    $waiterRole->permissions()->updateExistingPivot($viewOrders->id, ['enabled' => true]);

    $organization->users()->syncWithoutDetachingOrFail([
        $user->id => [
            'role_id' => $waiterRole->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ],
    ]);

    return $waiterRole;
}

function prompt66GuestCookieName(QrCode $qrCode): string
{
    return 'guest_token_'.substr(hash('sha256', $qrCode->public_token), 0, 24);
}
