<?php

declare(strict_types=1);

use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\AreaNodeWaiter;
use App\Models\BranchUser;
use App\Models\DraftOrderItem;
use App\Models\KitchenTicketItem;
use App\Models\ManualPayment;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\PermissionRole;
use App\Models\PermissionUserOverride;
use App\Models\Role;
use App\Models\TableSessionServicePoint;
use App\Models\WaiterCall;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

/**
 * @return list<class-string<Model>>
 */
function firstPartyFactoryModels(): array
{
    $models = [];

    foreach (File::allFiles(app_path('Models')) as $file) {
        $relativePath = str_replace(
            [app_path('Models').DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, '.php'],
            ['', '\\', ''],
            $file->getPathname(),
        );
        $model = 'App\\Models\\'.$relativePath;

        if (! class_exists($model) || ! is_subclass_of($model, Model::class)) {
            continue;
        }

        $reflection = new ReflectionClass($model);

        if (! $reflection->isAbstract()) {
            $models[] = $model;
        }
    }

    sort($models);

    return $models;
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

test('relationship factory defaults preserve ownership and session boundaries', function () {
    $areaAssignment = AreaNodeWaiter::factory()->create();
    $branchMembership = BranchUser::factory()->create();
    $draftItem = DraftOrderItem::factory()->create();
    $ticketItem = KitchenTicketItem::factory()->create();
    $payment = ManualPayment::factory()->create();
    $servicePointLink = TableSessionServicePoint::factory()->create();
    $waiterCall = WaiterCall::factory()->create();

    expect($areaAssignment->branch->organization_id)->toBe($areaAssignment->organization_id)
        ->and($areaAssignment->areaNode->branch_id)->toBe($areaAssignment->branch_id)
        ->and($branchMembership->branch->organization_id)->toBe($branchMembership->organization_id)
        ->and($draftItem->guest->table_session_id)->toBe($draftItem->draftOrder->table_session_id)
        ->and($draftItem->menuItem->menu->branch_id)->toBe($draftItem->draftOrder->tableSession->branch_id)
        ->and($ticketItem->orderItem->order_id)->toBe($ticketItem->kitchenTicket->order_id)
        ->and($payment->branch_id)->toBe($payment->tableSession->branch_id)
        ->and($payment->service_point_id)->toBe($payment->tableSession->service_point_id)
        ->and($servicePointLink->servicePoint->branch_id)->toBe($servicePointLink->tableSession->branch_id)
        ->and($waiterCall->requestedByGuest->table_session_id)->toBe($waiterCall->table_session_id)
        ->and($waiterCall->service_point_id)->toBe($waiterCall->tableSession->service_point_id);
});
