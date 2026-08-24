<?php

use App\Actions\Payments\BuildManualPaymentSummaryAction;
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
use App\Exceptions\BusinessRuleViolation;
use App\Livewire\Waiter\TableDetail\Payment;
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
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('sqlite payment transactions reserve the write lock before reading the remaining balance', function (): void {
    expect(config('database.connections.sqlite.transaction_mode'))->toBe('IMMEDIATE')
        ->and(config('database.connections.sqlite.busy_timeout'))->toBe(5000)
        ->and(config('database.connections.sqlite.journal_mode'))->toBe('WAL')
        ->and(config('database.connections.sqlite.synchronous'))->toBe('NORMAL');
});

test('payment manager can mark whole table paid and close the session', function () {
    [$organization, $servicePoint, $tableSession] = createPrompt67ManualPaymentContext();
    $manager = User::factory()->create(['name' => 'Payment Waiter']);
    attachPrompt67PaymentManager($manager, $organization);

    $component = Livewire::actingAs($manager)
        ->test(Payment::class, ['tableSessionId' => $tableSession->id])
        ->assertSet('payment.can_view', true)
        ->assertSet('payment.can_manage', true)
        ->assertSet('payment.can_record_table_payment', true)
        ->assertSet('payment.remaining_total', '€32.00')
        ->assertSee('id="waiter-payment-note"', false)
        ->assertSee('name="paymentNote"', false)
        ->set('paymentMethod', ManualPaymentMethod::CardTerminal->value)
        ->call('recordTablePayment')
        ->assertSee(__('payments.messages.payment_recorded'))
        ->assertSet('payment.is_fully_paid', true)
        ->assertSet('payment.remaining_total', '€0.00');

    $payment = ManualPayment::query()->firstOrFail();

    expect($payment->scope)->toBe(ManualPaymentScope::Table)
        ->and($payment->payment_method)->toBe(ManualPaymentMethod::CardTerminal)
        ->and($payment->amount_cents)->toBe(3200)
        ->and($tableSession->fresh()->status)->toBe(TableSessionStatus::Paid)
        ->and($tableSession->fresh()->active_service_point_id)->toBeNull()
        ->and((int) data_get($tableSession->fresh()->metadata, 'paid_by_user_id'))->toBe($manager->id)
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::Paid)
        ->and($tableSession->orders()->firstOrFail()->status)->toBe(OrderStatus::Paid);

    $component
        ->call('closePaidSession')
        ->assertSee(__('payments.messages.session_closed'));

    expect($tableSession->fresh()->status)->toBe(TableSessionStatus::Closed)
        ->and($tableSession->fresh()->closed_by_user_id)->toBe($manager->id)
        ->and($tableSession->fresh()->ended_at)->not->toBeNull()
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::Free)
        ->and($tableSession->orders()->firstOrFail()->status)->toBe(OrderStatus::Closed);
});

test('cashier role can mark individual guest payments without manage payments permission', function () {
    [$organization, $servicePoint, $tableSession, $ana, $boris] = createPrompt67ManualPaymentContext();
    $cashier = User::factory()->create(['name' => 'Branch Cashier']);
    attachPrompt67Cashier($cashier, $organization);

    Livewire::actingAs($cashier)
        ->test(Payment::class, ['tableSessionId' => $tableSession->id])
        ->assertSet('payment.can_manage', true)
        ->assertSet('payment.guest_balances.0.guest_name', 'Ana')
        ->assertSet('payment.guest_balances.0.remaining', '€20.00')
        ->assertSet('payment.guest_balances.1.guest_name', 'Boris')
        ->assertSet('payment.unpaid_guests_count', 2)
        ->set('paymentMethod', ManualPaymentMethod::Cash->value)
        ->call('recordGuestPayment', $ana->id)
        ->assertSee(__('payments.messages.payment_recorded'))
        ->assertSet('payment.remaining_total', '€12.00')
        ->assertSet('payment.unpaid_guests_count', 1)
        ->assertSet('payment.unpaid_guests.0.guest_name', 'Boris')
        ->set('paymentMethod', ManualPaymentMethod::CardTerminal->value)
        ->call('recordGuestPayment', $boris->id)
        ->assertSet('payment.is_fully_paid', true)
        ->assertSet('payment.unpaid_guests_count', 0);

    expect(ManualPayment::query()->count())->toBe(2)
        ->and(ManualPayment::query()->where('scope', ManualPaymentScope::Guest->value)->count())->toBe(2)
        ->and(ManualPayment::query()->where('table_session_guest_id', $ana->id)->value('amount_cents'))->toBe(2000)
        ->and(ManualPayment::query()->where('table_session_guest_id', $boris->id)->value('amount_cents'))->toBe(1200)
        ->and($tableSession->fresh()->status)->toBe(TableSessionStatus::Paid)
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::Paid);
});

test('split bill summary is based on confirmed guest order items', function () {
    [$organization, , $tableSession] = createPrompt67ManualPaymentContext();
    $manager = User::factory()->create(['name' => 'Split Bill Manager']);
    attachPrompt67PaymentManager($manager, $organization);

    $tableSession->orders()->firstOrFail()->forceFill(['total_price_cents' => 9900])->save();

    Livewire::actingAs($manager)
        ->test(Payment::class, ['tableSessionId' => $tableSession->id])
        ->assertSet('payment.confirmed_total', '€32.00')
        ->assertSet('payment.remaining_total', '€32.00')
        ->assertSet('payment.guest_balances.0.due', '€20.00')
        ->assertSet('payment.guest_balances.1.due', '€12.00')
        ->assertSet('payment.unpaid_guests_count', 2);
});

test('payment summary fails closed and warns once for nullable legacy money snapshots', function (): void {
    [, , $tableSession] = createPrompt67ManualPaymentContext();
    $branch = $tableSession->branch()->firstOrFail();

    setPrompt67LegacyPaymentColumnsNullable(true);

    try {
        $settings = BranchSetting::factory()
            ->for($branch)
            ->create([
                'service_charge_enabled' => true,
                'service_charge_basis_points' => null,
                'tips_enabled' => true,
            ]);
        $payment = ManualPayment::factory()
            ->forTableSession($tableSession)
            ->create([
                'covered_subtotal_cents' => null,
                'service_charge_basis_points' => null,
                'service_charge_cents' => null,
                'tips_cents' => null,
                'amount_cents' => null,
                'guest_name' => 'Sensitive legacy guest',
                'note' => 'Sensitive legacy payment note',
            ]);

        Log::spy();

        $summary = [];
        $queryCount = countDatabaseQueries(function () use (&$summary, $tableSession): void {
            $summary = app(BuildManualPaymentSummaryAction::class)->handle($tableSession);
        });

        expect($queryCount)
            ->toBe(9)
            ->and($summary)
            ->toMatchArray([
                'service_charge_basis_points' => 0,
                'service_charge_total_cents' => 0,
                'service_charge_paid_cents' => 0,
                'tips_paid_total_cents' => 0,
                'covered_subtotal_cents' => 0,
                'paid_total_cents' => 0,
                'remaining_subtotal_cents' => 3200,
                'remaining_total_cents' => 3200,
                'is_fully_paid' => false,
            ])
            ->and($summary['payments'][0])
            ->toMatchArray([
                'covered_subtotal' => '€0.00',
                'service_charge_percent' => '0.00',
                'service_charge_amount' => '€0.00',
                'tips_amount' => '€0.00',
                'amount' => '€0.00',
            ])
            ->and($settings->fresh()?->getAttribute('service_charge_basis_points'))
            ->toBeNull()
            ->and($payment->fresh()?->only([
                'covered_subtotal_cents',
                'service_charge_basis_points',
                'service_charge_cents',
                'tips_cents',
                'amount_cents',
            ]))
            ->toBe([
                'covered_subtotal_cents' => null,
                'service_charge_basis_points' => null,
                'service_charge_cents' => null,
                'tips_cents' => null,
                'amount_cents' => null,
            ]);

        Log::shouldHaveReceived('warning')
            ->once()
            ->with(
                'manual_payment_summary_nullable_snapshots_normalized',
                Mockery::on(function (array $context) use ($settings, $payment, $tableSession): bool {
                    $encodedContext = json_encode($context, JSON_THROW_ON_ERROR);

                    return $context === [
                        'event' => 'manual_payment_summary_nullable_snapshots_normalized',
                        'table_session_id' => $tableSession->id,
                        'normalized_count' => 6,
                        'normalized_fields' => [
                            [
                                'record_type' => 'branch_setting',
                                'record_id' => $settings->id,
                                'column' => 'service_charge_basis_points',
                            ],
                            [
                                'record_type' => 'manual_payment',
                                'record_id' => $payment->id,
                                'column' => 'covered_subtotal_cents',
                            ],
                            [
                                'record_type' => 'manual_payment',
                                'record_id' => $payment->id,
                                'column' => 'service_charge_basis_points',
                            ],
                            [
                                'record_type' => 'manual_payment',
                                'record_id' => $payment->id,
                                'column' => 'service_charge_cents',
                            ],
                            [
                                'record_type' => 'manual_payment',
                                'record_id' => $payment->id,
                                'column' => 'tips_cents',
                            ],
                            [
                                'record_type' => 'manual_payment',
                                'record_id' => $payment->id,
                                'column' => 'amount_cents',
                            ],
                        ],
                        'normalized_fields_truncated' => false,
                    ]
                        && ! str_contains($encodedContext, 'Sensitive legacy guest')
                        && ! str_contains($encodedContext, 'Sensitive legacy payment note');
                }),
            );
    } finally {
        ManualPayment::query()
            ->where('table_session_id', $tableSession->id)
            ->delete();
        BranchSetting::query()
            ->where('branch_id', $branch->id)
            ->delete();

        setPrompt67LegacyPaymentColumnsNullable(false);
    }
});

test('nullable payment summary warning bounds normalized field references', function (): void {
    [, , $tableSession] = createPrompt67ManualPaymentContext();
    $branch = $tableSession->branch()->firstOrFail();

    setPrompt67LegacyPaymentColumnsNullable(true);

    try {
        BranchSetting::factory()
            ->for($branch)
            ->create([
                'service_charge_enabled' => true,
                'service_charge_basis_points' => 2500,
            ]);
        ManualPayment::factory()
            ->count(11)
            ->forTableSession($tableSession)
            ->create([
                'covered_subtotal_cents' => null,
                'service_charge_basis_points' => null,
                'service_charge_cents' => null,
                'tips_cents' => null,
                'amount_cents' => null,
                'guest_name' => 'Sensitive legacy guest',
                'note' => 'Sensitive legacy payment note',
            ]);

        Log::spy();

        $summary = app(BuildManualPaymentSummaryAction::class)->handle($tableSession);

        expect($summary['service_charge_basis_points'])
            ->toBe(2500)
            ->and($summary['payments'][0]['service_charge_percent'])
            ->toBe('0.00');

        Log::shouldHaveReceived('warning')
            ->once()
            ->with(
                'manual_payment_summary_nullable_snapshots_normalized',
                Mockery::on(function (array $context): bool {
                    $encodedContext = json_encode($context, JSON_THROW_ON_ERROR);

                    return $context['normalized_count'] === 55
                        && count($context['normalized_fields']) === 50
                        && ($context['normalized_fields_truncated'] ?? null) === true
                        && ! str_contains($encodedContext, 'Sensitive legacy guest')
                        && ! str_contains($encodedContext, 'Sensitive legacy payment note');
                }),
            );
    } finally {
        ManualPayment::query()
            ->where('table_session_id', $tableSession->id)
            ->delete();
        BranchSetting::query()
            ->where('branch_id', $branch->id)
            ->delete();

        setPrompt67LegacyPaymentColumnsNullable(false);
    }
});

test('manual service charge and tips are visible and stored as payment snapshot', function () {
    [$organization, , $tableSession] = createPrompt67ManualPaymentContext();
    $manager = User::factory()->create(['name' => 'Service Charge Cashier']);
    attachPrompt67PaymentManager($manager, $organization);

    $branch = $tableSession->branch()->firstOrFail();
    $branch->settings()->create([
        ...BranchSetting::defaults($branch),
        'service_charge_enabled' => true,
        'service_charge_basis_points' => 1000,
        'tips_enabled' => true,
    ]);

    Livewire::actingAs($manager)
        ->test(Payment::class, ['tableSessionId' => $tableSession->id])
        ->assertSet('payment.confirmed_total', '€32.00')
        ->assertSet('payment.service_charge_enabled', true)
        ->assertSet('payment.service_charge_percent', '10.00')
        ->assertSet('payment.service_charge_total', '€3.20')
        ->assertSet('payment.tips_enabled', true)
        ->assertSet('payment.remaining_total', '€35.20')
        ->set('paymentMethod', ManualPaymentMethod::CardTerminal->value)
        ->set('tipsAmount', '5.00')
        ->call('recordTablePayment')
        ->assertSee(__('payments.messages.payment_recorded'))
        ->assertSet('payment.is_fully_paid', true)
        ->assertSet('payment.remaining_total', '€0.00')
        ->assertSet('payment.tips_paid_total', '€5.00');

    $payment = ManualPayment::query()->firstOrFail();

    expect($payment->amount_cents)->toBe(4020)
        ->and($payment->covered_subtotal_cents)->toBe(3200)
        ->and($payment->service_charge_basis_points)->toBe(1000)
        ->and($payment->service_charge_cents)->toBe(320)
        ->and($payment->tips_cents)->toBe(500)
        ->and($payment->metadata['bill_snapshot']['confirmed_total_cents'])->toBe(3200)
        ->and($payment->metadata['bill_snapshot']['service_charge_cents'])->toBe(320)
        ->and($payment->metadata['bill_snapshot']['tips_cents'])->toBe(500);
});

test('manual payment amounts are server calculated and reject negative or duplicate payments', function () {
    [$organization, , $tableSession, $ana] = createPrompt67ManualPaymentContext();
    $manager = User::factory()->create(['name' => 'Payment Security Manager']);
    attachPrompt67PaymentManager($manager, $organization);

    Livewire::actingAs($manager)
        ->test(Payment::class, ['tableSessionId' => $tableSession->id])
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

    expect($firstGuestPayment->amount_cents)->toBe(2000);

    Livewire::actingAs($manager)
        ->test(Payment::class, ['tableSessionId' => $tableSession->id])
        ->call('recordGuestPayment', $ana->id)
        ->assertHasErrors(['manual_payment']);

    expect(ManualPayment::query()->where('table_session_guest_id', $ana->id)->count())->toBe(1);
});

test('stale repeated payment submissions cannot create a second payment', function (): void {
    [$organization, , $tableSession] = createPrompt67ManualPaymentContext();
    $manager = User::factory()->create(['name' => 'Repeated Payment Manager']);
    attachPrompt67PaymentManager($manager, $organization);
    $firstRequestSession = TableSession::query()->findOrFail($tableSession->id);
    $staleSecondRequestSession = TableSession::query()->findOrFail($tableSession->id);

    app(RecordManualPaymentAction::class)->recordTable(
        tableSession: $firstRequestSession,
        recordedBy: $manager,
        paymentMethod: ManualPaymentMethod::Cash,
    );

    expect(fn () => app(RecordManualPaymentAction::class)->recordTable(
        tableSession: $staleSecondRequestSession,
        recordedBy: $manager,
        paymentMethod: ManualPaymentMethod::Cash,
    ))->toThrow(BusinessRuleViolation::class)
        ->and(ManualPayment::query()->where('table_session_id', $tableSession->id)->count())->toBe(1)
        ->and($tableSession->fresh()->status)->toBe(TableSessionStatus::Paid);
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

    expect($payment->amount_cents)->toBe(3200)
        ->and($payment->update(['amount_cents' => -10000, 'note' => null]))->toBeFalse()
        ->and($payment->fresh()->amount_cents)->toBe(3200)
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
        ->assertSeeText('€32.00');

    Livewire::actingAs($viewer)
        ->test(Payment::class, ['tableSessionId' => $tableSession->id])
        ->assertSet('payment.can_view', true)
        ->assertSet('payment.can_manage', false)
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
        ->test(Payment::class, ['tableSessionId' => $tableSession->id])
        ->assertSet('payment.has_open_draft', true)
        ->assertSet('payment.can_record_table_payment', false)
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
            'total_price_cents' => 3200,
            'currency' => 'EUR',
        ]);

    OrderItem::factory()
        ->for($order)
        ->for($ana, 'guest')
        ->create([
            'guest_name' => 'Ana',
            'item_name' => 'Dinner',
            'unit_price_cents' => 2000,
            'total_price_cents' => 2000,
        ]);
    OrderItem::factory()
        ->for($order)
        ->for($boris, 'guest')
        ->create([
            'guest_name' => 'Boris',
            'item_name' => 'Dessert',
            'unit_price_cents' => 1200,
            'total_price_cents' => 1200,
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

function setPrompt67LegacyPaymentColumnsNullable(bool $nullable): void
{
    Schema::withoutForeignKeyConstraints(function () use ($nullable): void {
        Schema::table('branch_settings', function (Blueprint $table) use ($nullable): void {
            $table->unsignedSmallInteger('service_charge_basis_points')
                ->default(0)
                ->nullable($nullable)
                ->change();
        });

        Schema::table('manual_payments', function (Blueprint $table) use ($nullable): void {
            $table->unsignedBigInteger('covered_subtotal_cents')
                ->default(0)
                ->nullable($nullable)
                ->change();
            $table->unsignedSmallInteger('service_charge_basis_points')
                ->default(0)
                ->nullable($nullable)
                ->change();
            $table->unsignedBigInteger('service_charge_cents')
                ->default(0)
                ->nullable($nullable)
                ->change();
            $table->unsignedBigInteger('tips_cents')
                ->default(0)
                ->nullable($nullable)
                ->change();
            $table->unsignedBigInteger('amount_cents')
                ->default(0)
                ->nullable($nullable)
                ->change();
        });
    });
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
