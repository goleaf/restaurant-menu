<?php

use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\AreaNode;
use App\Models\AreaNodeWaiter;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchOpeningHour;
use App\Models\BranchSetting;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\Invitation;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\ManualPayment;
use App\Models\Menu;
use App\Models\MenuAvailabilitySchedule;
use App\Models\MenuCategory;
use App\Models\MenuCategoryTranslation;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\PermissionRole;
use App\Models\PermissionUserOverride;
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

/**
 * @return list<class-string<Model>>
 */
function firstPartyFactoryModels(): array
{
    return [
        User::class,
        Organization::class,
        Brand::class,
        Branch::class,
        BranchSetting::class,
        Role::class,
        Permission::class,
        OrganizationUser::class,
        BranchUser::class,
        Invitation::class,
        AreaNode::class,
        AreaNodeWaiter::class,
        ServicePoint::class,
        QrCode::class,
        TableSession::class,
        TableSessionGuest::class,
        TableSessionJoinRequest::class,
        TableSessionServicePoint::class,
        Menu::class,
        MenuAvailabilitySchedule::class,
        MenuCategory::class,
        MenuCategoryTranslation::class,
        MenuItem::class,
        MenuItemTranslation::class,
        ModifierGroup::class,
        ModifierOption::class,
        DraftOrder::class,
        DraftOrderItem::class,
        Order::class,
        OrderItem::class,
        OrderStatusLog::class,
        AuditLog::class,
        KitchenDepartment::class,
        KitchenTicket::class,
        KitchenTicketItem::class,
        ManualPayment::class,
        OrganizationSubscription::class,
        PermissionRole::class,
        PermissionUserOverride::class,
        BranchOpeningHour::class,
        WaiterCall::class,
    ];
}

test('core flow models expose factories', function () {
    foreach (firstPartyFactoryModels() as $model) {
        $factory = 'Database\\Factories\\'.class_basename($model).'Factory';

        expect(class_exists($factory))->toBeTrue($factory.' is missing.')
            ->and(method_exists($model, 'factory'))->toBeTrue($model.' does not use HasFactory.');
    }
});

test('every first party model factory persists a valid default record', function () {
    foreach (firstPartyFactoryModels() as $model) {
        $record = $model::factory()->create();

        $this->assertModelExists($record);
    }
});

test('permission and membership factories create valid defaults', function () {
    $role = Role::factory()
        ->forSystemRole(SystemRole::Waiter)
        ->create();
    $permission = Permission::factory()
        ->forSystemPermission(SystemPermission::ViewOrders)
        ->create();
    $membership = OrganizationUser::factory()
        ->forRole($role)
        ->active()
        ->create();
    $permissionRole = PermissionRole::factory()
        ->forRole($role)
        ->forPermission($permission)
        ->enabled()
        ->create();
    $override = PermissionUserOverride::factory()
        ->forUser($membership->user)
        ->forPermission($permission)
        ->denied()
        ->create();

    expect($role->code)->toBe(SystemRole::Waiter)
        ->and($permission->code)->toBe(SystemPermission::ViewOrders->value)
        ->and($membership->organization)->not->toBeNull()
        ->and($membership->user)->not->toBeNull()
        ->and($membership->role_id)->toBe($role->id)
        ->and($permissionRole->enabled)->toBeTrue()
        ->and($override->enabled)->toBeFalse();
});
