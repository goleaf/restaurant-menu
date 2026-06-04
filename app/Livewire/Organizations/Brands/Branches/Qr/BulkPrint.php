<?php

namespace App\Livewire\Organizations\Brands\Branches\Qr;

use App\Actions\QrCodes\GenerateQrCodeForServicePointAction;
use App\Enums\QrCodeStatus;
use App\Enums\QrLabelPreset;
use App\Enums\SystemPermission;
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
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.print')]
#[Title('Bulk QR print')]
class BulkPrint extends Component
{
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
        $this->selectedServicePointIds = $this->servicePoints
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
                ? __('QR created.')
                : __('Active QR already exists.'),
        );
    }

    public function createMissingQrForVisible(
        GenerateQrCodeForServicePointAction $generateQrCode,
    ): void {
        $this->authorizeQrManagement();

        $selectedIds = $this->normalizedSelectedServicePointIds();

        foreach ($this->servicePoints as $servicePoint) {
            if ($servicePoint->activeQrCode instanceof QrCode) {
                continue;
            }

            $generateQrCode->handle($servicePoint, $this->currentUser());
            $selectedIds[] = $servicePoint->id;
        }

        $this->selectedServicePointIds = array_values(array_unique($selectedIds));

        unset($this->servicePoints, $this->printItems, $this->visibleMissingQrCount);

        Flux::toast(variant: 'success', text: __('Missing QR codes created.'));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    #[Computed]
    public function areaOptions(): array
    {
        return array_merge(
            [['value' => 'all', 'label' => __('All areas')]],
            [['value' => 'none', 'label' => __('No zone')]],
            $this->flattenAreaOptions($this->buildAreaTree($this->areaNodes)),
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
        $qrRenderer = app(QrCodeSvgRenderer::class);

        return $this->servicePoints
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
        return $this->servicePoints
            ->filter(fn (ServicePoint $servicePoint): bool => ! ($servicePoint->activeQrCode instanceof QrCode))
            ->count();
    }

    #[Computed]
    public function restaurantLogoUrl(): ?string
    {
        return app(QrPrintBrandingResolver::class)->localLogoUrlFor([
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
        return view('livewire.organizations.brands.branches.qr.bulk-print');
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
        $user = $this->currentUser();

        if (
            ! $user->canAccessBranch($this->branch, $this->organization)
            || ! $user->hasPermission(SystemPermission::GenerateQr, $this->organization)
        ) {
            abort(403);
        }
    }

    private function reloadBranchContext(): void
    {
        $branding = app(QrPrintBrandingResolver::class);

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
