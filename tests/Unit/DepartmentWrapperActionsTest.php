<?php

declare(strict_types=1);

use App\Actions\Bar\BuildBarDashboardAction;
use App\Actions\Bar\UpdateBarTicketItemStatusAction;
use App\Actions\Departments\BuildDepartmentDashboardAction;
use App\Actions\Departments\UpdateDepartmentTicketItemStatusAction;
use App\Actions\Kitchen\BuildKitchenDashboardAction;
use App\Actions\Kitchen\UpdateKitchenTicketItemStatusAction;
use App\Actions\Payments\ClosePaidTableSessionAction;
use App\Actions\TableSessions\CloseTableSessionAction;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\KitchenTicketItem;
use App\Models\TableSession;
use App\Models\User;

test('bar dashboard action delegates the exact bar access contract', function (): void {
    $user = new User;
    $expected = departmentDashboardPayload();
    $delegate = Mockery::mock(BuildDepartmentDashboardAction::class);
    $delegate->shouldReceive('handle')
        ->once()
        ->withArgs(fn (User $actualUser, ?int $departmentId, array $types, array $roles, array $permissions): bool => $actualUser === $user
            && $departmentId === 42
            && $types === [KitchenDepartmentType::Bar]
            && $roles === [SystemRole::Bartender, SystemRole::HeadChef]
            && $permissions === [SystemPermission::ViewOrders, SystemPermission::SendToKitchen])
        ->andReturn($expected);
    $delegate->shouldReceive('userHasAccess')
        ->once()
        ->withArgs(fn (User $actualUser, array $types, array $roles, array $permissions): bool => $actualUser === $user
            && $types === [KitchenDepartmentType::Bar]
            && $roles === [SystemRole::Bartender, SystemRole::HeadChef]
            && $permissions === [SystemPermission::ViewOrders, SystemPermission::SendToKitchen])
        ->andReturnTrue();

    $action = new BuildBarDashboardAction($delegate);

    expect($action->handle($user, 42))->toBe($expected)
        ->and($action->userHasAccess($user))->toBeTrue();
});

test('kitchen dashboard action delegates the exact kitchen access contract', function (): void {
    $user = new User;
    $expected = departmentDashboardPayload();
    $delegate = Mockery::mock(BuildDepartmentDashboardAction::class);
    $delegate->shouldReceive('handle')
        ->once()
        ->withArgs(fn (User $actualUser, ?int $departmentId, array $types, array $roles, array $permissions): bool => $actualUser === $user
            && $departmentId === null
            && $types === []
            && $roles === [SystemRole::HeadChef, SystemRole::Cook]
            && $permissions === [SystemPermission::ViewKitchen])
        ->andReturn($expected);
    $delegate->shouldReceive('userHasAccess')
        ->once()
        ->withArgs(fn (User $actualUser, array $types, array $roles, array $permissions): bool => $actualUser === $user
            && $types === []
            && $roles === [SystemRole::HeadChef, SystemRole::Cook]
            && $permissions === [SystemPermission::ViewKitchen])
        ->andReturnFalse();

    $action = new BuildKitchenDashboardAction($delegate);

    expect($action->handle($user))->toBe($expected)
        ->and($action->userHasAccess($user))->toBeFalse();
});

test('bar ticket update action delegates the exact bar transition contract', function (): void {
    $item = new KitchenTicketItem;
    $item->id = 41;
    $user = new User;
    $delegate = Mockery::mock(UpdateDepartmentTicketItemStatusAction::class);
    $delegate->shouldReceive('handle')
        ->once()
        ->withArgs(fn (int $actualItemId, KitchenTicketItemStatus $status, User $actualUser, array $types, array $roles, array $permissions): bool => $actualItemId === $item->id
            && $status === KitchenTicketItemStatus::Ready
            && $actualUser === $user
            && $types === [KitchenDepartmentType::Bar]
            && $roles === [SystemRole::Bartender, SystemRole::HeadChef]
            && $permissions === [SystemPermission::ViewOrders, SystemPermission::SendToKitchen])
        ->andReturn($item);

    $action = new UpdateBarTicketItemStatusAction($delegate);

    expect($action->handle($item->id, KitchenTicketItemStatus::Ready, $user))->toBe($item);
});

test('kitchen ticket update action delegates the exact kitchen transition contract', function (): void {
    $item = new KitchenTicketItem;
    $item->id = 42;
    $user = new User;
    $delegate = Mockery::mock(UpdateDepartmentTicketItemStatusAction::class);
    $delegate->shouldReceive('handle')
        ->once()
        ->withArgs(fn (int $actualItemId, KitchenTicketItemStatus $status, User $actualUser, array $types, array $roles, array $permissions): bool => $actualItemId === $item->id
            && $status === KitchenTicketItemStatus::InProgress
            && $actualUser === $user
            && $types === []
            && $roles === [SystemRole::HeadChef, SystemRole::Cook]
            && $permissions === [SystemPermission::ViewKitchen])
        ->andReturn($item);

    $action = new UpdateKitchenTicketItemStatusAction($delegate);

    expect($action->handle($item->id, KitchenTicketItemStatus::InProgress, $user))->toBe($item);
});

test('paid table close action delegates to the canonical close action', function (): void {
    $tableSession = new TableSession;
    $closedBy = new User;
    $delegate = Mockery::mock(CloseTableSessionAction::class);
    $delegate->shouldReceive('handle')
        ->once()
        ->with($tableSession, $closedBy)
        ->andReturn($tableSession);

    $action = new ClosePaidTableSessionAction($delegate);

    expect($action->handle($tableSession, $closedBy))->toBe($tableSession);
});

/**
 * @return array{
 *     has_access: bool,
 *     departments: list<array<string, mixed>>,
 *     selected_department_id: int|null,
 *     selected_department_name: string|null,
 *     tickets: list<array<string, mixed>>,
 *     ticket_count: int,
 *     new_item_count: int,
 *     in_progress_item_count: int,
 *     ready_item_count: int
 * }
 */
function departmentDashboardPayload(): array
{
    return [
        'has_access' => true,
        'departments' => [],
        'selected_department_id' => null,
        'selected_department_name' => null,
        'tickets' => [],
        'ticket_count' => 0,
        'new_item_count' => 0,
        'in_progress_item_count' => 0,
        'ready_item_count' => 0,
    ];
}
