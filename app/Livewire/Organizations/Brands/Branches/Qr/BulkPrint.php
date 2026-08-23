<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Qr;

use App\Actions\QrCodes\GenerateQrCodeForServicePointAction;
use App\Enums\QrCodeStatus;
use App\Enums\QrLabelPreset;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\QrCodeSvgRenderer;
use App\Services\QrPrintBrandingResolver;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.print')]
class BulkPrint extends Component
{
    private QrCodeSvgRenderer $qrCodeSvgRenderer;

    private QrPrintBrandingResolver $brandingResolver;

    public Organization $organization;

    public Brand $brand;

    public Branch $branch;

    #[Url(as: 'area', except: 'all')]
    public string $areaNodeId = 'all';

    #[Url(as: 'print_table_number', except: false)]
    public bool $printTableNumber = false;

    #[Url(as: 'preset', except: 'minimal')]
    public string $preset = 'minimal';

    /**
     * @var list<int>
     */
    public array $selectedServicePointIds = [];

    public function boot(
        QrCodeSvgRenderer $qrCodeSvgRenderer,
        QrPrintBrandingResolver $brandingResolver,
    ): void {
        $this->qrCodeSvgRenderer = $qrCodeSvgRenderer;
        $this->brandingResolver = $brandingResolver;
    }

    public function mount(Organization $organization, Brand $brand, Branch $branch): void
    {
        $this->organization = $organization;
        $this->brand = $brand;
        $this->branch = $branch;

        $this->authorizeRouteContext();
        $this->authorizeQrManagement();
        $this->preset = $this->normalizedPresetValue($this->preset);
        $this->reloadBranchContext();
    }

    public function updatedPreset(): void
    {
        $this->preset = $this->normalizedPresetValue($this->preset);
    }

    public function updatedAreaNodeId(): void
    {
        $this->selectedServicePointIds = [];

        unset($this->servicePoints, $this->printItems, $this->visibleMissingQrCount);
    }

    public function updatedSelectedServicePointIds(): void
    {
        $this->selectedServicePointIds = $this->normalizedSelectedServicePointIds();
    }

    public function selectAllVisible(): void
    {
        $this->selectedServicePointIds = $this->servicePoints()
            ->filter(fn (ServicePoint $servicePoint): bool => $servicePoint->activeQrCode instanceof QrCode)
            ->pluck('id')
            ->map(fn (int $servicePointId): int => $servicePointId)
            ->values()
            ->all();
    }

    public function clearSelection(): void
    {
        $this->selectedServicePointIds = [];
    }

    public function createQrForServicePoint(
        int $servicePointId,
        GenerateQrCodeForServicePointAction $generateQrCode,
    ): void {
        $this->authorizeQrManagement();

        $servicePoint = $this->findBranchServicePoint($servicePointId);
        $qrCode = $generateQrCode->handle($servicePoint, $this->currentUser());

        $this->selectedServicePointIds = array_values(array_unique([
            ...$this->normalizedSelectedServicePointIds(),
            $servicePoint->id,
        ]));

        unset($this->servicePoints, $this->printItems, $this->visibleMissingQrCount);

        Flux::toast(
            variant: 'success',
            text: $qrCode->wasRecentlyCreated
                ? __('qr.messages.created')
                : __('qr.messages.active_exists'),
        );
    }

    public function createMissingQrForVisible(
        GenerateQrCodeForServicePointAction $generateQrCode,
    ): void {
        $this->authorizeQrManagement();

        $selectedIds = $this->normalizedSelectedServicePointIds();

        foreach ($this->servicePoints() as $servicePoint) {
            if ($servicePoint->activeQrCode instanceof QrCode) {
                continue;
            }

            $generateQrCode->handle($servicePoint, $this->currentUser());
            $selectedIds[] = $servicePoint->id;
        }

        $this->selectedServicePointIds = array_values(array_unique($selectedIds));

        unset($this->servicePoints, $this->printItems, $this->visibleMissingQrCount);

        Flux::toast(variant: 'success', text: __('qr.messages.missing_created'));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    #[Computed]
    public function areaOptions(): array
    {
        return array_merge(
            [['value' => 'all', 'label' => __('qr.filters.all_areas')]],
            [['value' => 'none', 'label' => __('qr.filters.no_zone')]],
            $this->flattenAreaOptions($this->buildAreaTree($this->areaNodes())),
        );
    }

    /**
     * @return EloquentCollection<int, AreaNode>
     */
    #[Computed]
    public function areaNodes(): EloquentCollection
    {
        return $this->branch
            ->areaNodes()
            ->select([
                'id',
                'branch_id',
                'parent_id',
                'type',
                'name',
                'icon',
                'sort_order',
                'is_active',
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return EloquentCollection<int, ServicePoint>
     */
    #[Computed]
    public function servicePoints(): EloquentCollection
    {
        return $this->branch
            ->servicePoints()
            ->select([
                'id',
                'branch_id',
                'area_node_id',
                'type',
                'name',
                'display_number',
                'internal_code',
                'capacity',
                'icon',
                'status',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->when(
                $this->areaNodeId !== 'all' && $this->areaNodeId !== 'none',
                fn ($query) => $query->where('area_node_id', (int) $this->areaNodeId),
            )
            ->when(
                $this->areaNodeId === 'none',
                fn ($query) => $query->whereNull('area_node_id'),
            )
            ->with([
                'areaNode' => fn ($query) => $query->select([
                    'id',
                    'branch_id',
                    'parent_id',
                    'type',
                    'name',
                    'icon',
                    'sort_order',
                    'is_active',
                ]),
                'activeQrCode' => fn ($query) => $query->select([
                    'id',
                    'service_point_id',
                    'public_token',
                    'short_code',
                    'status',
                    'created_at',
                ])->where('status', QrCodeStatus::Active->value),
            ])
            ->orderBy('area_node_id')
            ->orderBy('display_number')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<array{service_point_id: int, brand_name: string, service_point_label: string, short_code: string, qr_image_data_uri: string}>
     */
    #[Computed]
    public function printItems(): array
    {
        $selectedIds = $this->normalizedSelectedServicePointIds();
        $qrRenderer = $this->qrCodeSvgRenderer;

        return $this->servicePoints()
            ->filter(fn (ServicePoint $servicePoint): bool => in_array($servicePoint->id, $selectedIds, true))
            ->filter(fn (ServicePoint $servicePoint): bool => $servicePoint->activeQrCode instanceof QrCode)
            ->map(function (ServicePoint $servicePoint) use ($qrRenderer): array {
                $qrCode = $servicePoint->activeQrCode;
                $publicUrl = route('public.qr.show', ['token' => $qrCode->public_token]);
                $svg = $qrRenderer->render($publicUrl, 420);

                return [
                    'service_point_id' => $servicePoint->id,
                    'brand_name' => $this->brand->name,
                    'service_point_label' => $servicePoint->display_number ?: $servicePoint->name,
                    'short_code' => $qrCode->short_code,
                    'qr_image_data_uri' => 'data:image/svg+xml;base64,'.base64_encode($svg),
                ];
            })
            ->values()
            ->all();
    }

    #[Computed]
    public function visibleMissingQrCount(): int
    {
        return $this->servicePoints()
            ->filter(fn (ServicePoint $servicePoint): bool => ! ($servicePoint->activeQrCode instanceof QrCode))
            ->count();
    }

    #[Computed]
    public function restaurantLogoUrl(): ?string
    {
        return $this->brandingResolver->localLogoUrlFor([
            $this->branch,
            $this->brand,
            $this->organization,
        ]);
    }

    #[Computed]
    public function selectedPreset(): QrLabelPreset
    {
        return QrLabelPreset::fromValue($this->preset);
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    #[Computed]
    public function presetOptions(): array
    {
        return QrLabelPreset::options();
    }

    public function render(): View
    {
        $printItems = $this->printItems();
        $selectedPreset = $this->selectedPreset();

        return view('livewire.organizations.brands.branches.qr.bulk-print', [
            'contextLabel' => $this->organization->name.' / '.$this->brand->name.' / '.$this->branch->name,
            'branchesUrl' => route('organizations.brands.branches.index', [
                $this->organization,
                $this->brand,
            ]),
            'pdfDownloadUrl' => route('organizations.brands.branches.qr.pdf', [
                $this->organization,
                $this->brand,
                $this->branch,
            ]),
            'areaOptions' => $this->areaOptions(),
            'presetOptions' => $this->presetOptions(),
            'visibleMissingQrCount' => $this->visibleMissingQrCount(),
            'printItems' => $printItems,
            'servicePointRows' => $this->servicePoints()
                ->map(fn (ServicePoint $servicePoint): array => $this->presentServicePoint($servicePoint))
                ->all(),
            'selectedPresetCssClass' => $selectedPreset->cssClass(),
            'selectedPresetValue' => $selectedPreset->value,
            'restaurantLogoUrl' => $this->restaurantLogoUrl(),
        ])
            ->title(__('qr.print.bulk_title'));
    }

    private function authorizeRouteContext(): void
    {
        if (
            $this->brand->organization_id !== $this->organization->id
            || $this->branch->organization_id !== $this->organization->id
            || $this->branch->brand_id !== $this->brand->id
        ) {
            abort(403);
        }
    }

    private function authorizeQrManagement(): void
    {
        Gate::forUser($this->currentUser())->authorize('viewAny', [QrCode::class, $this->branch]);
    }

    private function reloadBranchContext(): void
    {
        $branding = $this->brandingResolver;

        $this->organization = Organization::query()
            ->select($branding->columnsWithOptionalLogo(new Organization, ['id', 'owner_user_id', 'name']))
            ->whereKey($this->organization->id)
            ->firstOrFail();

        $this->brand = Brand::query()
            ->select($branding->columnsWithOptionalLogo(new Brand, ['id', 'organization_id', 'name']))
            ->whereKey($this->brand->id)
            ->where('organization_id', $this->organization->id)
            ->firstOrFail();

        $this->branch = Branch::query()
            ->select($branding->columnsWithOptionalLogo(new Branch, [
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
            ]))
            ->whereKey($this->branch->id)
            ->where('organization_id', $this->organization->id)
            ->where('brand_id', $this->brand->id)
            ->firstOrFail();
    }

    private function findBranchServicePoint(int $servicePointId): ServicePoint
    {
        return $this->branch
            ->servicePoints()
            ->select([
                'id',
                'branch_id',
                'area_node_id',
                'type',
                'name',
                'display_number',
                'internal_code',
                'capacity',
                'icon',
                'status',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->whereKey($servicePointId)
            ->firstOrFail();
    }

    /**
     * @return list<int>
     */
    private function normalizedSelectedServicePointIds(): array
    {
        return collect($this->selectedServicePointIds)
            ->map(fn (mixed $servicePointId): int => (int) $servicePointId)
            ->unique()
            ->values()
            ->all();
    }

    private function normalizedPresetValue(string $preset): string
    {
        return QrLabelPreset::fromValue($preset)->value;
    }

    /**
     * @return array{id: int, name: string, area_name: string, display_number: string, has_qr: bool, qr_short_code: string|null}
     */
    private function presentServicePoint(ServicePoint $servicePoint): array
    {
        $activeQrCode = $servicePoint->activeQrCode;

        return [
            'id' => $servicePoint->id,
            'name' => $servicePoint->name,
            'area_name' => $servicePoint->area_node_id === null
                ? __('qr.labels.no_zone')
                : $servicePoint->areaNode->name,
            'display_number' => $servicePoint->display_number ?: __('qr.labels.not_set'),
            'has_qr' => $activeQrCode instanceof QrCode,
            'qr_short_code' => $activeQrCode?->short_code,
        ];
    }

    /**
     * @param  EloquentCollection<int, AreaNode>  $nodes
     * @return list<array{id: int, name: string, depth: int, children: list<array>}>
     */
    private function buildAreaTree(EloquentCollection $nodes, ?int $parentId = null, int $depth = 0): array
    {
        return $nodes
            ->where('parent_id', $parentId)
            ->values()
            ->map(fn (AreaNode $node): array => [
                'id' => $node->id,
                'name' => $node->name,
                'depth' => $depth,
                'children' => $this->buildAreaTree($nodes, $node->id, $depth + 1),
            ])
            ->all();
    }

    /**
     * @param  list<array{id: int, name: string, depth: int, children: list<array>}>  $nodes
     * @return list<array{value: string, label: string}>
     */
    private function flattenAreaOptions(array $nodes): array
    {
        $options = [];

        foreach ($nodes as $node) {
            $options[] = [
                'value' => (string) $node['id'],
                'label' => str_repeat('- ', $node['depth']).$node['name'],
            ];

            $options = array_merge($options, $this->flattenAreaOptions($node['children']));
        }

        return $options;
    }

    private function currentUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}
