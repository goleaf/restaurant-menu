<?php

declare(strict_types=1);

namespace App\Livewire\PublicQr;

use App\Actions\DraftOrders\AddGuestDraftOrderItemAction;
use App\Actions\Localization\UpdateGuestLocaleAction;
use App\Actions\Menus\GetGuestMenuForBranchAction;
use App\Actions\Menus\GetGuestMenuItemGalleryAction;
use App\Enums\MenuAllergen;
use App\Enums\MenuDietaryLabel;
use App\Enums\SupportedCurrency;
use App\Enums\SupportedLocale;
use App\Models\MenuItem;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Services\PublicQr\PublicQrQueryService;
use App\Support\MoneyFormatter;
use App\Support\Validation\Menus\ModifierRules;
use App\Support\Validation\TableSessions\GuestRules;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Attributes\Url;
use Livewire\Component;

/** @property-read list<array<string, mixed>> $selectedItemGallery */
class GuestMenu extends Component
{
    private GetGuestMenuForBranchAction $getGuestMenuForBranch;

    private GetGuestMenuItemGalleryAction $getGuestMenuItemGallery;

    private PublicQrQueryService $publicQrQueries;

    private UpdateGuestLocaleAction $updateGuestLocale;

    #[Locked]
    public int $branchId;

    #[Locked]
    public int $tableSessionId = 0;

    #[Locked]
    public int $currentGuestId = 0;

    #[Locked]
    public string $publicToken = '';

    #[Locked]
    public string $itemAddAttemptId = '';

    public bool $guestCanAddItems = false;

    public bool $branchCanAcceptOrders = true;

    #[Reactive]
    public string $branchOpeningStatusMessage = '';

    public string $currency = 'EUR';

    #[Url(as: 'lang')]
    public string $language = '';

    /**
     * @var array<string, string>
     */
    public array $languageOptions = [];

    public ?int $selectedItemId = null;

    public ?int $selectedItemVariantId = null;

    /**
     * @var array<int, list<int>>
     */
    public array $selectedModifierOptions = [];

    public string $itemComment = '';

    public string $feedbackMessage = '';

    public string $search = '';

    public ?int $selectedCategoryId = null;

    /** @var list<string> */
    public array $dietaryFilters = [];

    /** @var list<string> */
    public array $excludedAllergens = [];

    /**
     * @var array<int, array{name: string, total_price: string, modifier_summary: list<string>, comment: string|null}>
     */
    public array $configuredItems = [];

    public function boot(
        GetGuestMenuForBranchAction $getGuestMenuForBranch,
        GetGuestMenuItemGalleryAction $getGuestMenuItemGallery,
        PublicQrQueryService $publicQrQueries,
        UpdateGuestLocaleAction $updateGuestLocale,
    ): void {
        $this->getGuestMenuForBranch = $getGuestMenuForBranch;
        $this->getGuestMenuItemGallery = $getGuestMenuItemGallery;
        $this->publicQrQueries = $publicQrQueries;
        $this->updateGuestLocale = $updateGuestLocale;
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
        session()->put('guest_menu_locales.'.$this->branchId, $this->language);
        $guest = $this->currentActiveGuest();

        if ($guest instanceof TableSessionGuest) {
            $this->updateGuestLocale->handle($guest, $this->language);
        }

        unset($this->guestMenu);
        unset($this->selectedItemGallery);
        $this->dispatch('guest-locale-updated', language: $this->language);
    }

    #[On('guest-locale-updated')]
    public function synchronizeGuestLocale(string $language): void
    {
        $this->language = $this->getGuestMenuForBranch->resolveLanguageForBranch($this->branchId, $language);
        $this->applyLocale();
        unset($this->guestMenu);
        unset($this->selectedItemGallery);
    }

    public function openItem(int $itemId): void
    {
        $item = $this->findItemInGuestMenu($itemId);

        if ($item === null) {
            return;
        }

        if ($this->selectedItemId !== $itemId) {
            $this->resetValidation();
            $this->itemAddAttemptId = $this->canConfigureItem($item) ? (string) Str::uuid() : '';
            $this->selectedItemId = $itemId;
            $this->selectedItemVariantId = $this->defaultVariantId($item);
            $this->selectedModifierOptions = [];
            $this->itemComment = '';

            foreach ($item['modifier_groups'] as $modifierGroup) {
                $this->selectedModifierOptions[$modifierGroup['id']] = [];
            }
        }

        unset($this->selectedItemGallery);
        $this->dispatch('guest-item-details-opened')->self();
    }

    public function closeItemSheet(): void
    {
        $this->resetValidation();
        $this->itemAddAttemptId = '';
        $this->selectedItemId = null;
        $this->selectedItemVariantId = null;
        $this->selectedModifierOptions = [];
        $this->itemComment = '';
        unset($this->selectedItemGallery);
    }

    public function resetMenuFilters(): void
    {
        $this->search = '';
        $this->selectedCategoryId = null;
        $this->dietaryFilters = [];
        $this->excludedAllergens = [];
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

    public function refreshConfiguredItem(): void
    {
        $this->resetValidation('menu_item');
        unset($this->guestMenu, $this->selectedItemGallery);

        if ($this->selectedItemId === null || $this->itemAddAttemptId === '') {
            return;
        }

        $item = $this->selectedItem();

        if ($item === null || ! (bool) $item['is_available']) {
            $this->addError('menu_item', __('menu.guest.item_no_longer_available'));
        }
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
            if ($this->itemAddAttemptId !== '') {
                $this->addError('menu_item', __('menu.guest.item_no_longer_available'));
            } else {
                $this->closeItemSheet();
            }

            return;
        }

        $this->itemComment = trim($this->itemComment);
        $validated = $this->validate([
            ...GuestRules::guestComment('itemComment'),
            ...ModifierRules::selectedModifierOptions('selectedModifierOptions'),
            'selectedItemVariantId' => ['nullable', 'integer', 'min:1'],
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
            if ($this->itemAddAttemptId === '') {
                $this->itemAddAttemptId = (string) Str::uuid();
            }

            $draftOrderItem = $addGuestDraftOrderItem->handle(
                tableSession: $tableSession,
                guest: $guest,
                menuItem: $menuItem,
                menuItemVariantId: $this->selectedItemVariantId,
                selectedModifierOptions: $this->selectedModifierOptions,
                comment: $this->itemComment,
                itemName: $item['name'],
                languageCode: $this->language,
                idempotencyKey: $this->itemAddAttemptId,
            );
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->configuredItems[$item['id']] = [
            'name' => $draftOrderItem->item_name,
            'total_price' => MoneyFormatter::formatCents($draftOrderItem->total_price_cents, $this->currency),
            'modifier_summary' => $this->selectedConfigurationSummary($item),
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

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function selectedItemGallery(): array
    {
        if ($this->selectedItemId === null) {
            return [];
        }

        return $this->getGuestMenuItemGallery->handle($this->branchId, $this->selectedItemId, $this->language);
    }

    public function render(): View
    {
        $this->applyLocale();

        $selectedItem = $this->selectedItem();
        $hasPendingItemConfiguration = $this->selectedItemId !== null && $this->itemAddAttemptId !== '';
        $categoryOptions = collect($this->guestMenu()['menus'] ?? [])
            ->flatMap(fn (array $menu): array => $menu['categories'] ?? [])
            ->map(fn (array $category): array => ['id' => (int) $category['id'], 'name' => (string) $category['name']])
            ->unique('id')
            ->values()
            ->all();
        $guestMenu = $this->displayGuestMenu();
        $availableMenus = $guestMenu['menus'] ?? [];

        return view('livewire.public-qr.guest-menu', [
            'guestMenu' => $guestMenu,
            'availableMenus' => $availableMenus,
            'availableMenuCount' => count($availableMenus),
            'unavailableMenus' => $guestMenu['unavailable_menus'] ?? [],
            'selectedItem' => $selectedItem === null ? null : $this->displayItem($selectedItem),
            'hasPendingItemConfiguration' => $hasPendingItemConfiguration,
            'hasMissingConfiguredItem' => $hasPendingItemConfiguration && $selectedItem === null,
            'canConfigureSelectedItem' => $selectedItem !== null && $this->canConfigureItem($selectedItem),
            'canReviewItemComment' => $hasPendingItemConfiguration || ($selectedItem !== null && $this->canConfigureItem($selectedItem)),
            'selectedItemGallery' => $selectedItem === null ? [] : $this->selectedItemGallery,
            'selectedItemTotal' => $selectedItem === null ? MoneyFormatter::formatCents(0, $this->currency) : $this->selectedItemTotal($selectedItem),
            'dietaryOptions' => MenuDietaryLabel::options($this->language),
            'allergenOptions' => MenuAllergen::options($this->language),
            'categoryOptions' => $categoryOptions,
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
        $selectedVariant = $this->selectedVariantInItem($item);
        $totalCents = (int) ($selectedVariant['price_cents'] ?? $item['price_cents']);

        foreach ($item['modifier_groups'] as $modifierGroup) {
            $selectedOptionIds = $this->selectedOptionIdsForGroup((int) $modifierGroup['id'], $item);

            foreach ($modifierGroup['options'] as $modifierOption) {
                if (in_array((int) $modifierOption['id'], $selectedOptionIds, true)) {
                    $totalCents += (int) $modifierOption['price_delta_cents'];
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
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    private function selectedConfigurationSummary(array $item): array
    {
        $summary = $this->selectedModifierSummary($item);
        $selectedVariant = $this->selectedVariantInItem($item);

        if ($selectedVariant !== null) {
            array_unshift($summary, (string) $selectedVariant['name']);
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
        $search = mb_strtolower(trim($this->search));
        $dietaryFilters = array_values(array_intersect(array_filter($this->dietaryFilters, is_string(...)), MenuDietaryLabel::values()));
        $excludedAllergens = array_values(array_intersect(array_filter($this->excludedAllergens, is_string(...)), MenuAllergen::values()));

        return collect($categories)
            ->filter(fn (array $category): bool => $this->selectedCategoryId === null || (int) $category['id'] === $this->selectedCategoryId)
            ->map(function (array $category) use ($search, $dietaryFilters, $excludedAllergens): array {
                $category['items'] = collect($category['items'] ?? [])
                    ->filter(function (array $item) use ($search, $dietaryFilters, $excludedAllergens): bool {
                        $haystack = mb_strtolower((string) $item['name'].' '.(string) ($item['description'] ?? ''));
                        $dietaryValues = collect($item['dietary_labels'] ?? [])->pluck('value')->all();
                        $allergenValues = collect($item['allergens'] ?? [])->pluck('value')->all();

                        return ($search === '' || str_contains($haystack, $search))
                            && array_diff($dietaryFilters, $dietaryValues) === []
                            && ($excludedAllergens === [] || ($allergenValues !== [] && array_intersect($excludedAllergens, $allergenValues) === []));
                    })
                    ->map(fn (array $item): array => $this->displayItem($item))
                    ->values()
                    ->all();

                return $category;
            })
            ->filter(fn (array $category): bool => $category['items'] !== [])
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $item */
    private function canConfigureItem(array $item): bool
    {
        return $this->guestCanAddItems && $this->branchCanAcceptOrders && (bool) $item['is_available'];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function displayItem(array $item): array
    {
        $item['variants'] = collect($item['variants'] ?? [])
            ->map(function (array $variant): array {
                $variant['formatted_price'] = MoneyFormatter::formatCents((int) $variant['price_cents'], $this->currency);

                return $variant;
            })
            ->values()
            ->all();
        $lowestPriceCents = collect($item['variants'])->min('price_cents');
        $formattedPrice = MoneyFormatter::formatCents(
            is_numeric($lowestPriceCents) ? (int) $lowestPriceCents : (int) $item['price_cents'],
            $this->currency,
        );
        $item['formatted_price'] = $item['variants'] === []
            ? $formattedPrice
            : __('menu.guest.from_price', ['price' => $formattedPrice]);
        $item['modifier_groups'] = collect($item['modifier_groups'])
            ->map(function (array $modifierGroup): array {
                $modifierGroup['options'] = collect($modifierGroup['options'])
                    ->map(function (array $modifierOption): array {
                        $modifierOption['formatted_price_delta'] = MoneyFormatter::formatSignedCents(
                            (int) $modifierOption['price_delta_cents'],
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

    /** @param array<string, mixed> $item */
    private function defaultVariantId(array $item): ?int
    {
        $variants = collect($item['variants'] ?? []);
        $variant = $variants->firstWhere('is_default', true) ?? $variants->first();

        return is_array($variant) ? (int) $variant['id'] : null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function selectedVariantInItem(array $item): ?array
    {
        if ($this->selectedItemVariantId === null) {
            return null;
        }

        foreach ($item['variants'] ?? [] as $variant) {
            if ((int) $variant['id'] === $this->selectedItemVariantId) {
                return $variant;
            }
        }

        return null;
    }

    private function currentTableSession(): ?TableSession
    {
        if ($this->tableSessionId < 1) {
            return null;
        }

        return $this->publicQrQueries->guestMenuTableSession($this->tableSessionId);
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

        return $this->publicQrQueries->activeGuest($this->currentGuestId, $this->tableSessionId, $guestToken);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function menuItemFor(array $item): ?MenuItem
    {
        return $this->publicQrQueries->menuItem((int) $item['id']);
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
