<?php

declare(strict_types=1);

namespace App\Services\Mcp;

use App\Actions\Payments\BuildManualPaymentSummaryAction;
use App\Actions\Payments\ResolvePaymentAccessibleBranchIdsAction;
use App\Enums\KitchenDepartmentType;
use App\Enums\McpAbility;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Mcp\McpContext;
use App\Models\AreaNodeWaiter;
use App\Models\AuditLog;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\ManualPayment;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\WaiterCall;
use App\Services\Reports\BranchReportQuery;
use App\Support\DisplayPreferences;
use App\Support\Reports\BranchReportPeriod;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class McpReadQueries
{
    public function __construct(
        private readonly BranchReportQuery $reports,
        private readonly ResolvePaymentAccessibleBranchIdsAction $paymentAccess,
        private readonly BuildManualPaymentSummaryAction $payments,
    ) {}

    public function authorize(McpContext $context, McpAbility $ability): void
    {
        $gate = Gate::forUser($context->user);
        match ($ability) {
            McpAbility::ListMenuItems => $gate->authorize('manageMenu', $context->branch),
            McpAbility::ListTables => $gate->authorize('viewAny', [ServicePoint::class, $context->branch]),
            McpAbility::ListOrders, McpAbility::ListDrafts, McpAbility::ListWaiterCalls => $gate->authorize('viewAny', [Order::class, $context->branch]),
            McpAbility::BranchReport => $this->requirePermission($context, SystemPermission::ViewReports),
            McpAbility::ListAuditEvents => $this->requirePermission($context, SystemPermission::ViewAuditLog),
            McpAbility::ListDepartmentTickets => $this->requireAccess($this->departmentQuery($context)->exists()),
            McpAbility::PaymentSummary => $this->requireAccess($this->paymentAccess->canView($context->user, $context->branch->id)),
            default => null,
        };
    }

    /** @param array<string, mixed> $arguments @return array<string, mixed> */
    public function handle(McpContext $context, McpAbility $ability, array $arguments): array
    {
        return match ($ability) {
            McpAbility::BranchContext => ['branch' => $this->project($context->branch, ['id', 'name', 'timezone', 'currency', 'pause_version', 'is_temporarily_closed', 'temporary_closed_until'])],
            McpAbility::ListMenuItems => ['currency' => $context->branch->currency, ...$this->page(
                MenuItem::query()->whereIn('menu_id', Menu::query()->select(['id'])->where('branch_id', $context->branch->id)),
                ['id', 'menu_id', 'category_id', 'name', 'description', 'price_cents', 'is_available', 'hidden_until', 'availability_version'], $arguments,
            )],
            McpAbility::ListTables => $this->page(ServicePoint::query()->where('branch_id', $context->branch->id),
                ['id', 'area_node_id', 'type', 'name', 'display_number', 'capacity', 'status', 'is_active'], $arguments),
            McpAbility::ListOrders => $this->orders($context, $arguments),
            McpAbility::ListDrafts => $this->drafts($context, $arguments),
            McpAbility::ListWaiterCalls => $this->page(
                WaiterCall::query()->where('branch_id', $context->branch->id)->whereIn('service_point_id', $this->waiterPoints($context, $arguments)),
                ['id', 'service_point_id', 'table_session_id', 'status', 'requested_at', 'handled_at'], $arguments,
            ),
            McpAbility::ListDepartmentTickets => $this->tickets($context, $arguments),
            McpAbility::BranchReport => $this->report($context, $arguments),
            McpAbility::ListAuditEvents => $this->page(AuditLog::query()->where('branch_id', $context->branch->id),
                ['id', 'action', 'entity_type', 'entity_id', 'created_at'], $arguments),
            McpAbility::PaymentSummary => $this->paymentSummary($context, (int) $arguments['table_session_id']),
            default => throw new AuthorizationException,
        };
    }

    /** @param array<string, mixed> $arguments @return array<string, mixed> */
    private function orders(McpContext $context, array $arguments): array
    {
        $orders = Order::query()->where('branch_id', $context->branch->id)
            ->whereIn('service_point_id', $this->waiterPoints($context, $arguments));
        if (isset($arguments['order_id'])) {
            $order = $orders->select(['id', 'branch_id', 'table_session_id', 'currency'])->whereKey((int) $arguments['order_id'])->firstOrFail();

            return ['order_id' => $order->id, 'currency' => $order->currency, ...$this->page(OrderItem::query()->where('order_id', $order->id),
                ['id', 'menu_item_id', 'item_name', 'item_name_snapshot', 'quantity', 'unit_price_cents', 'total_price_cents', 'cancelled_at'], $arguments)];
        }

        return $this->page($orders, ['id', 'service_point_id', 'table_session_id', 'status', 'confirmed_at', 'total_price_cents', 'currency'], $arguments);
    }

    /** @param array<string, mixed> $arguments @return array<string, mixed> */
    private function drafts(McpContext $context, array $arguments): array
    {
        $sessions = TableSession::query()->select(['id'])->where('branch_id', $context->branch->id)
            ->whereIn('service_point_id', $this->waiterPoints($context, $arguments));
        $drafts = DraftOrder::query()->whereIn('table_session_id', $sessions);
        if (isset($arguments['draft_id'])) {
            $draft = $drafts->select(['id'])->whereKey((int) $arguments['draft_id'])->firstOrFail();

            return ['draft_id' => $draft->id, 'currency' => $context->branch->currency, ...$this->page(DraftOrderItem::query()->where('draft_order_id', $draft->id),
                ['id', 'menu_item_id', 'item_name', 'quantity', 'unit_price_cents', 'total_price_cents'], $arguments)];
        }

        return $this->page($drafts, ['id', 'table_session_id', 'status', 'sent_to_waiter_at', 'rejected_at', 'converted_to_order_at'], $arguments);
    }

    /** @param array<string, mixed> $arguments @return array<string, mixed> */
    private function tickets(McpContext $context, array $arguments): array
    {
        $tickets = KitchenTicket::query()->where('branch_id', $context->branch->id)
            ->whereHas('order', fn ($orders) => $orders->where('branch_id', $context->branch->id))
            ->whereHas('tableSession', fn ($sessions) => $sessions->where('branch_id', $context->branch->id))
            ->whereHas('servicePoint', fn ($points) => $points->where('branch_id', $context->branch->id))
            ->whereIn('kitchen_department_id', $this->departmentQuery($context));
        if (isset($arguments['ticket_id'])) {
            $ticket = $tickets->select(['id', 'order_id'])->whereKey((int) $arguments['ticket_id'])->firstOrFail();

            return ['ticket_id' => $ticket->id, ...$this->page(KitchenTicketItem::query()->where('kitchen_ticket_id', $ticket->id)
                ->whereHas('orderItem', fn ($items) => $items->where('order_id', $ticket->order_id)),
                ['id', 'order_item_id', 'item_name', 'quantity', 'status', 'served_at'], $arguments)];
        }

        return $this->page($tickets, ['id', 'order_id', 'service_point_id', 'table_session_id', 'kitchen_department_id', 'department_type', 'department_name', 'status', 'sent_at'], $arguments);
    }

    /** @return Builder<KitchenDepartment> */
    private function departmentQuery(McpContext $context): Builder
    {
        $user = $context->user;
        $organizationId = $context->branch->organization_id;
        $headChef = $user->hasOrganizationRole($organizationId, SystemRole::HeadChef);
        $kitchen = $headChef || $user->hasOrganizationRole($organizationId, SystemRole::Cook)
            || $user->hasPermission(SystemPermission::ViewKitchen, $organizationId);
        $bar = $headChef || $user->hasOrganizationRole($organizationId, SystemRole::Bartender)
            || $user->hasPermission(SystemPermission::ViewOrders, $organizationId)
            || $user->hasPermission(SystemPermission::SendToKitchen, $organizationId);
        $types = [
            ...($kitchen ? KitchenDepartmentType::kitchenProductionTypes() : []),
            ...($bar ? KitchenDepartmentType::barProductionTypes() : []),
        ];

        return KitchenDepartment::query()->select(['id'])->where('branch_id', $context->branch->id)
            ->where('is_active', true)->whereIn('type', array_map(static fn (KitchenDepartmentType $type): string => $type->value, $types));
    }

    /** @param array<string, mixed> $arguments @return Builder<ServicePoint> */
    private function waiterPoints(McpContext $context, array $arguments): Builder
    {
        $points = ServicePoint::query()->select(['id'])->where('branch_id', $context->branch->id);
        if (($arguments['mine_only'] ?? true) === false || $context->user->isSuperadmin()) {
            return $points;
        }
        $assignments = AreaNodeWaiter::query()->select(['area_node_id'])
            ->where('branch_id', $context->branch->id)->where('user_id', $context->user->id);

        return $points->where(fn (Builder $query): Builder => $query->whereNotExists((clone $assignments)->select(['id']))
            ->orWhereIn('area_node_id', $assignments));
    }

    /** @param array<string, mixed> $arguments @return array<string, mixed> */
    private function report(McpContext $context, array $arguments): array
    {
        $branches = collect([$context->branch]);
        $period = BranchReportPeriod::fromSelection($branches, (string) ($arguments['period'] ?? 'today'),
            isset($arguments['date_from']) ? (string) $arguments['date_from'] : null,
            isset($arguments['date_to']) ? (string) $arguments['date_to'] : null);
        $orders = Order::query()->select(['id'])->forReportPeriod($period);
        $orderCurrencies = Order::query()->select(['currency'])->forReportPeriod($period)->distinct()->limit(51)->get();
        $paymentCurrencies = ManualPayment::query()->select(['currency'])->forReportPeriod($period)->distinct()->limit(51)->get();
        $itemGroups = OrderItem::query()->select(['item_name', 'item_name_snapshot'])->whereIn('order_id', $orders)
            ->whereNull('cancelled_at')->distinct()->limit(501)->get();
        if ($orderCurrencies->count() > 50 || $paymentCurrencies->count() > 50 || $itemGroups->count() > 500) {
            throw ValidationException::withMessages(['period' => __('mcp.errors.summary_limit')]);
        }
        $report = $this->reports->handle($branches, $period, DisplayPreferences::defaults());

        return ['branch_id' => $context->branch->id, 'period' => $period->ranges[$context->branch->id], 'report' => [
            'orders_count' => $report['orders_count'], 'order_total_cents' => $report['order_total_cents'],
            'single_currency' => $report['single_currency'], 'default_currency' => $report['default_currency'],
            'active_tables_count' => $report['active_tables_count'], 'closed_sessions_count' => $report['closed_sessions_count'],
            'cancelled_orders_count' => $report['cancelled_orders_count'],
            'currency_totals' => array_map(static fn (array $row): array => array_intersect_key($row,
                array_flip(['currency', 'total_cents', 'order_count'])), $report['currency_totals']),
            'payment_currency_totals' => array_map(static fn (array $row): array => array_intersect_key($row,
                array_flip(['currency', 'total_cents', 'payment_count'])), $report['payment_currency_totals']),
            'popular_items' => array_slice($report['popular_items'], 0, 5),
        ]];
    }

    /** @return array<string, mixed> */
    private function paymentSummary(McpContext $context, int $sessionId): array
    {
        $session = TableSession::query()->select(['id', 'branch_id', 'service_point_id', 'status'])
            ->where('branch_id', $context->branch->id)->whereKey($sessionId)->firstOrFail();
        Gate::forUser($context->user)->authorize('viewPayments', $session);
        $this->requireAccess(ServicePoint::query()->whereKey($session->service_point_id)->where('branch_id', $context->branch->id)->exists());
        $this->requireAccess(! $session->orders()->where(fn ($query) => $query->where('branch_id', '!=', $context->branch->id)
            ->orWhereHas('servicePoint', fn ($points) => $points->where('branch_id', '!=', $context->branch->id)))->exists());
        $this->requireAccess(! $session->manualPayments()->where(fn ($query) => $query->where('branch_id', '!=', $context->branch->id)
            ->orWhereHas('servicePoint', fn ($points) => $points->where('branch_id', '!=', $context->branch->id)))->exists());
        $session->loadCount(['guests', 'orders', 'manualPayments', 'draftOrders']);
        $itemCount = OrderItem::query()->whereIn('order_id', Order::query()->select(['id'])->where('table_session_id', $sessionId))->count();
        $upperBound = $session->guests_count + $session->orders_count + $session->draft_orders_count + (3 * $session->manual_payments_count) + $itemCount;
        if ($upperBound > 500) {
            throw ValidationException::withMessages(['table_session_id' => __('mcp.errors.summary_limit')]);
        }
        $summary = $this->payments->handle($session);
        $fields = ['currency', 'service_charge_enabled', 'service_charge_basis_points', 'service_charge_total_cents',
            'service_charge_paid_cents', 'remaining_service_charge_cents', 'tips_enabled', 'tips_paid_total_cents',
            'confirmed_total_cents', 'covered_subtotal_cents', 'remaining_subtotal_cents', 'paid_total_cents',
            'remaining_total_cents', 'has_payable_total', 'has_open_draft', 'is_fully_paid'];

        return ['table_session_id' => $sessionId, 'summary' => array_intersect_key($summary, array_flip($fields))];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $fields
     * @param  array<string, mixed>  $arguments
     * @return array{items: list<array<string, mixed>>, has_more: bool, next_after_id: int|null}
     */
    private function page(Builder $query, array $fields, array $arguments): array
    {
        $limit = (int) ($arguments['limit'] ?? 25);
        $rows = $query->select($fields)->where('id', '>', (int) ($arguments['after_id'] ?? 0))->orderBy('id')->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $page = $rows->take($limit);

        return ['items' => $page->map(fn (Model $row): array => $this->project($row, $fields))->values()->all(),
            'has_more' => $hasMore, 'next_after_id' => $hasMore ? (int) $page->last()?->getKey() : null];
    }

    /** @param list<string> $fields @return array<string, mixed> */
    private function project(Model $model, array $fields): array
    {
        $row = [];
        foreach ($fields as $field) {
            $value = $model->getAttribute($field);
            $row[$field] = match (true) {
                $value instanceof BackedEnum => $value->value,
                $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
                default => $value,
            };
        }

        return $row;
    }

    private function requirePermission(McpContext $context, SystemPermission $permission): void
    {
        $this->requireAccess($context->user->hasPermission($permission, $context->branch->organization_id));
    }

    private function requireAccess(bool $allowed): void
    {
        if (! $allowed) {
            throw new AuthorizationException;
        }
    }
}
