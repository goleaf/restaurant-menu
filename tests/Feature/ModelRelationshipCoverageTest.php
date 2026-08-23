<?php

declare(strict_types=1);

use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\AreaNode;
use App\Models\AreaNodeWaiter;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\Invitation;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\MenuItem;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\QrCode;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;
use App\Models\TableSessionServicePoint;
use App\Models\User;
use App\Models\WaiterCall;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

test('model relationship method returns an Eloquent relation', function (string $modelClass, string $relationship): void {
    /** @var Model $model */
    $model = new $modelClass;
    $relation = $model->{$relationship}();

    expect($relation)->toBeInstanceOf(Relation::class)
        ->and($relation->getParent())->toBe($model)
        ->and($relation->getRelated())->toBeInstanceOf(Model::class);
})->with([
    'area node waiter assignments' => [AreaNode::class, 'waiterAssignments'],
    'area waiter organization' => [AreaNodeWaiter::class, 'organization'],
    'area waiter user' => [AreaNodeWaiter::class, 'user'],
    'area waiter assigner' => [AreaNodeWaiter::class, 'assignedBy'],
    'branch staff assignments' => [Branch::class, 'staffAssignments'],
    'branch waiter area assignments' => [Branch::class, 'waiterAreaAssignments'],
    'branch waiter calls' => [Branch::class, 'waiterCalls'],
    'branch kitchen tickets' => [Branch::class, 'kitchenTickets'],
    'branch order status logs' => [Branch::class, 'orderStatusLogs'],
    'branch invitations' => [Branch::class, 'invitations'],
    'branch user organization' => [BranchUser::class, 'organization'],
    'branch user assigner' => [BranchUser::class, 'assignedBy'],
    'brand invitations' => [Brand::class, 'invitations'],
    'draft status logs' => [DraftOrder::class, 'statusLogs'],
    'draft item variant' => [DraftOrderItem::class, 'menuItemVariant'],
    'invitation brand' => [Invitation::class, 'brand'],
    'invitation inviter' => [Invitation::class, 'invitedBy'],
    'invitation accepter' => [Invitation::class, 'acceptedBy'],
    'department order items' => [KitchenDepartment::class, 'orderItems'],
    'department kitchen tickets' => [KitchenDepartment::class, 'kitchenTickets'],
    'ticket sender' => [KitchenTicket::class, 'sentByUser'],
    'ticket item menu item' => [KitchenTicketItem::class, 'menuItem'],
    'ticket item server' => [KitchenTicketItem::class, 'servedByUser'],
    'menu item draft items' => [MenuItem::class, 'draftOrderItems'],
    'menu item order items' => [MenuItem::class, 'orderItems'],
    'order item variant' => [OrderItem::class, 'menuItemVariant'],
    'status log branch' => [OrderStatusLog::class, 'branch'],
    'status log service point' => [OrderStatusLog::class, 'servicePoint'],
    'status log table session' => [OrderStatusLog::class, 'tableSession'],
    'status log draft order' => [OrderStatusLog::class, 'draftOrder'],
    'status log order' => [OrderStatusLog::class, 'order'],
    'status log user actor' => [OrderStatusLog::class, 'actorUser'],
    'status log guest actor' => [OrderStatusLog::class, 'actorGuest'],
    'organization branch users' => [Organization::class, 'branchUsers'],
    'organization invitations' => [Organization::class, 'invitations'],
    'organization active users' => [Organization::class, 'activeUsers'],
    'organization membership inviter' => [OrganizationUser::class, 'invitedBy'],
    'permission roles' => [Permission::class, 'roles'],
    'permission user overrides' => [Permission::class, 'usersWithOverrides'],
    'qr active service point' => [QrCode::class, 'activeServicePoint'],
    'role users' => [Role::class, 'users'],
    'role invitations' => [Role::class, 'invitations'],
    'role branch users' => [Role::class, 'branchUsers'],
    'service point waiter calls' => [ServicePoint::class, 'waiterCalls'],
    'service point kitchen tickets' => [ServicePoint::class, 'kitchenTickets'],
    'service point order status logs' => [ServicePoint::class, 'orderStatusLogs'],
    'service point manual payments' => [ServicePoint::class, 'manualPayments'],
    'service point session links' => [ServicePoint::class, 'tableSessionServicePointLinks'],
    'session invite creator' => [TableSession::class, 'guestInviteCreatedByGuest'],
    'session waiter calls' => [TableSession::class, 'waiterCalls'],
    'session kitchen tickets' => [TableSession::class, 'kitchenTickets'],
    'session order status logs' => [TableSession::class, 'orderStatusLogs'],
    'session service point links' => [TableSession::class, 'servicePointLinks'],
    'session guest waiter calls' => [TableSessionGuest::class, 'waiterCalls'],
    'session guest order items' => [TableSessionGuest::class, 'orderItems'],
    'session guest order status logs' => [TableSessionGuest::class, 'orderStatusLogs'],
    'session guest manual payments' => [TableSessionGuest::class, 'manualPayments'],
    'join request approver' => [TableSessionJoinRequest::class, 'approvedByUser'],
    'join request rejecter' => [TableSessionJoinRequest::class, 'rejectedByUser'],
    'session link creator' => [TableSessionServicePoint::class, 'linkedByUser'],
    'session link remover' => [TableSessionServicePoint::class, 'unlinkedByUser'],
    'user owned organizations' => [User::class, 'ownedOrganizations'],
    'user area assignments' => [User::class, 'areaNodeAssignments'],
    'user sent invitations' => [User::class, 'sentInvitations'],
    'user order status logs' => [User::class, 'orderStatusLogs'],
    'user handled waiter calls' => [User::class, 'handledWaiterCalls'],
    'user manual payments' => [User::class, 'manualPayments'],
    'waiter call active service point' => [WaiterCall::class, 'activeServicePoint'],
    'waiter call handler' => [WaiterCall::class, 'handledByUser'],
]);

test('cancelled order item scope returns only cancelled rows', function (): void {
    $active = OrderItem::factory()->create();
    $cancelled = OrderItem::factory()->cancelled()->create();

    expect(OrderItem::query()->cancelled()->pluck('id')->all())
        ->toBe([$cancelled->id])
        ->not->toContain($active->id);
});

test('role permission helper honors the enabled pivot flag for enum and string codes', function (): void {
    $role = Role::factory()->forSystemRole(SystemRole::Waiter)->create();
    $permission = Permission::factory()->forSystemPermission(SystemPermission::ViewOrders)->create();
    $role->permissions()->attach($permission, ['enabled' => true]);

    expect($role->hasPermission(SystemPermission::ViewOrders))->toBeTrue()
        ->and($role->hasPermission(SystemPermission::ViewOrders->value))->toBeTrue();

    $role->permissions()->updateExistingPivot($permission->id, ['enabled' => false]);

    expect($role->hasPermission(SystemPermission::ViewOrders))->toBeFalse();
});
