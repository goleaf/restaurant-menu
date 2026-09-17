<?php

declare(strict_types=1);

namespace App\Actions\DraftOrders;

use App\Actions\DraftOrders\Support\BuildDraftOrderItemModifierSnapshots;
use App\Actions\DraftOrders\Support\ResolveMenuItemVariantSelectionAction;
use App\Enums\BusinessRuleCode;
use App\Exceptions\BusinessRuleViolation;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\MenuItem;
use App\Services\Availability\AvailabilityEvaluator;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class EnsureDraftMenuItemAvailableAction
{
    public function __construct(
        private readonly AvailabilityEvaluator $availability,
        private readonly ResolveMenuItemVariantSelectionAction $variants,
        private readonly BuildDraftOrderItemModifierSnapshots $modifiers,
    ) {}

    public function handle(MenuItem $menuItem, int $branchId, string $field = 'menu_item', ?CarbonImmutable $at = null): void
    {
        if ($menuItem->menu === null || $menuItem->menu->branch_id !== $branchId) {
            throw BusinessRuleViolation::for(BusinessRuleCode::ItemUnavailable, $field, __('menu.guest.item_no_longer_available'));
        }
        $decision = $this->availability->item($menuItem, $at ?? CarbonImmutable::now());
        if (! $decision->acceptsNewOrders) {
            throw BusinessRuleViolation::for(
                BusinessRuleCode::ItemUnavailable,
                $decision->primaryCode() === 'variants_unavailable' ? 'selectedItemVariantId' : $field,
                $decision->publicMessage(),
            );
        }
    }

    /** @return array<string, mixed> */
    public static function relations(): array
    {
        return [...AvailabilityEvaluator::itemRelations(),
            'variants' => fn ($query) => $query->select(['id', 'menu_item_id', 'name', 'type', 'price_cents', 'is_available']),
            'modifierGroups' => fn ($query) => $query->select(['modifier_groups.id', 'modifier_groups.branch_id', 'modifier_groups.name', 'modifier_groups.is_required', 'modifier_groups.min_select', 'modifier_groups.max_select']),
            'modifierGroups.options' => fn ($query) => $query->select(['id', 'modifier_group_id', 'name', 'price_delta_cents', 'is_available']),
        ];
    }

    /** @param array<int|string, mixed> $selection */
    public function selection(DraftOrderItem $line, int $branchId, array $selection, ?int $variantId, CarbonImmutable $at, string $field = 'menu_item'): void
    {
        if (! $line->menuItem instanceof MenuItem) {
            throw ValidationException::withMessages([$field => __('menu.guest.item_no_longer_available')]);
        }
        $this->handle($line->menuItem, $branchId, $field, $at);
        if ($variantId === null && $line->menu_item_variant_id === null && $line->variant_name !== null) {
            throw ValidationException::withMessages([$field => __('menu.variants.validation.unavailable')]);
        }
        $this->variants->handle($line->menuItem, $variantId ?? $line->menu_item_variant_id);
        $this->modifiers->snapshotsFor($this->modifiers->groupsFor($line->menuItem), $selection);
    }

    public function draft(DraftOrder $draft, CarbonImmutable $at, string $field): void
    {
        $lines = DraftOrderItem::query()->select(['id', 'draft_order_id', 'menu_item_id', 'menu_item_variant_id', 'variant_name', 'selected_modifiers'])
            ->with(['menuItem' => fn ($query) => $query->select(['id', 'menu_id', 'category_id', 'name', 'is_available', 'hidden_until', 'deleted_at'])
                ->with(self::relations())])
            ->where('draft_order_id', $draft->id)->get();
        foreach ($lines as $line) {
            $selection = [];
            $snapshots = $line->getAttribute('selected_modifiers') ?? [];
            if (! is_array($snapshots)) {
                throw ValidationException::withMessages([$field => __('menu.guest.item_no_longer_available')]);
            }
            foreach ($snapshots as $option) {
                $groupId = is_array($option) ? filter_var($option['group_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
                $optionId = is_array($option) ? filter_var($option['option_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
                if ($groupId === false || $optionId === false) {
                    throw ValidationException::withMessages([$field => __('menu.guest.item_no_longer_available')]);
                }
                $selection[(string) $groupId][] = $optionId;
            }
            try {
                $this->selection($line, (int) $draft->tableSession->branch_id, $selection, $line->menu_item_variant_id, $at, $field);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages([$field => $exception->validator->errors()->first()]);
            }
        }
    }
}
