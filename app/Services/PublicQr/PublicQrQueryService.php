<?php

declare(strict_types=1);

namespace App\Services\PublicQr;

use App\Enums\QrCodeStatus;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionJoinRequestStatus;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\KitchenTicketItem;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

final class PublicQrQueryService
{
    public function __construct(
        private GuestEntryQueryService $guestEntryQueries,
        private PublicQrOrderQueryService $orderQueries,
    ) {}

    public function qrCodeForGuestPage(string $token): ?QrCode
    {
        return QrCode::query()
            ->select([
                'id',
                'service_point_id',
                'public_token',
                'short_code',
                'status',
            ])
            ->with([
                'servicePoint' => fn ($query) => $query
                    ->select(['id', 'branch_id', 'is_active'])
                    ->with([
                        'branch' => fn ($branchQuery) => $branchQuery
                            ->select(['id', 'organization_id', 'name', 'public_name'])
                            ->with([
                                'organization' => fn ($organizationQuery) => $organizationQuery
                                    ->select(['id'])
                                    ->with([
                                        'subscription' => fn ($subscriptionQuery) => $subscriptionQuery
                                            ->select(['id', 'organization_id', 'status']),
                                    ]),
                            ]),
                    ]),
            ])
            ->where('public_token', $token)
            ->first();
    }

    public function activeTableSessionForQr(string $publicToken, int $tableSessionId): ?TableSession
    {
        $qrCode = QrCode::query()
            ->select(['id', 'service_point_id', 'public_token', 'status'])
            ->with(['servicePoint' => fn ($query) => $query->select(['id', 'branch_id', 'is_active'])])
            ->where('public_token', $publicToken)
            ->where('status', QrCodeStatus::Active->value)
            ->first();

        $servicePoint = $qrCode?->servicePoint;

        if (! $servicePoint instanceof ServicePoint || ! $servicePoint->is_active) {
            return null;
        }

        $tableSession = TableSession::query()
            ->select([
                'id',
                'branch_id',
                'service_point_id',
                'opened_by_guest_id',
                'status',
                'source',
                'started_at',
                'ended_at',
                'guest_invite_token_hash',
                'guest_invite_created_at',
                'guest_invite_expires_at',
                'guest_invite_created_by_guest_id',
                'metadata',
            ])
            ->whereKey($tableSessionId)
            ->guestViewable()
            ->forQrServicePoint($servicePoint)
            ->first();

        if ($tableSession instanceof TableSession) {
            return $tableSession;
        }

        $tableSession = TableSession::query()
            ->select([
                'id',
                'branch_id',
                'service_point_id',
                'opened_by_guest_id',
                'status',
                'source',
                'started_at',
                'ended_at',
                'guest_invite_token_hash',
                'guest_invite_created_at',
                'guest_invite_expires_at',
                'guest_invite_created_by_guest_id',
                'metadata',
            ])
            ->whereKey($tableSessionId)
            ->where('branch_id', $servicePoint->branch_id)
            ->guestViewable()
            ->first();

        return $tableSession?->wasTransferredFrom($servicePoint) === true
            ? $tableSession
            : null;
    }

    public function activeGuest(
        int $guestId,
        int $tableSessionId,
        string $guestToken,
    ): ?TableSessionGuest {
        if ($guestId < 1 || $tableSessionId < 1 || strlen($guestToken) !== 64) {
            return null;
        }

        return TableSessionGuest::query()
            ->select([
                'id',
                'table_session_id',
                'guest_name',
                'guest_token',
                'locale',
                'status',
                'ready_at',
                'joined_at',
                'left_at',
            ])
            ->whereKey($guestId)
            ->where('table_session_id', $tableSessionId)
            ->where('guest_token', $guestToken)
            ->where('status', TableSessionGuestStatus::Active->value)
            ->first();
    }

    /** @return Collection<int, TableSessionGuest> */
    public function tableGuests(int $tableSessionId): Collection
    {
        return TableSessionGuest::query()
            ->select([
                'id',
                'table_session_id',
                'guest_name',
                'status',
                'ready_at',
                'joined_at',
            ])
            ->where('table_session_id', $tableSessionId)
            ->orderBy('guest_name')
            ->orderBy('id')
            ->limit(50)
            ->get();
    }

    /** @return Collection<int, TableSessionJoinRequest> */
    public function pendingJoinRequests(int $tableSessionId): Collection
    {
        return TableSessionJoinRequest::query()
            ->select([
                'id',
                'table_session_id',
                'guest_name',
                'status',
                'expires_at',
                'created_at',
            ])
            ->where('table_session_id', $tableSessionId)
            ->where('status', TableSessionJoinRequestStatus::Pending->value)
            ->where(function ($query): void {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(20)
            ->get();
    }

    public function pendingJoinRequest(int $joinRequestId, int $tableSessionId): ?TableSessionJoinRequest
    {
        return TableSessionJoinRequest::query()
            ->select([
                'id',
                'table_session_id',
                'guest_name',
                'guest_token',
                'status',
                'approved_by_guest_id',
                'rejected_by_guest_id',
                'approved_by_user_id',
                'rejected_by_user_id',
                'expires_at',
            ])
            ->whereKey($joinRequestId)
            ->where('table_session_id', $tableSessionId)
            ->where('status', TableSessionJoinRequestStatus::Pending->value)
            ->first();
    }

    /** @param list<class-string> $types */
    public function unreadNotificationCount(TableSessionGuest $guest, array $types): int
    {
        return (int) $guest->unreadNotifications()
            ->whereIn('type', $types)
            ->count();
    }

    /**
     * @param  list<class-string>  $types
     * @return Collection<int, DatabaseNotification>
     */
    public function unreadNotifications(TableSessionGuest $guest, array $types): Collection
    {
        return $guest->unreadNotifications()
            ->select(['id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at', 'created_at'])
            ->whereIn('type', $types)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get();
    }

    public function guestMenuTableSession(int $tableSessionId): ?TableSession
    {
        return $this->orderQueries->guestMenuTableSession($tableSessionId);
    }

    public function menuItem(int $menuItemId): ?MenuItem
    {
        return $this->orderQueries->menuItem($menuItemId);
    }

    public function statusTableSession(int $tableSessionId): ?TableSession
    {
        return $this->orderQueries->statusTableSession($tableSessionId);
    }

    public function statusDraftOrder(int $tableSessionId): ?DraftOrder
    {
        return $this->orderQueries->statusDraftOrder($tableSessionId);
    }

    /** @return Collection<int, Order> */
    public function recentOrders(int $tableSessionId): Collection
    {
        return $this->orderQueries->recentOrders($tableSessionId);
    }

    /** @return Collection<int, KitchenTicketItem> */
    public function ticketItemsForOrder(?Order $order): Collection
    {
        return $this->orderQueries->ticketItemsForOrder($order);
    }

    /** @return Collection<int, DraftOrderItem> */
    public function draftItems(DraftOrder $draftOrder): Collection
    {
        return $this->orderQueries->draftItems($draftOrder);
    }

    /** @param Collection<int, Order> $orders @return Collection<int, OrderItem> */
    public function orderItems(Collection $orders): Collection
    {
        return $this->orderQueries->orderItems($orders);
    }

    /** @return Collection<int, TableSessionGuest> */
    public function activeGuestsForDraft(int $tableSessionId): Collection
    {
        return $this->orderQueries->activeGuestsForDraft($tableSessionId);
    }

    public function draftOrderWithCart(int $tableSessionId, bool $includeOrderStatus = true): ?DraftOrder
    {
        return $this->orderQueries->draftOrderWithCart($tableSessionId, $includeOrderStatus);
    }

    public function draftOrderWithTotals(int $tableSessionId): ?DraftOrder
    {
        return $this->orderQueries->draftOrderWithTotals($tableSessionId);
    }

    public function confirmedOrdersTotalCents(int $tableSessionId): int
    {
        return $this->orderQueries->confirmedOrdersTotalCents($tableSessionId);
    }

    /** @return list<array{guest_id: int, guest_name: string, total_cents: int}> */
    public function confirmedOrderItemGuestTotals(int $tableSessionId): array
    {
        return $this->orderQueries->confirmedOrderItemGuestTotals($tableSessionId);
    }

    public function draftOrderForSending(int $tableSessionId): ?DraftOrder
    {
        return $this->orderQueries->draftOrderForSending($tableSessionId);
    }

    public function editableDraftOrderItem(
        int $itemId,
        int $currentGuestId,
        int $tableSessionId,
    ): ?DraftOrderItem {
        return $this->orderQueries->editableDraftOrderItem($itemId, $currentGuestId, $tableSessionId);
    }

    /** @return list<array{id: int, name: string, price_cents: int, formatted_price: string, is_default: bool}> */
    public function availableVariants(MenuItem $menuItem, string $currency): array
    {
        return $this->orderQueries->availableVariants($menuItem, $currency);
    }

    /** @return list<array{id: int, name: string, price_cents: int, formatted_price: string}> */
    public function localizedAvailableVariants(MenuItem $menuItem, string $language, string $currency): array
    {
        return $this->orderQueries->localizedAvailableVariants($menuItem, $language, $currency);
    }

    public function guestByToken(ServicePoint $servicePoint, string $guestToken): ?TableSessionGuest
    {
        return $this->guestEntryQueries->guestByToken($servicePoint, $guestToken);
    }

    public function joinRequestByToken(ServicePoint $servicePoint, string $guestToken): ?TableSessionJoinRequest
    {
        return $this->guestEntryQueries->joinRequestByToken($servicePoint, $guestToken);
    }

    public function tableSessionByInviteToken(ServicePoint $servicePoint, string $inviteToken): ?TableSession
    {
        return $this->guestEntryQueries->tableSessionByInviteToken($servicePoint, $inviteToken);
    }

    public function tableSessionForGuestNameConflict(ServicePoint $servicePoint): ?TableSession
    {
        return $this->guestEntryQueries->tableSessionForGuestNameConflict($servicePoint);
    }

    public function joinRequestByIdAndToken(int $joinRequestId, string $guestToken): ?TableSessionJoinRequest
    {
        return $this->guestEntryQueries->joinRequestByIdAndToken($joinRequestId, $guestToken);
    }

    public function joinRequestByCurrentState(
        int $joinRequestId,
        int $tableSessionId,
        string $guestName,
    ): ?TableSessionJoinRequest {
        return $this->guestEntryQueries->joinRequestByCurrentState($joinRequestId, $tableSessionId, $guestName);
    }

    public function guestForJoinRequest(TableSessionJoinRequest $joinRequest): ?TableSessionGuest
    {
        return $this->guestEntryQueries->guestForJoinRequest($joinRequest);
    }

    public function servicePointForTableSession(TableSession $tableSession): ServicePoint
    {
        return $this->guestEntryQueries->servicePointForTableSession($tableSession);
    }

    /** @return list<string> */
    public function activeGuestNames(int $tableSessionId): array
    {
        return $this->guestEntryQueries->activeGuestNames($tableSessionId);
    }
}
