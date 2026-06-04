<?php

use App\Enums\DraftOrderStatus;
use App\Enums\ManualPaymentMethod;
use App\Enums\ManualPaymentScope;
use App\Enums\OrderStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Livewire\Waiter\TableDetail;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\ManualPayment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Permission;
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

test('payment manager can mark whole table paid and close the session', function () {
    [$organization, $servicePoint, $tableSession] = createPrompt67ManualPaymentContext();
    $manager = User::factory()->create(['name' => 'Payment Waiter']);
    attachPrompt67PaymentManager($manager, $organization);

    $component = Livewire::actingAs($manager)
        ->test(TableDetail::class, ['tableSession' => $tableSession])
        ->assertSet('table.payment.can_view', true)
        ->assertSet('table.payment.can_manage', true)
        ->assertSet('table.payment.can_record_table_payment', true)
        ->assertSet('table.payment.remaining_total', '32.00 EUR')
        ->set('paymentMethod', ManualPaymentMethod::CardTerminal->value)
        ->call('recordTablePayment')
        ->assertSee('Оплата всего стола отмечена.')
        ->assertSet('table.payment.is_fully_paid', true)
        ->assertSet('table.payment.remaining_total', '0.00 EUR');

    $payment = ManualPayment::query()->firstOrFail();

    expect($payment->scope)->toBe(ManualPaymentScope::Table)
        ->and($payment->payment_method)->toBe(ManualPaymentMethod::CardTerminal)
        ->and($payment->amount)->toBe('32.00')
        ->and($tableSession->fresh()->status)->toBe(TableSessionStatus::Paid)
        ->and($tableSession->fresh()->active_service_point_id)->toBeNull()
        ->and((int) data_get($tableSession->fresh()->metadata, 'paid_by_user_id'))->toBe($manager->id)
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::Paid);

    $component
        ->call('closePaidSession')
        ->assertSee('Стол закрыт. Место свободно для следующих гостей.');

    expect($tableSession->fresh()->status)->toBe(TableSessionStatus::Closed)
        ->and($tableSession->fresh()->closed_by_user_id)->toBe($manager->id)
        ->and($tableSession->fresh()->ended_at)->not->toBeNull()
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::Free);
});

test('cashier role can mark individual guest payments without manage payments permission', function () {
    [$organization, $servicePoint, $tableSession, $ana, $boris] = createPrompt67ManualPaymentContext();
    $cashier = User::factory()->create(['name' => 'Branch Cashier']);
    attachPrompt67Cashier($cashier, $organization);

    Livewire::actingAs($cashier)
        ->test(TableDetail::class, ['tableSession' => $tableSession])
        ->assertSet('table.payment.can_manage', true)
        ->assertSet('table.payment.guest_balances.0.guest_name', 'Ana')
        ->assertSet('table.payment.guest_balances.0.remaining', '20.00 EUR')
        ->assertSet('table.payment.guest_balances.1.guest_name', 'Boris')
        ->set('paymentMethod', ManualPaymentMethod::Cash->value)
        ->call('recordGuestPayment', $ana->id)
        ->assertSee('Оплата гостя отмечена.')
        ->assertSet('table.payment.remaining_total', '12.00 EUR')
        ->set('paymentMethod', ManualPaymentMethod::CardTerminal->value)
        ->call('recordGuestPayment', $boris->id)
        ->assertSet('table.payment.is_fully_paid', true);

    expect(ManualPayment::query()->count())->toBe(2)
        ->and(ManualPayment::query()->where('scope', ManualPaymentScope::Guest->value)->count())->toBe(2)
        ->and($tableSession->fresh()->status)->toBe(TableSessionStatus::Paid)
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::Paid);
});

test('view payments permission can see payment summary but cannot record payment', function () {
    [$organization, , $tableSession] = createPrompt67ManualPaymentContext();
    $viewer = User::factory()->create(['name' => 'Payment Viewer']);
    attachPrompt67PaymentViewer($viewer, $organization);

    $this->actingAs($viewer)
        ->get(route('restaurant.waiter.tables.show', $tableSession))
        ->assertOk()
        ->assertSeeText('Payments')
        ->assertSeeText('32.00 EUR');

    Livewire::actingAs($viewer)
        ->test(TableDetail::class, ['tableSession' => $tableSession])
        ->assertSet('table.payment.can_view', true)
        ->assertSet('table.payment.can_manage', false)
        ->call('recordTablePayment')
        ->assertHasErrors(['manual_payment']);

    expect(ManualPayment::query()->exists())->toBeFalse()
        ->and($tableSession->fresh()->status)->toBe(TableSessionStatus::PaymentRequested);
});

test('manual payment is blocked while the latest draft is still open', function () {
    [$organization, , $tableSession] = createPrompt67ManualPaymentContext(withOpenDraft: true);
    $manager = User::factory()->create(['name' => 'Draft Payment Manager']);
    attachPrompt67PaymentManager($manager, $organization);

    Livewire::actingAs($manager)
        ->test(TableDetail::class, ['tableSession' => $tableSession])
        ->assertSet('table.payment.has_open_draft', true)
        ->assertSet('table.payment.can_record_table_payment', false)
        ->call('recordTablePayment')
        ->assertHasErrors(['manual_payment']);

    expect(ManualPayment::query()->exists())->toBeFalse()
        ->and($tableSession->fresh()->status)->toBe(TableSessionStatus::PaymentRequested);
});

function createPrompt67ManualPaymentContext(bool $withOpenDraft = false): array
{
    $organization = Organization::factory()->create(['name' => 'Prompt 67 Group']);
    $brand = Brand::factory()
        ->for($organization)
        ->create(['name' => 'Prompt 67 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 67 Branch',
            'city' => 'Vilnius',
            'currency' => 'EUR',
        ]);

    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Manual payment table',
            'status' => ServicePointStatus::PaymentRequested,
            'is_active' => true,
        ]);

    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->waiterOpened()
        ->create([
            'status' => TableSessionStatus::PaymentRequested,
            'started_at' => now(),
            'metadata' => ['bill_requested_at' => now()->toISOString()],
        ]);

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
        ->for($ana, 'guest')
        ->create([
            'guest_name' => 'Ana',
            'item_name' => 'Dinner',
            'unit_price' => '20.00',
            'total_price' => '20.00',
        ]);
    OrderItem::factory()
        ->for($order)
        ->for($boris, 'guest')
        ->create([
            'guest_name' => 'Boris',
            'item_name' => 'Dessert',
            'unit_price' => '12.00',
            'total_price' => '12.00',
        ]);

    if ($withOpenDraft) {
        DraftOrder::factory()
            ->for($tableSession)
            ->create(['status' => DraftOrderStatus::Draft]);
    }

    return [$organization, $servicePoint, $tableSession, $ana, $boris];
}

function attachPrompt67PaymentManager(User $user, Organization $organization): Role
{
    $role = Role::query()
        ->where('code', SystemRole::Waiter->value)
        ->firstOrFail();
    $managePayments = Permission::query()
        ->where('code', SystemPermission::ManagePayments->value)
        ->firstOrFail();

    $role->permissions()->updateExistingPivot($managePayments->id, ['enabled' => true]);

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

function attachPrompt67PaymentViewer(User $user, Organization $organization): Role
{
    $role = Role::query()
        ->where('code', SystemRole::Accountant->value)
        ->firstOrFail();
    $viewPayments = Permission::query()
        ->where('code', SystemPermission::ViewPayments->value)
        ->firstOrFail();

    $role->permissions()->updateExistingPivot($viewPayments->id, ['enabled' => true]);

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

function attachPrompt67Cashier(User $user, Organization $organization): Role
{
    $role = Role::query()
        ->where('code', SystemRole::Cashier->value)
        ->firstOrFail();

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
