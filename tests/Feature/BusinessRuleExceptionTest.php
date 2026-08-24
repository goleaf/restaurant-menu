<?php

use App\Actions\Departments\UpdateDepartmentTicketItemStatusAction;
use App\Actions\DraftOrders\Support\BuildDraftOrderItemModifierSnapshots;
use App\Actions\Orders\ChangeOrderStatusAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Payments\RecordManualPaymentAction;
use App\Actions\Waiter\AddDraftOrderItemByWaiterAction;
use App\Actions\Waiter\EnsureWaiterCanEditDraftOrderAction;
use App\Enums\ApplicationErrorType;
use App\Enums\BusinessRuleCode;
use App\Enums\DraftOrderStatus;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\ManualPaymentMethod;
use App\Enums\MenuStatus;
use App\Enums\OrderStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use App\Support\BusinessRules\BusinessRuleResult;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('business rule codes cover expected domain denials', function () {
    $codeClass = 'App\\Enums\\BusinessRuleCode';

    expect(enum_exists($codeClass))->toBeTrue()
        ->and($codeClass::values())->toBe([
            'session_closed',
            'draft_locked',
            'guest_not_active',
            'guest_not_approved',
            'order_already_cancelled',
            'order_item_already_cancelled',
            'order_item_not_cancellable',
            'payment_already_recorded',
            'department_already_ready',
            'payment_exceeds_remaining',
            'qr_disabled',
            'branch_inaccessible',
            'item_unavailable',
            'required_modifier_missing',
            'service_point_has_active_session',
            'structure_has_active_order',
        ]);
});

test('business rule violation is validation safe and not reportable', function () {
    $codeClass = 'App\\Enums\\BusinessRuleCode';
    $exceptionClass = 'App\\Exceptions\\BusinessRuleViolation';
    $resultClass = 'App\\Support\\BusinessRules\\BusinessRuleResult';

    expect(class_exists($exceptionClass))->toBeTrue()
        ->and(class_exists($resultClass))->toBeTrue();

    $exception = $exceptionClass::for($codeClass::SessionClosed, 'draft_edit');

    expect($exception)->toBeInstanceOf(ValidationException::class)
        ->and($exception)->toBeInstanceOf(ShouldntReport::class)
        ->and($exception->businessRule())->toBe($codeClass::SessionClosed)
        ->and($exception->errorType())->toBe(ApplicationErrorType::SessionClosed)
        ->and($exception->errors()['draft_edit'][0])->toBe(__('ui.enums.businessrulecode.nelzia_vypolnit_deistvie_dlia_zakrytogo_stola'))
        ->and($exception->errors()['draft_edit'][0])->not->toContain('SessionClosed')
        ->and($exception->errors()['draft_edit'][0])->not->toContain('session_closed');
});

test('business rule result represents allowed and denied outcomes', function (): void {
    $allowed = BusinessRuleResult::allowed();

    $allowed->throwIfDenied();

    expect($allowed->allowed)->toBeTrue()
        ->and($allowed->rule)->toBeNull()
        ->and($allowed->field)->toBe('business_rule')
        ->and($allowed->context)->toBe([]);

    $denied = BusinessRuleResult::denied(
        rule: BusinessRuleCode::SessionClosed,
        field: 'table_session',
        message: 'The table session is closed.',
        context: ['table_session_id' => 91],
    );

    try {
        $denied->throwIfDenied();
        $this->fail('Expected the denied result to throw a business rule violation.');
    } catch (BusinessRuleViolation $exception) {
        expect($denied->allowed)->toBeFalse()
            ->and($denied->rule)->toBe(BusinessRuleCode::SessionClosed)
            ->and($exception->businessRule())->toBe(BusinessRuleCode::SessionClosed)
            ->and($exception->field())->toBe('table_session')
            ->and($exception->errors()['table_session'][0])->toBe('The table session is closed.')
            ->and($exception->context())->toBe(['table_session_id' => 91]);
    }
});

test('closed session draft edit returns controlled business rule error', function () {
    [$draftOrder, $waiter] = createBusinessRuleClosedDraftScenario();

    try {
        app(EnsureWaiterCanEditDraftOrderAction::class)->handle($draftOrder, $waiter);
        $this->fail('Expected closed session to return a business rule violation.');
    } catch (Throwable $exception) {
        expect($exception::class)->toBe('App\\Exceptions\\BusinessRuleViolation')
            ->and($exception->businessRule()->value)->toBe('session_closed')
            ->and($exception->errors()['draft_edit'][0])->toBe(__('ui.actions.waiter.ensurewaitercaneditdraftorderaction.nelzia_redaktirovat_z'));
    }
});

test('required modifier missing returns controlled business rule error', function () {
    $menuItem = createBusinessRuleMenuItem();
    $modifierGroup = ModifierGroup::factory()
        ->for($menuItem->menu->branch)
        ->create([
            'name' => 'Required side',
            'is_required' => true,
            'min_select' => 1,
            'max_select' => 1,
        ]);
    ModifierOption::factory()
        ->for($modifierGroup)
        ->create(['name' => 'Rice', 'is_available' => true]);
    $menuItem->modifierGroups()->syncWithoutDetaching([$modifierGroup->id]);

    try {
        app(BuildDraftOrderItemModifierSnapshots::class)->snapshotsFor(
            app(BuildDraftOrderItemModifierSnapshots::class)->groupsFor($menuItem),
            [],
        );
        $this->fail('Expected required modifier to return a business rule violation.');
    } catch (Throwable $exception) {
        expect($exception::class)->toBe('App\\Exceptions\\BusinessRuleViolation')
            ->and($exception->businessRule())->toBe(BusinessRuleCode::RequiredModifierMissing)
            ->and($exception->errors()['selectedModifierOptions.'.$modifierGroup->id][0])->toBe(__('ui.actions.draftorders.support.builddraftorderitemmodifiersnapshots.vyberite_var'));
    }
});

test('already cancelled order returns controlled business rule error', function () {
    [$organization, $branch] = createBusinessRuleBranch();
    $order = Order::factory()
        ->create([
            'branch_id' => $branch->id,
            'status' => OrderStatus::Cancelled,
        ]);
    $waiter = User::factory()->create();
    attachBusinessRuleStaff($waiter, $organization, SystemRole::Waiter, [SystemPermission::CancelOrders]);

    try {
        app(ChangeOrderStatusAction::class)->handle(
            order: $order,
            newStatus: OrderStatus::Cancelled,
            changedBy: $waiter,
            reason: 'Already cancelled test.',
        );
        $this->fail('Expected already-cancelled order to return a business rule violation.');
    } catch (Throwable $exception) {
        expect($exception::class)->toBe('App\\Exceptions\\BusinessRuleViolation')
            ->and($exception->businessRule())->toBe(BusinessRuleCode::OrderAlreadyCancelled)
            ->and($exception->errors()['order_status'][0])->toBe(__('ui.actions.orders.changeorderstatusaction.zakaz_uze_otmenen'));
    }
});

test('repeating a ready department item transition is idempotent', function () {
    [$organization, $branch] = createBusinessRuleBranch();
    $department = KitchenDepartment::factory()
        ->for($branch)
        ->create([
            'type' => KitchenDepartmentType::Kitchen,
            'name' => 'Business Rule Kitchen',
        ]);
    $order = Order::factory()->create(['branch_id' => $branch->id]);
    $ticket = KitchenTicket::factory()
        ->for($order)
        ->create([
            'branch_id' => $branch->id,
            'service_point_id' => $order->service_point_id,
            'table_session_id' => $order->table_session_id,
            'kitchen_department_id' => $department->id,
            'department_type' => KitchenDepartmentType::Kitchen,
        ]);
    $item = KitchenTicketItem::factory()
        ->for($ticket, 'kitchenTicket')
        ->create(['status' => KitchenTicketItemStatus::Ready]);
    $chef = User::factory()->create();
    attachBusinessRuleStaff($chef, $organization, SystemRole::HeadChef);

    $updatedAt = $item->updated_at;
    $result = app(UpdateDepartmentTicketItemStatusAction::class)->handle(
        itemId: $item->id,
        status: KitchenTicketItemStatus::Ready,
        user: $chef,
        departmentTypes: [KitchenDepartmentType::Kitchen],
        roleCodes: [SystemRole::HeadChef],
        permissionCodes: [SystemPermission::ViewKitchen],
    );

    expect($result->status)->toBe(KitchenTicketItemStatus::Ready)
        ->and($result->updated_at?->equalTo($updatedAt))->toBeTrue();
});

test('pending guest waiter item add returns controlled business rule error', function () {
    [, , $draftOrder, $guest, $menuItem, $waiter] = createBusinessRuleEditableDraftScenario(
        guestStatus: TableSessionGuestStatus::PendingApproval,
    );

    try {
        app(AddDraftOrderItemByWaiterAction::class)->handle(
            draftOrder: $draftOrder,
            guest: $guest,
            menuItem: $menuItem,
            editedBy: $waiter,
            quantity: 1,
            selectedModifierOptions: [],
        );
        $this->fail('Expected pending guest to return a business rule violation.');
    } catch (Throwable $exception) {
        expect($exception::class)->toBe('App\\Exceptions\\BusinessRuleViolation')
            ->and($exception->businessRule())->toBe(BusinessRuleCode::GuestNotApproved)
            ->and($exception->errors()['addingGuestId'][0])->toBe(__('ui.actions.waiter.adddraftorderitembywaiteraction.gost_eshhe_ne_podtverzden'));
    }
});

test('unavailable menu item waiter add returns controlled business rule error', function () {
    [, , $draftOrder, $guest, $menuItem, $waiter] = createBusinessRuleEditableDraftScenario();
    $menuItem->forceFill(['is_available' => false])->save();

    try {
        app(AddDraftOrderItemByWaiterAction::class)->handle(
            draftOrder: $draftOrder,
            guest: $guest,
            menuItem: $menuItem,
            editedBy: $waiter,
            quantity: 1,
            selectedModifierOptions: [],
        );
        $this->fail('Expected unavailable item to return a business rule violation.');
    } catch (Throwable $exception) {
        expect($exception::class)->toBe('App\\Exceptions\\BusinessRuleViolation')
            ->and($exception->businessRule())->toBe(BusinessRuleCode::ItemUnavailable)
            ->and($exception->errors()['addingMenuItemId'][0])->toBe(__('ui.actions.waiter.adddraftorderitembywaiteraction.eto_bliudo_seicas_nedostu'));
    }
});

test('payment branch access denial returns controlled business rule error', function () {
    [, , $tableSession] = createBusinessRulePaymentSession();
    $user = User::factory()->create();

    try {
        app(RecordManualPaymentAction::class)->recordTable(
            tableSession: $tableSession,
            recordedBy: $user,
            paymentMethod: ManualPaymentMethod::Cash,
        );
        $this->fail('Expected inaccessible branch payment to return a business rule violation.');
    } catch (Throwable $exception) {
        expect($exception::class)->toBe('App\\Exceptions\\BusinessRuleViolation')
            ->and($exception->businessRule())->toBe(BusinessRuleCode::BranchInaccessible)
            ->and($exception->errors()['manual_payment'][0])->toBe(__('payments.errors.permission_denied'));
    }
});

test('paid session manual payment returns controlled business rule error', function () {
    [$organization, , $tableSession] = createBusinessRulePaymentSession(TableSessionStatus::Paid);
    $manager = User::factory()->create();
    attachBusinessRuleStaff($manager, $organization, SystemRole::RestaurantAdmin, [SystemPermission::ManagePayments]);

    try {
        app(RecordManualPaymentAction::class)->recordTable(
            tableSession: $tableSession,
            recordedBy: $manager,
            paymentMethod: ManualPaymentMethod::Cash,
        );
        $this->fail('Expected paid session payment to return a business rule violation.');
    } catch (Throwable $exception) {
        expect($exception::class)->toBe('App\\Exceptions\\BusinessRuleViolation')
            ->and($exception->businessRule())->toBe(BusinessRuleCode::PaymentExceedsRemaining)
            ->and($exception->errors()['manual_payment'][0])->toBe(__('payments.messages.session_paid'));
    }
});

function createBusinessRuleClosedDraftScenario(): array
{
    [$organization, $branch] = createBusinessRuleBranch();
    $areaNode = AreaNode::factory()->for($branch)->create(['name' => 'Business Rule Hall']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($areaNode)
        ->create([
            'name' => 'Closed session table',
            'status' => ServicePointStatus::Free,
        ]);
    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->create(['status' => TableSessionStatus::Closed]);
    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create(['status' => DraftOrderStatus::SentToWaiter]);
    $waiter = User::factory()->create();
    attachBusinessRuleStaff($waiter, $organization, SystemRole::Waiter, [SystemPermission::ConfirmOrders], $branch);

    return [$draftOrder, $waiter];
}

function createBusinessRuleEditableDraftScenario(TableSessionGuestStatus $guestStatus = TableSessionGuestStatus::Active): array
{
    [$organization, $branch] = createBusinessRuleBranch();
    $areaNode = AreaNode::factory()->for($branch)->create(['name' => 'Business Rule Active Hall']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($areaNode)
        ->create([
            'name' => 'Editable draft table',
            'status' => ServicePointStatus::Occupied,
        ]);
    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->create(['status' => TableSessionStatus::Active]);
    $guest = TableSessionGuest::factory()
        ->for($tableSession)
        ->create(['status' => $guestStatus]);
    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create(['status' => DraftOrderStatus::SentToWaiter]);
    $menu = Menu::factory()
        ->for($branch)
        ->create(['status' => MenuStatus::Active]);
    $category = MenuCategory::factory()
        ->for($menu)
        ->create(['is_active' => true]);
    $menuItem = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create(['is_available' => true]);
    $waiter = User::factory()->create();
    attachBusinessRuleStaff(
        $waiter,
        $organization,
        SystemRole::Waiter,
        [SystemPermission::ConfirmOrders, SystemPermission::EditPendingOrders],
        $branch,
    );

    return [$organization, $branch, $draftOrder, $guest, $menuItem, $waiter];
}

function createBusinessRulePaymentSession(TableSessionStatus $status = TableSessionStatus::PaymentRequested): array
{
    [$organization, $branch] = createBusinessRuleBranch();
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Business Rule Payment Table',
            'status' => ServicePointStatus::PaymentRequested,
            'is_active' => true,
        ]);
    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->create(['status' => $status]);

    return [$organization, $branch, $tableSession];
}

function createBusinessRuleBranch(): array
{
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Business Rule Group']);
    $brand = Brand::factory()
        ->for($organization)
        ->create(['name' => 'Business Rule Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create(['name' => 'Business Rule Branch']);

    return [$organization, $branch, $owner];
}

function createBusinessRuleMenuItem(): MenuItem
{
    [, $branch] = createBusinessRuleBranch();
    $menu = Menu::factory()
        ->for($branch)
        ->create(['status' => MenuStatus::Active]);
    $category = MenuCategory::factory()
        ->for($menu)
        ->create(['is_active' => true]);

    return MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create(['is_available' => true]);
}

function attachBusinessRuleStaff(
    User $user,
    $organization,
    SystemRole $roleCode,
    array $permissions = [],
    ?Branch $branch = null,
): Role {
    $role = Role::query()
        ->where('code', $roleCode->value)
        ->firstOrFail();

    $user->roles()->syncWithoutDetachingOrFail([$role->id]);

    foreach ($permissions as $permission) {
        $role->permissions()->updateExistingPivot(
            Permission::query()->where('code', $permission->value)->firstOrFail()->id,
            ['enabled' => true],
        );
    }

    $organizationUser = new OrganizationUser;
    $organizationUser->forceFill([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role_id' => $role->id,
        'status' => OrganizationUserStatus::Active,
        'joined_at' => now(),
        'invited_by_user_id' => null,
    ])->save();

    if ($branch instanceof Branch) {
        $branchUser = new BranchUser;
        $branchUser->forceFill([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => OrganizationUserStatus::Active,
            'assigned_at' => now(),
            'assigned_by_user_id' => null,
        ])->save();
    }

    return $role;
}
