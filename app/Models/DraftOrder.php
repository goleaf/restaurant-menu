<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DraftOrderStatus;
use App\Support\MoneyFormatter;
use Carbon\CarbonInterface;
use Database\Factories\DraftOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * @property DraftOrderStatus $status
 * @property CarbonInterface|null $sent_to_waiter_at
 * @property CarbonInterface|null $rejected_at
 * @property CarbonInterface|null $converted_to_order_at
 * @property-read TableSessionGuest|null $sentByGuest
 */
#[Fillable(['table_session_id', 'sent_to_waiter_at', 'sent_by_guest_id', 'rejected_at', 'rejected_by_user_id', 'rejection_reason', 'converted_to_order_at', 'converted_by_user_id'])]
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

        return MoneyFormatter::centsToDecimal((int) $items->sum('total_price_cents'));
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
                    'total' => MoneyFormatter::centsToDecimal((int) $items->sum('total_price_cents')),
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
            ->select(['id', 'draft_order_id', 'table_session_guest_id', 'total_price_cents', 'created_at'])
            ->with(['guest:id,guest_name'])
            ->get();
    }
}
