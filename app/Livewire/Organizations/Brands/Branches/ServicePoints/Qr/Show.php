<?php

namespace App\Livewire\Organizations\Brands\Branches\ServicePoints\Qr;

use App\Actions\QrCodes\DisableQrCodeAction;
use App\Actions\QrCodes\ReissueQrCodeForServicePointAction;
use App\Enums\DangerousAction;
use App\Enums\QrCodeStatus;
use App\Enums\SystemPermission;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\QrCodeSvgRenderer;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Show extends Component
{
    public Organization $organization;

    public Brand $brand;

    public Branch $branch;

    public ServicePoint $servicePoint;

    public QrCode $qrCode;

    public bool $confirmingReissue = false;

    public string $qrDisableReason = '';

    public string $qrReissueConfirmation = '';

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
        $this->reloadQrCode();
    }

    public function disableQr(DisableQrCodeAction $disableQrCode): void
    {
        $this->authorizeQrManagement();

        $validated = $this->validate([
            'qrDisableReason' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'qrDisableReason.required' => __('qr.validation.disable_reason_required'),
            'qrDisableReason.min' => __('qr.validation.disable_reason_min'),
        ]);

        $disableQrCode->handle(
            qrCode: $this->qrCode,
            disabledBy: $this->currentUser(),
            reason: (string) $validated['qrDisableReason'],
        );

        $this->confirmingReissue = false;
        $this->qrDisableReason = '';
        $this->reloadQrCode();

        Flux::modals()->close();
        Flux::toast(variant: 'success', text: __('qr.messages.disabled'));
    }

    public function confirmReissue(): void
    {
        $this->authorizeQrManagement();

        $this->confirmingReissue = true;
        $this->qrReissueConfirmation = '';
    }

    public function cancelReissue(): void
    {
        $this->confirmingReissue = false;
        $this->qrReissueConfirmation = '';
    }

    public function reissueQr(ReissueQrCodeForServicePointAction $reissueQrCode): void
    {
        $this->authorizeQrManagement();

        $this->validate([
            'qrReissueConfirmation' => ['required', 'string', Rule::in([$this->qrCode->short_code])],
        ], [
            'qrReissueConfirmation.required' => __('qr.validation.reissue_confirmation_required'),
            'qrReissueConfirmation.in' => __('qr.validation.reissue_confirmation_mismatch'),
        ]);

        $newQrCode = $reissueQrCode->handle($this->qrCode, $this->currentUser());

        $this->qrReissueConfirmation = '';

        $this->redirectRoute(
            'organizations.brands.branches.service-points.qr.show',
            [
                'organization' => $this->organization,
                'brand' => $this->brand,
                'branch' => $this->branch,
                'servicePoint' => $this->servicePoint,
                'qrCode' => $newQrCode,
            ],
            navigate: true,
        );
    }

    public function downloadQrImage(QrCodeSvgRenderer $qrCodeSvgRenderer): StreamedResponse
    {
        $this->authorizeQrManagement();

        $svg = $qrCodeSvgRenderer->render($this->publicUrl);
        $filename = strtolower($this->qrCode->short_code).'.svg';

        return response()->streamDownload(
            function () use ($svg): void {
                echo $svg;
            },
            $filename,
            ['Content-Type' => 'image/svg+xml'],
        );
    }

    #[Computed]
    public function publicUrl(): string
    {
        return route('public.qr.show', ['token' => $this->qrCode->public_token]);
    }

    #[Computed]
    public function qrImageDataUri(): string
    {
        $svg = app(QrCodeSvgRenderer::class)->render($this->publicUrl);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    #[Computed]
    public function statusColor(): string
    {
        return match ($this->qrCode->status) {
            QrCodeStatus::Active => 'green',
            QrCodeStatus::Disabled => 'amber',
            QrCodeStatus::Revoked => 'red',
        };
    }

    public function render(): View
    {
        return view('livewire.organizations.brands.branches.service-points.qr.show')
            ->title(__('qr.labels.title'));
    }

    public function dangerousAction(string $action): DangerousAction
    {
        return DangerousAction::from($action);
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

    private function reloadQrCode(): void
    {
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
            ->with([
                'servicePoint' => fn ($query) => $query
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
                    ->with([
                        'areaNode' => fn ($query) => $query->select([
                            'id',
                            'branch_id',
                            'name',
                        ]),
                    ]),
            ])
            ->whereKey($this->qrCode->id)
            ->where('service_point_id', $this->servicePoint->id)
            ->firstOrFail();

        $this->servicePoint = $this->qrCode->servicePoint;
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
