<?php

namespace App\Livewire\Organizations\Brands\Branches\ServicePoints\Qr;

use App\Enums\SystemPermission;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\QrCodeSvgRenderer;
use App\Services\QrPrintBrandingResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.print')]
#[Title('Print QR sticker')]
class PrintTemplate extends Component
{
    public Organization $organization;

    public Brand $brand;

    public Branch $branch;

    public ServicePoint $servicePoint;

    public QrCode $qrCode;

    #[Url(as: 'print_table_number', except: false)]
    public bool $printTableNumber = false;

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
        $this->reloadPrintContext();
    }

    #[Computed]
    public function publicUrl(): string
    {
        return route('public.qr.show', ['token' => $this->qrCode->public_token]);
    }

    #[Computed]
    public function qrImageDataUri(): string
    {
        $svg = app(QrCodeSvgRenderer::class)->render($this->publicUrl, 420);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
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
    public function tableLabel(): string
    {
        return $this->servicePoint->display_number ?: $this->servicePoint->name;
    }

    public function render(): View
    {
        return view('livewire.organizations.brands.branches.service-points.qr.print-template');
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
        $user = $this->currentUser();

        if (
            ! $user->canAccessBranch($this->branch, $this->organization)
            || ! $user->hasPermission(SystemPermission::GenerateQr, $this->organization)
        ) {
            abort(403);
        }
    }

    private function reloadPrintContext(): void
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

    private function currentUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}
