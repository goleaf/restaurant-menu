<?php

declare(strict_types=1);

use App\Actions\Mcp\IssueMcpAccessTokenAction;
use App\Actions\Payments\BuildManualPaymentSummaryAction;
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
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\ManualPayment;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\PermissionUserOverride;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use App\Models\WaiterCall;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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

function mcpReadDenied(string $tool, array $arguments = [], ?string $errorKey = null): void
{
    $response = app($tool)->handle(new Request($arguments));
    expect($response)->toBeInstanceOf(Response::class)->and($response->isError())->toBeTrue();
    if ($errorKey !== null) {
        expect((string) $response->content())->toBe(__($errorKey));
    }
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
    mcpReadDenied(ListOrdersTool::class, $arguments, 'mcp.errors.invalid_arguments');
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
    PermissionUserOverride::factory()->forOrganization($this->branch->organization)->create([
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
    mcpReadDenied(PaymentSummaryTool::class, ['table_session_id' => $foreign->id], 'mcp.errors.request_denied');
});

test('MCP read schemas declare closed typed inputs and harmless read annotations', function (string $tool): void {
    $definition = app($tool)->toArray();
    $definition['inputSchema']['properties'] = (array) $definition['inputSchema']['properties'];
    expect($definition['inputSchema']['additionalProperties'])->toBeFalse()
        ->and($definition['annotations']['readOnlyHint'])->toBeTrue()
        ->and($definition['annotations']['openWorldHint'])->toBeFalse();
    if (isset($definition['inputSchema']['properties']['limit'])) {
        expect($definition['inputSchema']['properties']['limit']['type'])->toBe('integer')
            ->and($definition['inputSchema']['properties']['limit']['maximum'])->toBe(50);
    }
})->with('mcp read tools');

test('MCP parent selection pages children and rejects foreign identifiers even when actor can access both branches', function (): void {
    $session = TableSession::factory()->forServicePoint(ServicePoint::factory()->for($this->branch)->create())->active()->create();
    $foreignSession = TableSession::factory()->forServicePoint(ServicePoint::factory()->for($this->otherBranch)->create())->active()->create();
    $order = Order::factory()->forTableSession($session)->withItems(3)->create();
    $foreign = Order::factory()->forTableSession($foreignSession)->withItems()->create();
    DraftOrderItem::factory()->count(3)->create(['draft_order_id' => $order->draft_order_id]);
    $department = KitchenDepartment::factory()->for($this->branch)->create();
    $ticket = KitchenTicket::factory()->forOrder($order)->create(['kitchen_department_id' => $department->id]);
    $order->load('items');
    foreach ($order->items as $item) {
        KitchenTicketItem::factory()->forDispatchedOrderItem($ticket, $item)->create();
    }
    $foreignTicket = KitchenTicket::factory()->forOrder($foreign)->create();

    foreach ([[ListOrdersTool::class, 'order_id', $order->id, $foreign->id],
        [ListDraftsTool::class, 'draft_id', $order->draft_order_id, $foreign->draft_order_id],
        [ListDepartmentTicketsTool::class, 'ticket_id', $ticket->id, $foreignTicket->id]] as [$tool, $key, $id, $foreignId]) {
        $first = mcpReadPayload($tool, [$key => $id, 'limit' => 2]);
        $last = mcpReadPayload($tool, [$key => $id, 'limit' => 2, 'after_id' => $first['next_after_id']]);
        expect($first['items'])->toHaveCount(2)->and($first['has_more'])->toBeTrue()
            ->and($last['items'])->toHaveCount(1)->and($last['has_more'])->toBeFalse()
            ->and($last['items'][0]['id'])->toBeGreaterThan($first['next_after_id']);
        mcpReadDenied($tool, [$key => $foreignId]);
    }
});

test('MCP domain permissions gate reports audit and payment summaries independently of token grants', function (string $tool, array $permissions): void {
    foreach ($permissions as $permission) {
        PermissionUserOverride::factory()->forOrganization($this->branch->organization)->create([
            'user_id' => $this->user->id, 'permission_id' => Permission::query()->where('code', $permission->value)->firstOrFail()->id,
            'enabled' => false,
        ]);
    }
    expect(app($tool)->shouldRegister(new Request))->toBeFalse();
    mcpReadDenied($tool);
})->with([
    [BranchReportTool::class, [SystemPermission::ViewReports]],
    [ListAuditEventsTool::class, [SystemPermission::ViewAuditLog]],
    [PaymentSummaryTool::class, [SystemPermission::ViewPayments, SystemPermission::ManagePayments]],
]);

test('MCP department access includes only eligible active department types', function (): void {
    $cook = Role::query()->where('code', SystemRole::Cook->value)->firstOrFail();
    $this->membership->forceFill(['role_id' => $cook->id])->save();
    $session = TableSession::factory()->forServicePoint(ServicePoint::factory()->for($this->branch)->create())->active()->create();
    $order = Order::factory()->forTableSession($session)->create();
    $kitchen = KitchenDepartment::factory()->for($this->branch)->create(['type' => 'kitchen']);
    $bar = KitchenDepartment::factory()->for($this->branch)->create(['type' => 'bar']);
    $inactive = KitchenDepartment::factory()->for($this->branch)->create(['type' => 'kitchen', 'is_active' => false]);
    $visible = KitchenTicket::factory()->forOrder($order)->create(['kitchen_department_id' => $kitchen->id]);
    KitchenTicket::factory()->forOrder($order)->create(['kitchen_department_id' => $bar->id, 'department_type' => 'bar', 'department_name' => 'Bar']);
    KitchenTicket::factory()->forOrder($order)->create(['kitchen_department_id' => $inactive->id, 'department_name' => 'Inactive']);
    expect(array_column(mcpReadPayload(ListDepartmentTicketsTool::class)['items'], 'id'))->toBe([$visible->id]);
});

test('MCP summary rejects excessive hydration before invoking the existing summary action', function (): void {
    $session = TableSession::factory()->forServicePoint(ServicePoint::factory()->for($this->branch)->create())->active()->create();
    TableSessionGuest::factory()->count(501)->for($session)->create();
    $this->mock(BuildManualPaymentSummaryAction::class)->shouldNotReceive('handle');
    mcpReadDenied(PaymentSummaryTool::class, ['table_session_id' => $session->id], 'mcp.errors.invalid_arguments');
});

test('MCP menu pagination has fixed query and hydration budgets as the catalogue grows', function (): void {
    $menu = Menu::factory()->for($this->branch)->create();
    $category = MenuCategory::factory()->for($menu)->create();
    MenuItem::factory()->count(6)->for($menu)->create(['category_id' => $category->id]);
    $hydrated = 0;
    Event::listen('eloquent.retrieved: '.MenuItem::class, function () use (&$hydrated): void {
        $hydrated++;
    });
    Model::preventLazyLoading();
    $measure = function () use (&$hydrated): array {
        $hydrated = 0;
        DB::flushQueryLog();
        DB::enableQueryLog();
        mcpReadPayload(ListMenuItemsTool::class, ['limit' => 5]);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return [$queries, $hydrated];
    };
    $small = $measure();
    MenuItem::factory()->count(60)->for($menu)->create(['category_id' => $category->id]);
    $large = $measure();
    expect($large)->toBe($small)->and($large[0])->toBeLessThanOrEqual(25)->and($large[1])->toBe(6);
});

test('MCP report totals exclude another authorized branch and obey branch local calendar boundaries', function (): void {
    $session = TableSession::factory()->forServicePoint(ServicePoint::factory()->for($this->branch)->create())->active()->create();
    $foreign = TableSession::factory()->forServicePoint(ServicePoint::factory()->for($this->otherBranch)->create())->active()->create();
    Order::factory()->forTableSession($session)->create(['total_price_cents' => 1234, 'confirmed_at' => '2026-09-15 21:00:00']);
    Order::factory()->forTableSession($session)->create(['total_price_cents' => 4444, 'confirmed_at' => '2026-09-15 20:59:59']);
    Order::factory()->forTableSession($foreign)->create(['total_price_cents' => 9999, 'confirmed_at' => '2026-09-15 21:00:00']);
    $report = mcpReadPayload(BranchReportTool::class, ['period' => 'custom', 'date_from' => '2026-09-16', 'date_to' => '2026-09-16']);
    expect($report['report']['orders_count'])->toBe(1)->and($report['report']['order_total_cents'])->toBe(1234)
        ->and($report['period']['start'])->toBe('2026-09-15 21:00:00');
});

test('MCP payment totals preserve exact integer amounts from the domain summary', function (): void {
    $session = TableSession::factory()->forServicePoint(ServicePoint::factory()->for($this->branch)->create())->active()->create();
    $order = Order::factory()->forTableSession($session)->create(['total_price_cents' => 1234]);
    OrderItem::factory()->for($order)->create(['unit_price_cents' => 1234, 'unit_price_snapshot_cents' => 1234, 'quantity' => 1, 'total_price_cents' => 1234]);
    $expected = app(BuildManualPaymentSummaryAction::class)->handle($session);
    $summary = mcpReadPayload(PaymentSummaryTool::class, ['table_session_id' => $session->id])['summary'];
    expect($summary['confirmed_total_cents'])->toBe(1234)
        ->and($summary['remaining_total_cents'])->toBe($expected['remaining_total_cents'])
        ->and($summary['is_fully_paid'])->toBeFalse();
});

test('MCP unpublished menu requires management rights even with branch and token access', function (): void {
    $this->membership->forceFill(['role_id' => Role::query()->where('code', SystemRole::Cook->value)->firstOrFail()->id])->save();
    expect(app(ListMenuItemsTool::class)->shouldRegister(new Request))->toBeFalse();
    mcpReadDenied(ListMenuItemsTool::class, [], 'mcp.errors.request_denied');
});

test('MCP report preserves separate order and payment totals per currency', function (): void {
    $session = TableSession::factory()->forServicePoint(ServicePoint::factory()->forBranch($this->branch)->create())->active()->create();
    Order::factory()->forTableSession($session)->create(['currency' => 'EUR', 'total_price_cents' => 1234, 'confirmed_at' => now()]);
    Order::factory()->forTableSession($session)->create(['currency' => 'USD', 'total_price_cents' => 5678, 'confirmed_at' => now()]);
    ManualPayment::factory()->forTableSession($session)->create(['currency' => 'GBP', 'amount_cents' => 901, 'paid_at' => now()]);
    $report = mcpReadPayload(BranchReportTool::class)['report'];
    expect($report['order_total_cents'])->toBeNull()->and($report['single_currency'])->toBeNull()
        ->and($report['currency_totals'])->toBe([
            ['currency' => 'EUR', 'total_cents' => 1234, 'order_count' => 1],
            ['currency' => 'USD', 'total_cents' => 5678, 'order_count' => 1],
        ])->and($report['payment_currency_totals'])->toBe([['currency' => 'GBP', 'total_cents' => 901, 'payment_count' => 1]]);
});

test('MCP report includes received payments in a period without new orders', function (): void {
    $session = TableSession::factory()->forServicePoint(ServicePoint::factory()->forBranch($this->branch)->create())->active()->create();
    ManualPayment::factory()->forTableSession($session)->create(['amount_cents' => 987, 'paid_at' => now()]);
    $report = mcpReadPayload(BranchReportTool::class)['report'];
    expect($report['orders_count'])->toBe(0)->and($report['currency_totals'])->toBe([])
        ->and($report['payment_currency_totals'])->toBe([['currency' => 'EUR', 'total_cents' => 987, 'payment_count' => 1]]);
});

test('MCP item reads carry historical order currency independently of the branch currency', function (): void {
    $session = TableSession::factory()->forServicePoint(ServicePoint::factory()->forBranch($this->branch)->create())->active()->create();
    $order = Order::factory()->forTableSession($session)->withItems()->create(['currency' => 'USD']);
    expect(mcpReadPayload(ListOrdersTool::class, ['order_id' => $order->id])['currency'])->toBe('USD');
    $draft = DraftOrder::factory()->forTableSession($session)->withItems()->create();
    expect(mcpReadPayload(ListDraftsTool::class, ['draft_id' => $draft->id])['currency'])->toBe('EUR')
        ->and(mcpReadPayload(ListMenuItemsTool::class)['currency'])->toBe('EUR');
});

test('MCP summary limit and domain calculations share the same transaction snapshot', function (): void {
    $session = TableSession::factory()->forServicePoint(ServicePoint::factory()->forBranch($this->branch)->create())->active()->create();
    $baseline = DB::transactionLevel();
    $this->mock(BuildManualPaymentSummaryAction::class)->shouldReceive('handle')->once()->andReturnUsing(function () use ($baseline): array {
        expect(DB::transactionLevel())->toBe($baseline + 1);

        return ['currency' => 'EUR', 'confirmed_total_cents' => 0];
    });
    mcpReadPayload(PaymentSummaryTool::class, ['table_session_id' => $session->id]);
    expect(DB::transactionLevel())->toBe($baseline);
});

test('MCP summary rejects records whose branch conflicts with their session', function (): void {
    $session = TableSession::factory()->forServicePoint(ServicePoint::factory()->forBranch($this->branch)->create())->active()->create();
    Order::factory()->forTableSession($session)->create(['branch_id' => $this->otherBranch->id]);
    $this->mock(BuildManualPaymentSummaryAction::class)->shouldNotReceive('handle');
    mcpReadDenied(PaymentSummaryTool::class, ['table_session_id' => $session->id], 'mcp.errors.request_denied');
});

test('MCP tickets with foreign parent chains are omitted before item disclosure', function (): void {
    $department = KitchenDepartment::factory()->for($this->branch)->create();
    $foreignOrder = Order::factory()->create();
    $ticket = KitchenTicket::factory()->forOrder($foreignOrder)->create(['branch_id' => $this->branch->id, 'kitchen_department_id' => $department->id]);
    expect(mcpReadPayload(ListDepartmentTicketsTool::class)['items'])->toBe([]);
    mcpReadDenied(ListDepartmentTicketsTool::class, ['ticket_id' => $ticket->id], 'mcp.errors.request_denied');
});

test('MCP menu management permission revocation changes an existing tool instance', function (): void {
    $tool = app(ListMenuItemsTool::class);
    expect($tool->shouldRegister(new Request))->toBeTrue();
    PermissionUserOverride::factory()->forOrganization($this->branch->organization)->create([
        'user_id' => $this->user->id,
        'permission_id' => Permission::query()->where('code', SystemPermission::ManageMenu->value)->firstOrFail()->id,
        'enabled' => false,
    ]);
    expect($tool->shouldRegister(new Request))->toBeFalse();
    mcpReadDenied(ListMenuItemsTool::class, [], 'mcp.errors.request_denied');
});
