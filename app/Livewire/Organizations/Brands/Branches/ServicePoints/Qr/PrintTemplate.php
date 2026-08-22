<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\ServicePoints\Qr;

use App\Enums\QrLabelPreset;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\QrCodeSvgRenderer;
use App\Services\QrPrintBrandingResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.print')]
class PrintTemplate extends Component
{
    private QrCodeSvgRenderer $qrCodeSvgRenderer;

    private QrPrintBrandingResolver $brandingResolver;

    public Organization $organization;

    public Brand $brand;

    public Branch $branch;

    public ServicePoint $servicePoint;

    public QrCode $qrCode;

    #[Url(as: 'print_table_number', except: false)]
    public bool $printTableNumber = false;

    #[Url(as: 'preset', except: 'minimal')]
    public string $preset = 'minimal';

    public function boot(
        QrCodeSvgRenderer $qrCodeSvgRenderer,
        QrPrintBrandingResolver $brandingResolver,
    ): void {
        $this->qrCodeSvgRenderer = $qrCodeSvgRenderer;
        $this->brandingResolver = $brandingResolver;
    }

    public function mount(
        Organization $organization,
        Brand $brand,
        Branch $branch,
        ServicePoint $servicePoint,
        QrCode $qrCode,
    ): void {
        $this->organization = $organization;
        $this->brand = $brand;
        $this->branch = $branch;
        $this->servicePoint = $servicePoint;
        $this->qrCode = $qrCode;

        $this->authorizeRouteContext();
        $this->authorizeQrManagement();
        $this->preset = $this->normalizedPresetValue($this->preset);
        $this->reloadPrintContext();
    }

    public function updatedPreset(): void
    {
        $this->preset = $this->normalizedPresetValue($this->preset);
    }

    #[Computed]
    public function publicUrl(): string
    {
        return route('public.qr.show', ['token' => $this->qrCode->public_token]);
    }

    #[Computed]
    public function qrImageDataUri(): string
    {
        $svg = $this->qrCodeSvgRenderer->render($this->publicUrl(), 420);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
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
    public function tableLabel(): string
    {
        return $this->servicePoint->display_number ?: $this->servicePoint->name;
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
        $selectedPreset = $this->selectedPreset();

        return view('livewire.organizations.brands.branches.service-points.qr.print-template', [
            'contextLabel' => $this->organization->name.' / '.$this->brand->name.' / '.$this->branch->name,
            'qrPageUrl' => route('organizations.brands.branches.service-points.qr.show', [
                $this->organization,
                $this->brand,
                $this->branch,
                $this->servicePoint,
                $this->qrCode,
            ]),
            'presetOptions' => $this->presetOptions(),
            'selectedPresetCssClass' => $selectedPreset->cssClass(),
            'selectedPresetValue' => $selectedPreset->value,
            'restaurantLogoUrl' => $this->restaurantLogoUrl(),
            'brandName' => $this->brand->name,
            'qrImageDataUri' => $this->qrImageDataUri(),
            'qrShortCode' => $this->qrCode->short_code,
            'tableLabel' => $this->tableLabel(),
        ])
            ->title(__('qr.print.single_title'));
    }

    private function authorizeRouteContext(): void
    {
        if (
            $this->brand->organization_id !== $this->organization->id
            || $this->branch->organization_id !== $this->organization->id
            || $this->branch->brand_id !== $this->brand->id
            || $this->servicePoint->branch_id !== $this->branch->id
            || $this->qrCode->service_point_id !== $this->servicePoint->id
        ) {
            abort(403);
        }
    }

    private function authorizeQrManagement(): void
    {
        Gate::forUser($this->currentUser())->authorize('generateQr', $this->branch);
    }

    private function reloadPrintContext(): void
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

        $this->servicePoint = ServicePoint::query()
            ->select([
                'id',
                'branch_id',
                'area_node_id',
                'type',
                'name',
                'display_number',
                'capacity',
                'icon',
                'status',
                'is_active',
            ])
            ->whereKey($this->servicePoint->id)
            ->where('branch_id', $this->branch->id)
            ->firstOrFail();

        $this->qrCode = QrCode::query()
            ->select([
                'id',
                'service_point_id',
                'public_token',
                'short_code',
                'status',
                'created_by_user_id',
                'revoked_at',
                'revoked_by_user_id',
                'created_at',
                'updated_at',
            ])
            ->whereKey($this->qrCode->id)
            ->where('service_point_id', $this->servicePoint->id)
            ->firstOrFail();
    }

    private function normalizedPresetValue(string $preset): string
    {
        return QrLabelPreset::fromValue($preset)->value;
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
