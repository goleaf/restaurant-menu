<?php

namespace App\Actions\Waiter;

use App\Enums\DraftOrderStatus;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionStatus;
use App\Models\Branch;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class BuildWaiterDashboardAction
{
    public function __construct(
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
    ) {}

    /**
     * @return array{
     *     has_access: bool,
     *     branches: list<array<string, mixed>>,
     *     service_point_count: int,
     *     active_session_count: int,
     *     new_draft_count: int
     * }
     */
    public function handle(User $user): array
    {
        $branchIds = $this->accessibleBranchIds($user);

        if ($branchIds->isEmpty()) {
            return [
                'has_access' => false,
                'branches' => [],
                'service_point_count' => 0,
                'active_session_count' => 0,
                'new_draft_count' => 0,
            ];
        }

        $branches = Branch::query()
            ->select(['id', 'organization_id', 'brand_id', 'name', 'city', 'timezone', 'currency', 'is_active'])
            ->with([
                'organization' => fn ($query) => $query->select(['id', 'name']),
                'brand' => fn ($query) => $query->select(['id', 'organization_id', 'name']),
            ])
            ->whereIn('id', $branchIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $branchIds = $branches->pluck('id');

        $servicePoints = ServicePoint::query()
            ->select(['id', 'branch_id', 'area_node_id', 'type', 'name', 'display_number', 'capacity', 'icon', 'status', 'is_active'])
            ->with(['areaNode' => fn ($query) => $query->select(['id', 'branch_id', 'name'])])
            ->whereIn('branch_id', $branchIds)
            ->orderBy('branch_id')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $sessions = TableSession::query()
            ->select(['id', 'branch_id', 'service_point_id', 'opened_by_user_id', 'opened_by_guest_id', 'status', 'source', 'started_at', 'created_at'])
            ->withCount(['activeGuests'])
            ->with([
                'openedByUser' => fn ($query) => $query->select(['id', 'name']),
                'openedByGuest' => fn ($query) => $query->select(['id', 'guest_name']),
            ])
            ->whereIn('branch_id', $branchIds)
            ->whereIn('status', $this->openSessionStatuses())
            ->orderBy('branch_id')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->get();

        $draftOrders = $this->sentDraftOrders($sessions->pluck('id'));
        $draftsBySessionId = $draftOrders->keyBy('table_session_id');
        $sessionsByServicePointId = $sessions->groupBy('service_point_id');
        $servicePointsByBranchId = $servicePoints->groupBy('branch_id');
        $sessionsByBranchId = $sessions->groupBy('branch_id');
        $draftsByBranchId = $this->draftsByBranchId($draftOrders, $sessions);

        return [
            'has_access' => true,
            'branches' => $branches
                ->map(fn (Branch $branch): array => $this->branchPayload(
                    branch: $branch,
                    servicePoints: $servicePointsByBranchId->get($branch->id, new Collection),
                    sessions: $sessionsByBranchId->get($branch->id, new Collection),
                    drafts: $draftsByBranchId->get($branch->id, new Collection),
                    sessionsByServicePointId: $sessionsByServicePointId,
                    draftsBySessionId: $draftsBySessionId,
                ))
                ->values()
                ->all(),
            'service_point_count' => $servicePoints->count(),
            'active_session_count' => $sessions->count(),
            'new_draft_count' => $draftOrders->count(),
        ];
    }

    public function userHasAccess(User $user): bool
    {
        return $this->accessibleBranchIds($user)->isNotEmpty();
    }

    /**
     * @return Collection<int, int>
     */
    private function accessibleBranchIds(User $user): Collection
    {
        return $this->resolveAccessibleBranchIds->handle($user);
    }

    /**
     * @param  Collection<int, int>  $tableSessionIds
     * @return EloquentCollection<int, DraftOrder>
     */
    private function sentDraftOrders(Collection $tableSessionIds): EloquentCollection
    {
        if ($tableSessionIds->isEmpty()) {
            return new EloquentCollection;
        }

        return DraftOrder::query()
            ->select(['id', 'table_session_id', 'status', 'sent_to_waiter_at', 'sent_by_guest_id', 'created_at', 'updated_at'])
            ->withCount(['items'])
            ->with([
                'sentByGuest' => fn ($query) => $query->select(['id', 'guest_name']),
                'items' => fn ($query) => $query->select(['id', 'draft_order_id', 'total_price']),
            ])
            ->where('status', DraftOrderStatus::SentToWaiter->value)
            ->whereIn('table_session_id', $tableSessionIds)
            ->orderByDesc('sent_to_waiter_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return list<string>
     */
    private function openSessionStatuses(): array
    {
        return [
            TableSessionStatus::Pending->value,
            TableSessionStatus::Active->value,
            TableSessionStatus::WaitingWaiterConfirmation->value,
            TableSessionStatus::PaymentRequested->value,
        ];
    }

    /**
     * @param  Collection<int, ServicePoint>  $servicePoints
     * @param  Collection<int, TableSession>  $sessions
     * @param  Collection<int, DraftOrder>  $drafts
     * @param  Collection<int, Collection<int, TableSession>>  $sessionsByServicePointId
     * @param  Collection<int, DraftOrder>  $draftsBySessionId
     * @return array<string, mixed>
     */
    private function branchPayload(
        Branch $branch,
        Collection $servicePoints,
        Collection $sessions,
        Collection $drafts,
        Collection $sessionsByServicePointId,
        Collection $draftsBySessionId,
    ): array {
        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'brand_name' => $branch->brand?->name,
            'organization_name' => $branch->organization?->name,
            'city' => $branch->city,
            'currency' => $branch->currency,
            'is_active' => $branch->is_active,
            'service_point_count' => count($servicePoints),
            'active_session_count' => count($sessions),
            'new_draft_count' => count($drafts),
            'service_points' => $servicePoints
                ->map(fn (ServicePoint $servicePoint): array => $this->servicePointPayload(
                    servicePoint: $servicePoint,
                    sessions: $sessionsByServicePointId->get($servicePoint->id, new Collection),
                    draftsBySessionId: $draftsBySessionId,
                    currency: $branch->currency,
                ))
                ->values()
                ->all(),
            'drafts' => $drafts
                ->map(fn (DraftOrder $draftOrder): array => $this->draftPayload($draftOrder, $branch->currency))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, TableSession>  $sessions
     * @param  Collection<int, DraftOrder>  $draftsBySessionId
     * @return array<string, mixed>
     */
    private function servicePointPayload(ServicePoint $servicePoint, Collection $sessions, Collection $draftsBySessionId, string $currency): array
    {
        $status = $servicePoint->status instanceof ServicePointStatus
            ? $servicePoint->status
            : ServicePointStatus::from((string) $servicePoint->status);

        return [
            'id' => $servicePoint->id,
            'name' => $servicePoint->name,
            'display_number' => $servicePoint->display_number,
            'area_name' => $servicePoint->areaNode?->name,
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_color' => $status->badgeColor(),
            'capacity' => $servicePoint->capacity,
            'is_active' => $servicePoint->is_active,
            'sessions' => $sessions
                ->map(fn (TableSession $tableSession): array => $this->sessionPayload(
                    tableSession: $tableSession,
                    draftOrder: $draftsBySessionId->get($tableSession->id),
                    currency: $currency,
                ))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionPayload(TableSession $tableSession, ?DraftOrder $draftOrder, string $currency): array
    {
        $status = $tableSession->status instanceof TableSessionStatus
            ? $tableSession->status
            : TableSessionStatus::from((string) $tableSession->status);

        return [
            'id' => $tableSession->id,
            'detail_url' => route('restaurant.waiter.tables.show', $tableSession),
            'status' => $status->value,
            'status_label' => $status->label(),
            'source_label' => $tableSession->source->label(),
            'started_at' => $tableSession->started_at?->format('Y-m-d H:i') ?? $tableSession->created_at?->format('Y-m-d H:i'),
            'opened_by' => $tableSession->openedByUser?->name ?? $tableSession->openedByGuest?->guest_name,
            'active_guest_count' => (int) ($tableSession->active_guests_count ?? 0),
            'draft' => $draftOrder instanceof DraftOrder ? $this->draftPayload($draftOrder, $currency) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function draftPayload(DraftOrder $draftOrder, string $currency): array
    {
        $totalCents = $draftOrder->items->sum(
            fn (DraftOrderItem $item): int => $this->decimalToCents($item->total_price),
        );

        return [
            'id' => $draftOrder->id,
            'table_session_id' => $draftOrder->table_session_id,
            'sent_at' => $draftOrder->sent_to_waiter_at?->format('Y-m-d H:i'),
            'sent_by_guest_name' => $draftOrder->sentByGuest?->guest_name,
            'items_count' => (int) ($draftOrder->items_count ?? 0),
            'total' => $this->formatCents($totalCents).' '.$currency,
        ];
    }

    /**
     * @param  EloquentCollection<int, DraftOrder>  $draftOrders
     * @param  EloquentCollection<int, TableSession>  $sessions
     * @return Collection<int, Collection<int, DraftOrder>>
     */
    private function draftsByBranchId(EloquentCollection $draftOrders, EloquentCollection $sessions): Collection
    {
        $sessionBranchIds = $sessions->pluck('branch_id', 'id');

        return $draftOrders->groupBy(
            fn (DraftOrder $draftOrder): int => (int) $sessionBranchIds->get($draftOrder->table_session_id),
        );
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
