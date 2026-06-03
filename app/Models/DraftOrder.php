<?php

namespace App\Models;

use App\Enums\DraftOrderStatus;
use Database\Factories\DraftOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

#[Fillable(['table_session_id', 'status', 'sent_to_waiter_at', 'sent_by_guest_id', 'rejected_at', 'rejected_by_user_id', 'rejection_reason', 'converted_to_order_at', 'converted_by_user_id'])]
class DraftOrder extends Model
{
    /** @use HasFactory<DraftOrderFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DraftOrderStatus::class,
            'sent_to_waiter_at' => 'datetime',
            'rejected_at' => 'datetime',
            'converted_to_order_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TableSession, $this>
     */
    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
    }

    /**
     * @return BelongsTo<TableSessionGuest, $this>
     */
    public function sentByGuest(): BelongsTo
    {
        return $this->belongsTo(TableSessionGuest::class, 'sent_by_guest_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function convertedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_by_user_id');
    }

    /**
     * @return HasOne<Order, $this>
     */
    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    /**
     * @return HasMany<DraftOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(DraftOrderItem::class)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * @return HasMany<OrderStatusLog, $this>
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class)
            ->orderBy('occurred_at')
            ->orderBy('id');
    }

    public function totalAmount(): string
    {
        $items = $this->loadedItems();

        return self::formatCents($items->sum(
            fn (DraftOrderItem $item): int => self::decimalToCents($item->total_price),
        ));
    }

    /**
     * @return list<array{guest_id: int, guest_name: string, total: string}>
     */
    public function guestTotals(): array
    {
        return $this->loadedItems()
            ->groupBy('table_session_guest_id')
            ->map(function (Collection $items): array {
                /** @var DraftOrderItem|null $firstItem */
                $firstItem = $items->first();

                return [
                    'guest_id' => (int) $firstItem?->table_session_guest_id,
                    'guest_name' => (string) $firstItem?->guest?->guest_name,
                    'total' => self::formatCents($items->sum(
                        fn (DraftOrderItem $item): int => self::decimalToCents($item->total_price),
                    )),
                ];
            })
            ->sortBy(fn (array $guestTotal): string => mb_strtolower($guestTotal['guest_name']))
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, DraftOrderItem>
     */
    private function loadedItems(): Collection
    {
        if ($this->relationLoaded('items')) {
            $this->items->loadMissing(['guest:id,guest_name']);

            return $this->items;
        }

        return $this->items()
            ->select(['id', 'draft_order_id', 'table_session_guest_id', 'total_price', 'created_at'])
            ->with(['guest:id,guest_name'])
            ->get();
    }

    private static function decimalToCents(string|int|float|null $amount): int
    {
        $normalized = number_format((float) ($amount ?? 0), 2, '.', '');
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '-');
        [$whole, $fraction] = explode('.', $normalized);
        $cents = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

        return $negative ? -$cents : $cents;
    }

    private static function formatCents(int $cents): string
    {
        $negative = $cents < 0;
        $absoluteCents = abs($cents);
        $formatted = intdiv($absoluteCents, 100).'.'.str_pad((string) ($absoluteCents % 100), 2, '0', STR_PAD_LEFT);

        return $negative ? '-'.$formatted : $formatted;
    }
}
