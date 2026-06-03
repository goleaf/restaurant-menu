<?php

namespace App\Actions\DraftOrders;

use App\Enums\DraftOrderStatus;
use App\Enums\MenuStatus;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddGuestDraftOrderItemAction
{
    /**
     * @param  array<string, mixed>  $selectedModifierOptions
     */
    public function handle(
        TableSession $tableSession,
        TableSessionGuest $guest,
        MenuItem $menuItem,
        array $selectedModifierOptions,
        ?string $comment = null,
        ?string $itemName = null,
    ): DraftOrderItem {
        return DB::transaction(function () use ($tableSession, $guest, $menuItem, $selectedModifierOptions, $comment, $itemName): DraftOrderItem {
            $tableSession = $this->reloadTableSession($tableSession);
            $guest = $this->reloadGuest($guest);
            $menuItem = $this->reloadMenuItem($menuItem);

            $this->ensureGuestCanAddItems($tableSession, $guest);
            $this->ensureMenuItemCanBeAdded($tableSession, $menuItem);

            $modifierGroups = $this->assignedModifierGroupsFor($menuItem);
            $selection = $this->normalizeSelectedModifierOptions($selectedModifierOptions);
            $this->ensureModifierSelectionIsValid($modifierGroups, $selection);

            $selectedModifiers = $this->selectedModifierSnapshots($modifierGroups, $selection);
            $unitPriceCents = self::decimalToCents($menuItem->price);
            $modifierTotalCents = collect($selectedModifiers)
                ->sum(fn (array $modifier): int => self::decimalToCents($modifier['price_delta']));
            $lineTotalCents = max(0, $unitPriceCents + $modifierTotalCents);
            $draftOrder = $this->draftOrderFor($tableSession);

            return $draftOrder->items()->create([
                'table_session_guest_id' => $guest->id,
                'menu_item_id' => $menuItem->id,
                'item_name' => $this->snapshotName($itemName, $menuItem),
                'quantity' => 1,
                'unit_price' => self::centsToDecimal($unitPriceCents),
                'modifier_total' => self::centsToDecimal($modifierTotalCents),
                'total_price' => self::centsToDecimal($lineTotalCents),
                'selected_modifiers' => $selectedModifiers,
                'comment' => $this->normalizeComment($comment),
            ])->refresh();
        });
    }

    private function reloadTableSession(TableSession $tableSession): TableSession
    {
        return TableSession::query()
            ->select([
                'id',
                'branch_id',
                'service_point_id',
                'status',
                'ended_at',
            ])
            ->whereKey($tableSession->id)
            ->firstOrFail();
    }

    private function reloadGuest(TableSessionGuest $guest): TableSessionGuest
    {
        return TableSessionGuest::query()
            ->select([
                'id',
                'table_session_id',
                'guest_name',
                'guest_token',
                'status',
                'joined_at',
                'left_at',
            ])
            ->whereKey($guest->id)
            ->firstOrFail();
    }

    private function reloadMenuItem(MenuItem $menuItem): MenuItem
    {
        return MenuItem::query()
            ->select([
                'id',
                'menu_id',
                'category_id',
                'name',
                'price',
                'is_available',
            ])
            ->with([
                'menu' => fn ($query) => $query->select([
                    'id',
                    'branch_id',
                    'status',
                ]),
                'category' => fn ($query) => $query->select([
                    'id',
                    'menu_id',
                    'is_active',
                ]),
            ])
            ->whereKey($menuItem->id)
            ->firstOrFail();
    }

    private function ensureGuestCanAddItems(TableSession $tableSession, TableSessionGuest $guest): void
    {
        if ($guest->table_session_id !== $tableSession->id
            || $guest->status !== TableSessionGuestStatus::Active
            || in_array($tableSession->status, [TableSessionStatus::Closed, TableSessionStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'guest' => __('Только активный гость за этим столом может добавлять позиции.'),
            ]);
        }
    }

    private function ensureMenuItemCanBeAdded(TableSession $tableSession, MenuItem $menuItem): void
    {
        if ($menuItem->menu?->branch_id !== $tableSession->branch_id
            || $menuItem->menu?->status !== MenuStatus::Active
            || ! $menuItem->category?->is_active
            || ! $menuItem->is_available) {
            throw ValidationException::withMessages([
                'menu_item' => __('Это блюдо сейчас недоступно.'),
            ]);
        }
    }

    /**
     * @return Collection<int, ModifierGroup>
     */
    private function assignedModifierGroupsFor(MenuItem $menuItem): Collection
    {
        return $menuItem->modifierGroups()
            ->select([
                'modifier_groups.id',
                'modifier_groups.branch_id',
                'modifier_groups.name',
                'modifier_groups.is_required',
                'modifier_groups.min_select',
                'modifier_groups.max_select',
                'modifier_groups.sort_order',
            ])
            ->with([
                'options' => fn ($query) => $query
                    ->select([
                        'id',
                        'modifier_group_id',
                        'name',
                        'price_delta',
                        'is_available',
                        'sort_order',
                    ])
                    ->where('is_available', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->orderBy('id'),
            ])
            ->get();
    }

    /**
     * @param  array<string, mixed>  $selectedModifierOptions
     * @return array<int, list<int>>
     */
    private function normalizeSelectedModifierOptions(array $selectedModifierOptions): array
    {
        return collect($selectedModifierOptions)
            ->mapWithKeys(function (mixed $optionIds, string|int $modifierGroupId): array {
                $normalizedGroupId = (int) $modifierGroupId;

                if ($normalizedGroupId < 1 || ! is_array($optionIds)) {
                    return [];
                }

                $normalizedOptionIds = collect($optionIds)
                    ->map(fn (mixed $optionId): int => (int) $optionId)
                    ->filter(fn (int $optionId): bool => $optionId > 0)
                    ->unique()
                    ->values()
                    ->all();

                return [$normalizedGroupId => $normalizedOptionIds];
            })
            ->all();
    }

    /**
     * @param  Collection<int, ModifierGroup>  $modifierGroups
     * @param  array<int, list<int>>  $selection
     */
    private function ensureModifierSelectionIsValid(Collection $modifierGroups, array $selection): void
    {
        $assignedGroupIds = $modifierGroups->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        foreach (array_keys($selection) as $modifierGroupId) {
            if (! in_array($modifierGroupId, $assignedGroupIds, true)) {
                throw ValidationException::withMessages([
                    'selectedModifierOptions.'.$modifierGroupId => __('Выбранный вариант недоступен.'),
                ]);
            }
        }

        $modifierGroups->each(function (ModifierGroup $modifierGroup) use ($selection): void {
            $selectedOptionIds = $selection[$modifierGroup->id] ?? [];
            $availableOptionIds = $modifierGroup->options
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            foreach ($selectedOptionIds as $optionId) {
                if (! in_array($optionId, $availableOptionIds, true)) {
                    throw ValidationException::withMessages([
                        'selectedModifierOptions.'.$modifierGroup->id => __('Выбранный вариант недоступен.'),
                    ]);
                }
            }

            $selectedCount = count($selectedOptionIds);
            $minSelect = (int) $modifierGroup->min_select;
            $maxSelect = (int) $modifierGroup->max_select;

            if ($modifierGroup->is_required && $minSelect === 0) {
                $minSelect = 1;
            }

            if ($selectedCount < $minSelect) {
                throw ValidationException::withMessages([
                    'selectedModifierOptions.'.$modifierGroup->id => __('Выберите вариант.'),
                ]);
            }

            if ($maxSelect > 0 && $selectedCount > $maxSelect) {
                throw ValidationException::withMessages([
                    'selectedModifierOptions.'.$modifierGroup->id => __('Выбрано слишком много вариантов.'),
                ]);
            }
        });
    }

    /**
     * @param  Collection<int, ModifierGroup>  $modifierGroups
     * @param  array<int, list<int>>  $selection
     * @return list<array{group_id: int, group_name: string, option_id: int, option_name: string, price_delta: string}>
     */
    private function selectedModifierSnapshots(Collection $modifierGroups, array $selection): array
    {
        $snapshots = [];

        $modifierGroups->each(function (ModifierGroup $modifierGroup) use ($selection, &$snapshots): void {
            $selectedOptionIds = $selection[$modifierGroup->id] ?? [];

            $modifierGroup->options->each(function (ModifierOption $modifierOption) use ($modifierGroup, $selectedOptionIds, &$snapshots): void {
                if (! in_array($modifierOption->id, $selectedOptionIds, true)) {
                    return;
                }

                $snapshots[] = [
                    'group_id' => $modifierGroup->id,
                    'group_name' => $modifierGroup->name,
                    'option_id' => $modifierOption->id,
                    'option_name' => $modifierOption->name,
                    'price_delta' => $modifierOption->price_delta,
                ];
            });
        });

        return $snapshots;
    }

    private function draftOrderFor(TableSession $tableSession): DraftOrder
    {
        $draftOrder = DraftOrder::query()->firstOrCreate(
            ['table_session_id' => $tableSession->id],
            ['status' => DraftOrderStatus::Draft],
        );

        if ($draftOrder->status !== DraftOrderStatus::Draft) {
            throw ValidationException::withMessages([
                'draft_order' => __('Этот черновик уже отправлен официанту.'),
            ]);
        }

        return $draftOrder;
    }

    private function snapshotName(?string $itemName, MenuItem $menuItem): string
    {
        $normalizedItemName = str((string) $itemName)->squish()->toString();

        return $normalizedItemName === '' ? $menuItem->name : $normalizedItemName;
    }

    private function normalizeComment(?string $comment): ?string
    {
        $normalizedComment = trim((string) $comment);

        if ($normalizedComment === '') {
            return null;
        }

        if (mb_strlen($normalizedComment) > 500) {
            throw ValidationException::withMessages([
                'itemComment' => __('Комментарий слишком длинный.'),
            ]);
        }

        return $normalizedComment;
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
