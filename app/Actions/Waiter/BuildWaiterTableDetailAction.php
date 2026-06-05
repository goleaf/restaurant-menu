<?php

namespace App\Actions\Waiter;

use App\Actions\Payments\BuildManualPaymentSummaryAction;
use App\Actions\Payments\ResolvePaymentAccessibleBranchIdsAction;
use App\Enums\DraftOrderStatus;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\OrderStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionSource;
use App\Enums\TableSessionStatus;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\TableSessionServicePoint;
use App\Models\User;
use Illuminate\Support\Collection;

class BuildWaiterTableDetailAction
{
    public function __construct(
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
        private readonly ResolvePaymentAccessibleBranchIdsAction $resolvePaymentAccess,
        private readonly BuildManualPaymentSummaryAction $buildPaymentSummary,
    ) {}

    /**
     * @return array{has_access: bool, table: array<string, mixed>|null}
     */
    public function handle(User $user, TableSession $tableSession): array
    {
        $accessibleBranchIds = $this->resolveAccessibleBranchIds->handle($user);
        $paymentViewableBranchIds = $this->resolvePaymentAccess->viewableBranchIds($user);

        if (! $accessibleBranchIds->contains((int) $tableSession->branch_id)
            && ! $paymentViewableBranchIds->contains((int) $tableSession->branch_id)) {
            return [
                'has_access' => false,
                'table' => null,
            ];
        }

        $tableSession = TableSession::query()
            ->select([
                'id',
                'branch_id',
                'service_point_id',
                'opened_by_user_id',
                'opened_by_guest_id',
                'status',
                'source',
                'started_at',
                'created_at',
            ])
            ->with([
                'branch' => fn ($query) => $query
                    ->select(['id', 'organization_id', 'brand_id', 'name', 'city', 'currency', 'is_active'])
                    ->with([
                        'organization' => fn ($organizationQuery) => $organizationQuery->select(['id', 'name']),
                        'brand' => fn ($brandQuery) => $brandQuery->select(['id', 'organization_id', 'name']),
                    ]),
                'servicePoint' => fn ($query) => $query
                    ->select(['id', 'branch_id', 'area_node_id', 'type', 'name', 'display_number', 'capacity', 'status', 'is_active'])
                    ->with(['areaNode' => fn ($areaQuery) => $areaQuery->select(['id', 'branch_id', 'name'])]),
                'activeServicePointLinks' => fn ($query) => $query
                    ->select([
                        'id',
                        'table_session_id',
                        'service_point_id',
                        'linked_by_user_id',
                        'linked_at',
                        'unlinked_at',
                    ])
                    ->with([
                        'servicePoint' => fn ($servicePointQuery) => $servicePointQuery
                            ->select(['id', 'branch_id', 'area_node_id', 'type', 'name', 'display_number', 'capacity', 'status', 'is_active'])
                            ->with(['areaNode' => fn ($areaQuery) => $areaQuery->select(['id', 'branch_id', 'name'])]),
                    ]),
                'openedByUser' => fn ($query) => $query->select(['id', 'name']),
                'openedByGuest' => fn ($query) => $query->select(['id', 'guest_name']),
                'guests' => fn ($query) => $query->select([
                    'id',
                    'table_session_id',
                    'guest_name',
                    'status',
                    'ready_at',
                    'joined_at',
                    'left_at',
                ]),
                'draftOrder' => fn ($query) => $query
                    ->select([
                        'draft_orders.id',
                        'draft_orders.table_session_id',
                        'draft_orders.status',
                        'draft_orders.sent_to_waiter_at',
                        'draft_orders.sent_by_guest_id',
                        'draft_orders.rejected_at',
                        'draft_orders.rejected_by_user_id',
                        'draft_orders.rejection_reason',
                        'draft_orders.converted_to_order_at',
                        'draft_orders.converted_by_user_id',
                        'draft_orders.created_at',
                        'draft_orders.updated_at',
                    ])
                    ->with([
                        'sentByGuest' => fn ($guestQuery) => $guestQuery->select(['id', 'guest_name']),
                        'rejectedByUser' => fn ($userQuery) => $userQuery->select(['id', 'name']),
                        'convertedByUser' => fn ($userQuery) => $userQuery->select(['id', 'name']),
                        'order' => fn ($orderQuery) => $orderQuery
                            ->select([
                                'id',
                                'draft_order_id',
                                'status',
                                'confirmed_at',
                                'confirmed_by_user_id',
                                'total_price',
                            ])
                            ->withCount('kitchenTickets')
                            ->with(['kitchenTickets' => fn ($ticketQuery) => $ticketQuery
                                ->select([
                                    'id',
                                    'order_id',
                                    'department_name',
                                ])
                                ->with(['items' => fn ($itemQuery) => $itemQuery
                                    ->select([
                                        'id',
                                        'kitchen_ticket_id',
                                        'guest_name',
                                        'item_name',
                                        'quantity',
                                        'status',
                                        'served_at',
                                        'served_by_user_id',
                                        'selected_modifiers',
                                        'comment',
                                        'created_at',
                                    ])
                                    ->orderBy('created_at')
                                    ->orderBy('id')])]),
                        'items' => fn ($itemsQuery) => $itemsQuery
                            ->select([
                                'id',
                                'draft_order_id',
                                'table_session_guest_id',
                                'menu_item_id',
                                'item_name',
                                'quantity',
                                'unit_price',
                                'modifier_total',
                                'total_price',
                                'selected_modifiers',
                                'comment',
                                'created_at',
                            ])
                            ->with(['guest' => fn ($guestQuery) => $guestQuery->select(['id', 'table_session_id', 'guest_name', 'status'])]),
                    ]),
                'orders' => fn ($query) => $query
                    ->select([
                        'id',
                        'table_session_id',
                        'status',
                        'total_price',
                    ])
                    ->whereNotIn('status', [OrderStatus::Cancelled->value])
                    ->with(['items' => fn ($itemQuery) => $itemQuery->select([
                        'id',
                        'order_id',
                        'total_price',
                    ])])
                    ->orderBy('created_at')
                    ->orderBy('id'),
            ])
            ->whereKey($tableSession->id)
            ->firstOrFail();

        return [
            'has_access' => true,
            'table' => $this->tablePayload($tableSession, $user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tablePayload(TableSession $tableSession, User $user): array
    {
        $branch = $tableSession->branch;
        $servicePoint = $tableSession->servicePoint;
        $draftOrder = $tableSession->draftOrder;
        $currency = $branch?->currency ?? 'EUR';
        $sessionStatus = $tableSession->status instanceof TableSessionStatus
            ? $tableSession->status
            : TableSessionStatus::from((string) $tableSession->status);
        $sessionSource = $tableSession->source instanceof TableSessionSource
            ? $tableSession->source
            : TableSessionSource::from((string) $tableSession->source);
        $servicePointStatus = $servicePoint?->status instanceof ServicePointStatus
            ? $servicePoint->status
            : ServicePointStatus::from((string) ($servicePoint?->status ?? ServicePointStatus::Free->value));

        $confirmOrderBranchIds = $this->resolveAccessibleBranchIds
            ->handle($user, SystemPermission::ConfirmOrders);
        $editPendingOrderBranchIds = $this->resolveAccessibleBranchIds
            ->handle($user, SystemPermission::EditPendingOrders);
        $canReviewDraft = $confirmOrderBranchIds->contains((int) $tableSession->branch_id);
        $canEditPendingDraft = $confirmOrderBranchIds
            ->merge($editPendingOrderBranchIds)
            ->unique()
            ->contains((int) $tableSession->branch_id);
        $sendToKitchenBranchIds = $this->resolveAccessibleBranchIds
            ->handle($user, SystemPermission::SendToKitchen);
        $canSendToKitchen = $sendToKitchenBranchIds->contains((int) $tableSession->branch_id);
        $paymentViewableBranchIds = $this->resolvePaymentAccess->viewableBranchIds($user);
        $paymentManageableBranchIds = $this->resolvePaymentAccess->manageableBranchIds($user);
        $canViewPayments = $paymentViewableBranchIds->contains((int) $tableSession->branch_id);
        $canManagePayments = $paymentManageableBranchIds->contains((int) $tableSession->branch_id);
        $closeTableSessionBranchIds = $this->resolveAccessibleBranchIds
            ->handle($user, SystemPermission::CloseTableSessions);
        $canManuallyCloseTableSession = $closeTableSessionBranchIds->contains((int) $tableSession->branch_id);
        $transferBranchIds = $this->resolveAccessibleBranchIds
            ->handle($user, SystemPermission::ViewOrders)
            ->merge($this->resolveAccessibleBranchIds->handle($user, SystemPermission::ConfirmOrders))
            ->unique()
            ->values();
        $canTransferTableSession = $sessionStatus === TableSessionStatus::Active
            && $transferBranchIds->contains((int) $tableSession->branch_id);
        $canMergeTableSession = $canTransferTableSession;
        $canCloseTableSession = ! in_array($sessionStatus, [
            TableSessionStatus::Closed,
            TableSessionStatus::Cancelled,
        ], true) && (
            $canManuallyCloseTableSession
            || ($canManagePayments && $sessionStatus === TableSessionStatus::Paid)
        );
        $canAddManualOrderItems = $canEditPendingDraft && $sessionStatus === TableSessionStatus::Active;

        $guestSections = $this->guestSections(
            guests: $tableSession->guests,
            draftItems: $draftOrder?->items ?? collect(),
            currency: $currency,
        );

        $draftTotalCents = collect($guestSections)->sum(fn (array $guestSection): int => (int) $guestSection['total_cents']);
        $confirmedOrdersTotalCents = $this->confirmedOrdersTotalCents($tableSession->orders);
        $openDraftTotalCents = $this->openDraftTotalCents($draftOrder, $draftTotalCents);
        $tableTotalCents = $confirmedOrdersTotalCents + $openDraftTotalCents;
        $paymentSummary = $canViewPayments
            ? $this->buildPaymentSummary->handle($tableSession)
            : [];

        return [
            'id' => $tableSession->id,
            'branch' => [
                'id' => $branch?->id,
                'name' => $branch?->name,
                'brand_name' => $branch?->brand?->name,
                'organization_name' => $branch?->organization?->name,
                'city' => $branch?->city,
                'currency' => $currency,
            ],
            'zone' => [
                'name' => $servicePoint?->areaNode?->name,
            ],
            'service_point' => [
                'id' => $servicePoint?->id,
                'name' => $servicePoint?->name,
                'display_number' => $servicePoint?->display_number,
                'capacity' => $servicePoint?->capacity,
                'status_label' => $servicePointStatus->label(),
                'status_color' => $servicePointStatus->badgeColor(),
                'is_active' => (bool) $servicePoint?->is_active,
            ],
            'linked_service_points' => $this->linkedServicePointsPayload($tableSession),
            'session' => [
                'id' => $tableSession->id,
                'status_label' => $sessionStatus->label(),
                'source_label' => $sessionSource->label(),
                'started_at' => $tableSession->started_at?->format('Y-m-d H:i') ?? $tableSession->created_at?->format('Y-m-d H:i'),
                'opened_by' => $tableSession->openedByUser?->name ?? $tableSession->openedByGuest?->guest_name,
                'can_close' => $canCloseTableSession,
                'can_close_manually' => $canManuallyCloseTableSession,
                'close_requires_warning' => $canCloseTableSession && $sessionStatus !== TableSessionStatus::Paid,
            ],
            'draft' => $this->draftPayload(
                draftOrder: $draftOrder,
                currency: $currency,
                totalCents: $draftTotalCents,
                canReviewDraft: $canReviewDraft,
                canEditPendingDraft: $canEditPendingDraft,
                canSendToKitchen: $canSendToKitchen,
            ),
            'payment' => $this->paymentPayload(
                summary: $paymentSummary,
                sessionStatus: $sessionStatus,
                canViewPayments: $canViewPayments,
                canManagePayments: $canManagePayments,
            ),
            'manual_order' => [
                'can_add' => $canAddManualOrderItems,
            ],
            'transfer' => $this->transferPayload($tableSession, $canTransferTableSession),
            'merge' => $this->mergePayload($tableSession, $canMergeTableSession),
            'guest_sections' => $guestSections,
            'current_draft_total' => $this->formatCents($draftTotalCents).' '.$currency,
            'confirmed_orders_total' => $this->formatCents($confirmedOrdersTotalCents).' '.$currency,
            'confirmed_order_count' => $tableSession->orders->count(),
            'table_total' => $this->formatCents($tableTotalCents).' '.$currency,
            'total' => $this->formatCents($tableTotalCents).' '.$currency,
            'guest_count' => count($guestSections),
            'item_count' => collect($guestSections)->sum(fn (array $guestSection): int => count($guestSection['items'])),
        ];
    }

    /**
     * @return array{can_transfer: bool, available_service_points: list<array{id: int, label: string, name: string, display_number: string|null, zone_name: string|null}>}
     */
    private function transferPayload(TableSession $tableSession, bool $canTransfer): array
    {
        if (! $canTransfer) {
            return [
                'can_transfer' => false,
                'available_service_points' => [],
            ];
        }

        $openStatuses = [
            TableSessionStatus::Pending->value,
            TableSessionStatus::Active->value,
            TableSessionStatus::WaitingWaiterConfirmation->value,
            TableSessionStatus::PaymentRequested->value,
        ];

        $availableServicePoints = ServicePoint::query()
            ->select(['id', 'branch_id', 'area_node_id', 'name', 'display_number', 'status', 'is_active'])
            ->with(['areaNode' => fn ($query) => $query->select(['id', 'branch_id', 'name'])])
            ->where('branch_id', $tableSession->branch_id)
            ->whereKeyNot($tableSession->service_point_id)
            ->where('is_active', true)
            ->where('status', ServicePointStatus::Free->value)
            ->whereDoesntHave('tableSessions', fn ($query) => $query->whereIn('status', $openStatuses))
            ->whereDoesntHave('activeTableSessionServicePointLinks')
            ->orderBy('name')
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->map(fn (ServicePoint $servicePoint): array => [
                'id' => $servicePoint->id,
                'label' => $this->servicePointTransferLabel($servicePoint),
                'name' => $servicePoint->name,
                'display_number' => $servicePoint->display_number,
                'zone_name' => $servicePoint->areaNode?->name,
            ])
            ->values()
            ->all();

        return [
            'can_transfer' => true,
            'available_service_points' => $availableServicePoints,
        ];
    }

    /**
     * @return list<array{id: int, name: string, display_number: string|null, zone_name: string|null, status_label: string, status_color: string}>
     */
    private function linkedServicePointsPayload(TableSession $tableSession): array
    {
        return $tableSession
            ->activeServicePointLinks
            ->map(function (TableSessionServicePoint $link): ?array {
                $servicePoint = $link->servicePoint;

                if (! $servicePoint instanceof ServicePoint) {
                    return null;
                }

                $status = $servicePoint->status instanceof ServicePointStatus
                    ? $servicePoint->status
                    : ServicePointStatus::from((string) $servicePoint->status);

                return [
                    'id' => $servicePoint->id,
                    'name' => $servicePoint->name,
                    'display_number' => $servicePoint->display_number,
                    'zone_name' => $servicePoint->areaNode?->name,
                    'status_label' => $status->label(),
                    'status_color' => $status->badgeColor(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{can_merge: bool, available_service_points: list<array{id: int, label: string, name: string, display_number: string|null, zone_name: string|null}>}
     */
    private function mergePayload(TableSession $tableSession, bool $canMerge): array
    {
        if (! $canMerge) {
            return [
                'can_merge' => false,
                'available_service_points' => [],
            ];
        }

        $openStatuses = [
            TableSessionStatus::Pending->value,
            TableSessionStatus::Active->value,
            TableSessionStatus::WaitingWaiterConfirmation->value,
            TableSessionStatus::PaymentRequested->value,
        ];

        $availableServicePoints = ServicePoint::query()
            ->select(['id', 'branch_id', 'area_node_id', 'name', 'display_number', 'status', 'is_active'])
            ->with(['areaNode' => fn ($query) => $query->select(['id', 'branch_id', 'name'])])
            ->where('branch_id', $tableSession->branch_id)
            ->whereKeyNot($tableSession->service_point_id)
            ->where('is_active', true)
            ->where('status', ServicePointStatus::Free->value)
            ->whereDoesntHave('tableSessions', fn ($query) => $query->whereIn('status', $openStatuses))
            ->whereDoesntHave('activeTableSessionServicePointLinks')
            ->orderBy('name')
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->map(fn (ServicePoint $servicePoint): array => [
                'id' => $servicePoint->id,
                'label' => $this->servicePointTransferLabel($servicePoint),
                'name' => $servicePoint->name,
                'display_number' => $servicePoint->display_number,
                'zone_name' => $servicePoint->areaNode?->name,
            ])
            ->values()
            ->all();

        return [
            'can_merge' => true,
            'available_service_points' => $availableServicePoints,
        ];
    }

    private function servicePointTransferLabel(ServicePoint $servicePoint): string
    {
        $parts = [$servicePoint->name];

        if (filled($servicePoint->display_number)) {
            $parts[] = __('№ :number', ['number' => $servicePoint->display_number]);
        }

        if ($servicePoint->areaNode?->name) {
            $parts[] = $servicePoint->areaNode->name;
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function paymentPayload(
        array $summary,
        TableSessionStatus $sessionStatus,
        bool $canViewPayments,
        bool $canManagePayments,
    ): array {
        if (! $canViewPayments) {
            return [
                'can_view' => false,
                'can_manage' => false,
                'can_record_table_payment' => false,
                'can_close_session' => false,
                'payment_methods' => [],
                'guest_balances' => [],
                'payments' => [],
            ];
        }

        $canRecord = $canManagePayments
            && (bool) ($summary['has_payable_total'] ?? false)
            && ! (bool) ($summary['has_open_draft'] ?? false)
            && (int) ($summary['remaining_total_cents'] ?? 0) > 0
            && ! in_array($sessionStatus, [
                TableSessionStatus::Paid,
                TableSessionStatus::Closed,
                TableSessionStatus::Cancelled,
            ], true);

        $guestBalances = collect($summary['guest_balances'] ?? [])
            ->map(function (array $guestBalance) use ($canRecord): array {
                $guestBalance['can_record_payment'] = $canRecord && (int) ($guestBalance['remaining_cents'] ?? 0) > 0;

                return $guestBalance;
            })
            ->values()
            ->all();
        $unpaidGuests = collect($summary['unpaid_guests'] ?? [])
            ->values()
            ->all();

        return [
            'can_view' => true,
            'can_manage' => $canManagePayments,
            'can_record_table_payment' => $canRecord,
            'can_close_session' => $canManagePayments && $sessionStatus === TableSessionStatus::Paid,
            'payment_methods' => $summary['payment_methods'] ?? [],
            'currency' => $summary['currency'] ?? 'EUR',
            'confirmed_total_cents' => (int) ($summary['confirmed_total_cents'] ?? 0),
            'paid_total_cents' => (int) ($summary['paid_total_cents'] ?? 0),
            'remaining_total_cents' => (int) ($summary['remaining_total_cents'] ?? 0),
            'confirmed_total' => $summary['confirmed_total'] ?? '0.00 EUR',
            'paid_total' => $summary['paid_total'] ?? '0.00 EUR',
            'remaining_total' => $summary['remaining_total'] ?? '0.00 EUR',
            'has_payable_total' => (bool) ($summary['has_payable_total'] ?? false),
            'has_open_draft' => (bool) ($summary['has_open_draft'] ?? false),
            'is_fully_paid' => (bool) ($summary['is_fully_paid'] ?? false),
            'guest_balances' => $guestBalances,
            'unpaid_guests' => $unpaidGuests,
            'unpaid_guests_count' => (int) ($summary['unpaid_guests_count'] ?? count($unpaidGuests)),
            'payments' => $summary['payments'] ?? [],
        ];
    }

    /**
     * @param  Collection<int, Order>  $orders
     */
    private function confirmedOrdersTotalCents(Collection $orders): int
    {
        return $orders->sum(
            fn (Order $order): int => $order->items->sum(
                fn (OrderItem $item): int => $this->decimalToCents($item->total_price),
            ),
        );
    }

    private function openDraftTotalCents(?DraftOrder $draftOrder, int $draftTotalCents): int
    {
        if (! $draftOrder instanceof DraftOrder) {
            return 0;
        }

        $status = $draftOrder->status instanceof DraftOrderStatus
            ? $draftOrder->status
            : DraftOrderStatus::from((string) $draftOrder->status);

        return $status === DraftOrderStatus::ConvertedToOrder ? 0 : $draftTotalCents;
    }

    /**
     * @param  Collection<int, TableSessionGuest>  $guests
     * @param  Collection<int, DraftOrderItem>  $draftItems
     * @return list<array<string, mixed>>
     */
    private function guestSections(Collection $guests, Collection $draftItems, string $currency): array
    {
        $guestSections = [];

        $guests->each(function (TableSessionGuest $guest) use (&$guestSections): void {
            $status = $guest->status instanceof TableSessionGuestStatus
                ? $guest->status
                : TableSessionGuestStatus::from((string) $guest->status);

            $guestSections[$guest->id] = [
                'guest_id' => $guest->id,
                'guest_name' => $guest->guest_name,
                'status_label' => $status->label(),
                'is_ready' => $guest->ready_at !== null,
                'total_cents' => 0,
                'items' => [],
            ];
        });

        $draftItems->each(function (DraftOrderItem $item) use (&$guestSections, $currency): void {
            $guestId = (int) $item->table_session_guest_id;
            $guestName = $item->guest?->guest_name ?? __('Guest');

            if (! isset($guestSections[$guestId])) {
                $guestSections[$guestId] = [
                    'guest_id' => $guestId,
                    'guest_name' => $guestName,
                    'status_label' => $item->guest?->status?->label() ?? __('Guest'),
                    'is_ready' => false,
                    'total_cents' => 0,
                    'items' => [],
                ];
            }

            $itemTotalCents = $this->decimalToCents($item->total_price);
            $unitTotalCents = max(0, $this->decimalToCents($item->unit_price) + $this->decimalToCents($item->modifier_total));
            $guestSections[$guestId]['total_cents'] += $itemTotalCents;
            $guestSections[$guestId]['items'][] = [
                'id' => $item->id,
                'menu_item_id' => $item->menu_item_id,
                'item_name' => $item->item_name,
                'quantity' => $item->quantity,
                'unit_price' => $this->formatCents($this->decimalToCents($item->unit_price)).' '.$currency,
                'modifier_total' => $this->formatCents($this->decimalToCents($item->modifier_total)).' '.$currency,
                'unit_total_price' => $this->formatCents($unitTotalCents).' '.$currency,
                'total_price' => $this->formatCents($itemTotalCents).' '.$currency,
                'comment' => $item->comment,
                'modifiers' => $this->modifierSummary($item->selected_modifiers ?? [], $currency),
            ];
        });

        return collect($guestSections)
            ->sortBy(fn (array $guestSection): string => mb_strtolower($guestSection['guest_name']))
            ->map(fn (array $guestSection): array => [
                'guest_id' => $guestSection['guest_id'],
                'guest_name' => $guestSection['guest_name'],
                'status_label' => $guestSection['status_label'],
                'is_ready' => $guestSection['is_ready'],
                'total_cents' => $guestSection['total_cents'],
                'total' => $this->formatCents((int) $guestSection['total_cents']).' '.$currency,
                'items' => $guestSection['items'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $selectedModifiers
     * @return list<array{label: string, price_delta: string|null}>
     */
    private function modifierSummary(array $selectedModifiers, string $currency): array
    {
        return collect($selectedModifiers)
            ->map(function (array $modifier) use ($currency): array {
                $groupName = (string) ($modifier['group_name'] ?? $modifier['group'] ?? '');
                $optionName = (string) ($modifier['option_name'] ?? $modifier['option'] ?? '');
                $priceDelta = $modifier['price_delta'] ?? null;

                return [
                    'label' => trim($groupName) === '' ? $optionName : $groupName.': '.$optionName,
                    'price_delta' => $priceDelta === null
                        ? null
                        : $this->formatCents($this->decimalToCents($priceDelta)).' '.$currency,
                ];
            })
            ->filter(fn (array $modifier): bool => trim($modifier['label']) !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function draftPayload(
        ?DraftOrder $draftOrder,
        string $currency,
        int $totalCents,
        bool $canReviewDraft,
        bool $canEditPendingDraft,
        bool $canSendToKitchen,
    ): array {
        if (! $draftOrder instanceof DraftOrder) {
            return [
                'id' => null,
                'status_value' => null,
                'status_label' => __('No draft'),
                'sent_at' => null,
                'sent_by_guest_name' => null,
                'rejected_at' => null,
                'rejected_by_user_name' => null,
                'rejection_reason' => null,
                'converted_to_order_at' => null,
                'converted_by_user_name' => null,
                'order_id' => null,
                'order_status_value' => null,
                'order_status_label' => null,
                'order_ticket_count' => 0,
                'order_ticket_departments' => [],
                'order_ticket_items' => [],
                'ready_ticket_item_count' => 0,
                'served_ticket_item_count' => 0,
                'total' => '0.00 '.$currency,
                'can_confirm' => false,
                'can_reject' => false,
                'can_return_to_draft' => false,
                'can_edit' => false,
                'can_send_to_kitchen' => false,
            ];
        }

        $status = $draftOrder->status instanceof DraftOrderStatus
            ? $draftOrder->status
            : DraftOrderStatus::from((string) $draftOrder->status);
        $orderStatus = $draftOrder->order?->status instanceof OrderStatus
            ? $draftOrder->order->status
            : null;
        $orderTicketCount = $draftOrder->order === null
            ? 0
            : (int) ($draftOrder->order->getAttribute('kitchen_tickets_count') ?? $draftOrder->order->kitchenTickets->count());
        $orderTicketDepartments = $draftOrder->order === null
            ? []
            : $draftOrder->order->kitchenTickets
                ->pluck('department_name')
                ->filter()
                ->unique()
                ->values()
                ->all();
        $orderTicketItems = $this->orderTicketItems($draftOrder, $currency);
        $readyTicketItemCount = collect($orderTicketItems)
            ->filter(fn (array $item): bool => (bool) $item['is_ready'])
            ->count();
        $servedTicketItemCount = collect($orderTicketItems)
            ->filter(fn (array $item): bool => (bool) $item['is_served'])
            ->count();

        return [
            'id' => $draftOrder->id,
            'status_value' => $status->value,
            'status_label' => $status->label(),
            'sent_at' => $draftOrder->sent_to_waiter_at?->format('Y-m-d H:i'),
            'sent_by_guest_name' => $draftOrder->sentByGuest?->guest_name,
            'rejected_at' => $draftOrder->rejected_at?->format('Y-m-d H:i'),
            'rejected_by_user_name' => $draftOrder->rejectedByUser?->name,
            'rejection_reason' => $draftOrder->rejection_reason,
            'converted_to_order_at' => $draftOrder->converted_to_order_at?->format('Y-m-d H:i'),
            'converted_by_user_name' => $draftOrder->convertedByUser?->name,
            'order_id' => $draftOrder->order?->id,
            'order_status_value' => $orderStatus?->value,
            'order_status_label' => $orderStatus?->label(),
            'order_ticket_count' => $orderTicketCount,
            'order_ticket_departments' => $orderTicketDepartments,
            'order_ticket_items' => $orderTicketItems,
            'ready_ticket_item_count' => $readyTicketItemCount,
            'served_ticket_item_count' => $servedTicketItemCount,
            'total' => $this->formatCents($totalCents).' '.$currency,
            'can_confirm' => $canReviewDraft && in_array($status, [DraftOrderStatus::SentToWaiter, DraftOrderStatus::WaiterReview], true),
            'can_reject' => $canReviewDraft && in_array($status, [DraftOrderStatus::SentToWaiter, DraftOrderStatus::WaiterReview], true),
            'can_return_to_draft' => $canReviewDraft && $status === DraftOrderStatus::Rejected,
            'can_edit' => $canEditPendingDraft && in_array($status, [DraftOrderStatus::SentToWaiter, DraftOrderStatus::WaiterReview], true),
            'can_send_to_kitchen' => $canSendToKitchen && $orderStatus === OrderStatus::ConfirmedByWaiter,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function orderTicketItems(DraftOrder $draftOrder, string $currency): array
    {
        if ($draftOrder->order === null) {
            return [];
        }

        return $draftOrder->order
            ->kitchenTickets
            ->flatMap(fn (KitchenTicket $ticket): Collection => $ticket->items->map(
                fn (KitchenTicketItem $item): array => $this->orderTicketItemPayload($ticket, $item, $currency),
            ))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function orderTicketItemPayload(KitchenTicket $ticket, KitchenTicketItem $item, string $currency): array
    {
        $status = $item->status instanceof KitchenTicketItemStatus
            ? $item->status
            : KitchenTicketItemStatus::from((string) $item->status);

        return [
            'id' => $item->id,
            'department_name' => $ticket->department_name,
            'guest_name' => $item->guest_name,
            'item_name' => $item->item_name,
            'quantity' => $item->quantity,
            'status_value' => $status->value,
            'status_label' => $status->label(),
            'status_color' => $item->served_at === null ? $status->badgeColor() : 'sky',
            'is_ready' => $status === KitchenTicketItemStatus::Ready,
            'is_served' => $item->served_at !== null,
            'served_at' => $item->served_at?->format('Y-m-d H:i'),
            'comment' => $item->comment,
            'modifiers' => $this->modifierSummary($item->selected_modifiers ?? [], $currency),
        ];
    }

    private function decimalToCents(string|int|float|null $amount): int
    {
        $normalized = number_format((float) ($amount ?? 0), 2, '.', '');
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '-');
        [$whole, $fraction] = explode('.', $normalized);
        $cents = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

        return $negative ? -$cents : $cents;
    }

    private function formatCents(int $cents): string
    {
        $negative = $cents < 0;
        $absoluteCents = abs($cents);
        $formatted = intdiv($absoluteCents, 100).'.'.str_pad((string) ($absoluteCents % 100), 2, '0', STR_PAD_LEFT);

        return $negative ? '-'.$formatted : $formatted;
    }
}
