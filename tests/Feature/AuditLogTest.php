<?php

use App\Actions\AuditLogs\BuildAuditLogIndexAction;
use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Departments\UpdateDepartmentTicketItemStatusAction;
use App\Actions\Orders\ChangeOrderStatusAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Payments\RecordManualPaymentAction;
use App\Actions\QrCodes\ReissueQrCodeForServicePointAction;
use App\Actions\ServicePoints\UpdateServicePointAction;
use App\Actions\Staff\SetUserPermissionOverrideAction;
use App\Actions\TableSessions\CloseTableSessionAction;
use App\Actions\Waiter\ConfirmDraftOrderByWaiterAction;
use App\Actions\Waiter\RejectDraftOrderByWaiterAction;
use App\Actions\Waiter\UpdateDraftOrderItemByWaiterAction;
use App\Enums\AuditLogAction;
use App\Enums\DraftOrderStatus;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\ManualPaymentMethod;
use App\Enums\MenuStatus;
use App\Enums\OrderStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\PermissionOverrideState;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\ServicePointType;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Livewire\AuditLogs\Index as AuditLogIndex;
use App\Models\AreaNode;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\QrCode;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('audit log schema and page are restricted by view audit log permission', function () {
    [$organization, , $branch, , , , , $manager] = createPrompt71Context();
    $viewer = User::factory()->create(['name' => 'Audit Viewer']);
    attachPrompt71Staff($viewer, $organization, []);

    AuditLog::factory()->create([
        'organization_id' => $organization->id,
        'branch_id' => $branch->id,
        'user_id' => $manager->id,
        'action' => AuditLogAction::MenuPriceChanged,
        'entity_type' => 'menu_item',
        'entity_id' => 777,
        'old_values' => ['price_cents' => 1000],
        'new_values' => ['price_cents' => 1200],
    ]);

    expect(Schema::hasTable('audit_logs'))->toBeTrue()
        ->and(Schema::hasColumns('audit_logs', [
            'organization_id',
            'branch_id',
            'user_id',
            'guest_id',
            'guest_display_name',
            'guest_token',
            'action',
            'entity_type',
            'entity_id',
            'old_values',
            'new_values',
            'created_at',
        ]))->toBeTrue();

    $this->get(route('restaurant.audit-log.index'))
        ->assertRedirect(route('login'));

    $this->actingAs($viewer)
        ->get(route('restaurant.audit-log.index'))
        ->assertForbidden();

    grantPrompt71Permission($viewer, $organization, SystemPermission::ViewAuditLog);

    $this->actingAs($viewer)
        ->get(route('restaurant.audit-log.index'))
        ->assertOk()
        ->assertSeeText('Audit log')
        ->assertSeeText('Price changed')
        ->assertSeeText('Audit Viewer');

    Livewire::actingAs($viewer)
        ->test(AuditLogIndex::class)
        ->assertSet('payload.has_access', true)
        ->assertSee('Price changed');
});

test('audit logs are immutable through ordinary model edit and delete paths', function () {
    $auditLog = AuditLog::factory()->create([
        'entity_type' => 'menu_item',
        'old_values' => ['price_cents' => 1000],
        'new_values' => ['price_cents' => 1200],
    ]);

    expect($auditLog->update(['entity_type' => 'tampered']))->toBeFalse()
        ->and($auditLog->fresh()->entity_type)->toBe('menu_item')
        ->and($auditLog->delete())->toBeFalse()
        ->and(AuditLog::query()->whereKey($auditLog->id)->exists())->toBeTrue();
});

test('guest audit actors use display name and values mask sensitive tokens', function () {
    [$organization, , $branch, , $servicePoint] = createPrompt71Context();
    $viewer = User::factory()->create(['name' => 'Guest Audit Viewer']);
    attachPrompt71Staff($viewer, $organization, [SystemPermission::ViewAuditLog]);
    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->create(['status' => TableSessionStatus::Active]);
    $guest = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => '  Ana Guest  ',
            'guest_token' => str_repeat('g', 64),
            'status' => TableSessionGuestStatus::Active,
        ]);

    $auditLog = app(RecordAuditLogAction::class)->handle(
        action: AuditLogAction::DraftOrderEditedByWaiter,
        entityType: 'draft_order',
        entityId: 123,
        actorGuest: $guest,
        guestToken: str_repeat('x', 64),
        organizationId: $organization->id,
        branchId: $branch->id,
        oldValues: [
            'guest_token' => str_repeat('a', 64),
            'nested' => ['invite_token' => str_repeat('b', 64)],
        ],
        newValues: [
            'api_secret' => 'plain-secret',
            'quantity' => 2,
        ],
    );

    $payload = app(BuildAuditLogIndexAction::class)->handle($viewer);
    $row = collect($payload['logs']->items())->firstWhere('id', $auditLog->id);

    expect($auditLog->fresh()->guest_id)->toBe($guest->id)
        ->and($auditLog->fresh()->guest_display_name)->toBe('Ana Guest')
        ->and($auditLog->fresh()->guest_token)->toBeNull()
        ->and($auditLog->fresh()->old_values['guest_token'])->toBe('[redacted]')
        ->and($auditLog->fresh()->old_values['nested']['invite_token'])->toBe('[redacted]')
        ->and($auditLog->fresh()->new_values['api_secret'])->toBe('[redacted]')
        ->and($row['actor'])->toBe('Ana Guest')
        ->and($row['old_summary'])->not->toContain(str_repeat('a', 64))
        ->and($row['old_summary'])->not->toContain(str_repeat('b', 64))
        ->and($row['new_summary'])->not->toContain('plain-secret');
});

test('menu service point qr and staff changes create audit events', function () {
    [$organization, , , $firstArea, $servicePoint, $menuItem, $qrCode, $manager] = createPrompt71Context();
    $terrace = AreaNode::factory()->for($servicePoint->branch)->create(['name' => 'Terrace']);
    $staff = User::factory()->create(['name' => 'Permission Target']);
    attachPrompt71Staff($staff, $organization, [SystemPermission::ViewOrders]);
    $viewOrders = Permission::query()
        ->where('code', SystemPermission::ViewOrders->value)
        ->firstOrFail();

    $this->actingAs($manager);

    $menuItem->update(['price_cents' => 1450]);
    $menuItem->update(['is_available' => false]);
    $menuItemId = $menuItem->id;
    $menuItem->delete();

    app(UpdateServicePointAction::class)->handle(
        servicePoint: $servicePoint,
        data: [
            'area_node_id' => $terrace->id,
            'type' => ServicePointType::Table->value,
            'name' => 'Moved table',
            'display_number' => 'M-1',
            'capacity' => 4,
            'icon' => 'sparkles',
            'is_active' => true,
        ],
        updatedBy: $manager,
    );

    app(ReissueQrCodeForServicePointAction::class)->handle($qrCode, $manager);
    app(SetUserPermissionOverrideAction::class)->handle($staff, $viewOrders, PermissionOverrideState::Deny, $manager, $organization->id);

    $moveLog = expectPrompt71Audit(AuditLogAction::ServicePointMoved, 'service_point', $servicePoint->id);
    $qrLog = expectPrompt71Audit(AuditLogAction::QrReissued, 'qr_code');
    $staffLog = expectPrompt71Audit(AuditLogAction::StaffPermissionChanged, 'staff_permission', $staff->id);

    expect(expectPrompt71Audit(AuditLogAction::MenuPriceChanged, 'menu_item', $menuItemId))->toBeInstanceOf(AuditLog::class)
        ->and(expectPrompt71Audit(AuditLogAction::MenuAvailabilityChanged, 'menu_item', $menuItemId))->toBeInstanceOf(AuditLog::class)
        ->and(expectPrompt71Audit(AuditLogAction::MenuItemDeleted, 'menu_item', $menuItemId))->toBeInstanceOf(AuditLog::class)
        ->and($moveLog->old_values['area_node_id'])->toBe($firstArea->id)
        ->and($moveLog->new_values['area_node_id'])->toBe($terrace->id)
        ->and($qrLog->new_values['service_point_id'])->toBe($servicePoint->id)
        ->and($staffLog->new_values['state'])->toBe('deny');
});

test('order payment and table session actions create audit events', function () {
    [$organization, , , , $servicePoint, $menuItem, , $manager] = createPrompt71Context();
    attachPrompt71Staff($manager, $organization, [
        SystemPermission::ViewOrders,
        SystemPermission::ConfirmOrders,
        SystemPermission::CancelOrders,
        SystemPermission::ManagePayments,
        SystemPermission::CloseTableSessions,
        SystemPermission::ViewKitchen,
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
    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create([
            'status' => DraftOrderStatus::SentToWaiter,
            'sent_to_waiter_at' => now(),
            'sent_by_guest_id' => $guest->id,
        ]);
    $draftOrderItem = DraftOrderItem::factory()
        ->for($draftOrder)
        ->for($guest, 'guest')
        ->for($menuItem)
        ->create([
            'item_name' => $menuItem->name,
            'unit_price_cents' => 1800,
            'total_price_cents' => 1800,
        ]);

    app(UpdateDraftOrderItemByWaiterAction::class)->handle(
        draftOrderItem: $draftOrderItem,
        editedBy: $manager,
        quantity: 1,
        selectedModifierOptions: [],
        comment: 'No onions',
    );

    $order = app(ConfirmDraftOrderByWaiterAction::class)->handle($draftOrder, $manager);
    $orderItem = $order->items()->firstOrFail();
    $department = KitchenDepartment::factory()
        ->for($servicePoint->branch)
        ->create([
            'type' => KitchenDepartmentType::Kitchen,
            'name' => 'Audit Kitchen',
            'is_active' => true,
        ]);
    $ticket = KitchenTicket::factory()
        ->for($order)
        ->create([
            'branch_id' => $order->branch_id,
            'service_point_id' => $order->service_point_id,
            'table_session_id' => $order->table_session_id,
            'kitchen_department_id' => $department->id,
            'department_type' => KitchenDepartmentType::Kitchen->value,
            'department_name' => $department->name,
        ]);
    $ticketItem = KitchenTicketItem::factory()
        ->for($ticket)
        ->for($orderItem)
        ->create([
            'table_session_guest_id' => $orderItem->table_session_guest_id,
            'menu_item_id' => $orderItem->menu_item_id,
            'item_name' => $orderItem->item_name,
            'quantity' => $orderItem->quantity,
            'status' => KitchenTicketItemStatus::New,
        ]);

    app(UpdateDepartmentTicketItemStatusAction::class)->handle(
        item: $ticketItem,
        status: KitchenTicketItemStatus::Ready,
        user: $manager,
        departmentTypes: [KitchenDepartmentType::Kitchen],
        roleCodes: [],
        permissionCodes: [SystemPermission::ViewKitchen],
    );

    app(RecordManualPaymentAction::class)->recordTable(
        tableSession: $tableSession,
        recordedBy: $manager,
        paymentMethod: ManualPaymentMethod::Cash,
    );

    app(ChangeOrderStatusAction::class)->handle(
        order: $order,
        newStatus: OrderStatus::Cancelled,
        changedBy: $manager,
        reason: 'Audit test cancellation',
    );

    app(CloseTableSessionAction::class)->handle($tableSession, $manager);

    expect(expectPrompt71Audit(AuditLogAction::DraftOrderEditedByWaiter, 'draft_order_item', $draftOrderItem->id)->new_values['operation'])->toBe('waiter_item_updated')
        ->and(expectPrompt71Audit(AuditLogAction::OrderConfirmed, 'order', $order->id)->new_values['order_status'])->toBe(OrderStatus::ConfirmedByWaiter->value)
        ->and(expectPrompt71Audit(AuditLogAction::DepartmentItemReady, 'kitchen_ticket_item', $ticketItem->id)->new_values['status'])->toBe(KitchenTicketItemStatus::Ready->value)
        ->and(expectPrompt71Audit(AuditLogAction::PaymentRecorded, 'manual_payment')->new_values['amount_cents'])->toBe(1800)
        ->and(expectPrompt71Audit(AuditLogAction::OrderCancelled, 'order', $order->id)->new_values['reason'])->toBe('Audit test cancellation')
        ->and(expectPrompt71Audit(AuditLogAction::TableSessionClosed, 'table_session', $tableSession->id)->new_values['status'])->toBe(TableSessionStatus::Closed->value);
});

test('waiter rejected drafts create audit events', function () {
    [$organization, , , , $servicePoint, $menuItem, , $manager] = createPrompt71Context();
    attachPrompt71Staff($manager, $organization, [
        SystemPermission::ViewOrders,
        SystemPermission::ConfirmOrders,
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
    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create([
            'status' => DraftOrderStatus::SentToWaiter,
            'sent_to_waiter_at' => now(),
            'sent_by_guest_id' => $guest->id,
        ]);
    DraftOrderItem::factory()
        ->for($draftOrder)
        ->for($guest, 'guest')
        ->for($menuItem)
        ->create([
            'item_name' => $menuItem->name,
            'unit_price_cents' => 1800,
            'total_price_cents' => 1800,
        ]);

    app(RejectDraftOrderByWaiterAction::class)->handle(
        draftOrder: $draftOrder,
        rejectedBy: $manager,
        reason: 'Out of stock',
    );

    $auditLog = expectPrompt71Audit(AuditLogAction::DraftOrderRejected, 'draft_order', $draftOrder->id);

    expect($auditLog->user_id)->toBe($manager->id)
        ->and($auditLog->branch_id)->toBe($servicePoint->branch_id)
        ->and($auditLog->new_values['status'])->toBe(DraftOrderStatus::Rejected->value)
        ->and($auditLog->new_values['reason'])->toBe('Out of stock')
        ->and($auditLog->created_at)->not->toBeNull();
});

function createPrompt71Context(): array
{
    $manager = User::factory()->create(['name' => 'Prompt 71 Manager']);
    $organization = (new CreateOrganizationAction)->handle($manager, ['name' => 'Prompt 71 Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Prompt 71 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 71 Branch',
            'currency' => 'EUR',
        ]);
    $area = AreaNode::factory()->for($branch)->create(['name' => 'Main hall']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($area)
        ->create([
            'name' => 'Prompt 71 Table',
            'status' => ServicePointStatus::Occupied,
            'is_active' => true,
        ]);
    $menu = Menu::factory()
        ->for($branch)
        ->create([
            'name' => 'Prompt 71 Menu',
            'status' => MenuStatus::Active,
        ]);
    $category = MenuCategory::factory()
        ->for($menu)
        ->create([
            'name' => 'Main',
            'is_active' => true,
        ]);
    $menuItem = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create([
            'name' => 'Audit pasta',
            'price_cents' => 1200,
            'is_available' => true,
        ]);
    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'public_token' => 'prompt71auditpublictoken',
            'short_code' => 'QR-P71AUD',
            'status' => QrCodeStatus::Active,
            'created_by_user_id' => $manager->id,
        ]);

    return [$organization, $brand, $branch, $area, $servicePoint, $menuItem, $qrCode, $manager->fresh()];
}

/**
 * @param  list<SystemPermission>  $permissions
 */
function attachPrompt71Staff(User $user, Organization $organization, array $permissions): void
{
    $role = Role::query()
        ->where('code', SystemRole::Waiter->value)
        ->firstOrFail();

    foreach ($permissions as $permission) {
        grantPrompt71RolePermission($role, $permission);
    }

    $organization->users()->syncWithoutDetachingOrFail([
        $user->id => [
            'role_id' => $role->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ],
    ]);
}

function grantPrompt71Permission(User $user, Organization $organization, SystemPermission $permission): void
{
    $membership = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $user->id)
        ->firstOrFail();

    grantPrompt71RolePermission($membership->role, $permission);
}

function grantPrompt71RolePermission(Role $role, SystemPermission $permission): void
{
    $permissionModel = Permission::query()
        ->where('code', $permission->value)
        ->firstOrFail();

    $role->permissions()->updateExistingPivot($permissionModel->id, ['enabled' => true]);
}

function expectPrompt71Audit(AuditLogAction $action, string $entityType, ?int $entityId = null): AuditLog
{
    return AuditLog::query()
        ->where('action', $action->value)
        ->where('entity_type', $entityType)
        ->when($entityId !== null, fn ($query) => $query->where('entity_id', $entityId))
        ->latest('id')
        ->firstOrFail();
}
