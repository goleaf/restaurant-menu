<?php

namespace App\Livewire\PublicQr;

use App\Enums\QrCodeStatus;
use App\Models\QrCode;
use App\Models\ServicePoint;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Guest QR')]
class Show extends Component
{
    public string $state = 'not_found';

    public string $title = '';

    public string $message = '';

    /**
     * @var array{organization_name: string, brand_name: string, branch_name: string, branch_city: string, branch_country: string, service_point_name: string, service_point_type: string, area_name: string|null, short_code: string}
     */
    public array $landing = [
        'organization_name' => '',
        'brand_name' => '',
        'branch_name' => '',
        'branch_city' => '',
        'branch_country' => '',
        'service_point_name' => '',
        'service_point_type' => '',
        'area_name' => null,
        'short_code' => '',
    ];

    public function mount(string $token): void
    {
        $qrCode = $this->findQrCode($token);

        if (! $qrCode instanceof QrCode) {
            $this->showError(
                state: 'not_found',
                title: __('QR code not found'),
                message: __('Please ask the staff for a fresh QR code.'),
            );

            return;
        }

        if ($qrCode->status === QrCodeStatus::Disabled) {
            $this->showError(
                state: 'disabled',
                title: __('QR code is temporarily disabled'),
                message: __('Please ask the staff to help you with this place.'),
            );

            return;
        }

        if ($qrCode->status === QrCodeStatus::Revoked) {
            $this->showError(
                state: 'revoked',
                title: __('QR code is no longer active'),
                message: __('This QR code has been replaced. Please ask the staff for the current code.'),
            );

            return;
        }

        $servicePoint = $qrCode->servicePoint;

        if (! $servicePoint instanceof ServicePoint || ! $servicePoint->is_active) {
            $this->showError(
                state: 'inactive_service_point',
                title: __('This place is temporarily unavailable'),
                message: __('Please ask the staff before ordering from this place.'),
            );

            return;
        }

        $branch = $servicePoint->branch;
        $brand = $branch->brand;
        $organization = $branch->organization;

        $this->state = 'ready';
        $this->title = __('Welcome');
        $this->message = __('You are opening the guest page for this place.');
        $this->landing = [
            'organization_name' => $organization->name,
            'brand_name' => $brand->name,
            'branch_name' => $branch->name,
            'branch_city' => $branch->city,
            'branch_country' => $branch->country,
            'service_point_name' => $servicePoint->name,
            'service_point_type' => $servicePoint->type->label(),
            'area_name' => $servicePoint->areaNode?->name,
            'short_code' => $qrCode->short_code,
        ];
    }

    public function render(): View
    {
        return view('livewire.public-qr.show');
    }

    private function findQrCode(string $token): ?QrCode
    {
        return QrCode::query()
            ->select([
                'id',
                'service_point_id',
                'public_token',
                'short_code',
                'status',
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
                        'is_active',
                    ])
                    ->with([
                        'areaNode' => fn ($query) => $query->select([
                            'id',
                            'branch_id',
                            'name',
                        ]),
                        'branch' => fn ($query) => $query
                            ->select([
                                'id',
                                'organization_id',
                                'brand_id',
                                'name',
                                'city',
                                'country',
                            ])
                            ->with([
                                'brand' => fn ($query) => $query->select([
                                    'id',
                                    'organization_id',
                                    'name',
                                ]),
                                'organization' => fn ($query) => $query->select([
                                    'id',
                                    'name',
                                ]),
                            ]),
                    ]),
            ])
            ->where('public_token', $token)
            ->first();
    }

    private function showError(string $state, string $title, string $message): void
    {
        $this->state = $state;
        $this->title = $title;
        $this->message = $message;
    }
}
