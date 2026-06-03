<?php

namespace App\Livewire\PublicQr;

use App\Enums\TableSessionGuestStatus;
use App\Models\DraftOrder as DraftOrderModel;
use App\Models\DraftOrderItem;
use App\Models\TableSessionGuest;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Isolate;
use Livewire\Component;

#[Isolate]
class DraftOrder extends Component
{
    public int $tableSessionId = 0;

    public int $currentGuestId = 0;

    public string $currency = 'EUR';

    /**
     * @var list<array{id: int, guest_id: int, guest_name: string, item_name: string, quantity: int, unit_price: string, modifier_total: string, total_price: string, modifiers: list<string>, comment: string|null, is_current_guest: bool}>
     */
    public array $items = [];

    /**
     * @var list<array{guest_id: int, guest_name: string, total: string, is_current_guest: bool}>
     */
    public array $guestTotals = [];

    public string $totalAmount = '0.00';

    public int $itemCount = 0;

    public function mount(int $tableSessionId, int $currentGuestId, string $currency = 'EUR'): void
    {
        $this->tableSessionId = $tableSessionId;
        $this->currentGuestId = $currentGuestId;
        $this->currency = $currency;

        $this->refreshDraft();
    }

    public function refreshDraft(): void
    {
        $guests = $this->activeGuests();
        $draftOrder = $this->draftOrder();
        $draftItems = $draftOrder?->items ?? collect();
        $guestTotals = [];
        $totalCents = 0;

        $guests->each(function (TableSessionGuest $guest) use (&$guestTotals): void {
            $guestTotals[$guest->id] = [
                'guest_id' => $guest->id,
                'guest_name' => $guest->guest_name,
                'total_cents' => 0,
                'is_current_guest' => $guest->id === $this->currentGuestId,
            ];
        });

        $this->items = $draftItems
            ->map(function (DraftOrderItem $item) use (&$guestTotals, &$totalCents): array {
                $itemTotalCents = self::decimalToCents($item->total_price);
                $totalCents += $itemTotalCents;

                if ($item->guest instanceof TableSessionGuest && ! isset($guestTotals[$item->guest->id])) {
                    $guestTotals[$item->guest->id] = [
                        'guest_id' => $item->guest->id,
                        'guest_name' => $item->guest->guest_name,
                        'total_cents' => 0,
                        'is_current_guest' => $item->guest->id === $this->currentGuestId,
                    ];
                }

                if ($item->guest instanceof TableSessionGuest) {
                    $guestTotals[$item->guest->id]['total_cents'] += $itemTotalCents;
                }

                return [
                    'id' => $item->id,
                    'guest_id' => (int) $item->table_session_guest_id,
                    'guest_name' => $item->guest?->guest_name ?? __('Гость'),
                    'item_name' => $item->item_name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'modifier_total' => $item->modifier_total,
                    'total_price' => $item->total_price,
                    'modifiers' => $this->modifierSummary($item->selected_modifiers),
                    'comment' => $item->comment,
                    'is_current_guest' => $item->table_session_guest_id === $this->currentGuestId,
                ];
            })
            ->all();

        $this->guestTotals = collect($guestTotals)
            ->sortBy(fn (array $guestTotal): string => mb_strtolower($guestTotal['guest_name']))
            ->map(fn (array $guestTotal): array => [
                'guest_id' => $guestTotal['guest_id'],
                'guest_name' => $guestTotal['guest_name'],
                'total' => self::centsToDecimal($guestTotal['total_cents']),
                'is_current_guest' => $guestTotal['is_current_guest'],
            ])
            ->values()
            ->all();

        $this->totalAmount = self::centsToDecimal($totalCents);
        $this->itemCount = count($this->items);
    }

    public function render(): View
    {
        return view('livewire.public-qr.draft-order');
    }

    /**
     * @return Collection<int, TableSessionGuest>
     */
    private function activeGuests(): Collection
    {
        return TableSessionGuest::query()
            ->select([
                'id',
                'table_session_id',
                'guest_name',
                'status',
            ])
            ->where('table_session_id', $this->tableSessionId)
            ->where('status', TableSessionGuestStatus::Active->value)
            ->orderBy('guest_name')
            ->orderBy('id')
            ->limit(100)
            ->get();
    }

    private function draftOrder(): ?DraftOrderModel
    {
        return DraftOrderModel::query()
            ->select([
                'id',
                'table_session_id',
                'status',
            ])
            ->with([
                'items' => fn ($query) => $query
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
                    ->with([
                        'guest' => fn ($guestQuery) => $guestQuery->select([
                            'id',
                            'guest_name',
                            'status',
                        ]),
                    ])
                    ->orderBy('created_at')
                    ->orderBy('id'),
            ])
            ->where('table_session_id', $this->tableSessionId)
            ->first();
    }

    /**
     * @return list<string>
     */
    private function modifierSummary(mixed $selectedModifiers): array
    {
        if (! is_array($selectedModifiers)) {
            return [];
        }

        return collect($selectedModifiers)
            ->map(function (mixed $modifier): ?string {
                if (! is_array($modifier)) {
                    return null;
                }

                $optionName = $modifier['option_name'] ?? null;

                return is_string($optionName) && $optionName !== '' ? $optionName : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    private static function decimalToCents(string|int|float|null $amount): int
    {
        return (int) round(((float) ($amount ?? 0)) * 100);
    }

    private static function centsToDecimal(int $amount): string
    {
        $negative = $amount < 0;
        $absoluteAmount = abs($amount);
        $formatted = intdiv($absoluteAmount, 100).'.'.str_pad((string) ($absoluteAmount % 100), 2, '0', STR_PAD_LEFT);

        return $negative ? '-'.$formatted : $formatted;
    }
}
