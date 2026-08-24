<?php

declare(strict_types=1);

namespace App\Livewire\Onboarding;

use App\Actions\Onboarding\GenerateOnboardingQrCodesAction;
use App\Actions\Onboarding\SaveOnboardingAreaAction;
use App\Actions\Onboarding\SaveOnboardingBranchAction;
use App\Actions\Onboarding\SaveOnboardingBrandAction;
use App\Actions\Onboarding\SaveOnboardingOrganizationAction;
use App\Actions\Onboarding\SaveOnboardingServicePointsAction;
use App\Actions\Onboarding\SaveOnboardingStarterMenuAction;
use App\Enums\SupportedCurrency;
use App\Livewire\Forms\Onboarding\RestaurantSetupForm;
use App\Models\RestaurantOnboarding;
use App\Models\User;
use App\Services\Onboarding\RestaurantSetupQueryService;
use App\Support\RestaurantSetupOptions;
use Closure;
use Flux\Flux;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * @property-read array{
 *     step: int,
 *     highest_step: int,
 *     completed: bool,
 *     done: array<int, bool>,
 *     summary: array<string, string|int|null>,
 *     form: array<string, string|int>
 * } $setup
 */
final class RestaurantSetup extends Component
{
    private RestaurantSetupQueryService $setupQueries;

    private Application $application;

    /** @var array{onboarding: RestaurantOnboarding|null, step: int, highest_step: int, completed: bool, done: array<int, bool>, summary: array<string, string|int|null>, form: array<string, string|int>}|null */
    private ?array $persistentStateCache = null;

    public RestaurantSetupForm $form;

    #[Locked]
    public ?int $onboardingId = null;

    #[Locked]
    public int $step = 1;

    public function boot(RestaurantSetupQueryService $setupQueries, Application $application): void
    {
        $this->setupQueries = $setupQueries;
        $this->application = $application;
    }

    public function mount(): void
    {
        $state = $this->persistentState();
        $onboarding = $state['onboarding'];

        if ($onboarding instanceof RestaurantOnboarding) {
            $this->onboardingId = $onboarding->id;
        }

        $this->form->areaName = __('ui.onboarding.restaurant_setup.defaults.area_name');
        $this->form->tablePrefix = __('ui.onboarding.restaurant_setup.defaults.table_prefix');
        $this->form->menuName = __('ui.onboarding.restaurant_setup.defaults.menu_name');
        $this->form->categoryName = __('ui.onboarding.restaurant_setup.defaults.category_name');
        $this->form->itemName = __('ui.onboarding.restaurant_setup.defaults.item_name');
        $configuredTimezone = config('app.timezone');
        $this->form->branchTimezone = RestaurantSetupOptions::defaultTimezone(
            is_string($configuredTimezone) ? $configuredTimezone : null,
        );
        $this->form->hydrateFromPersistentState($state['form']);
        $this->step = $state['step'];
    }

    public function createOrganization(SaveOnboardingOrganizationAction $save): void
    {
        $state = $this->persistentState();
        $validated = $this->form->validateOrganization($this->currentUser(), $state['onboarding']?->organization_id);
        $onboarding = $save->handle($this->currentUser(), $this->onboardingId, ['name' => $validated['organizationName']]);
        $this->afterMutation($onboarding, 'ui.livewire.onboarding.restaurantsetup.kompaniia_sozdana');
    }

    public function createBrand(SaveOnboardingBrandAction $save): void
    {
        $state = $this->requiredState(2);
        $validated = $this->form->validateBrand($state['onboarding']->organization, $state['onboarding']->brand_id);
        $onboarding = $save->handle($this->currentUser(), $this->requiredOnboardingId(), ['name' => $validated['brandName']]);
        $this->afterMutation($onboarding, 'ui.livewire.onboarding.restaurantsetup.restoran_sozdan');
    }

    public function createBranch(SaveOnboardingBranchAction $save): void
    {
        $state = $this->requiredState(3);
        $validated = $this->form->validateBranch($state['onboarding']->brand, $state['onboarding']->branch_id);
        $onboarding = $save->handle($this->currentUser(), $this->requiredOnboardingId(), [
            'name' => $validated['branchName'], 'address' => $validated['branchAddress'], 'city' => $validated['branchCity'],
            'country' => RestaurantSetupOptions::countryName($validated['branchCountryCode']),
            'timezone' => $validated['branchTimezone'], 'currency' => SupportedCurrency::normalize($validated['branchCurrency']), 'is_active' => true,
        ]);
        $this->afterMutation($onboarding, 'ui.livewire.onboarding.restaurantsetup.filial_sozdan');
    }

    public function createArea(SaveOnboardingAreaAction $save): void
    {
        $this->requiredState(4);
        $validated = $this->form->validateArea();
        $onboarding = $save->handle($this->currentUser(), $this->requiredOnboardingId(), [
            'parent_id' => null, 'type' => $validated['areaType'], 'name' => $validated['areaName'],
            'icon' => $validated['areaIcon'] ?: null, 'sort_order' => 0, 'is_active' => true,
        ]);
        $this->afterMutation($onboarding, 'ui.livewire.onboarding.restaurantsetup.zona_dobavlena');
    }

    public function createServicePoints(SaveOnboardingServicePointsAction $save): void
    {
        $this->requiredState(5);
        $validated = $this->form->validateServicePoints();
        $onboarding = $save->handle($this->currentUser(), $this->requiredOnboardingId(), $validated);
        $this->afterMutation($onboarding, 'ui.livewire.onboarding.restaurantsetup.pervye_stoly_dobavleny');
    }

    public function generateQrCodes(GenerateOnboardingQrCodesAction $generate): void
    {
        $this->requiredState(6);
        $onboarding = $generate->handle($this->currentUser(), $this->requiredOnboardingId());
        $this->afterMutation($onboarding, 'ui.livewire.onboarding.restaurantsetup.qr_kody_gotovy');
    }

    public function createStarterMenu(SaveOnboardingStarterMenuAction $save): void
    {
        $this->requiredState(7);
        $validated = $this->form->validateStarterMenu();
        $onboarding = $save->handle($this->currentUser(), $this->requiredOnboardingId(), [
            'menu_name' => $validated['menuName'], 'category_name' => $validated['categoryName'],
            'item_name' => $validated['itemName'], 'item_price' => $validated['itemPrice'],
        ]);
        $this->afterMutation($onboarding, 'ui.livewire.onboarding.restaurantsetup.pervoe_meniu_dobavleno');
    }

    public function goToStep(mixed $step): void
    {
        if (! is_int($step)) {
            return;
        }

        $highest = (int) $this->setup()['highest_step'];

        if ($step < 1 || $step > $highest) {
            return;
        }

        $this->step = $step;
        $this->resetValidation();
        $this->dispatch('onboarding-step-changed');
    }

    /** @return array<string, string> */
    #[Computed]
    public function countryOptions(): array
    {
        return RestaurantSetupOptions::countryOptions($this->application->getLocale());
    }

    /** @return array<string, string> */
    #[Computed]
    public function timezoneOptions(): array
    {
        return RestaurantSetupOptions::timezoneOptions();
    }

    /** @return array<string, string> */
    #[Computed]
    public function currencyOptions(): array
    {
        return RestaurantSetupOptions::currencyOptions();
    }

    /** @return array<string, string> */
    #[Computed]
    public function areaTypeOptions(): array
    {
        return RestaurantSetupOptions::areaTypeOptions();
    }

    /** @return array<string, string> */
    #[Computed]
    public function areaIconOptions(): array
    {
        return RestaurantSetupOptions::areaIconOptions();
    }

    /** @return array{step: int, highest_step: int, completed: bool, done: array<int, bool>, summary: array<string, string|int|null>, form: array<string, string|int>} */
    #[Computed]
    public function setup(): array
    {
        $state = $this->persistentState();
        unset($state['onboarding']);

        return $state;
    }

    /** @return list<array{number: int, label: string, icon: string, is_done: bool, is_current: bool, is_available: bool}> */
    #[Computed]
    public function steps(): array
    {
        $state = $this->setup();
        $definitions = [
            [1, __('ui.livewire.onboarding.restaurantsetup.kompaniia'), 'building-office'],
            [2, __('ui.livewire.onboarding.restaurantsetup.restoran'), 'building-storefront'],
            [3, __('ui.livewire.onboarding.restaurantsetup.adres'), 'map-pin'],
            [4, __('ui.livewire.onboarding.restaurantsetup.zona'), 'rectangle-group'],
            [5, __('ui.livewire.onboarding.restaurantsetup.stoly'), 'squares-2x2'],
            [6, __('permissions.groups.qr'), 'qr-code'], [7, __('ui.livewire.onboarding.restaurantsetup.meniu'), 'book-open'],
            [8, __('ui.livewire.onboarding.restaurantsetup.proverka'), 'check-circle'],
        ];

        return collect($definitions)->map(fn (array $definition): array => [
            'number' => $definition[0], 'label' => $definition[1], 'icon' => $definition[2],
            'is_done' => $state['done'][$definition[0]], 'is_current' => $this->step === $definition[0],
            'is_available' => $definition[0] <= $state['highest_step'],
        ])->all();
    }

    /** @return array<string, string|int|null> */
    #[Computed]
    public function summary(): array
    {
        return $this->setup()['summary'];
    }

    public function render(): View
    {
        return view('livewire.onboarding.restaurant-setup')->title(__('ui.onboarding.restaurant_setup.nastroit_restoran'));
    }

    public function exception(Throwable $e, Closure $stopPropagation): void
    {
        if ($e instanceof ValidationException) {
            $this->dispatch('onboarding-validation-failed');
        }
    }

    private function currentUser(): User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : abort(401);
    }

    /** @return array{onboarding: RestaurantOnboarding|null, step: int, highest_step: int, completed: bool, done: array<int, bool>, summary: array<string, string|int|null>, form: array<string, string|int>} */
    private function persistentState(): array
    {
        if ($this->persistentStateCache !== null) {
            return $this->persistentStateCache;
        }

        $state = $this->setupQueries->presentation($this->currentUser(), $this->onboardingId);
        $onboarding = $state['onboarding'];

        if ($onboarding instanceof RestaurantOnboarding) {
            Gate::authorize('view', $onboarding);
        } else {
            Gate::authorize('create', RestaurantOnboarding::class);
        }

        return $this->persistentStateCache = $state;
    }

    /** @return array{onboarding: RestaurantOnboarding, step: int, highest_step: int, completed: bool, done: array<int, bool>, summary: array<string, string|int|null>, form: array<string, string|int>} */
    private function requiredState(int $minimumStep): array
    {
        $state = $this->persistentState();
        abort_unless($state['onboarding'] instanceof RestaurantOnboarding && $state['highest_step'] >= $minimumStep, 409);
        Gate::authorize('update', $state['onboarding']);

        return $state;
    }

    private function requiredOnboardingId(): int
    {
        return $this->onboardingId ?? abort(409);
    }

    private function afterMutation(RestaurantOnboarding $onboarding, string $toastKey): void
    {
        $this->onboardingId = $onboarding->id;
        $this->persistentStateCache = null;
        unset($this->setup, $this->summary, $this->steps);
        $state = $this->persistentState();
        $this->form->hydrateFromPersistentState($state['form']);
        $this->step = $state['step'];
        $this->dispatch('onboarding-step-changed');
        Flux::toast(variant: 'success', text: __($toastKey));
    }
}
