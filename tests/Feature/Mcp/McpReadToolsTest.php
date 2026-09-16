<?php

declare(strict_types=1);

use App\Actions\Mcp\IssueMcpAccessTokenAction;
use App\Enums\McpAbility;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Mcp\McpAccess;
use App\Mcp\McpContext;
use App\Mcp\Tools\BranchContextTool;
use App\Mcp\Tools\BranchReportTool;
use App\Mcp\Tools\ListAuditEventsTool;
use App\Mcp\Tools\ListDepartmentTicketsTool;
use App\Mcp\Tools\ListDraftsTool;
use App\Mcp\Tools\ListMenuItemsTool;
use App\Mcp\Tools\ListOrdersTool;
use App\Mcp\Tools\ListTablesTool;
use App\Mcp\Tools\ListWaiterCallsTool;
use App\Mcp\Tools\PaymentSummaryTool;
use App\Models\AreaNode;
use App\Models\AreaNodeWaiter;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\PermissionUserOverride;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\User;
use App\Models\WaiterCall;
use Database\Seeders\SystemPermissionsSeeder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    config(['restaurant-mcp.enabled' => true]);
    $this->branch = Branch::factory()->create();
    $this->otherBranch = Branch::factory()->forBrand($this->branch->brand)->create();
    $this->user = User::factory()->create();
    $this->membership = OrganizationUser::factory()->forOrganization($this->branch->organization)
        ->forUser($this->user)->forSystemRole(SystemRole::Owner)->active()->create();
    $this->issued = app(IssueMcpAccessTokenAction::class)->handle($this->user, $this->branch, 'Read fixtures', McpAbility::readOnly());
    request()->attributes->set(McpContext::class, app(McpAccess::class)->resolve($this->issued->record->id));
});

function mcpReadPayload(string $tool, array $arguments = []): array
{
    $response = app($tool)->handle(new Request($arguments));
    expect($response)->toBeInstanceOf(ResponseFactory::class);

    return $response->getStructuredContent();
}

function mcpReadDenied(string $tool, array $arguments = []): void
{
    $response = app($tool)->handle(new Request($arguments));
    expect($response)->toBeInstanceOf(Response::class)->and($response->isError())->toBeTrue();
}

dataset('mcp read tools', [
    BranchContextTool::class, ListMenuItemsTool::class, ListTablesTool::class,
    ListOrdersTool::class, ListDraftsTool::class, ListWaiterCallsTool::class,
    ListDepartmentTicketsTool::class, BranchReportTool::class, ListAuditEventsTool::class, PaymentSummaryTool::class,
]);

test('MCP branch context states exact identity without suggesting token abilities are current rights', function (): void {
    expect(mcpReadPayload(BranchContextTool::class))->toBe([
        'branch' => ['id' => $this->branch->id, 'name' => $this->branch->name, 'timezone' => 'Europe/Vilnius', 'currency' => 'EUR'],
    ]);
});

test('MCP reads reject unknown ownership arguments', function (string $tool): void {
    foreach (['branch_id', 'organization_id', 'user_id', 'unexpected'] as $key) {
        mcpReadDenied($tool, [$key => $this->otherBranch->id]);
    }
})->with('mcp read tools');

test('MCP reads reauthorize token and membership on direct execution', function (string $tool): void {
    $this->issued->record->forceFill(['revoked_at' => now()])->save();
    expect(app($tool)->shouldRegister(new Request))->toBeFalse();
    mcpReadDenied($tool);
    $this->issued->record->forceFill(['revoked_at' => null])->save();
    $this->membership->forceFill(['status' => OrganizationUserStatus::Suspended])->save();
    mcpReadDenied($tool);
})->with('mcp read tools');

test('MCP read bounds retain original JSON types', function (array $arguments): void {
    mcpReadDenied(ListOrdersTool::class, $arguments);
})->with([
    [['limit' => '10']], [['limit' => true]], [['limit' => 1.5]], [['limit' => 0]], [['limit' => 51]],
    [['after_id' => -1]], [['after_id' => '1']], [['mine_only' => 1]], [['mine_only' => 'false']],
]);

test('MCP menu reads paginate only token branch and omit private paths', function (): void {
    $menu = Menu::factory()->for($this->branch)->create();
    $items = MenuItem::factory()->count(3)->for($menu)->create(['image' => 'private/fixture-secret.jpg']);
    MenuItem::factory()->for(Menu::factory()->for($this->otherBranch))->create(['name' => 'FOREIGN']);
    $first = mcpReadPayload(ListMenuItemsTool::class, ['limit' => 2]);
    $last = mcpReadPayload(ListMenuItemsTool::class, ['limit' => 2, 'after_id' => $first['next_after_id']]);
    expect(array_column($first['items'], 'id'))->toBe($items->take(2)->modelKeys())
        ->and($first['has_more'])->toBeTrue()
        ->and(array_column($last['items'], 'id'))->toBe([$items->last()->id])
        ->and($last['has_more'])->toBeFalse()->and($last['next_after_id'])->toBeNull()
        ->and(json_encode($first))->not->toContain('private/', 'FOREIGN', 'image');
});

test('MCP table metadata never includes guest sessions or order data', function (): void {
    $point = ServicePoint::factory()->for($this->branch)->create();
    TableSession::factory()->forServicePoint($point)->active()->create();
    ServicePoint::factory()->for($this->otherBranch)->create();
    $payload = mcpReadPayload(ListTablesTool::class);
    expect(array_column($payload['items'], 'id'))->toBe([$point->id])
        ->and(json_encode($payload))->not->toContain('token', 'session', 'orders', 'guest');
});

test('MCP operational reads use exact waiter assignments and branch filters', function (): void {
    $mine = AreaNode::factory()->for($this->branch)->create();
    $other = AreaNode::factory()->for($this->branch)->create();
    AreaNodeWaiter::factory()->create(['branch_id' => $this->branch->id, 'area_node_id' => $mine->id, 'user_id' => $this->user->id]);
    $mineSession = TableSession::factory()->forServicePoint(ServicePoint::factory()->for($this->branch)->create(['area_node_id' => $mine->id]))->active()->create();
    $otherSession = TableSession::factory()->forServicePoint(ServicePoint::factory()->for($this->branch)->create(['area_node_id' => $other->id]))->active()->create();
    $foreignSession = TableSession::factory()->forServicePoint(ServicePoint::factory()->for($this->otherBranch)->create())->active()->create();
    $order = Order::factory()->forTableSession($mineSession)->create();
    Order::factory()->forTableSession($otherSession)->create();
    Order::factory()->forTableSession($foreignSession)->create();
    $call = WaiterCall::factory()->forTableSession($mineSession)->create();
    WaiterCall::factory()->forTableSession($otherSession)->create();
    WaiterCall::factory()->forTableSession($foreignSession)->create();
    expect(array_column(mcpReadPayload(ListOrdersTool::class)['items'], 'id'))->toBe([$order->id])
        ->and(mcpReadPayload(ListOrdersTool::class, ['mine_only' => false])['items'])->toHaveCount(2)
        ->and(array_column(mcpReadPayload(ListDraftsTool::class)['items'], 'id'))->toBe([$order->draft_order_id])
        ->and(array_column(mcpReadPayload(ListWaiterCallsTool::class)['items'], 'id'))->toBe([$call->id]);
});

test('MCP order permission removal takes effect within the same tool instance', function (): void {
    $tool = app(ListOrdersTool::class);
    expect($tool->shouldRegister(new Request))->toBeTrue();
    PermissionUserOverride::factory()->create([
        'user_id' => $this->user->id, 'organization_id' => $this->branch->organization_id,
        'permission_id' => Permission::query()->where('code', SystemPermission::ViewOrders->value)->firstOrFail()->id,
        'enabled' => false,
    ]);
    expect($tool->shouldRegister(new Request))->toBeFalse();
    mcpReadDenied(ListOrdersTool::class);
});

test('MCP department tickets intersect permitted departments with the token branch', function (): void {
    $department = KitchenDepartment::factory()->for($this->branch)->create();
    $foreignDepartment = KitchenDepartment::factory()->for($this->otherBranch)->create();
    $session = TableSession::factory()->forServicePoint(ServicePoint::factory()->for($this->branch)->create())->active()->create();
    $ticket = KitchenTicket::factory()->forOrder(Order::factory()->forTableSession($session)->create())->create(['kitchen_department_id' => $department->id]);
    KitchenTicket::factory()->create(['branch_id' => $this->otherBranch->id, 'kitchen_department_id' => $foreignDepartment->id]);
    expect(array_column(mcpReadPayload(ListDepartmentTicketsTool::class)['items'], 'id'))->toBe([$ticket->id]);
});

test('MCP audit projection never exposes old new or personal data', function (): void {
    $audit = AuditLog::factory()->create(['branch_id' => $this->branch->id, 'organization_id' => $this->branch->organization_id,
        'old_values' => ['secret' => 'SENSITIVE'], 'new_values' => ['email' => 'PRIVATE']]);
    AuditLog::factory()->create(['branch_id' => $this->otherBranch->id]);
    $payload = mcpReadPayload(ListAuditEventsTool::class, ['after_id' => $audit->id - 1]);
    expect(array_keys($payload['items'][0]))->toBe(['id', 'action', 'entity_type', 'entity_id', 'created_at'])
        ->and(array_column($payload['items'], 'id'))->toBe([$audit->id])
        ->and(json_encode($payload))->not->toContain('SENSITIVE', 'PRIVATE', 'user_id', 'guest_id', 'old_values', 'new_values');
});

test('MCP reports retain branch timezone and enforce a maximum 31 day interval', function (): void {
    $payload = mcpReadPayload(BranchReportTool::class, ['period' => 'custom', 'date_from' => '2026-09-01', 'date_to' => '2026-09-30']);
    expect($payload['period']['timezone'])->toBe('Europe/Vilnius')->and($payload['report']['orders_count'])->toBe(0);
    mcpReadDenied(BranchReportTool::class, ['period' => 'custom', 'date_from' => '2026-08-01', 'date_to' => '2026-09-30']);
});

test('MCP payment totals reject another accessible branch session and omit guest collections', function (): void {
    $session = TableSession::factory()->forServicePoint(ServicePoint::factory()->for($this->branch)->create())->active()->create();
    $foreign = TableSession::factory()->forServicePoint(ServicePoint::factory()->for($this->otherBranch)->create())->active()->create();
    $payload = mcpReadPayload(PaymentSummaryTool::class, ['table_session_id' => $session->id]);
    expect($payload['summary']['remaining_total_cents'])->toBe(0)
        ->and($payload['summary'])->not->toHaveKeys(['guest_balances', 'unpaid_guests', 'payments']);
    mcpReadDenied(PaymentSummaryTool::class, ['table_session_id' => $foreign->id]);
});
