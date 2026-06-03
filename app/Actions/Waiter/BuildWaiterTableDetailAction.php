<?php

namespace App\Actions\Waiter;

use App\Enums\DraftOrderStatus;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionSource;
use App\Enums\TableSessionStatus;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use Illuminate\Support\Collection;

class BuildWaiterTableDetailAction
{
    public function __construct(
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
    ) {}

    /**
     * @return array{has_access: bool, table: array<string, mixed>|null}
     */
    public function handle(User $user, TableSession $tableSession): array
    {
        $accessibleBranchIds = $this->resolveAccessibleBranchIds->handle($user);

        if (! $accessibleBranchIds->contains((int) $tableSession->branch_id)) {
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
                    ->select(['id', 'table_session_id', 'status', 'sent_to_waiter_at', 'sent_by_guest_id', 'created_at', 'updated_at'])
                    ->with([
                        'sentByGuest' => fn ($guestQuery) => $guestQuery->select(['id', 'guest_name']),
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
            ])
            ->whereKey($tableSession->id)
            ->firstOrFail();

        return [
            'has_access' => true,
            'table' => $this->tablePayload($tableSession),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tablePayload(TableSession $tableSession): array
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

        $guestSections = $this->guestSections(
            guests: $tableSession->guests,
            draftItems: $draftOrder?->items ?? collect(),
            currency: $currency,
        );

        $totalCents = collect($guestSections)->sum(fn (array $guestSection): int => (int) $guestSection['total_cents']);

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
            'session' => [
                'id' => $tableSession->id,
                'status_label' => $sessionStatus->label(),
                'source_label' => $sessionSource->label(),
                'started_at' => $tableSession->started_at?->format('Y-m-d H:i') ?? $tableSession->created_at?->format('Y-m-d H:i'),
                'opened_by' => $tableSession->openedByUser?->name ?? $tableSession->openedByGuest?->guest_name,
            ],
            'draft' => $this->draftPayload($draftOrder, $currency, $totalCents),
            'guest_sections' => $guestSections,
            'total' => $this->formatCents($totalCents).' '.$currency,
            'guest_count' => count($guestSections),
            'item_count' => collect($guestSections)->sum(fn (array $guestSection): int => count($guestSection['items'])),
        ];
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
    private function draftPayload(?DraftOrder $draftOrder, string $currency, int $totalCents): array
    {
        if (! $draftOrder instanceof DraftOrder) {
            return [
                'id' => null,
                'status_label' => __('No draft'),
                'sent_at' => null,
                'sent_by_guest_name' => null,
                'total' => '0.00 '.$currency,
            ];
        }

        $status = $draftOrder->status instanceof DraftOrderStatus
            ? $draftOrder->status
            : DraftOrderStatus::from((string) $draftOrder->status);

        return [
            'id' => $draftOrder->id,
            'status_label' => $status->label(),
            'sent_at' => $draftOrder->sent_to_waiter_at?->format('Y-m-d H:i'),
            'sent_by_guest_name' => $draftOrder->sentByGuest?->guest_name,
            'total' => $this->formatCents($totalCents).' '.$currency,
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
