<?php

namespace App\Livewire\Onboarding;

use App\Actions\AreaNodes\CreateAreaNodeAction;
use App\Actions\Branches\CreateBranchAction;
use App\Actions\Brands\CreateBrandAction;
use App\Actions\Onboarding\CreateStarterMenuAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\QrCodes\GenerateQrCodeForServicePointAction;
use App\Actions\ServicePoints\CreateServicePointAction;
use App\Enums\AreaNodeType;
use App\Enums\ServicePointType;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Настроить ресторан')]
class RestaurantSetup extends Component
{
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

    public string $organizationName = '';

    public string $brandName = '';

    public string $branchName = '';

    public string $branchAddress = '';

    public string $branchCity = '';

    public string $branchCountry = '';

    public string $branchTimezone = 'Europe/Vilnius';

    public string $branchCurrency = 'EUR';

    public string $areaName = 'Главный зал';

    public string $areaType = 'hall';

    public string $areaIcon = 'rectangle-group';

    public int $tableCount = 4;

    public string $tablePrefix = 'Стол';

    public int $tableCapacity = 4;

    public string $menuName = 'Основное меню';

    public string $categoryName = 'Основное';

    public string $itemName = 'Тестовое блюдо';

    public string $itemPrice = '10.00';

    public function createOrganization(CreateOrganizationAction $createOrganization): void
    {
        $this->organizationName = trim($this->organizationName);

        $validated = $this->validate([
            'organizationName' => [
                'required',
                'string',
                'max:120',
                Rule::unique((new Organization)->getTable(), 'name')
                    ->where(fn ($query) => $query->where('owner_user_id', $this->currentUser()->id)),
            ],
        ]);

        $organization = $createOrganization->handle($this->currentUser(), [
            'name' => $validated['organizationName'],
        ]);

        $this->organizationId = $organization->id;
        $this->step = 2;
        unset($this->summary, $this->steps);

        Flux::toast(variant: 'success', text: __('Компания создана.'));
    }

    public function createBrand(CreateBrandAction $createBrand): void
    {
        $organization = $this->findOrganization();
        $this->brandName = trim($this->brandName);

        $validated = $this->validate([
            'brandName' => [
                'required',
                'string',
                'max:120',
                Rule::unique((new Brand)->getTable(), 'name')
                    ->where(fn ($query) => $query->where('organization_id', $organization->id)),
            ],
        ]);

        $brand = $createBrand->handle($organization, [
            'name' => $validated['brandName'],
        ]);

        $this->brandId = $brand->id;
        $this->step = 3;
        unset($this->summary, $this->steps);

        Flux::toast(variant: 'success', text: __('Ресторан создан.'));
    }

    public function createBranch(CreateBranchAction $createBranch): void
    {
        $brand = $this->findBrand();
        $this->branchName = trim($this->branchName);
        $this->branchAddress = trim($this->branchAddress);
        $this->branchCity = trim($this->branchCity);
        $this->branchCountry = trim($this->branchCountry);
        $this->branchCurrency = mb_strtoupper(trim($this->branchCurrency));

        $validated = $this->validate([
            'branchName' => [
                'required',
                'string',
                'max:160',
                Rule::unique((new Branch)->getTable(), 'name')
                    ->where(fn ($query) => $query->where('brand_id', $brand->id)),
            ],
            'branchAddress' => ['required', 'string', 'max:255'],
            'branchCity' => ['required', 'string', 'max:120'],
            'branchCountry' => ['required', 'string', 'max:120'],
            'branchTimezone' => ['required', 'timezone', 'max:64'],
            'branchCurrency' => ['required', 'string', 'size:3'],
        ]);

        $branch = $createBranch->handle($brand, [
            'name' => $validated['branchName'],
            'address' => $validated['branchAddress'],
            'city' => $validated['branchCity'],
            'country' => $validated['branchCountry'],
            'timezone' => $validated['branchTimezone'],
            'currency' => $validated['branchCurrency'],
            'is_active' => true,
        ]);

        $this->branchId = $branch->id;
        $this->step = 4;
        unset($this->summary, $this->steps);

        Flux::toast(variant: 'success', text: __('Филиал создан.'));
    }

    public function createArea(CreateAreaNodeAction $createAreaNode): void
    {
        $branch = $this->findBranch();
        $this->areaName = trim($this->areaName);
        $this->areaIcon = trim($this->areaIcon);

        $validated = $this->validate([
            'areaName' => ['required', 'string', 'max:160'],
            'areaType' => ['required', Rule::in(AreaNodeType::values())],
            'areaIcon' => ['nullable', 'string', 'max:80'],
        ]);

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

        Flux::toast(variant: 'success', text: __('Зона добавлена.'));
    }

    public function createServicePoints(CreateServicePointAction $createServicePoint): void
    {
        $branch = $this->findBranch();
        $areaNode = $this->findAreaNode();
        $this->tablePrefix = trim($this->tablePrefix);

        $validated = $this->validate([
            'tableCount' => ['required', 'integer', 'min:1', 'max:20'],
            'tablePrefix' => ['required', 'string', 'max:40'],
            'tableCapacity' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $servicePointIds = [];

        for ($number = 1; $number <= (int) $validated['tableCount']; $number++) {
            $servicePoint = $createServicePoint->handle($branch, [
                'area_node_id' => $areaNode->id,
                'type' => ServicePointType::Table->value,
                'name' => $validated['tablePrefix'].' '.$number,
                'display_number' => (string) $number,
                'capacity' => (int) $validated['tableCapacity'],
                'icon' => 'squares-2x2',
                'is_active' => true,
            ]);

            $servicePointIds[] = $servicePoint->id;
        }

        $this->servicePointIds = $servicePointIds;
        $this->step = 6;
        unset($this->summary, $this->steps);

        Flux::toast(variant: 'success', text: __('Первые столы добавлены.'));
    }

    public function generateQrCodes(GenerateQrCodeForServicePointAction $generateQrCode): void
    {
        $this->findBranch();

        $qrCodeIds = ServicePoint::query()
            ->select(['id', 'branch_id'])
            ->whereIn('id', $this->servicePointIds)
            ->where('branch_id', $this->branchId)
            ->orderBy('id')
            ->get()
            ->map(fn (ServicePoint $servicePoint): int => $generateQrCode->handle($servicePoint, $this->currentUser())->id)
            ->values()
            ->all();

        if ($qrCodeIds === []) {
            $this->addError('servicePointIds', __('Сначала добавьте столы.'));

            return;
        }

        $this->qrCodeIds = $qrCodeIds;
        $this->step = 7;
        unset($this->summary, $this->steps);

        Flux::toast(variant: 'success', text: __('QR-коды готовы.'));
    }

    public function createStarterMenu(CreateStarterMenuAction $createStarterMenu): void
    {
        $branch = $this->findBranch();
        $this->menuName = trim($this->menuName);
        $this->categoryName = trim($this->categoryName);
        $this->itemName = trim($this->itemName);
        $this->itemPrice = trim($this->itemPrice);

        $validated = $this->validate([
            'menuName' => ['required', 'string', 'max:160'],
            'categoryName' => ['required', 'string', 'max:160'],
            'itemName' => ['required', 'string', 'max:180'],
            'itemPrice' => ['required', 'numeric', 'min:0', 'max:999999.99'],
        ]);

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

        Flux::toast(variant: 'success', text: __('Первое меню добавлено.'));
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
            [1, __('Компания'), 'building-office', $this->organizationId !== null],
            [2, __('Ресторан'), 'building-storefront', $this->brandId !== null],
            [3, __('Адрес'), 'map-pin', $this->branchId !== null],
            [4, __('Зона'), 'rectangle-group', $this->areaNodeId !== null],
            [5, __('Столы'), 'squares-2x2', $this->servicePointIds !== []],
            [6, __('QR'), 'qr-code', $this->qrCodeIds !== []],
            [7, __('Меню'), 'book-open', $this->menuId !== null],
            [8, __('Проверка'), 'check-circle', $this->menuId !== null],
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
        $organization = $this->organizationId === null ? null : Organization::query()
            ->select(['id', 'owner_user_id', 'name'])
            ->whereKey($this->organizationId)
            ->first();

        if ($organization instanceof Organization && ! $this->currentUser()->canAccessOrganization($organization)) {
            $organization = null;
        }

        $brand = $organization instanceof Organization && $this->brandId !== null ? Brand::query()
            ->select(['id', 'organization_id', 'name'])
            ->where('organization_id', $organization->id)
            ->whereKey($this->brandId)
            ->first() : null;
        $branch = $brand instanceof Brand && $this->branchId !== null ? Branch::query()
            ->select(['id', 'organization_id', 'brand_id', 'name'])
            ->where('organization_id', $organization->id)
            ->where('brand_id', $brand->id)
            ->whereKey($this->branchId)
            ->first() : null;
        $areaNode = $branch instanceof Branch && $this->areaNodeId !== null ? AreaNode::query()
            ->select(['id', 'branch_id', 'name'])
            ->where('branch_id', $branch->id)
            ->whereKey($this->areaNodeId)
            ->first() : null;
        $menu = $branch instanceof Branch && $this->menuId !== null ? Menu::query()
            ->select(['id', 'branch_id', 'name'])
            ->where('branch_id', $branch->id)
            ->whereKey($this->menuId)
            ->first() : null;
        $qrCode = $this->firstQrCode($branch);

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
        return view('livewire.onboarding.restaurant-setup');
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
        if ($this->organizationId === null) {
            abort(403);
        }

        $organization = Organization::query()
            ->select(['id', 'owner_user_id', 'name'])
            ->whereKey($this->organizationId)
            ->firstOrFail();

        if (! $this->currentUser()->canAccessOrganization($organization)) {
            abort(403);
        }

        return $organization;
    }

    private function findBrand(): Brand
    {
        $organization = $this->findOrganization();

        return $organization
            ->brands()
            ->select(['id', 'organization_id', 'name'])
            ->whereKey($this->brandId)
            ->firstOrFail();
    }

    private function findBranch(): Branch
    {
        $brand = $this->findBrand();

        return $brand
            ->branches()
            ->select([
                'id',
                'organization_id',
                'brand_id',
                'name',
                'address',
                'city',
                'country',
                'timezone',
                'currency',
                'is_active',
            ])
            ->whereKey($this->branchId)
            ->firstOrFail();
    }

    private function findAreaNode(): AreaNode
    {
        $branch = $this->findBranch();

        return $branch
            ->areaNodes()
            ->select(['id', 'branch_id', 'name'])
            ->whereKey($this->areaNodeId)
            ->firstOrFail();
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

    private function firstQrCode(?Branch $branch = null): ?QrCode
    {
        if ($this->qrCodeIds === [] || ! $branch instanceof Branch) {
            return null;
        }

        return QrCode::query()
            ->select(['id', 'service_point_id', 'public_token', 'short_code', 'status'])
            ->whereIn('id', $this->qrCodeIds)
            ->whereHas('servicePoint', function ($query) use ($branch): void {
                $query->where('branch_id', $branch->id);
            })
            ->oldest('id')
            ->first();
    }
}
