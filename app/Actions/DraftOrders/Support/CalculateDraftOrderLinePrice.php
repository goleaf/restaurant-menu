<?php

namespace App\Actions\DraftOrders\Support;

use App\Models\DraftOrderItem;
use App\Models\MenuItem;
use App\Support\MoneyFormatter;
use Illuminate\Validation\ValidationException;

class CalculateDraftOrderLinePrice
{
    public function __construct(
        private readonly BuildDraftOrderItemModifierSnapshots $modifierSnapshots,
    ) {}

    /**
     * @param  array<string, mixed>  $selectedModifierOptions
     * @return array{unit_price: string, modifier_total: string, total_price: string, selected_modifiers: list<array{group_id: int, group_name: string, option_id: int, option_name: string, price_delta: string}>}
     */
    public function forMenuItem(MenuItem $menuItem, array $selectedModifierOptions, int $quantity): array
    {
        $modifierGroups = $this->modifierSnapshots->groupsFor($menuItem);
        $selectedModifiers = $this->modifierSnapshots->snapshotsFor($modifierGroups, $selectedModifierOptions);
        $unitPriceCents = MoneyFormatter::decimalToCents($menuItem->price);
        $modifierTotalCents = $this->modifierSnapshots->modifierTotalCents($selectedModifiers);

        return $this->payload(
            unitPriceCents: $unitPriceCents,
            modifierTotalCents: $modifierTotalCents,
            quantity: $quantity,
            selectedModifiers: $selectedModifiers,
        );
    }

    /**
     * @param  array<string, mixed>  $selectedModifierOptions
     * @return array{unit_price: string, modifier_total: string, total_price: string, selected_modifiers: list<array{group_id: int, group_name: string, option_id: int, option_name: string, price_delta: string}>}
     */
    public function forDraftOrderItem(DraftOrderItem $draftOrderItem, array $selectedModifierOptions, int $quantity): array
    {
        $selectedModifiers = $this->existingModifierSnapshots($draftOrderItem->selected_modifiers);
        $modifierTotalCents = $this->modifierSnapshots->modifierTotalCents($selectedModifiers);

        if ($draftOrderItem->menuItem instanceof MenuItem) {
            $modifierGroups = $this->modifierSnapshots->groupsFor($draftOrderItem->menuItem);
            $selectedModifiers = $this->preserveExistingModifierPrices(
                currentSnapshots: $this->modifierSnapshots->snapshotsFor($modifierGroups, $selectedModifierOptions),
                existingSnapshots: $selectedModifiers,
            );
            $modifierTotalCents = $this->modifierSnapshots->modifierTotalCents($selectedModifiers);
        }

        return $this->payload(
            unitPriceCents: MoneyFormatter::decimalToCents($draftOrderItem->unit_price),
            modifierTotalCents: $modifierTotalCents,
            quantity: $quantity,
            selectedModifiers: $selectedModifiers,
        );
    }

    /**
     * @param  list<array{group_id: int, group_name: string, option_id: int, option_name: string, price_delta: string}>  $selectedModifiers
     * @return array{unit_price: string, modifier_total: string, total_price: string, selected_modifiers: list<array{group_id: int, group_name: string, option_id: int, option_name: string, price_delta: string}>}
     */
    private function payload(int $unitPriceCents, int $modifierTotalCents, int $quantity, array $selectedModifiers): array
    {
        if ($unitPriceCents < 0) {
            throw ValidationException::withMessages([
                'menu_item' => __('Цена позиции не может быть отрицательной.'),
            ]);
        }

        $lineUnitTotalCents = $unitPriceCents + $modifierTotalCents;

        if ($lineUnitTotalCents < 0) {
            throw ValidationException::withMessages([
                'selectedModifierOptions' => __('Итоговая цена позиции не может быть отрицательной.'),
            ]);
        }

        return [
            'unit_price' => MoneyFormatter::centsToDecimal($unitPriceCents),
            'modifier_total' => MoneyFormatter::centsToDecimal($modifierTotalCents),
            'total_price' => MoneyFormatter::centsToDecimal($lineUnitTotalCents * $quantity),
            'selected_modifiers' => $selectedModifiers,
        ];
    }

    /**
     * @return list<array{group_id: int, group_name: string, option_id: int, option_name: string, price_delta: string}>
     */
    private function existingModifierSnapshots(mixed $selectedModifiers): array
    {
        if (! is_array($selectedModifiers)) {
            return [];
        }

        return collect($selectedModifiers)
            ->map(function (mixed $modifier): ?array {
                if (! is_array($modifier)) {
                    return null;
                }

                $groupId = (int) ($modifier['group_id'] ?? 0);
                $optionId = (int) ($modifier['option_id'] ?? 0);

                if ($groupId < 1 || $optionId < 1) {
                    return null;
                }

                return [
                    'group_id' => $groupId,
                    'group_name' => (string) ($modifier['group_name'] ?? ''),
                    'option_id' => $optionId,
                    'option_name' => (string) ($modifier['option_name'] ?? ''),
                    'price_delta' => MoneyFormatter::centsToDecimal(
                        MoneyFormatter::decimalToCents($modifier['price_delta'] ?? '0.00'),
                    ),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  list<array{group_id: int, group_name: string, option_id: int, option_name: string, price_delta: string}>  $currentSnapshots
     * @param  list<array{group_id: int, group_name: string, option_id: int, option_name: string, price_delta: string}>  $existingSnapshots
     * @return list<array{group_id: int, group_name: string, option_id: int, option_name: string, price_delta: string}>
     */
    private function preserveExistingModifierPrices(array $currentSnapshots, array $existingSnapshots): array
    {
        return collect($currentSnapshots)
            ->map(function (array $currentSnapshot) use ($existingSnapshots): array {
                $existingSnapshot = collect($existingSnapshots)
                    ->first(fn (array $snapshot): bool => (int) $snapshot['group_id'] === (int) $currentSnapshot['group_id']
                        && (int) $snapshot['option_id'] === (int) $currentSnapshot['option_id']);

                return is_array($existingSnapshot) ? $existingSnapshot : $currentSnapshot;
            })
            ->values()
            ->all();
    }
}
