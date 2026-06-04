<?php

namespace App\Livewire\QrCodes;

use App\Actions\QrCodes\DisableQrCodeAction;
use App\Actions\QrCodes\ReissueQrCodeForServicePointAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\QrCodeStatus;
use App\Enums\SystemPermission;
use App\Models\Branch;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('QR lookup')]
class ShortCodeLookup extends Component
{
    public string $shortCode = '';

    public bool $searched = false;

    public bool $confirmingReissue = false;

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

        unset($this->qrCode);
    }

    public function disableQr(DisableQrCodeAction $disableQrCode): void
    {
        $qrCode = $this->resolvedQrCodeOrAbort();

        $disableQrCode->handle($qrCode);

        $this->confirmingReissue = false;

        unset($this->qrCode);

        Flux::toast(variant: 'success', text: __('QR disabled.'));
    }

    public function confirmReissue(): void
    {
        $this->resolvedQrCodeOrAbort();

        $this->confirmingReissue = true;
    }

    public function cancelReissue(): void
    {
        $this->confirmingReissue = false;
    }

    public function reissueQr(ReissueQrCodeForServicePointAction $reissueQrCode): void
    {
        $qrCode = $this->resolvedQrCodeOrAbort();
        $newQrCode = $reissueQrCode->handle($qrCode, $this->currentUser());

        $this->shortCode = $newQrCode->short_code;
        $this->searched = true;
        $this->confirmingReissue = false;

        unset($this->qrCode);

        Flux::toast(variant: 'success', text: __('QR reissued.'));
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

        return QrCode::query()
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
                    ->withTrashed()
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
                        'deleted_at',
                    ])
                    ->with([
                        'areaNode' => fn ($query) => $query
                            ->withTrashed()
                            ->select([
                                'id',
                                'branch_id',
                                'name',
                                'deleted_at',
                            ]),
                        'branch' => fn ($query) => $query
                            ->withTrashed()
                            ->select([
                                'id',
                                'organization_id',
                                'brand_id',
                                'name',
                                'city',
                                'country',
                                'deleted_at',
                            ])
                            ->with([
                                'organization' => fn ($query) => $query
                                    ->withTrashed()
                                    ->select(['id', 'name', 'deleted_at']),
                                'brand' => fn ($query) => $query
                                    ->withTrashed()
                                    ->select(['id', 'organization_id', 'name', 'deleted_at']),
                            ]),
                    ]),
            ])
            ->where('short_code', $this->shortCode)
            ->whereHas('servicePoint', function ($query) use ($accessibleBranchIds): void {
                $query->whereIn('branch_id', $accessibleBranchIds);
            })
            ->first();
    }

    #[Computed]
    public function publicUrl(): ?string
    {
        if (! $this->qrCode instanceof QrCode) {
            return null;
        }

        return route('public.qr.show', ['token' => $this->qrCode->public_token]);
    }

    #[Computed]
    public function adminUrl(): ?string
    {
        $qrCode = $this->qrCode;

        if (! $qrCode instanceof QrCode || ! $qrCode->servicePoint instanceof ServicePoint) {
            return null;
        }

        $servicePoint = $qrCode->servicePoint;
        $branch = $servicePoint->branch;

        if (! $branch instanceof Branch || ! $branch->brand || ! $branch->organization) {
            return null;
        }

        return route('organizations.brands.branches.service-points.qr.show', [
            'organization' => $branch->organization,
            'brand' => $branch->brand,
            'branch' => $branch,
            'servicePoint' => $servicePoint,
            'qrCode' => $qrCode,
        ]);
    }

    public function statusColor(QrCode $qrCode): string
    {
        return match ($qrCode->status) {
            QrCodeStatus::Active => 'green',
            QrCodeStatus::Disabled => 'amber',
            QrCodeStatus::Revoked => 'red',
        };
    }

    public function render(): View
    {
        return view('livewire.qr-codes.short-code-lookup');
    }

    /**
     * @return Collection<int, int>
     */
    private function accessibleBranchIds(): Collection
    {
        return app(ResolveWaiterAccessibleBranchIdsAction::class)
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

        $qrCode = $this->qrCode;

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
