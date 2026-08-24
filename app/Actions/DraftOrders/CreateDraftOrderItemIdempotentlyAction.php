<?php

declare(strict_types=1);

namespace App\Actions\DraftOrders;

use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Support\Orders\IdempotencyKey;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

final class CreateDraftOrderItemIdempotentlyAction
{
    /**
     * @param  array{table_session_guest_id: int, menu_item_id: int|null, menu_item_variant_id: int|null, item_name: string, variant_name: string|null, variant_type: string|null, quantity: int, unit_price_cents: int, modifier_total_cents: int, total_price_cents: int, selected_modifiers: array<int, mixed>, comment: string|null}  $data
     */
    public function handle(DraftOrder $draftOrder, array $data, ?IdempotencyKey $idempotencyKey): DraftOrderItem
    {
        if (! $idempotencyKey instanceof IdempotencyKey) {
            return $draftOrder->items()->create($data);
        }

        $draftOrderItem = $this->query($draftOrder)->createOrFirst(
            ['idempotency_key' => $idempotencyKey->value],
            $data,
        );

        $this->ensureSameGuest($draftOrderItem, $data['table_session_guest_id']);

        return $draftOrderItem;
    }

    public function existing(
        DraftOrder $draftOrder,
        IdempotencyKey $idempotencyKey,
        ?int $expectedGuestId = null,
    ): ?DraftOrderItem {
        $draftOrderItem = $this->query($draftOrder)
            ->where('idempotency_key', $idempotencyKey->value)
            ->first();

        if ($draftOrderItem instanceof DraftOrderItem && $expectedGuestId !== null) {
            $this->ensureSameGuest($draftOrderItem, $expectedGuestId);
        }

        return $draftOrderItem;
    }

    /** @return HasMany<DraftOrderItem, DraftOrder> */
    private function query(DraftOrder $draftOrder): HasMany
    {
        return $draftOrder->items()->select([
            'id',
            'draft_order_id',
            'table_session_guest_id',
            'menu_item_id',
            'menu_item_variant_id',
            'item_name',
            'variant_name',
            'variant_type',
            'quantity',
            'unit_price_cents',
            'modifier_total_cents',
            'total_price_cents',
            'selected_modifiers',
            'comment',
            'idempotency_key',
            'created_at',
            'updated_at',
        ]);
    }

    private function ensureSameGuest(DraftOrderItem $draftOrderItem, int $expectedGuestId): void
    {
        if ($draftOrderItem->table_session_guest_id !== $expectedGuestId) {
            throw ValidationException::withMessages([
                'idempotency_key' => __('errors.types.validation_error.message'),
            ]);
        }
    }
}
