<?php

declare(strict_types=1);

namespace App\Livewire\QrCodes;

use App\Actions\QrCodes\DisableQrCodeAction;
use App\Actions\QrCodes\ReissueQrCodeForServicePointAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\QrCodeStatus;
use App\Enums\SystemPermission;
use App\Models\QrCode;
use App\Models\User;
use App\Services\QrCodes\QrCodeQueryService;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ShortCodeLookup extends Component
{
    private ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds;

    private QrCodeQueryService $qrCodeQueries;

    public string $shortCode = '';

    public bool $searched = false;

    public bool $confirmingReissue = false;

    public string $qrDisableReason = '';

    public string $qrReissueConfirmation = '';

    public function boot(
        ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
        QrCodeQueryService $qrCodeQueries,
    ): void {
        $this->resolveAccessibleBranchIds = $resolveAccessibleBranchIds;
        $this->qrCodeQueries = $qrCodeQueries;
    }

    public function mount(): void
    {
        if ($this->accessibleBranchIds()->isEmpty()) {
            abort(403);
        }
    }

    public function search(): void
    {
        $this->shortCode = $this->normalizeShortCode($this->shortCode);

        $this->validate([
            'shortCode' => ['required', 'string', 'max:24'],
        ]);

        $this->searched = true;
        $this->confirmingReissue = false;
        $this->qrDisableReason = '';
        $this->qrReissueConfirmation = '';

        unset($this->qrCode);
    }

    public function disableQr(DisableQrCodeAction $disableQrCode): void
    {
        $qrCode = $this->resolvedQrCodeOrAbort();

        $validated = $this->validate([
            'qrDisableReason' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'qrDisableReason.required' => __('qr.validation.disable_reason_required'),
            'qrDisableReason.min' => __('qr.validation.disable_reason_min'),
        ]);

        $disableQrCode->handle(
            qrCode: $qrCode,
            disabledBy: $this->currentUser(),
            reason: (string) $validated['qrDisableReason'],
        );

        $this->confirmingReissue = false;
        $this->qrDisableReason = '';

        unset($this->qrCode);

        Flux::modals()->close();
        Flux::toast(variant: 'success', text: __('qr.messages.disabled'));
    }

    public function confirmReissue(): void
    {
        $this->resolvedQrCodeOrAbort();

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
        $qrCode = $this->resolvedQrCodeOrAbort();

        $this->validate([
            'qrReissueConfirmation' => ['required', 'string', Rule::in([$qrCode->short_code])],
        ], [
            'qrReissueConfirmation.required' => __('qr.validation.reissue_confirmation_required'),
            'qrReissueConfirmation.in' => __('qr.validation.reissue_confirmation_mismatch'),
        ]);

        $newQrCode = $reissueQrCode->handle($qrCode, $this->currentUser());

        $this->shortCode = $newQrCode->short_code;
        $this->searched = true;
        $this->confirmingReissue = false;
        $this->qrReissueConfirmation = '';

        unset($this->qrCode);

        Flux::modals()->close();
        Flux::toast(variant: 'success', text: __('qr.messages.reissued'));
    }

    #[Computed]
    public function qrCode(): ?QrCode
    {
        if (! $this->searched || $this->shortCode === '') {
            return null;
        }

        $accessibleBranchIds = $this->accessibleBranchIds();

        if ($accessibleBranchIds->isEmpty()) {
            abort(403);
        }

        return $this->qrCodeQueries->findAccessibleByShortCode($this->shortCode, $accessibleBranchIds);
    }

    #[Computed]
    public function publicUrl(): ?string
    {
        $qrCode = $this->qrCode();

        if (! $qrCode instanceof QrCode) {
            return null;
        }

        return route('public.qr.show', ['token' => $qrCode->public_token]);
    }

    #[Computed]
    public function adminUrl(): ?string
    {
        $qrCode = $this->qrCode();

        if (! $qrCode instanceof QrCode) {
            return null;
        }

        $servicePoint = $qrCode->servicePoint;
        $branch = $servicePoint->branch;

        return route('organizations.brands.branches.service-points.qr.show', [
            'organization' => $branch->organization,
            'brand' => $branch->brand,
            'branch' => $branch,
            'servicePoint' => $servicePoint,
            'qrCode' => $qrCode,
        ]);
    }

    public function render(): View
    {
        return view('livewire.qr-codes.short-code-lookup', [
            'qrCode' => $this->qrCodePresentation(),
        ])
            ->title(__('qr.lookup.title'));
    }

    /**
     * @return array{
     *     id: int,
     *     short_code: string,
     *     is_active: bool,
     *     status_color: string,
     *     localized_status: string,
     *     branch_name: string,
     *     location: string,
     *     organization_name: string,
     *     brand_name: string,
     *     zone_name: string,
     *     service_point_name: string,
     *     display_number: string,
     *     public_url: string|null,
     *     admin_url: string|null
     * }|null
     */
    private function qrCodePresentation(): ?array
    {
        $qrCode = $this->qrCode();

        if (! $qrCode instanceof QrCode) {
            return null;
        }

        $servicePoint = $qrCode->servicePoint;
        $branch = $servicePoint->branch;
        $status = $qrCode->status;

        return [
            'id' => $qrCode->id,
            'short_code' => $qrCode->short_code,
            'is_active' => $status === QrCodeStatus::Active,
            'status_color' => match ($status) {
                QrCodeStatus::Active => 'green',
                QrCodeStatus::Disabled => 'amber',
                QrCodeStatus::Revoked => 'red',
            },
            'localized_status' => __($status->label()),
            'branch_name' => $branch->name,
            'location' => collect([$branch->city, $branch->country])->filter()->implode(', ') ?: __('qr.labels.location_not_set'),
            'organization_name' => $branch->organization->name,
            'brand_name' => $branch->brand->name,
            'zone_name' => $servicePoint->area_node_id === null ? __('qr.labels.no_zone') : $servicePoint->areaNode->name,
            'service_point_name' => $servicePoint->name,
            'display_number' => $servicePoint->display_number ?: __('qr.labels.number_not_set'),
            'public_url' => $this->publicUrl(),
            'admin_url' => $this->adminUrl(),
        ];
    }

    /**
     * @return Collection<int, int<1, max>>
     */
    private function accessibleBranchIds(): Collection
    {
        return $this->resolveAccessibleBranchIds
            ->handle($this->currentUser(), SystemPermission::GenerateQr)
            ->map(fn (mixed $branchId): int => (int) $branchId)
            ->filter(fn (int $branchId): bool => $branchId > 0)
            ->unique()
            ->values();
    }

    private function normalizeShortCode(string $shortCode): string
    {
        return Str::of($shortCode)
            ->trim()
            ->replaceMatches('/\s+/', '')
            ->upper()
            ->toString();
    }

    private function resolvedQrCodeOrAbort(): QrCode
    {
        $this->shortCode = $this->normalizeShortCode($this->shortCode);
        $this->searched = true;

        unset($this->qrCode);

        $qrCode = $this->qrCode();

        if (! $qrCode instanceof QrCode) {
            abort(404);
        }

        return $qrCode;
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
