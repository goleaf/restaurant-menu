<?php

namespace App\Livewire\PublicQr;

use App\Actions\Menus\GetGuestMenuForBranchAction;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class GuestMenu extends Component
{
    public int $branchId;

    public string $currency = 'EUR';

    #[Url(as: 'lang')]
    public string $language = '';

    /**
     * @var array<string, string>
     */
    public array $languageOptions = [];

    public ?int $selectedItemId = null;

    /**
     * @var array<string, list<int>>
     */
    public array $selectedModifierOptions = [];

    public string $itemComment = '';

    /**
     * @var array<int, array{name: string, total_price: string, modifier_summary: list<string>, comment: string|null}>
     */
    public array $configuredItems = [];

    public function mount(int $branchId, string $currency = 'EUR'): void
    {
        $this->branchId = $branchId;
        $this->currency = $currency;
        $this->languageOptions = GetGuestMenuForBranchAction::supportedLanguageLabels();
        $this->language = app(GetGuestMenuForBranchAction::class)->resolveLanguageForBranch($branchId, $this->language);
    }

    public function updatedLanguage(): void
    {
        $this->language = app(GetGuestMenuForBranchAction::class)->resolveLanguageForBranch($this->branchId, $this->language);
        unset($this->guestMenu);
        $this->configuredItems = [];
        $this->closeItemSheet();
    }

    public function openItem(int $itemId): void
    {
        $item = $this->findItemInGuestMenu($itemId);

        if ($item === null || ! (bool) $item['is_available']) {
            return;
        }

        $this->resetValidation();
        $this->selectedItemId = $itemId;
        $this->selectedModifierOptions = [];
        $this->itemComment = '';

        foreach ($item['modifier_groups'] as $modifierGroup) {
            $this->selectedModifierOptions[(string) $modifierGroup['id']] = [];
        }
    }

    public function closeItemSheet(): void
    {
        $this->resetValidation();
        $this->selectedItemId = null;
        $this->selectedModifierOptions = [];
        $this->itemComment = '';
    }

    public function toggleModifierOption(int $modifierGroupId, int $modifierOptionId): void
    {
        $item = $this->selectedItem();
        $group = $item === null ? null : $this->findModifierGroupInItem($item, $modifierGroupId);
        $option = $group === null ? null : $this->findModifierOptionInGroup($group, $modifierOptionId);

        if ($group === null || $option === null) {
            return;
        }

        $selected = $this->selectedOptionIdsForGroup($modifierGroupId, $item);

        if (in_array($modifierOptionId, $selected, true)) {
            $this->selectedModifierOptions[(string) $modifierGroupId] = array_values(array_filter(
                $selected,
                fn (int $selectedOptionId): bool => $selectedOptionId !== $modifierOptionId,
            ));

            return;
        }

        $maxSelect = max(0, (int) $group['max_select']);

        if ($maxSelect === 0) {
            return;
        }

        if ($maxSelect === 1) {
            $this->selectedModifierOptions[(string) $modifierGroupId] = [$modifierOptionId];

            return;
        }

        if (count($selected) >= $maxSelect) {
            return;
        }

        $selected[] = $modifierOptionId;
        $this->selectedModifierOptions[(string) $modifierGroupId] = array_values($selected);
    }

    public function saveConfiguredItem(): void
    {
        $this->resetValidation();

        $item = $this->selectedItem();

        if ($item === null || ! (bool) $item['is_available']) {
            $this->closeItemSheet();

            return;
        }

        if (! $this->validateModifierSelection($item)) {
            return;
        }

        $this->itemComment = trim($this->itemComment);
        $this->configuredItems[$item['id']] = [
            'name' => $item['name'],
            'total_price' => $this->selectedItemTotal($item),
            'modifier_summary' => $this->selectedModifierSummary($item),
            'comment' => $this->itemComment === '' ? null : $this->itemComment,
        ];

        $this->closeItemSheet();
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function guestMenu(): array
    {
        return app(GetGuestMenuForBranchAction::class)->handle($this->branchId, $this->language);
    }

    public function render(): View
    {
        $selectedItem = $this->selectedItem();

        return view('livewire.public-qr.guest-menu', [
            'guestMenu' => $this->guestMenu,
            'selectedItem' => $selectedItem,
            'selectedItemTotal' => $selectedItem === null ? '0.00' : $this->selectedItemTotal($selectedItem),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function selectedItem(): ?array
    {
        if ($this->selectedItemId === null) {
            return null;
        }

        return $this->findItemInGuestMenu($this->selectedItemId);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findItemInGuestMenu(int $itemId): ?array
    {
        foreach ($this->guestMenu['categories'] as $category) {
            foreach ($category['items'] as $item) {
                if ((int) $item['id'] === $itemId) {
                    return $item;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function findModifierGroupInItem(array $item, int $modifierGroupId): ?array
    {
        foreach ($item['modifier_groups'] as $modifierGroup) {
            if ((int) $modifierGroup['id'] === $modifierGroupId) {
                return $modifierGroup;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $modifierGroup
     * @return array<string, mixed>|null
     */
    private function findModifierOptionInGroup(array $modifierGroup, int $modifierOptionId): ?array
    {
        foreach ($modifierGroup['options'] as $modifierOption) {
            if ((int) $modifierOption['id'] === $modifierOptionId) {
                return $modifierOption;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function validateModifierSelection(array $item): bool
    {
        $isValid = true;

        foreach ($item['modifier_groups'] as $modifierGroup) {
            $selectedCount = count($this->selectedOptionIdsForGroup((int) $modifierGroup['id'], $item));
            $minSelect = (int) $modifierGroup['min_select'];
            $maxSelect = (int) $modifierGroup['max_select'];
            $errorKey = 'selectedModifierOptions.'.(string) $modifierGroup['id'];

            if ((bool) $modifierGroup['is_required'] && $minSelect === 0) {
                $minSelect = 1;
            }

            if ($selectedCount < $minSelect) {
                $this->addError($errorKey, __('Выберите вариант.'));
                $isValid = false;
            }

            if ($maxSelect > 0 && $selectedCount > $maxSelect) {
                $this->addError($errorKey, __('Выбрано слишком много вариантов.'));
                $isValid = false;
            }
        }

        if (mb_strlen($this->itemComment) > 500) {
            $this->addError('itemComment', __('Комментарий слишком длинный.'));
            $isValid = false;
        }

        return $isValid;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<int>
     */
    private function selectedOptionIdsForGroup(int $modifierGroupId, array $item): array
    {
        $selectedOptionIds = collect($this->selectedModifierOptions[(string) $modifierGroupId] ?? [])
            ->map(fn (mixed $optionId): int => (int) $optionId)
            ->filter(fn (int $optionId): bool => $optionId > 0)
            ->values();
        $group = $this->findModifierGroupInItem($item, $modifierGroupId);

        if ($group === null) {
            return [];
        }

        $availableOptionIds = collect($group['options'])
            ->pluck('id')
            ->map(fn (mixed $optionId): int => (int) $optionId)
            ->all();

        return $selectedOptionIds
            ->filter(fn (int $optionId): bool => in_array($optionId, $availableOptionIds, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function selectedItemTotal(array $item): string
    {
        $totalCents = $this->decimalToCents((string) $item['price']);

        foreach ($item['modifier_groups'] as $modifierGroup) {
            $selectedOptionIds = $this->selectedOptionIdsForGroup((int) $modifierGroup['id'], $item);

            foreach ($modifierGroup['options'] as $modifierOption) {
                if (in_array((int) $modifierOption['id'], $selectedOptionIds, true)) {
                    $totalCents += $this->decimalToCents((string) $modifierOption['price_delta']);
                }
            }
        }

        return $this->centsToDecimal(max(0, $totalCents));
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    private function selectedModifierSummary(array $item): array
    {
        $summary = [];

        foreach ($item['modifier_groups'] as $modifierGroup) {
            $selectedOptionIds = $this->selectedOptionIdsForGroup((int) $modifierGroup['id'], $item);

            foreach ($modifierGroup['options'] as $modifierOption) {
                if (in_array((int) $modifierOption['id'], $selectedOptionIds, true)) {
                    $summary[] = $modifierOption['name'];
                }
            }
        }

        return $summary;
    }

    private function decimalToCents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function centsToDecimal(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
