<?php

declare(strict_types=1);

namespace App\Livewire\PublicQr;

use App\Actions\DraftOrders\AddGuestDraftOrderItemAction;
use App\Actions\Menus\GetGuestMenuForBranchAction;
use App\Enums\SupportedCurrency;
use App\Enums\SupportedLocale;
use App\Enums\TableSessionGuestStatus;
use App\Models\MenuItem;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Support\MoneyFormatter;
use App\Support\Validation\RestaurantValidationRules;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class GuestMenu extends Component
{
    private GetGuestMenuForBranchAction $getGuestMenuForBranch;

    #[Locked]
    public int $branchId;

    #[Locked]
    public int $tableSessionId = 0;

    #[Locked]
    public int $currentGuestId = 0;

    #[Locked]
    public string $publicToken = '';

    public bool $guestCanAddItems = false;

    public bool $branchCanAcceptOrders = true;

    public string $branchOpeningStatusMessage = '';

    public string $currency = 'EUR';

    #[Url(as: 'lang')]
    public string $language = '';

    /**
     * @var array<string, string>
     */
    public array $languageOptions = [];

    public ?int $selectedItemId = null;

    /**
     * @var array<int, list<int>>
     */
    public array $selectedModifierOptions = [];

    public string $itemComment = '';

    public string $feedbackMessage = '';

    /**
     * @var array<int, array{name: string, total_price: string, modifier_summary: list<string>, comment: string|null}>
     */
    public array $configuredItems = [];

    public function boot(GetGuestMenuForBranchAction $getGuestMenuForBranch): void
    {
        $this->getGuestMenuForBranch = $getGuestMenuForBranch;
    }

    public function mount(
        int $branchId,
        string $currency = 'EUR',
        int $tableSessionId = 0,
        int $currentGuestId = 0,
        string $publicToken = '',
        bool $guestCanAddItems = false,
        bool $branchCanAcceptOrders = true,
        string $branchOpeningStatusMessage = '',
        ?string $language = null,
    ): void {
        $this->branchId = $branchId;
        $this->currency = $currency;
        $this->tableSessionId = $tableSessionId;
        $this->currentGuestId = $currentGuestId;
        $this->publicToken = $publicToken;
        $this->guestCanAddItems = $guestCanAddItems;
        $this->branchCanAcceptOrders = $branchCanAcceptOrders;
        $this->branchOpeningStatusMessage = $branchOpeningStatusMessage;
        $this->currency = SupportedCurrency::normalize($currency);
        $this->languageOptions = GetGuestMenuForBranchAction::supportedLanguageLabels();
        $this->language = $this->getGuestMenuForBranch->resolveLanguageForBranch($branchId, $language ?? $this->language);
        $this->applyLocale();
    }

    public function updatedLanguage(): void
    {
        $this->language = $this->getGuestMenuForBranch->resolveLanguageForBranch($this->branchId, $this->language);
        $this->applyLocale();
        unset($this->guestMenu);
        $this->configuredItems = [];
        $this->closeItemSheet();
    }

    public function openItem(int $itemId): void
    {
        if (! $this->guestCanAddItems || ! $this->branchCanAcceptOrders) {
            return;
        }

        $item = $this->findItemInGuestMenu($itemId);

        if ($item === null || ! (bool) $item['is_available']) {
            return;
        }

        $this->resetValidation();
        $this->selectedItemId = $itemId;
        $this->selectedModifierOptions = [];
        $this->itemComment = '';

        foreach ($item['modifier_groups'] as $modifierGroup) {
            $this->selectedModifierOptions[$modifierGroup['id']] = [];
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
            $this->selectedModifierOptions[$modifierGroupId] = array_values(array_filter(
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
            $this->selectedModifierOptions[$modifierGroupId] = [$modifierOptionId];

            return;
        }

        if (count($selected) >= $maxSelect) {
            return;
        }

        $selected[] = $modifierOptionId;
        $this->selectedModifierOptions[$modifierGroupId] = $selected;
    }

    public function saveConfiguredItem(AddGuestDraftOrderItemAction $addGuestDraftOrderItem): void
    {
        $this->resetValidation();
        $this->feedbackMessage = '';

        if (! $this->branchCanAcceptOrders) {
            $this->addError('guest', __('menu.guest.closed_error'));

            return;
        }

        $tableSession = $this->currentTableSession();
        $guest = $this->currentActiveGuest();

        if (! $tableSession instanceof TableSession || ! $guest instanceof TableSessionGuest) {
            $this->addError('guest', __('menu.guest.active_guest_required'));

            return;
        }

        $item = $this->selectedItem();

        if ($item === null || ! (bool) $item['is_available']) {
            $this->closeItemSheet();

            return;
        }

        $this->itemComment = trim($this->itemComment);
        $validated = $this->validate([
            ...RestaurantValidationRules::guestComment('itemComment'),
            ...RestaurantValidationRules::selectedModifierOptions('selectedModifierOptions'),
        ]);
        $this->itemComment = (string) ($validated['itemComment'] ?? '');
        $this->selectedModifierOptions = $validated['selectedModifierOptions'] ?? [];

        if (! $this->validateModifierSelection($item)) {
            return;
        }

        $menuItem = $this->menuItemFor($item);

        if (! $menuItem instanceof MenuItem) {
            $this->addError('guest', __('menu.guest.active_guest_required'));

            return;
        }

        try {
            $draftOrderItem = $addGuestDraftOrderItem->handle(
                tableSession: $tableSession,
                guest: $guest,
                menuItem: $menuItem,
                selectedModifierOptions: $this->selectedModifierOptions,
                comment: $this->itemComment,
                itemName: $item['name'],
            );
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->configuredItems[$item['id']] = [
            'name' => $draftOrderItem->item_name,
            'total_price' => MoneyFormatter::format($draftOrderItem->total_price, $this->currency),
            'modifier_summary' => $this->selectedModifierSummary($item),
            'comment' => $draftOrderItem->comment,
        ];
        $this->feedbackMessage = __('menu.guest.item_added');

        $this->closeItemSheet();
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function guestMenu(): array
    {
        return $this->getGuestMenuForBranch->handle($this->branchId, $this->language);
    }

    public function render(): View
    {
        $this->applyLocale();

        $selectedItem = $this->selectedItem();
        $guestMenu = $this->displayGuestMenu();
        $availableMenus = $guestMenu['menus'] ?? [];

        return view('livewire.public-qr.guest-menu', [
            'guestMenu' => $guestMenu,
            'availableMenus' => $availableMenus,
            'availableMenuCount' => count($availableMenus),
            'unavailableMenus' => $guestMenu['unavailable_menus'] ?? [],
            'selectedItem' => $selectedItem === null ? null : $this->displayItem($selectedItem),
            'selectedItemTotal' => $selectedItem === null ? MoneyFormatter::format(0, $this->currency) : $this->selectedItemTotal($selectedItem),
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

    private function applyLocale(): void
    {
        $this->language = SupportedLocale::normalize($this->language);

        App::setLocale($this->language);
        session()->put('interface_locale', $this->language);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findItemInGuestMenu(int $itemId): ?array
    {
        foreach ($this->guestMenu()['menus'] ?? [] as $menu) {
            foreach ($menu['categories'] ?? [] as $category) {
                foreach ($category['items'] ?? [] as $item) {
                    if ((int) $item['id'] === $itemId) {
                        return $item;
                    }
                }
            }
        }

        foreach ($this->guestMenu()['categories'] ?? [] as $category) {
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
                $this->addError($errorKey, __('menu.guest.required_modifier_missing'));
                $isValid = false;
            }

            if ($maxSelect > 0 && $selectedCount > $maxSelect) {
                $this->addError($errorKey, __('menu.guest.modifier_limit_exceeded'));
                $isValid = false;
            }
        }

        if (mb_strlen($this->itemComment) > 500) {
            $this->addError('itemComment', __('menu.guest.comment_too_long'));
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
        $selectedOptionIds = collect($this->selectedModifierOptions[$modifierGroupId] ?? [])
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
        $totalCents = MoneyFormatter::decimalToCents((string) $item['price']);

        foreach ($item['modifier_groups'] as $modifierGroup) {
            $selectedOptionIds = $this->selectedOptionIdsForGroup((int) $modifierGroup['id'], $item);

            foreach ($modifierGroup['options'] as $modifierOption) {
                if (in_array((int) $modifierOption['id'], $selectedOptionIds, true)) {
                    $totalCents += MoneyFormatter::decimalToCents((string) $modifierOption['price_delta']);
                }
            }
        }

        return MoneyFormatter::formatCents(max(0, $totalCents), $this->currency);
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

    /**
     * @return array<string, mixed>
     */
    private function displayGuestMenu(): array
    {
        $guestMenu = $this->guestMenu();
        $menus = collect($guestMenu['menus'] ?? []);

        if ($menus->isEmpty() && ($guestMenu['menu'] ?? null) !== null) {
            $menus = collect([
                [
                    'id' => $guestMenu['menu']['id'],
                    'name' => $guestMenu['menu']['name'],
                    'availability' => $guestMenu['availability'] ?? [],
                    'categories' => $guestMenu['categories'] ?? [],
                ],
            ]);
        }

        $guestMenu['menus'] = $menus
            ->map(function (array $menu): array {
                $menu['categories'] = $this->displayCategories($menu['categories'] ?? []);

                return $menu;
            })
            ->values()
            ->all();
        $guestMenu['categories'] = $this->displayCategories($guestMenu['categories'] ?? []);

        return $guestMenu;
    }

    /**
     * @param  list<array<string, mixed>>  $categories
     * @return list<array<string, mixed>>
     */
    private function displayCategories(array $categories): array
    {
        return collect($categories)
            ->map(function (array $category): array {
                $category['items'] = collect($category['items'] ?? [])
                    ->map(fn (array $item): array => $this->displayItem($item))
                    ->values()
                    ->all();

                return $category;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function displayItem(array $item): array
    {
        $item['formatted_price'] = MoneyFormatter::format((string) $item['price'], $this->currency);
        $item['modifier_groups'] = collect($item['modifier_groups'])
            ->map(function (array $modifierGroup): array {
                $modifierGroup['options'] = collect($modifierGroup['options'])
                    ->map(function (array $modifierOption): array {
                        $modifierOption['formatted_price_delta'] = MoneyFormatter::formatSigned(
                            (string) $modifierOption['price_delta'],
                            $this->currency,
                        );

                        return $modifierOption;
                    })
                    ->values()
                    ->all();

                return $modifierGroup;
            })
            ->values()
            ->all();

        return $item;
    }

    private function currentTableSession(): ?TableSession
    {
        if ($this->tableSessionId < 1) {
            return null;
        }

        return TableSession::query()
            ->select([
                'id',
                'branch_id',
                'service_point_id',
                'status',
                'ended_at',
            ])
            ->whereKey($this->tableSessionId)
            ->first();
    }

    private function currentActiveGuest(): ?TableSessionGuest
    {
        if ($this->currentGuestId < 1 || $this->tableSessionId < 1) {
            return null;
        }

        $guestToken = $this->guestTokenFromCurrentState();

        if ($guestToken === null) {
            return null;
        }

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
            ->whereKey($this->currentGuestId)
            ->where('table_session_id', $this->tableSessionId)
            ->where('guest_token', $guestToken)
            ->where('status', TableSessionGuestStatus::Active->value)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function menuItemFor(array $item): ?MenuItem
    {
        return MenuItem::query()
            ->select(['id'])
            ->whereKey((int) $item['id'])
            ->first();
    }

    private function guestTokenFromCurrentState(): ?string
    {
        if ($this->publicToken === '') {
            return null;
        }

        $guestToken = request()->cookie($this->guestTokenCookieName($this->publicToken));

        if (is_string($guestToken) && strlen($guestToken) === 64) {
            return $guestToken;
        }

        $guestToken = session('guest_entries.'.$this->publicToken.'.guest_token');

        if (! is_string($guestToken) || strlen($guestToken) !== 64) {
            return null;
        }

        return $guestToken;
    }

    private function guestTokenCookieName(string $publicToken): string
    {
        return 'guest_token_'.substr(hash('sha256', $publicToken), 0, 24);
    }

    private function showValidationException(ValidationException $exception): void
    {
        foreach ($exception->errors() as $field => $messages) {
            $this->addError($field, (string) collect($messages)->first());
        }
    }
}
