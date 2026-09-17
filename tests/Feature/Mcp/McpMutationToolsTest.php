<?php

declare(strict_types=1);

use App\Actions\Mcp\IssueMcpAccessTokenAction;
use App\Enums\McpAbility;
use App\Enums\SystemRole;
use App\Enums\TableSessionStatus;
use App\Mcp\McpAccess;
use App\Mcp\McpContext;
use App\Mcp\Tools\OpenTableTool;
use App\Mcp\Tools\SetMenuAvailabilityTool;
use App\Mcp\Tools\SetOrderingPauseTool;
use App\Models\Branch;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicketItem;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\OrganizationUser;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\User;
use App\Models\WaiterCall;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    config(['restaurant-mcp.enabled' => true, 'restaurant-mcp.writes_enabled' => true]);
    $this->branch = Branch::factory()->create();
    $this->user = User::factory()->create();
    OrganizationUser::factory()->forOrganization($this->branch->organization)->forUser($this->user)
        ->forSystemRole(SystemRole::Owner)->active()->create();
    $this->issued = app(IssueMcpAccessTokenAction::class)->handle($this->user, $this->branch, 'Mutation fixture',
        array_map(fn (McpAbility $ability): string => $ability->value, McpAbility::cases()), 24);
    request()->attributes->set(McpContext::class, app(McpAccess::class)->resolve($this->issued->record->id));
    $this->arguments = ['confirmed' => true, 'idempotency_key' => (string) Str::uuid()];
});

test('every MCP mutation requires explicit confirmation before domain work', function (string $tool): void {
    $class = 'App\\Mcp\\Tools\\'.$tool.'Tool';
    $response = app($class)->handle(new Request(['idempotency_key' => (string) Str::uuid(), 'confirmed' => false]));
    expect($response)->toBeInstanceOf(Response::class)->and($response->isError())->toBeTrue();
    $this->assertDatabaseCount('mcp_mutation_receipts', 0);
})->with(['SetMenuAvailability', 'SetOrderingPause', 'OpenTable', 'ConfirmDraft', 'RejectDraft', 'HandleWaiterCall',
    'UpdateTicketItem', 'ServeTicketItem', 'RecordPayment', 'CloseTable']);

test('ordering pause reuses the domain action and replay preserves a later change', function (): void {
    $tool = app(SetOrderingPauseTool::class);
    $input = [...$this->arguments, 'closed' => true, 'reason' => 'Kitchen break', 'expected_version' => 0, 'timezone' => $this->branch->timezone, 'request_id' => $this->arguments['idempotency_key']];
    $first = $tool->handle(new Request($input));
    expect($first)->toBeInstanceOf(ResponseFactory::class)
        ->and($first->getStructuredContent()['paused'])->toBeTrue();
    $this->branch->forceFill(['is_temporarily_closed' => false])->save();
    $again = $tool->handle(new Request($input));
    expect($again->getStructuredContent())->toBe($first->getStructuredContent())
        ->and($this->branch->fresh()->is_temporarily_closed)->toBeFalse();
    $this->assertDatabaseCount('mcp_mutation_receipts', 1);
});

test('open table is branch bound and replays the original visit', function (): void {
    $point = ServicePoint::factory()->forBranch($this->branch)->free()->create();
    $tool = app(OpenTableTool::class);
    $input = [...$this->arguments, 'service_point_id' => $point->id];
    $first = $tool->handle(new Request($input));
    expect($first)->toBeInstanceOf(ResponseFactory::class);
    expect($tool->handle(new Request($input))->getStructuredContent())->toBe($first->getStructuredContent());
    $this->assertDatabaseCount('table_sessions', 1);
    $foreign = ServicePoint::factory()->create();
    $denied = $tool->handle(new Request([...$this->arguments, 'service_point_id' => $foreign->id, 'idempotency_key' => (string) Str::uuid()]));
    expect($denied->isError())->toBeTrue();
    $this->assertDatabaseCount('table_sessions', 1);
});

test('mutation schema rejects unknown fields and coerced JSON scalars', function (array $input): void {
    $response = app(SetOrderingPauseTool::class)->handle(new Request([...$this->arguments, ...$input]));
    expect($response)->toBeInstanceOf(Response::class)->and($response->isError())->toBeTrue();
    $this->assertDatabaseCount('mcp_mutation_receipts', 0);
})->with([[['closed' => 'true']], [['closed' => 1]], [['closed' => true, 'branch_id' => 1]], [['closed' => true, 'confirmed' => 'true']]]);

function mcpMutationResult(string $name, array $input): array
{
    $tool = app('App\\Mcp\\Tools\\'.$name.'Tool');
    $response = $tool->handle(new Request(['confirmed' => true, 'idempotency_key' => (string) Str::uuid(), ...$input]));
    expect($response)->toBeInstanceOf(ResponseFactory::class);

    return $response->getStructuredContent();
}

test('MCP executes a full approved service and offline settlement workflow without duplicating payment', function (string $departmentType): void {
    $point = ServicePoint::factory()->forBranch($this->branch)->free()->create();
    $openKey = (string) Str::uuid();
    $opened = mcpMutationResult('OpenTable', ['service_point_id' => $point->id, 'idempotency_key' => $openKey]);
    $session = TableSession::query()->findOrFail($opened['table_session_id']);
    $department = KitchenDepartment::factory()->for($this->branch)->create(['type' => $departmentType]);
    $menu = Menu::factory()->for($this->branch)->active()->create();
    $item = MenuItem::factory()->for($menu)->create(['kitchen_department_id' => $department->id, 'price_cents' => 1000]);
    $draft = DraftOrder::factory()->forTableSession($session)->sentToWaiter()->create();
    DraftOrderItem::factory()->for($draft)->create(['menu_item_id' => $item->id, 'item_name' => $item->name]);
    $confirmKey = (string) Str::uuid();
    $confirmed = mcpMutationResult('ConfirmDraft', ['draft_id' => $draft->id, 'idempotency_key' => $confirmKey]);
    expect($confirmed['total_cents'])->toBe(1000);
    $ticketItem = KitchenTicketItem::query()->whereHas('kitchenTicket', fn ($query) => $query->where('order_id', $confirmed['order_id']))->firstOrFail();
    $ready = mcpMutationResult('UpdateTicketItem', ['ticket_item_id' => $ticketItem->id, 'status' => 'ready']);
    expect($ready['status'])->toBe('ready');
    expect(mcpMutationResult('ServeTicketItem', ['ticket_item_id' => $ticketItem->id])['served'])->toBeTrue();
    $paymentKey = (string) Str::uuid();
    $arguments = ['table_session_id' => $session->id, 'method' => 'cash', 'idempotency_key' => $paymentKey];
    $payment = mcpMutationResult('RecordPayment', $arguments);
    expect($payment['amount_cents'])->toBe(1000)->and($payment['currency'])->toBe('EUR')
        ->and(mcpMutationResult('RecordPayment', $arguments))->toBe($payment);
    $this->assertDatabaseCount('manual_payments', 1);
    expect(mcpMutationResult('CloseTable', ['table_session_id' => $session->id])['status'])->toBe('closed');
    expect(mcpMutationResult('OpenTable', ['service_point_id' => $point->id, 'idempotency_key' => $openKey]))->toBe($opened)
        ->and(mcpMutationResult('ConfirmDraft', ['draft_id' => $draft->id, 'idempotency_key' => $confirmKey]))->toBe($confirmed)
        ->and($session->fresh()->status)->toBe(TableSessionStatus::Closed);
    $this->assertDatabaseCount('table_sessions', 1);
})->with(['kitchen', 'bar']);

test('MCP menu availability draft rejection and waiter acknowledgement use real domain actions', function (): void {
    $menu = Menu::factory()->for($this->branch)->create();
    $item = MenuItem::factory()->for($menu)->create();
    expect(mcpMutationResult('SetMenuAvailability', ['menu_item_id' => $item->id, 'is_available' => false, 'expected_version' => 0, 'timezone' => $this->branch->timezone, 'request_id' => (string) Str::uuid()])['is_available'])->toBeFalse()
        ->and($item->fresh()->is_available)->toBeFalse();
    $session = TableSession::factory()->forServicePoint(ServicePoint::factory()->forBranch($this->branch)->create())->active()->create();
    $draft = DraftOrder::factory()->forTableSession($session)->sentToWaiter()->withItems()->create();
    $arguments = ['draft_id' => $draft->id, 'reason' => 'Unavailable today', 'idempotency_key' => (string) Str::uuid()];
    $rejected = mcpMutationResult('RejectDraft', $arguments);
    expect($rejected['status'])->toBe('rejected')->and(mcpMutationResult('RejectDraft', $arguments))->toBe($rejected);
    $call = WaiterCall::factory()->forTableSession($session)->pending()->create();
    expect(mcpMutationResult('HandleWaiterCall', ['waiter_call_id' => $call->id])['status'])->toBe('handled');
});

test('MCP mutations cannot select another accessible branch through resource identifiers', function (string $tool, string $model, string $key, array $extra): void {
    $foreign = $model::factory()->create();
    if ($tool === 'SetMenuAvailability') {
        $extra = [...$extra, 'expected_version' => 0, 'timezone' => $this->branch->timezone, 'request_id' => (string) Str::uuid()];
    }
    $response = app('App\\Mcp\\Tools\\'.$tool.'Tool')->handle(new Request([...$this->arguments, $key => $foreign->id, ...$extra]));
    expect($response)->toBeInstanceOf(Response::class)->and($response->isError())->toBeTrue();
    $this->assertDatabaseCount('mcp_mutation_receipts', 0);
})->with([
    ['SetMenuAvailability', MenuItem::class, 'menu_item_id', ['is_available' => false]],
    ['ConfirmDraft', DraftOrder::class, 'draft_id', []],
    ['RejectDraft', DraftOrder::class, 'draft_id', ['reason' => 'Unavailable']],
    ['HandleWaiterCall', WaiterCall::class, 'waiter_call_id', []],
    ['UpdateTicketItem', KitchenTicketItem::class, 'ticket_item_id', ['status' => 'ready']],
    ['ServeTicketItem', KitchenTicketItem::class, 'ticket_item_id', []],
    ['RecordPayment', TableSession::class, 'table_session_id', ['method' => 'cash']],
    ['CloseTable', TableSession::class, 'table_session_id', []],
]);

test('MCP item availability requires the observed version and preserves independent hiding', function (): void {
    $menu = Menu::factory()->for($this->branch)->create();
    $item = MenuItem::factory()->for($menu)->create(['availability_version' => 4, 'hidden_until' => '2027-01-01 12:00:00']);
    $arguments = ['menu_item_id' => $item->id, 'is_available' => false, 'expected_version' => 4,
        'timezone' => $this->branch->timezone, 'request_id' => (string) Str::uuid()];
    $saved = mcpMutationResult('SetMenuAvailability', $arguments);
    expect($saved['availability_version'])->toBe(5)->and($item->fresh()->is_available)->toBeFalse()
        ->and($item->fresh()->getRawOriginal('hidden_until'))->toBe('2027-01-01 12:00:00');
    $tool = app(SetMenuAvailabilityTool::class);
    $denied = $tool->handle(new Request([...$this->arguments, ...$arguments, 'is_available' => true, 'request_id' => (string) Str::uuid()]));
    expect($denied)->toBeInstanceOf(Response::class)->and($denied->isError())->toBeTrue()
        ->and($item->fresh()->availability_version)->toBe(5)->and($item->fresh()->is_available)->toBeFalse();
    $this->assertDatabaseCount('availability_commands', 1);
});
