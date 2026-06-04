<?php

namespace App\Livewire\Organizations\Brands\Branches;

use App\Actions\Branches\CreateBranchAction;
use App\Actions\Branches\DeleteBranchAction;
use App\Actions\Branches\UpdateBranchAction;
use App\Actions\Media\DeleteLocalMediaFileAction;
use App\Actions\Media\StoreLocalImageAction;
use App\Enums\QrCodeStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Branches')]
class Index extends Component
{
    use WithFileUploads;

    public Organization $organization;

    public Brand $brand;

    /**
     * @var array<int, mixed>
     */
    public array $branchLogos = [];

    public string $name = '';

    public string $address = '';

    public string $city = '';

    public string $country = '';

    public string $timezone = 'Europe/Vilnius';

    public string $currency = 'EUR';

    public bool $isActive = true;

    public ?int $editingBranchId = null;

    public string $editingName = '';

    public string $editingAddress = '';

    public string $editingCity = '';

    public string $editingCountry = '';

    public string $editingTimezone = '';

    public string $editingCurrency = '';

    public bool $editingIsActive = true;

    public ?int $deletingBranchId = null;

    public bool $canManageBranches = false;

    public bool $canManageZones = false;

    public bool $canManageServicePoints = false;

    public bool $canManageMenu = false;

    public bool $canChangeAvailability = false;

    public bool $canChangeServicePointStatus = false;

    public bool $canOpenTable = false;

    public bool $canGenerateQr = false;

    public bool $canManageStaff = false;

    public function mount(Organization $organization, Brand $brand): void
    {
        $this->organization = $organization;
        $this->brand = $brand;

        if ($brand->organization_id !== $organization->id) {
            abort(403);
        }

        if (! $this->currentUser()->canAccessOrganization($organization)) {
            abort(403);
        }

        $this->canManageBranches = $this->currentUser()->canManageOrganizationBranches($organization);
        $this->canManageZones = $this->currentUser()->hasPermission(SystemPermission::ManageZones, $organization);
        $this->canManageServicePoints = $this->currentUser()->hasPermission(SystemPermission::ManageServicePoints, $organization);
        $this->canManageMenu = $this->currentUser()->hasPermission(SystemPermission::ManageMenu, $organization);
        $this->canChangeAvailability = $this->currentUser()->hasPermission(SystemPermission::ChangeAvailability, $organization);
        $this->canChangeServicePointStatus = $this->canManageServicePoints
            || $this->currentUser()->hasOrganizationRole($organization, SystemRole::Waiter);
        $this->canOpenTable = $this->currentUser()->hasPermission(SystemPermission::ViewOrders, $organization)
            || $this->currentUser()->hasPermission(SystemPermission::ConfirmOrders, $organization);
        $this->canGenerateQr = $this->currentUser()->hasPermission(SystemPermission::GenerateQr, $organization);
        $this->canManageStaff = $this->currentUser()->hasPermission(SystemPermission::ManageStaff, $organization);
    }

    public function create(CreateBranchAction $createBranch): void
    {
        $this->authorizeBranchManagement();

        $validated = $this->validate($this->branchRules());

        $createBranch->handle($this->brand, $this->branchPayload($validated));

        $this->resetCreateForm();
        unset($this->branches);

        Flux::toast(variant: 'success', text: __('Branch created.'));
    }

    public function startEditing(int $branchId): void
    {
        $this->authorizeBranchManagement();

        $branch = $this->findBrandBranch($branchId);

        $this->editingBranchId = $branch->id;
        $this->editingName = $branch->name;
        $this->editingAddress = $branch->address;
        $this->editingCity = $branch->city;
        $this->editingCountry = $branch->country;
        $this->editingTimezone = $branch->timezone;
        $this->editingCurrency = $branch->currency;
        $this->editingIsActive = $branch->is_active;
        $this->deletingBranchId = null;
    }

    public function cancelEditing(): void
    {
        $this->reset(
            'editingBranchId',
            'editingName',
            'editingAddress',
            'editingCity',
            'editingCountry',
            'editingTimezone',
            'editingCurrency',
        );

        $this->editingIsActive = true;
    }

    public function update(UpdateBranchAction $updateBranch): void
    {
        $this->authorizeBranchManagement();

        if ($this->editingBranchId === null) {
            return;
        }

        $validated = $this->validate($this->branchRules('editing', $this->editingBranchId));

        $updateBranch->handle(
            $this->findBrandBranch($this->editingBranchId),
            $this->branchPayload($validated, 'editing'),
        );

        $this->cancelEditing();
        unset($this->branches);

        Flux::toast(variant: 'success', text: __('Branch updated.'));
    }

    public function confirmDelete(int $branchId): void
    {
        $this->authorizeBranchManagement();

        $branch = $this->findBrandBranch($branchId);

        $this->deletingBranchId = $branch->id;
        $this->cancelEditing();
    }

    public function cancelDelete(): void
    {
        $this->reset('deletingBranchId');
    }

    public function delete(DeleteBranchAction $deleteBranch): void
    {
        $this->authorizeBranchManagement();

        if ($this->deletingBranchId === null) {
            return;
        }

        $deleteBranch->handle($this->findBrandBranch($this->deletingBranchId));

        $this->cancelDelete();
        unset($this->branches);

        Flux::toast(variant: 'success', text: __('Branch deleted.'));
    }

    public function saveLogo(int $branchId, StoreLocalImageAction $storeLocalImage): void
    {
        $this->authorizeBranchManagement();

        $branch = $this->findBrandBranch($branchId);

        $this->validate([
            'branchLogos.'.$branch->id => StoreLocalImageAction::validationRules(),
        ]);

        $file = $this->branchLogos[$branch->id] ?? null;

        if (! $file instanceof UploadedFile) {
            return;
        }

        $branch->update([
            'logo_path' => $storeLocalImage->handle(
                file: $file,
                directory: 'media/organizations/'.$this->organization->id.'/brands/'.$this->brand->id.'/branches/'.$branch->id.'/logos',
                oldPath: $branch->logo_path,
            ),
        ]);

        unset($this->branchLogos[$branch->id], $this->branches);

        Flux::toast(variant: 'success', text: __('Logo uploaded.'));
    }

    public function removeLogo(int $branchId, DeleteLocalMediaFileAction $deleteLocalMediaFile): void
    {
        $this->authorizeBranchManagement();

        $branch = $this->findBrandBranch($branchId);

        $deleteLocalMediaFile->handle($branch->logo_path);
        $branch->update(['logo_path' => null]);

        unset($this->branchLogos[$branch->id], $this->branches);

        Flux::toast(variant: 'success', text: __('Logo removed.'));
    }

    /**
     * @return EloquentCollection<int, Branch>
     */
    #[Computed]
    public function branches(): EloquentCollection
    {
        return $this->brand
            ->branches()
            ->select([
                'id',
                'organization_id',
                'brand_id',
                'name',
                'logo_path',
                'address',
                'city',
                'country',
                'timezone',
                'currency',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->withCount([
                'areaNodes as setup_active_area_nodes_count' => fn ($query) => $query->where('is_active', true),
                'servicePoints as setup_active_service_points_count' => fn ($query) => $query->where('is_active', true),
                'servicePoints as setup_active_qr_codes_count' => fn ($query) => $query
                    ->where('is_active', true)
                    ->whereHas('activeQrCode'),
            ])
            ->with([
                'servicePoints' => fn ($query) => $query
                    ->select([
                        'id',
                        'branch_id',
                        'name',
                        'is_active',
                    ])
                    ->where('is_active', true)
                    ->with([
                        'activeQrCode' => fn ($query) => $query
                            ->select([
                                'id',
                                'service_point_id',
                                'public_token',
                                'short_code',
                                'status',
                            ])
                            ->where('status', QrCodeStatus::Active->value),
                    ])
                    ->orderBy('id'),
            ])
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<array{
     *     branch: Branch,
     *     counts: array{areas: int, service_points: int, qr_codes: int},
     *     steps: list<array{
     *         number: int,
     *         label: string,
     *         description: string,
     *         icon: string,
     *         href: string|null,
     *         button_label: string|null,
     *         is_done: bool,
     *         is_available: bool
     *     }>
     * }>
     */
    #[Computed]
    public function branchSetupGuides(): array
    {
        return $this->branches
            ->map(fn (Branch $branch): array => [
                'branch' => $branch,
                'counts' => [
                    'areas' => (int) ($branch->setup_active_area_nodes_count ?? 0),
                    'service_points' => (int) ($branch->setup_active_service_points_count ?? 0),
                    'qr_codes' => (int) ($branch->setup_active_qr_codes_count ?? 0),
                ],
                'steps' => $this->branchSetupSteps($branch),
            ])
            ->values()
            ->all();
    }

    public function render(): View
    {
        return view('livewire.organizations.brands.branches.index');
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function branchRules(string $prefix = '', ?int $ignoreBranchId = null): array
    {
        $fieldPrefix = $prefix === '' ? '' : $prefix;
        $nameField = $fieldPrefix === '' ? 'name' : $fieldPrefix.'Name';

        $uniqueRule = Rule::unique((new Branch)->getTable(), 'name')
            ->where(fn ($query) => $query->where('brand_id', $this->brand->id));

        if ($ignoreBranchId !== null) {
            $uniqueRule->ignore($ignoreBranchId);
        }

        return [
            $nameField => ['required', 'string', 'max:160', $uniqueRule],
            $fieldPrefix === '' ? 'address' : $fieldPrefix.'Address' => ['required', 'string', 'max:255'],
            $fieldPrefix === '' ? 'city' : $fieldPrefix.'City' => ['required', 'string', 'max:120'],
            $fieldPrefix === '' ? 'country' : $fieldPrefix.'Country' => ['required', 'string', 'max:120'],
            $fieldPrefix === '' ? 'timezone' : $fieldPrefix.'Timezone' => ['required', 'timezone', 'max:64'],
            $fieldPrefix === '' ? 'currency' : $fieldPrefix.'Currency' => ['required', 'string', 'size:3'],
            $fieldPrefix === '' ? 'isActive' : $fieldPrefix.'IsActive' => ['boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{name: string, address: string, city: string, country: string, timezone: string, currency: string, is_active: bool}
     */
    private function branchPayload(array $validated, string $prefix = ''): array
    {
        return [
            'name' => $validated[$prefix === '' ? 'name' : $prefix.'Name'],
            'address' => $validated[$prefix === '' ? 'address' : $prefix.'Address'],
            'city' => $validated[$prefix === '' ? 'city' : $prefix.'City'],
            'country' => $validated[$prefix === '' ? 'country' : $prefix.'Country'],
            'timezone' => $validated[$prefix === '' ? 'timezone' : $prefix.'Timezone'],
            'currency' => strtoupper($validated[$prefix === '' ? 'currency' : $prefix.'Currency']),
            'is_active' => (bool) $validated[$prefix === '' ? 'isActive' : $prefix.'IsActive'],
        ];
    }

    private function resetCreateForm(): void
    {
        $this->reset('name', 'address', 'city', 'country');
        $this->timezone = 'Europe/Vilnius';
        $this->currency = 'EUR';
        $this->isActive = true;
    }

    /**
     * @return list<array{
     *     number: int,
     *     label: string,
     *     description: string,
     *     icon: string,
     *     href: string|null,
     *     button_label: string|null,
     *     is_done: bool,
     *     is_available: bool
     * }>
     */
    private function branchSetupSteps(Branch $branch): array
    {
        $areaCount = (int) ($branch->setup_active_area_nodes_count ?? 0);
        $servicePointCount = (int) ($branch->setup_active_service_points_count ?? 0);
        $qrCount = (int) ($branch->setup_active_qr_codes_count ?? 0);
        $firstActiveQrCode = $this->firstActiveQrCode($branch);

        return [
            [
                'number' => 1,
                'label' => __('Создать филиал'),
                'description' => $branch->is_active
                    ? __('Филиал создан и готов к настройке.')
                    : __('Филиал создан, но пока выключен.'),
                'icon' => 'building-office',
                'href' => $this->canManageBranches
                    ? route('organizations.brands.branches.settings.index', [$this->organization, $this->brand, $branch])
                    : null,
                'button_label' => $this->canManageBranches ? __('Настройки') : null,
                'is_done' => true,
                'is_available' => $this->canManageBranches,
            ],
            [
                'number' => 2,
                'label' => __('Добавить зоны'),
                'description' => $areaCount > 0
                    ? trans_choice('{1} :count зона уже добавлена|[2,*] :count зоны уже добавлены', $areaCount, ['count' => $areaCount])
                    : __('Создайте зал, террасу или VIP-зону.'),
                'icon' => 'rectangle-group',
                'href' => $this->canManageZones
                    ? route('organizations.brands.branches.areas.index', [$this->organization, $this->brand, $branch])
                    : null,
                'button_label' => __('Зоны'),
                'is_done' => $areaCount > 0,
                'is_available' => $this->canManageZones,
            ],
            [
                'number' => 3,
                'label' => __('Добавить столы'),
                'description' => $servicePointCount > 0
                    ? trans_choice('{1} :count стол или место добавлено|[2,*] :count столов или мест добавлено', $servicePointCount, ['count' => $servicePointCount])
                    : __('Добавьте столы, барные места или комнаты.'),
                'icon' => 'squares-2x2',
                'href' => $this->canManageServicePoints
                    ? route('organizations.brands.branches.service-points.index', [$this->organization, $this->brand, $branch])
                    : null,
                'button_label' => __('Столы'),
                'is_done' => $servicePointCount > 0,
                'is_available' => $this->canManageServicePoints,
            ],
            [
                'number' => 4,
                'label' => __('Сгенерировать QR'),
                'description' => $qrCount > 0
                    ? trans_choice('{1} :count QR уже готов|[2,*] :count QR уже готовы', $qrCount, ['count' => $qrCount])
                    : __('Создайте постоянный QR для каждого стола.'),
                'icon' => 'qr-code',
                'href' => $this->canGenerateQr && $servicePointCount > 0
                    ? route('organizations.brands.branches.service-points.index', [$this->organization, $this->brand, $branch])
                    : null,
                'button_label' => __('QR'),
                'is_done' => $qrCount > 0,
                'is_available' => $this->canGenerateQr && $servicePointCount > 0,
            ],
            [
                'number' => 5,
                'label' => __('Напечатать QR'),
                'description' => $qrCount > 0
                    ? __('Откройте страницу печати наклеек.')
                    : __('Печать появится после создания QR.'),
                'icon' => 'printer',
                'href' => $this->canGenerateQr && $qrCount > 0
                    ? route('organizations.brands.branches.qr.print', [$this->organization, $this->brand, $branch])
                    : null,
                'button_label' => __('Печать'),
                'is_done' => $qrCount > 0,
                'is_available' => $this->canGenerateQr && $qrCount > 0,
            ],
            [
                'number' => 6,
                'label' => __('Открыть гостевое меню'),
                'description' => $firstActiveQrCode instanceof QrCode
                    ? __('Проверьте экран, который увидит гость.')
                    : __('Гостевой экран откроется после QR.'),
                'icon' => 'book-open',
                'href' => $firstActiveQrCode instanceof QrCode
                    ? route('public.qr.show', ['token' => $firstActiveQrCode->public_token])
                    : null,
                'button_label' => __('Открыть'),
                'is_done' => $firstActiveQrCode instanceof QrCode,
                'is_available' => $firstActiveQrCode instanceof QrCode,
            ],
        ];
    }

    private function firstActiveQrCode(Branch $branch): ?QrCode
    {
        return $branch
            ->servicePoints
            ->first(fn (ServicePoint $servicePoint): bool => $servicePoint->activeQrCode instanceof QrCode)
            ?->activeQrCode;
    }

    private function currentUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    private function authorizeBranchManagement(): void
    {
        if (! $this->canManageBranches) {
            abort(403);
        }
    }

    private function findBrandBranch(int $branchId): Branch
    {
        return $this->brand
            ->branches()
            ->select([
                'id',
                'organization_id',
                'brand_id',
                'name',
                'logo_path',
                'address',
                'city',
                'country',
                'timezone',
                'currency',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->whereKey($branchId)
            ->firstOrFail();
    }
}
