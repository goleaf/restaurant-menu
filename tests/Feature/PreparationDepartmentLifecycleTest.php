<?php

declare(strict_types=1);

use App\Actions\KitchenDepartments\DeleteKitchenDepartmentAction;
use App\Actions\KitchenDepartments\SetKitchenDepartmentActiveAction;
use App\Actions\KitchenDepartments\UpdateKitchenDepartmentAction;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Menu\KitchenDepartments;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrganizationUser;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('department deactivation cannot hide unfinished or ready unserved work', function (KitchenTicketItemStatus $status): void {
    [$department, $ticket, $item] = preparationDepartmentLifecycleFixture($status);

    expect(fn () => app(SetKitchenDepartmentActiveAction::class)->handle($department, false))->toThrow(ValidationException::class)
        ->and($department->fresh()->is_active)->toBeTrue()
        ->and($ticket->fresh()->kitchen_department_id)->toBe($department->id)
        ->and($item->fresh()->status)->toBe($status);
})->with([KitchenTicketItemStatus::New, KitchenTicketItemStatus::Accepted, KitchenTicketItemStatus::InProgress, KitchenTicketItemStatus::Ready]);

test('department edit cannot bypass active work or change historical routing', function (): void {
    [$department] = preparationDepartmentLifecycleFixture(KitchenTicketItemStatus::InProgress);
    $originalName = $department->name;
    $action = app(UpdateKitchenDepartmentAction::class);

    expect(fn () => $action->handle($department, ['type' => $department->type, 'name' => 'Hidden work', 'sort_order' => 1, 'is_active' => false]))
        ->toThrow(ValidationException::class)
        ->and($department->fresh()->name)->toBe($originalName)
        ->and(fn () => $action->handle($department, ['type' => KitchenDepartmentType::Bar, 'name' => 'Other family', 'sort_order' => 1, 'is_active' => true]))
        ->toThrow(ValidationException::class)
        ->and($department->fresh()->type)->toBe(KitchenDepartmentType::Kitchen);
});

test('department deletion preserves preparation history and its identifiers', function (): void {
    [$department, $ticket] = preparationDepartmentLifecycleFixture(KitchenTicketItemStatus::Cancelled);

    expect(fn () => app(DeleteKitchenDepartmentAction::class)->handle($department))->toThrow(ValidationException::class)
        ->and($department->fresh())->not->toBeNull()
        ->and($ticket->fresh()->kitchen_department_id)->toBe($department->id);
});

test('served or cancelled work permits deactivation while preserving history', function (KitchenTicketItemStatus $status, bool $served): void {
    [$department, $ticket, $item] = preparationDepartmentLifecycleFixture($status);
    if ($served) {
        $item->forceFill(['served_at' => now()])->save();
    }

    app(SetKitchenDepartmentActiveAction::class)->handle($department, false);

    expect($department->fresh()->is_active)->toBeFalse()
        ->and($ticket->fresh()->kitchen_department_id)->toBe($department->id);
})->with(['served' => [KitchenTicketItemStatus::Ready, true], 'cancelled' => [KitchenTicketItemStatus::Cancelled, false]]);

test('an unused department remains editable and removable', function (): void {
    $department = KitchenDepartment::factory()->create();
    app(UpdateKitchenDepartmentAction::class)->handle($department, ['type' => KitchenDepartmentType::Bar, 'name' => 'New bar', 'sort_order' => 1, 'is_active' => false]);
    expect($department->fresh()->type)->toBe(KitchenDepartmentType::Bar)->and($department->fresh()->is_active)->toBeFalse();
    app(DeleteKitchenDepartmentAction::class)->handle($department);
    expect($department->fresh())->toBeNull();
});

test('department management identifies the exact row when deactivation is blocked', function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    [$department] = preparationDepartmentLifecycleFixture(KitchenTicketItemStatus::Ready);
    $branch = $department->branch;
    $user = User::factory()->create();
    OrganizationUser::factory()->forUser($user)->forOrganization($branch->organization)->forSystemRole(SystemRole::Owner)->create();

    Livewire::actingAs($user)->test(KitchenDepartments::class, [
        'organizationId' => $branch->organization_id,
        'brandId' => $branch->brand_id,
        'branchId' => $branch->id,
    ])->call('setKitchenDepartmentActive', $department->id, false)
        ->assertHasErrors(['kitchenDepartment.'.$department->id])
        ->assertSee(__('preparation.department.active_work'));

    expect($department->fresh()->is_active)->toBeTrue();
});

/** @return array{KitchenDepartment, KitchenTicket, KitchenTicketItem} */
function preparationDepartmentLifecycleFixture(KitchenTicketItemStatus $status): array
{
    $department = KitchenDepartment::factory()->create();
    $order = Order::factory()->recycle($department->branch)->sentToDepartments()->create();
    $ticket = KitchenTicket::factory()->forOrder($order)->for($department, 'kitchenDepartment')->create();
    $orderItem = OrderItem::factory()->for($order)->create();
    $item = KitchenTicketItem::factory()->forDispatchedOrderItem($ticket, $orderItem)->create(['status' => $status]);

    return [$department, $ticket, $item];
}
