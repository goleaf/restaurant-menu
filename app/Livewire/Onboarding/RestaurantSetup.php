<?php

declare(strict_types=1);

namespace App\Livewire\Onboarding;

use App\Actions\AreaNodes\CreateAreaNodeAction;
use App\Actions\Branches\CreateBranchAction;
use App\Actions\Brands\CreateBrandAction;
use App\Actions\Onboarding\CreateOnboardingServicePointsAction;
use App\Actions\Onboarding\CreateStarterMenuAction;
use App\Actions\Onboarding\GenerateQrCodesForServicePointsAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\SupportedCurrency;
use App\Livewire\Forms\Onboarding\RestaurantSetupForm;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\User;
use App\Services\Onboarding\RestaurantSetupQueryService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class RestaurantSetup extends Component
{
    private RestaurantSetupQueryService $setupQueries;

    public RestaurantSetupForm $form;

    public int $step = 1;

    public ?int $organizationId = null;

    public ?int $brandId = null;

    public ?int $branchId = null;

    public ?int $areaNodeId = null;

    /**
     * @var list<int>
     */
    public array $servicePointIds = [];

    /**
     * @var list<int>
     */
    public array $qrCodeIds = [];

    public ?int $menuId = null;

    public ?int $menuCategoryId = null;

    public ?int $menuItemId = null;

    /**
     * @var array<string, string>
     */
    public array $currencyOptions = [];

    public function boot(RestaurantSetupQueryService $setupQueries): void
    {
        $this->setupQueries = $setupQueries;
    }

    public function mount(): void
    {
        $this->currencyOptions = SupportedCurrency::labels();
    }

    public function createOrganization(CreateOrganizationAction $createOrganization): void
    {
        $validated = $this->form->validateOrganization($this->currentUser());

        $organization = $createOrganization->handle($this->currentUser(), [
            'name' => $validated['organizationName'],
        ]);

        $this->organizationId = $organization->id;
        $this->step = 2;
        unset($this->summary, $this->steps);

        Flux::toast(variant: 'success', text: __('ui.livewire.onboarding.restaurantsetup.kompaniia_sozdana'));
    }

    public function createBrand(CreateBrandAction $createBrand): void
    {
        $organization = $this->findOrganization();
        $validated = $this->form->validateBrand($organization);

        $brand = $createBrand->handle($organization, [
            'name' => $validated['brandName'],
        ]);

        $this->brandId = $brand->id;
        $this->step = 3;
        unset($this->summary, $this->steps);

        Flux::toast(variant: 'success', text: __('ui.livewire.onboarding.restaurantsetup.restoran_sozdan'));
    }

    public function createBranch(CreateBranchAction $createBranch): void
    {
        $brand = $this->findBrand();
        $validated = $this->form->validateBranch($brand);

        $branch = $createBranch->handle($brand, [
            'name' => $validated['branchName'],
            'address' => $validated['branchAddress'],
            'city' => $validated['branchCity'],
            'country' => $validated['branchCountry'],
            'timezone' => $validated['branchTimezone'],
            'currency' => SupportedCurrency::normalize($validated['branchCurrency']),
            'is_active' => true,
        ]);

        $this->branchId = $branch->id;
        $this->step = 4;
        unset($this->summary, $this->steps);

        Flux::toast(variant: 'success', text: __('ui.livewire.onboarding.restaurantsetup.filial_sozdan'));
    }

    public function createArea(CreateAreaNodeAction $createAreaNode): void
    {
        $branch = $this->findBranch();
        $validated = $this->form->validateArea();

        $areaNode = $createAreaNode->handle($branch, [
            'parent_id' => null,
            'type' => $validated['areaType'],
            'name' => $validated['areaName'],
            'icon' => $validated['areaIcon'] ?: null,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->areaNodeId = $areaNode->id;
        $this->step = 5;
        unset($this->summary, $this->steps);

        Flux::toast(variant: 'success', text: __('ui.livewire.onboarding.restaurantsetup.zona_dobavlena'));
    }

    public function createServicePoints(CreateOnboardingServicePointsAction $createServicePoints): void
    {
        $branch = $this->findBranch();
        $areaNode = $this->findAreaNode();
        $validated = $this->form->validateServicePoints();

        $this->servicePointIds = $createServicePoints->handle($branch, $areaNode, $validated);
        $this->step = 6;
        unset($this->summary, $this->steps);

        Flux::toast(variant: 'success', text: __('ui.livewire.onboarding.restaurantsetup.pervye_stoly_dobavleny'));
    }

    public function generateQrCodes(GenerateQrCodesForServicePointsAction $generateQrCodes): void
    {
        $qrCodeIds = $generateQrCodes->handle(
            $this->findBranch(),
            $this->servicePointIds,
            $this->currentUser(),
        );

        if ($qrCodeIds === []) {
            $this->addError('servicePointIds', __('ui.livewire.onboarding.restaurantsetup.snacala_dobavte_stoly'));

            return;
        }

        $this->qrCodeIds = $qrCodeIds;
        $this->step = 7;
        unset($this->summary, $this->steps);

        Flux::toast(variant: 'success', text: __('ui.livewire.onboarding.restaurantsetup.qr_kody_gotovy'));
    }

    public function createStarterMenu(CreateStarterMenuAction $createStarterMenu): void
    {
        $branch = $this->findBranch();
        $validated = $this->form->validateStarterMenu();

        $starterMenu = $createStarterMenu->handle($branch, [
            'menu_name' => $validated['menuName'],
            'category_name' => $validated['categoryName'],
            'item_name' => $validated['itemName'],
            'item_price' => $validated['itemPrice'],
        ]);

        $this->menuId = $starterMenu['menu']->id;
        $this->menuCategoryId = $starterMenu['category']->id;
        $this->menuItemId = $starterMenu['item']->id;
        $this->step = 8;
        unset($this->summary, $this->steps);

        Flux::toast(variant: 'success', text: __('ui.livewire.onboarding.restaurantsetup.pervoe_meniu_dobavleno'));
    }

    public function goToStep(int $step): void
    {
        if ($step < 1 || $step > $this->highestAvailableStep()) {
            return;
        }

        $this->step = $step;
    }

    /**
     * @return list<array{number: int, label: string, icon: string, is_done: bool, is_current: bool, is_available: bool}>
     */
    #[Computed]
    public function steps(): array
    {
        $availableStep = $this->highestAvailableStep();

        return collect([
            [1, __('ui.livewire.onboarding.restaurantsetup.kompaniia'), 'building-office', $this->organizationId !== null],
            [2, __('ui.livewire.onboarding.restaurantsetup.restoran'), 'building-storefront', $this->brandId !== null],
            [3, __('ui.livewire.onboarding.restaurantsetup.adres'), 'map-pin', $this->branchId !== null],
            [4, __('ui.livewire.onboarding.restaurantsetup.zona'), 'rectangle-group', $this->areaNodeId !== null],
            [5, __('ui.livewire.onboarding.restaurantsetup.stoly'), 'squares-2x2', $this->servicePointIds !== []],
            [6, __('permissions.groups.qr'), 'qr-code', $this->qrCodeIds !== []],
            [7, __('ui.livewire.onboarding.restaurantsetup.meniu'), 'book-open', $this->menuId !== null],
            [8, __('ui.livewire.onboarding.restaurantsetup.proverka'), 'check-circle', $this->menuId !== null],
        ])->map(fn (array $step): array => [
            'number' => $step[0],
            'label' => $step[1],
            'icon' => $step[2],
            'is_done' => $step[3],
            'is_current' => $this->step === $step[0],
            'is_available' => $step[0] <= $availableStep,
        ])->all();
    }

    /**
     * @return array{
     *     organization: string|null,
     *     brand: string|null,
     *     branch: string|null,
     *     area: string|null,
     *     service_points: int,
     *     qr_codes: int,
     *     menu: string|null,
     *     guest_url: string|null,
     *     branch_url: string|null,
     *     menu_url: string|null,
     *     print_url: string|null
     * }
     */
    #[Computed]
    public function summary(): array
    {
        $context = $this->setupQueries->summaryContext(
            $this->currentUser(),
            $this->organizationId,
            $this->brandId,
            $this->branchId,
            $this->areaNodeId,
            $this->menuId,
            $this->qrCodeIds,
        );
        $organization = $context['organization'];
        $brand = $context['brand'];
        $branch = $context['branch'];
        $areaNode = $context['areaNode'];
        $menu = $context['menu'];
        $qrCode = $context['qrCode'];

        return [
            'organization' => $organization?->name,
            'brand' => $brand?->name,
            'branch' => $branch?->name,
            'area' => $areaNode?->name,
            'service_points' => count($this->servicePointIds),
            'qr_codes' => count($this->qrCodeIds),
            'menu' => $menu?->name,
            'guest_url' => $qrCode instanceof QrCode ? route('public.qr.show', ['token' => $qrCode->public_token]) : null,
            'branch_url' => $organization instanceof Organization && $brand instanceof Brand
                ? route('organizations.brands.branches.index', [$organization, $brand])
                : null,
            'menu_url' => $organization instanceof Organization && $brand instanceof Brand && $branch instanceof Branch
                ? route('organizations.brands.branches.menu.index', [$organization, $brand, $branch])
                : null,
            'print_url' => $organization instanceof Organization && $brand instanceof Brand && $branch instanceof Branch
                ? route('organizations.brands.branches.qr.print', [$organization, $brand, $branch])
                : null,
        ];
    }

    public function render(): View
    {
        return view('livewire.onboarding.restaurant-setup')
            ->title(__('ui.onboarding.restaurant_setup.nastroit_restoran'));
    }

    private function currentUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    private function findOrganization(): Organization
    {
        return $this->setupQueries->findOrganization($this->currentUser(), $this->organizationId);
    }

    private function findBrand(): Brand
    {
        return $this->setupQueries->findBrand($this->findOrganization(), $this->brandId);
    }

    private function findBranch(): Branch
    {
        return $this->setupQueries->findBranch($this->findBrand(), $this->branchId);
    }

    private function findAreaNode(): AreaNode
    {
        return $this->setupQueries->findAreaNode($this->findBranch(), $this->areaNodeId);
    }

    private function highestAvailableStep(): int
    {
        return match (true) {
            $this->menuId !== null => 8,
            $this->qrCodeIds !== [] => 7,
            $this->servicePointIds !== [] => 6,
            $this->areaNodeId !== null => 5,
            $this->branchId !== null => 4,
            $this->brandId !== null => 3,
            $this->organizationId !== null => 2,
            default => 1,
        };
    }
}
