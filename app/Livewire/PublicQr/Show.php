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

    public string $guestName = '';

    public ?string $preparedGuestName = null;

    /**
     * @var array{organization_name: string, brand_name: string, brand_initial: string, branch_name: string, branch_city: string, branch_country: string, venue_name: string, logo_url: string|null, service_point_name: string, service_point_display_number: string|null, service_point_type: string, area_name: string|null, short_code: string}
     */
    public array $landing = [
        'organization_name' => '',
        'brand_name' => '',
        'brand_initial' => '',
        'branch_name' => '',
        'branch_city' => '',
        'branch_country' => '',
        'venue_name' => '',
        'logo_url' => null,
        'service_point_name' => '',
        'service_point_display_number' => null,
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
        $this->title = $branch->name;
        $this->message = __('Enter your name to continue.');
        $this->landing = [
            'organization_name' => $organization->name,
            'brand_name' => $brand->name,
            'brand_initial' => str($brand->name)->substr(0, 1)->upper()->toString(),
            'branch_name' => $branch->name,
            'branch_city' => $branch->city,
            'branch_country' => $branch->country,
            'venue_name' => $branch->name,
            'logo_url' => $branch->logoUrl() ?? $brand->logoUrl() ?? $organization->logoUrl(),
            'service_point_name' => $servicePoint->name,
            'service_point_display_number' => $servicePoint->display_number,
            'service_point_type' => $servicePoint->type->label(),
            'area_name' => $servicePoint->areaNode?->name,
            'short_code' => $qrCode->short_code,
        ];
    }

    public function enterTable(): void
    {
        if ($this->state !== 'ready') {
            return;
        }

        $validated = $this->validate([
            'guestName' => ['required', 'string', 'min:2', 'max:80'],
        ], [
            'guestName.required' => __('Введите имя, чтобы войти за стол.'),
            'guestName.min' => __('Имя должно содержать минимум 2 символа.'),
            'guestName.max' => __('Имя должно быть не длиннее 80 символов.'),
        ]);

        $this->preparedGuestName = str($validated['guestName'])->squish()->toString();
        $this->guestName = $this->preparedGuestName;
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
                        'display_number',
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
                                'logo_path',
                                'city',
                                'country',
                            ])
                            ->with([
                                'brand' => fn ($query) => $query->select([
                                    'id',
                                    'organization_id',
                                    'name',
                                    'logo_path',
                                ]),
                                'organization' => fn ($query) => $query->select([
                                    'id',
                                    'name',
                                    'logo_path',
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
