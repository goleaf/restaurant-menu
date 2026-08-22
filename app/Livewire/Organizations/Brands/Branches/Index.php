<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches;

use App\Actions\Branches\CreateBranchAction;
use App\Actions\Branches\DeleteBranchAction;
use App\Actions\Branches\UpdateBranchAction;
use App\Actions\Media\RemoveLocalImageAction;
use App\Actions\Media\ReplaceLocalImageAction;
use App\Actions\Media\StoreLocalImageAction;
use App\Enums\QrCodeStatus;
use App\Enums\SupportedCurrency;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use App\Support\Validation\RestaurantValidationRules;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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

    public string $branchSuspendReason = '';

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

    /**
     * @var array<string, string>
     */
    public array $currencyOptions = [];

    public function mount(Organization $organization, Brand $brand): void
    {
        $this->organization = $organization;
        $this->brand = $brand;
        $this->currencyOptions = SupportedCurrency::labels();

        if ($brand->organization_id !== $organization->id) {
            abort(403);
        }

        $user = $this->currentUser();
        Gate::forUser($user)->authorize('view', $brand);

        $this->canManageBranches = Gate::forUser($user)->allows('create', [Branch::class, $organization]);
        $this->canManageZones = $user->hasPermission(SystemPermission::ManageZones, $organization);
        $this->canManageServicePoints = $user->hasPermission(SystemPermission::ManageServicePoints, $organization);
        $this->canManageMenu = $user->hasPermission(SystemPermission::ManageMenu, $organization);
        $this->canChangeAvailability = $user->hasPermission(SystemPermission::ChangeAvailability, $organization);
        $this->canChangeServicePointStatus = $this->canManageServicePoints
            || $user->hasOrganizationRole($organization, SystemRole::Waiter);
        $this->canOpenTable = $user->hasPermission(SystemPermission::ViewOrders, $organization)
            || $user->hasPermission(SystemPermission::ConfirmOrders, $organization);
        $this->canGenerateQr = $user->hasPermission(SystemPermission::GenerateQr, $organization);
        $this->canManageStaff = $user->hasPermission(SystemPermission::ManageStaff, $organization);
    }

    public function create(CreateBranchAction $createBranch): void
    {
        $this->authorizeBranchManagement();
        $this->currency = SupportedCurrency::clean($this->currency);

        $validated = $this->validate($this->branchRules());

        $createBranch->handle($this->brand, $this->branchPayload($validated));

        $this->resetCreateForm();
        unset($this->branches);

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.index.branch_created'));
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
        $this->branchSuspendReason = '';
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
            'branchSuspendReason',
        );

        $this->editingIsActive = true;
    }

    public function update(UpdateBranchAction $updateBranch): void
    {
        $this->authorizeBranchManagement();

        if ($this->editingBranchId === null) {
            return;
        }

        $this->editingCurrency = SupportedCurrency::clean($this->editingCurrency);

        $branch = $this->findBrandBranch($this->editingBranchId);
        $validated = $this->validate($this->branchRules('editing', $this->editingBranchId));
        $reason = null;

        if ($branch->is_active && ! (bool) $validated['editingIsActive']) {
            $reasonValidation = $this->validate(RestaurantValidationRules::auditReason('branchSuspendReason'), [
                'branchSuspendReason.required' => __('ui.livewire.organizations.brands.branches.index.explain_why_this_branch_is'),
                'branchSuspendReason.min' => __('ui.livewire.organizations.brands.branches.index.the_suspension_reason_must'),
            ]);

            $reason = (string) $reasonValidation['branchSuspendReason'];
        }

        $updateBranch->handle(
            $branch,
            $this->branchPayload($validated, 'editing'),
            $this->currentUser(),
            $reason,
        );

        $this->cancelEditing();
        unset($this->branches);

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.index.branch_updated'));
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

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.index.branch_deleted'));
    }

    public function saveLogo(int $branchId, ReplaceLocalImageAction $replaceLocalImage): void
    {
        $this->authorizeBranchManagement();

        $branch = $this->findBrandBranch($branchId);

        $this->validate(
            RestaurantValidationRules::imageUpload('branchLogos.'.$branch->id),
            StoreLocalImageAction::validationMessages('branchLogos.'.$branch->id),
        );

        $file = $this->branchLogos[$branch->id] ?? null;

        if (! $file instanceof UploadedFile) {
            return;
        }

        $replaceLocalImage->handle(
            file: $file,
            directory: 'media/organizations/'.$this->organization->id.'/brands/'.$this->brand->id.'/branches/'.$branch->id.'/logos',
            oldPath: $branch->logo_path,
            persist: function (string $path) use ($branch): void {
                $branch->forceFill(['logo_path' => $path])->saveOrFail();
            },
        );

        unset($this->branchLogos[$branch->id], $this->branches);

        Flux::toast(variant: 'success', text: __('uploads.messages.uploaded'));
    }

    public function removeLogo(int $branchId, RemoveLocalImageAction $removeLocalImage): void
    {
        $this->authorizeBranchManagement();

        $branch = $this->findBrandBranch($branchId);

        $removeLocalImage->handle(
            oldPath: $branch->logo_path,
            persist: function () use ($branch): void {
                $branch->forceFill(['logo_path' => null])->saveOrFail();
            },
        );

        unset($this->branchLogos[$branch->id], $this->branches);

        Flux::toast(variant: 'success', text: __('uploads.messages.removed'));
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
            ->whereIn('id', $this->currentUser()->accessibleBranchIdsForOrganization($this->organization))
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

        $rules = RestaurantValidationRules::branchBase($fieldPrefix);
        $rules[$nameField][] = $uniqueRule;

        return $rules;
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
            'currency' => SupportedCurrency::normalize($validated[$prefix === '' ? 'currency' : $prefix.'Currency']),
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
                'label' => __('ui.livewire.organizations.brands.branches.index.sozdat_filial'),
                'description' => $branch->is_active
                    ? __('ui.livewire.organizations.brands.branches.index.filial_sozdan_i_gotov_k_nas')
                    : __('ui.livewire.organizations.brands.branches.index.filial_sozdan_no_poka_vykli'),
                'icon' => 'building-office',
                'href' => $this->canManageBranches
                    ? route('organizations.brands.branches.settings.index', [$this->organization, $this->brand, $branch])
                    : null,
                'button_label' => $this->canManageBranches ? __('ui.livewire.organizations.brands.branches.index.nastroiki') : null,
                'is_done' => true,
                'is_available' => $this->canManageBranches,
            ],
            [
                'number' => 2,
                'label' => __('ui.livewire.organizations.brands.branches.index.dobavit_zony'),
                'description' => $areaCount > 0
                    ? trans_choice('ui.livewire.organizations.brands.branches.index.1_zona_uze_dobavlena_2_zony', $areaCount, ['count' => $areaCount])
                    : __('ui.livewire.organizations.brands.branches.index.sozdaite_zal_terrasu_ili_vi'),
                'icon' => 'rectangle-group',
                'href' => $this->canManageZones
                    ? route('organizations.brands.branches.areas.index', [$this->organization, $this->brand, $branch])
                    : null,
                'button_label' => __('ui.livewire.organizations.brands.branches.index.zony'),
                'is_done' => $areaCount > 0,
                'is_available' => $this->canManageZones,
            ],
            [
                'number' => 3,
                'label' => __('ui.livewire.organizations.brands.branches.index.dobavit_stoly'),
                'description' => $servicePointCount > 0
                    ? trans_choice('ui.livewire.organizations.brands.branches.index.1_stol_ili_mesto_dobavleno', $servicePointCount, ['count' => $servicePointCount])
                    : __('ui.livewire.organizations.brands.branches.index.dobavte_stoly_barnye_mesta'),
                'icon' => 'squares-2x2',
                'href' => $this->canManageServicePoints
                    ? route('organizations.brands.branches.service-points.index', [$this->organization, $this->brand, $branch])
                    : null,
                'button_label' => __('ui.livewire.onboarding.restaurantsetup.stoly'),
                'is_done' => $servicePointCount > 0,
                'is_available' => $this->canManageServicePoints,
            ],
            [
                'number' => 4,
                'label' => __('qr.setup.generate.title'),
                'description' => $qrCount > 0
                    ? trans_choice('qr.setup.generate.ready_count', $qrCount, ['count' => $qrCount])
                    : __('qr.setup.generate.description'),
                'icon' => 'qr-code',
                'href' => $this->canGenerateQr && $servicePointCount > 0
                    ? route('organizations.brands.branches.service-points.index', [$this->organization, $this->brand, $branch])
                    : null,
                'button_label' => __('qr.labels.qr'),
                'is_done' => $qrCount > 0,
                'is_available' => $this->canGenerateQr && $servicePointCount > 0,
            ],
            [
                'number' => 5,
                'label' => __('qr.setup.print.title'),
                'description' => $qrCount > 0
                    ? __('qr.setup.print.description')
                    : __('qr.setup.print.unavailable_description'),
                'icon' => 'printer',
                'href' => $this->canGenerateQr && $qrCount > 0
                    ? route('organizations.brands.branches.qr.print', [$this->organization, $this->brand, $branch])
                    : null,
                'button_label' => __('qr.actions.print'),
                'is_done' => $qrCount > 0,
                'is_available' => $this->canGenerateQr && $qrCount > 0,
            ],
            [
                'number' => 6,
                'label' => __('qr.setup.guest_menu.title'),
                'description' => $firstActiveQrCode instanceof QrCode
                    ? __('qr.setup.guest_menu.description')
                    : __('qr.setup.guest_menu.unavailable_description'),
                'icon' => 'book-open',
                'href' => $firstActiveQrCode instanceof QrCode
                    ? route('public.qr.show', ['token' => $firstActiveQrCode->public_token])
                    : null,
                'button_label' => __('qr.actions.open_guest_url'),
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
        Gate::forUser($this->currentUser())->authorize('create', [Branch::class, $this->organization]);
    }

    private function findBrandBranch(int $branchId): Branch
    {
        $branch = $this->brand
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

        Gate::forUser($this->currentUser())->authorize('view', $branch);

        return $branch;
    }
}
