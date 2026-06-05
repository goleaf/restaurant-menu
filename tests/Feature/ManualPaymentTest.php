<?php

use App\Actions\Payments\RecordManualPaymentAction;
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
use App\Models\BranchSetting;
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
        ->assertSee(__('payments.messages.payment_recorded'))
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
        ->assertSee(__('payments.messages.session_closed'));

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
        ->assertSet('table.payment.unpaid_guests_count', 2)
        ->set('paymentMethod', ManualPaymentMethod::Cash->value)
        ->call('recordGuestPayment', $ana->id)
        ->assertSee(__('payments.messages.payment_recorded'))
        ->assertSet('table.payment.remaining_total', '12.00 EUR')
        ->assertSet('table.payment.unpaid_guests_count', 1)
        ->assertSet('table.payment.unpaid_guests.0.guest_name', 'Boris')
        ->set('paymentMethod', ManualPaymentMethod::CardTerminal->value)
        ->call('recordGuestPayment', $boris->id)
        ->assertSet('table.payment.is_fully_paid', true)
        ->assertSet('table.payment.unpaid_guests_count', 0);

    expect(ManualPayment::query()->count())->toBe(2)
        ->and(ManualPayment::query()->where('scope', ManualPaymentScope::Guest->value)->count())->toBe(2)
        ->and(ManualPayment::query()->where('table_session_guest_id', $ana->id)->value('amount'))->toBe('20.00')
        ->and(ManualPayment::query()->where('table_session_guest_id', $boris->id)->value('amount'))->toBe('12.00')
        ->and($tableSession->fresh()->status)->toBe(TableSessionStatus::Paid)
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::Paid);
});

test('split bill summary is based on confirmed guest order items', function () {
    [$organization, , $tableSession] = createPrompt67ManualPaymentContext();
    $manager = User::factory()->create(['name' => 'Split Bill Manager']);
    attachPrompt67PaymentManager($manager, $organization);

    $tableSession->orders()->firstOrFail()->forceFill(['total_price' => '99.00'])->save();

    Livewire::actingAs($manager)
        ->test(TableDetail::class, ['tableSession' => $tableSession])
        ->assertSet('table.confirmed_orders_total', '32.00 EUR')
        ->assertSet('table.payment.confirmed_total', '32.00 EUR')
        ->assertSet('table.payment.remaining_total', '32.00 EUR')
        ->assertSet('table.payment.guest_balances.0.due', '20.00 EUR')
        ->assertSet('table.payment.guest_balances.1.due', '12.00 EUR')
        ->assertSet('table.payment.unpaid_guests_count', 2);
});

test('manual service charge and tips are visible and stored as payment snapshot', function () {
    [$organization, , $tableSession] = createPrompt67ManualPaymentContext();
    $manager = User::factory()->create(['name' => 'Service Charge Cashier']);
    attachPrompt67PaymentManager($manager, $organization);

    $branch = $tableSession->branch()->firstOrFail();
    $branch->settings()->create([
        ...BranchSetting::defaults($branch),
        'service_charge_enabled' => true,
        'service_charge_percent' => '10.00',
        'tips_enabled' => true,
    ]);

    Livewire::actingAs($manager)
        ->test(TableDetail::class, ['tableSession' => $tableSession])
        ->assertSet('table.payment.confirmed_total', '32.00 EUR')
        ->assertSet('table.payment.service_charge_enabled', true)
        ->assertSet('table.payment.service_charge_percent', '10.00')
        ->assertSet('table.payment.service_charge_total', '3.20 EUR')
        ->assertSet('table.payment.tips_enabled', true)
        ->assertSet('table.payment.remaining_total', '35.20 EUR')
        ->set('paymentMethod', ManualPaymentMethod::CardTerminal->value)
        ->set('tipsAmount', '5.00')
        ->call('recordTablePayment')
        ->assertSee(__('payments.messages.payment_recorded'))
        ->assertSet('table.payment.is_fully_paid', true)
        ->assertSet('table.payment.remaining_total', '0.00 EUR')
        ->assertSet('table.payment.tips_paid_total', '5.00 EUR');

    $payment = ManualPayment::query()->firstOrFail();

    expect($payment->amount)->toBe('40.20')
        ->and($payment->covered_subtotal_amount)->toBe('32.00')
        ->and($payment->service_charge_percent)->toBe('10.00')
        ->and($payment->service_charge_amount)->toBe('3.20')
        ->and($payment->tips_amount)->toBe('5.00')
        ->and($payment->metadata['bill_snapshot']['confirmed_total'])->toBe('32.00')
        ->and($payment->metadata['bill_snapshot']['service_charge_amount'])->toBe('3.20')
        ->and($payment->metadata['bill_snapshot']['tips_amount'])->toBe('5.00');
});

test('manual payment amounts are server calculated and reject negative or duplicate payments', function () {
    [$organization, , $tableSession, $ana] = createPrompt67ManualPaymentContext();
    $manager = User::factory()->create(['name' => 'Payment Security Manager']);
    attachPrompt67PaymentManager($manager, $organization);

    Livewire::actingAs($manager)
        ->test(TableDetail::class, ['tableSession' => $tableSession])
        ->set('tipsAmount', '-1.00')
        ->call('recordTablePayment')
        ->assertHasErrors(['tipsAmount']);

    expect(ManualPayment::query()->exists())->toBeFalse();

    $firstGuestPayment = app(RecordManualPaymentAction::class)->recordGuest(
        tableSession: $tableSession,
        guest: $ana,
        recordedBy: $manager,
        paymentMethod: ManualPaymentMethod::Cash,
    );

    expect($firstGuestPayment->amount)->toBe('20.00');

    Livewire::actingAs($manager)
        ->test(TableDetail::class, ['tableSession' => $tableSession])
        ->call('recordGuestPayment', $ana->id)
        ->assertHasErrors(['manual_payment']);

    expect(ManualPayment::query()->where('table_session_guest_id', $ana->id)->count())->toBe(1);
});

test('manual payments cannot be silently corrected through ordinary model paths', function () {
    [$organization, , $tableSession] = createPrompt67ManualPaymentContext();
    $manager = User::factory()->create(['name' => 'Payment Immutability Manager']);
    attachPrompt67PaymentManager($manager, $organization);

    $payment = app(RecordManualPaymentAction::class)->recordTable(
        tableSession: $tableSession,
        recordedBy: $manager,
        paymentMethod: ManualPaymentMethod::CardTerminal,
    );

    expect($payment->amount)->toBe('32.00')
        ->and($payment->update(['amount' => '-100.00', 'note' => null]))->toBeFalse()
        ->and($payment->fresh()->amount)->toBe('32.00')
        ->and($payment->delete())->toBeFalse()
        ->and(ManualPayment::query()->whereKey($payment->id)->exists())->toBeTrue();
});

test('view payments permission can see payment summary but cannot record payment', function () {
    [$organization, , $tableSession] = createPrompt67ManualPaymentContext();
    $viewer = User::factory()->create(['name' => 'Payment Viewer']);
    attachPrompt67PaymentViewer($viewer, $organization);

    $this->actingAs($viewer)
        ->get(route('restaurant.waiter.tables.show', $tableSession))
        ->assertOk()
        ->assertSeeText(__('payments.title'))
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
    $managePayments = Permission::query()
        ->where('code', SystemPermission::ManagePayments->value)
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
    $user->permissionOverrides()->syncWithoutDetaching([
        $managePayments->id => ['enabled' => false],
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
