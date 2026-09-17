<?php

declare(strict_types=1);

namespace App\Livewire\Onboarding;

use App\Actions\Onboarding\CompleteRestaurantPreparationAction;
use App\Actions\Onboarding\CreateRestaurantSetupAction;
use App\Actions\Onboarding\GenerateOnboardingQrCodesAction;
use App\Actions\Onboarding\SaveOnboardingAreaAction;
use App\Actions\Onboarding\SaveOnboardingServicePointsAction;
use App\Actions\Onboarding\SaveOnboardingStarterMenuAction;
use App\Actions\Onboarding\UseExistingSetupMenuAction;
use App\Actions\Onboarding\UseExistingSetupSpaceAction;
use App\Livewire\Forms\Onboarding\RestaurantSetupForm;
use App\Models\RestaurantOnboarding;
use App\Models\User;
use App\Services\Onboarding\RestaurantSetupQueryService;
use App\Services\Organizations\RestaurantCenterQuery;
use App\Support\RestaurantSetupOptions;
use Closure;
use Flux\Flux;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

final class RestaurantSetup extends Component
{
    public RestaurantSetupForm $form;

    #[Locked]
    public ?int $onboardingId = null;

    #[Locked]
    public int $actorId;

    #[Locked]
    public string $creationKey;

    #[Url(history: true)]
    public int $step = 1;

    public string $organizationSearch = '';

    public string $brandSearch = '';

    public mixed $existingMenuId = '';

    public string $menuSearch = '';

    public string $areaSearch = '';

    public mixed $existingAreaId = '';

    #[Locked]
    public int $setupVersion = 0;

    private RestaurantSetupQueryService $queries;

    private RestaurantCenterQuery $center;

    private Application $application;

    public function boot(RestaurantSetupQueryService $queries, RestaurantCenterQuery $center, Application $application): void
    {
        $this->queries = $queries;
        $this->center = $center;
        $this->application = $application;
    }

    public function mount(Request $request, ?int $setup = null): void
    {
        $this->actorId = (int) Auth::id();
        $this->creationKey = (string) Str::uuid();
        $this->onboardingId = $setup;
        $this->form->branchTimezone = RestaurantSetupOptions::defaultTimezone(config('app.timezone'));
        if ($setup !== null) {
            $this->queries->findForUserOrFail($this->actor(), $setup);
            $state = $this->queries->presentation($this->actor(), $setup);
            $this->setupVersion = $state['onboarding']->setup_version;
            $this->form->hydrateFromPersistentState($state['form']);
            $this->form->organizationId = $state['onboarding']->organization_id;
            $this->form->brandId = $state['onboarding']->brand_id;
            if ($state['done'][3]) {
                $this->creationKey = '';
            }
        } else {
            $this->form->organizationId = $request->query('organization') ?: null;
            $this->form->brandId = $request->query('brand') ?: null;
        }
    }

    public function createRestaurant(CreateRestaurantSetupAction $create): void
    {
        abort_if($this->creationKey === '', 409);
        $setup = $create->handle($this->actor(), $this->form->validateCreation(), $this->creationKey, $this->onboardingId);
        abort_if($this->onboardingId !== null && $this->onboardingId !== $setup->id, 409);
        $this->onboardingId = $setup->id;
        $this->setupVersion = $setup->setup_version;
        $this->step = 2;
        $this->saved();
        $this->dispatch('restaurant-created', url: route('restaurants.setup', ['setup' => $setup->id, 'step' => 2]));
    }

    public function updatedFormOrganizationId(): void
    {
        $this->form->brandId = null;
    }

    public function createArea(SaveOnboardingAreaAction $save): void
    {
        $id = $this->requiredId();
        $data = $this->form->validateArea();
        $saved = $save->handle($this->actor(), $id, ['parent_id' => null, 'type' => $data['areaType'], 'name' => $data['areaName'], 'icon' => $data['areaIcon'] ?: null, 'sort_order' => 0, 'is_active' => true]);
        $this->setupVersion = $saved->setup_version;
        $this->saved();
    }

    public function createServicePoints(SaveOnboardingServicePointsAction $save): void
    {
        $saved = $save->handle($this->actor(), $this->requiredId(), $this->form->validateServicePoints());
        $this->setupVersion = $saved->setup_version;
        $this->saved();
    }

    public function generateQrCodes(GenerateOnboardingQrCodesAction $generate): void
    {
        $generate->handle($this->actor(), $this->requiredId());
        $this->saved();
    }

    public function createStarterMenu(SaveOnboardingStarterMenuAction $save): void
    {
        $id = $this->requiredId();
        $data = $this->form->validateStarterMenu();
        $saved = $save->handle($this->actor(), $id, ['menu_name' => $data['menuName'], 'category_name' => $data['categoryName'], 'item_name' => $data['itemName'], 'item_price' => $data['itemPrice']]);
        $this->setupVersion = $saved->setup_version;
        $this->saved();
    }

    public function useExistingMenu(UseExistingSetupMenuAction $use): void
    {
        $data = $this->validate(['existingMenuId' => ['required', 'integer', 'min:1']], [], ['existingMenuId' => __('center.menu')]);
        $saved = $use->handle($this->actor(), $this->requiredId(), (int) $data['existingMenuId'], $this->setupVersion);
        $this->setupVersion = $saved->setup_version;
        $this->saved();
    }

    public function useExistingSpace(UseExistingSetupSpaceAction $use): void
    {
        $data = $this->validate(['existingAreaId' => ['required', 'integer', 'min:1']], [], ['existingAreaId' => __('center.rooms')]);
        $saved = $use->handle($this->actor(), $this->requiredId(), (int) $data['existingAreaId'], $this->setupVersion);
        $this->setupVersion = $saved->setup_version;
        $this->saved();
    }

    public function complete(CompleteRestaurantPreparationAction $complete): void
    {
        $complete->handle($this->actor(), $this->requiredId());
        $this->saved();
    }

    public function goToStep(mixed $step): void
    {
        abort_unless(is_int($step) && $step >= 1 && $step <= 4, 422);
        if ($step > 1) {
            $this->requiredId();
        }
        $this->step = $step;
    }

    public function render(): View
    {
        abort_unless($this->step >= 1 && $this->step <= 4 && ($this->onboardingId !== null || $this->step === 1), 422);
        $actor = $this->actor();
        $state = $this->queries->presentation($actor, $this->onboardingId);
        $organizations = $this->center->creationOrganizations($actor, $this->organizationSearch, $this->form->organizationId);
        $brands = $this->center->creationBrands($actor, $this->form->organizationId, $this->brandSearch, $this->form->brandId);
        abort_unless($this->onboardingId !== null || Gate::forUser($actor)->allows('create', RestaurantOnboarding::class) || $organizations !== [], 403);

        return view('livewire.onboarding.restaurant-setup', [
            'readiness' => $this->step === 4 && $state['done'][3] ? $this->queries->readiness($actor, $this->requiredId()) : null,
            'state' => $state, 'areaOptions' => $this->onboardingId !== null && $this->step === 2 ? $this->queries->areaOptions($actor, $this->onboardingId, $this->areaSearch) : [], 'menuOptions' => $this->onboardingId !== null && $this->step === 3 ? $this->queries->menuOptions($actor, $this->onboardingId, $this->menuSearch) : [], 'organizations' => $organizations, 'brands' => $brands,
            'countries' => RestaurantSetupOptions::countryOptions($this->application->getLocale()),
            'currencies' => RestaurantSetupOptions::currencyOptions(), 'timezones' => RestaurantSetupOptions::timezoneOptions(),
            'steps' => [1 => __('center.details'), 2 => __('center.rooms'), 3 => __('center.menu'), 4 => __('center.review')],
            'canCreateOrganization' => Gate::forUser($actor)->allows('create', RestaurantOnboarding::class),
        ])->title(__('center.title'));
    }

    public function exception(Throwable $e, Closure $stopPropagation): void
    {
        if (! $e instanceof ValidationException) {
            return;
        }
        $errors = [];
        foreach ($e->errors() as $field => $messages) {
            $key = array_key_exists($field, $this->form->all()) ? 'form.'.$field : $field;
            $errors[$key] = $messages;
        }
        $this->setErrorBag($errors);
        $first = (string) array_key_first($errors);
        $this->step = match (true) {
            str_starts_with($first, 'form.menu'), str_starts_with($first, 'form.category'), str_starts_with($first, 'form.item'), $first === 'existingMenuId' => 3,
            str_starts_with($first, 'form.area'), str_starts_with($first, 'form.table'), $first === 'existingAreaId' => 2,
            $first === 'preparation' => 4,
            default => 1,
        };
        $this->dispatch('onboarding-validation-failed');
        $stopPropagation();
    }

    private function actor(): User
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 401);
        abort_unless((int) $actor->id === $this->actorId, 403);

        return $actor;
    }

    private function requiredId(): int
    {
        $id = $this->onboardingId ?? abort(409);
        Gate::forUser($this->actor())->authorize('update', $this->queries->findForUserOrFail($this->actor(), $id));

        return $id;
    }

    private function saved(): void
    {
        Flux::toast(variant: 'success', text: __('center.saved'));
    }
}
