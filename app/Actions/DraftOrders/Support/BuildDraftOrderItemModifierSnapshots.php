<?php

declare(strict_types=1);

namespace App\Actions\DraftOrders\Support;

use App\Enums\BusinessRuleCode;
use App\Exceptions\BusinessRuleViolation;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierGroupTranslation;
use App\Models\ModifierOption;
use App\Models\ModifierOptionTranslation;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BuildDraftOrderItemModifierSnapshots
{
    /**
     * @return Collection<int, ModifierGroup>
     */
    public function groupsFor(MenuItem $menuItem, ?string $languageCode = null): Collection
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
            ->when($languageCode !== null, fn ($query) => $query->addSelect([
                'localized_name' => ModifierGroupTranslation::query()
                    ->select('name')
                    ->whereColumn('modifier_group_id', 'modifier_groups.id')
                    ->where('language_code', $languageCode)
                    ->limit(1),
            ]))
            ->with([
                'options' => fn ($query) => $query
                    ->select([
                        'id',
                        'modifier_group_id',
                        'name',
                        'price_delta_cents',
                        'is_available',
                        'sort_order',
                    ])
                    ->when($languageCode !== null, fn ($optionQuery) => $optionQuery->addSelect([
                        'localized_name' => ModifierOptionTranslation::query()
                            ->select('name')
                            ->whereColumn('modifier_option_id', 'modifier_options.id')
                            ->where('language_code', $languageCode)
                            ->limit(1),
                    ]))
                    ->where('is_available', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->orderBy('id'),
            ])
            ->get();
    }

    /**
     * @param  Collection<int, ModifierGroup>  $modifierGroups
     * @param  array<string, mixed>  $selectedModifierOptions
     * @return list<array{group_id: int, group_name: string, option_id: int, option_name: string, price_delta_cents: int}>
     */
    public function snapshotsFor(Collection $modifierGroups, array $selectedModifierOptions): array
    {
        $selection = $this->normalizeSelectedModifierOptions($selectedModifierOptions);

        $this->ensureModifierSelectionIsValid($modifierGroups, $selection);

        return $this->selectedModifierSnapshots($modifierGroups, $selection);
    }

    /**
     * @param  list<array{price_delta_cents: int}>  $selectedModifiers
     */
    public function modifierTotalCents(array $selectedModifiers): int
    {
        return collect($selectedModifiers)
            ->sum(fn (array $modifier): int => $modifier['price_delta_cents']);
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
                throw BusinessRuleViolation::for(
                    BusinessRuleCode::ItemUnavailable,
                    'selectedModifierOptions.'.$modifierGroupId,
                    __('ui.actions.draftorders.support.builddraftorderitemmodifiersnapshots.vybrannyi_va'),
                );
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
                    throw BusinessRuleViolation::for(
                        BusinessRuleCode::ItemUnavailable,
                        'selectedModifierOptions.'.$modifierGroup->id,
                        __('ui.actions.draftorders.support.builddraftorderitemmodifiersnapshots.vybrannyi_va'),
                    );
                }
            }

            $selectedCount = count($selectedOptionIds);
            $minSelect = (int) $modifierGroup->min_select;
            $maxSelect = (int) $modifierGroup->max_select;

            if ($modifierGroup->is_required && $minSelect === 0) {
                $minSelect = 1;
            }

            if ($selectedCount < $minSelect) {
                throw BusinessRuleViolation::for(
                    BusinessRuleCode::RequiredModifierMissing,
                    'selectedModifierOptions.'.$modifierGroup->id,
                    __('ui.actions.draftorders.support.builddraftorderitemmodifiersnapshots.vyberite_var'),
                );
            }

            if ($maxSelect > 0 && $selectedCount > $maxSelect) {
                throw ValidationException::withMessages([
                    'selectedModifierOptions.'.$modifierGroup->id => __('ui.actions.draftorders.support.builddraftorderitemmodifiersnapshots.vybrano_slis'),
                ]);
            }
        });
    }

    /**
     * @param  Collection<int, ModifierGroup>  $modifierGroups
     * @param  array<int, list<int>>  $selection
     * @return list<array{group_id: int, group_name: string, option_id: int, option_name: string, price_delta_cents: int}>
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
                    'group_name' => $this->localizedName($modifierGroup),
                    'option_id' => $modifierOption->id,
                    'option_name' => $this->localizedName($modifierOption),
                    'price_delta_cents' => $modifierOption->price_delta_cents,
                ];
            });
        });

        return $snapshots;
    }

    private function localizedName(ModifierGroup|ModifierOption $model): string
    {
        $localizedName = $model->getAttribute('localized_name');

        return is_string($localizedName) && filled($localizedName)
            ? $localizedName
            : $model->name;
    }
}
