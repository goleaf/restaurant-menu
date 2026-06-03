<?php

namespace App\Livewire\PublicQr;

use App\Actions\TableSessions\CreateGuestPendingTableSessionAction;
use App\Enums\GuestTableEntryState;
use App\Enums\QrCodeStatus;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use Illuminate\Support\Facades\Cookie;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Guest QR')]
class Show extends Component
{
    public string $token = '';

    public string $state = 'not_found';

    public string $title = '';

    public string $message = '';

    public string $guestName = '';

    public ?string $preparedGuestName = null;

    public string $entryState = '';

    public string $entryMessage = '';

    public ?int $currentTableSessionId = null;

    public ?int $currentGuestId = null;

    public bool $guestCanAddItems = false;

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
        $this->token = $token;

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

        $this->restoreGuestFromCookie($qrCode);
    }

    public function enterTable(CreateGuestPendingTableSessionAction $createGuestPendingTableSession): void
    {
        if ($this->state !== 'ready') {
            return;
        }

        if ($this->currentGuestId !== null) {
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

        $qrCode = $this->findQrCode($this->token);

        if (! $qrCode instanceof QrCode || $qrCode->status !== QrCodeStatus::Active) {
            $this->showError(
                state: 'not_found',
                title: __('QR code not found'),
                message: __('Please ask the staff for a fresh QR code.'),
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

        $result = $createGuestPendingTableSession->handle($servicePoint, $this->preparedGuestName);
        $entryState = $result['state'];
        $tableSession = $result['table_session'];
        $guest = $result['guest'];

        $this->entryState = $entryState->value;
        $this->entryMessage = $this->messageForEntryState($entryState);
        $this->currentTableSessionId = $tableSession instanceof TableSession ? $tableSession->id : null;
        $this->currentGuestId = $guest instanceof TableSessionGuest ? $guest->id : null;
        $this->guestCanAddItems = $guest instanceof TableSessionGuest
            && $tableSession instanceof TableSession
            && $this->canGuestAddItems($guest, $tableSession);

        if ($guest instanceof TableSessionGuest && $tableSession instanceof TableSession) {
            Cookie::queue($this->guestTokenCookieName($qrCode->public_token), $guest->guest_token, 60 * 24 * 30);

            session()->put('guest_entries.'.$qrCode->public_token, [
                'table_session_id' => $tableSession->id,
                'guest_id' => $guest->id,
                'guest_token' => $guest->guest_token,
            ]);
        }
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

    private function restoreGuestFromCookie(QrCode $qrCode): void
    {
        $guestToken = request()->cookie($this->guestTokenCookieName($qrCode->public_token));

        if (! is_string($guestToken) || strlen($guestToken) !== 64) {
            return;
        }

        $servicePoint = $qrCode->servicePoint;

        if (! $servicePoint instanceof ServicePoint) {
            return;
        }

        $guest = $this->findGuestByToken($servicePoint, $guestToken);

        if (! $guest instanceof TableSessionGuest || ! $guest->tableSession instanceof TableSession) {
            return;
        }

        $tableSession = $guest->tableSession;

        $this->guestName = $guest->guest_name;
        $this->preparedGuestName = $guest->guest_name;
        $this->currentGuestId = $guest->id;
        $this->currentTableSessionId = $tableSession->id;
        $this->guestCanAddItems = $this->canGuestAddItems($guest, $tableSession);
        $this->entryState = $this->guestCanAddItems ? 'guest_restored' : 'guest_blocked';
        $this->entryMessage = $this->messageForGuestAccess($guest, $tableSession);
    }

    private function findGuestByToken(ServicePoint $servicePoint, string $guestToken): ?TableSessionGuest
    {
        return TableSessionGuest::query()
            ->select([
                'id',
                'table_session_id',
                'guest_name',
                'guest_token',
                'status',
                'joined_at',
                'left_at',
            ])
            ->with([
                'tableSession' => fn ($query) => $query->select([
                    'id',
                    'branch_id',
                    'service_point_id',
                    'status',
                    'ended_at',
                ]),
            ])
            ->where('guest_token', $guestToken)
            ->whereHas('tableSession', fn ($query) => $query
                ->where('branch_id', $servicePoint->branch_id)
                ->where('service_point_id', $servicePoint->id))
            ->first();
    }

    private function showError(string $state, string $title, string $message): void
    {
        $this->state = $state;
        $this->title = $title;
        $this->message = $message;
    }

    private function guestTokenCookieName(string $publicToken): string
    {
        return 'guest_token_'.substr(hash('sha256', $publicToken), 0, 24);
    }

    private function canGuestAddItems(TableSessionGuest $guest, TableSession $tableSession): bool
    {
        if (in_array($tableSession->status, [TableSessionStatus::Closed, TableSessionStatus::Cancelled], true)) {
            return false;
        }

        return $guest->status === TableSessionGuestStatus::Active;
    }

    private function messageForGuestAccess(TableSessionGuest $guest, TableSession $tableSession): string
    {
        if (in_array($tableSession->status, [TableSessionStatus::Closed, TableSessionStatus::Cancelled], true)) {
            return __('Эта сессия стола уже закрыта. Пожалуйста, обратитесь к официанту.');
        }

        return match ($guest->status) {
            TableSessionGuestStatus::Active => __('Вы уже за этим столом. Ваш вход сохранён.'),
            TableSessionGuestStatus::PendingApproval => __('Ваше присоединение ожидает подтверждения текущими гостями.'),
            TableSessionGuestStatus::Rejected => __('Ваше присоединение к этому столу не подтверждено.'),
            TableSessionGuestStatus::Removed => __('Вы были удалены из этой сессии стола.'),
            TableSessionGuestStatus::Left => __('Вы уже покинули эту сессию стола.'),
        };
    }

    private function messageForEntryState(GuestTableEntryState $state): string
    {
        return match ($state) {
            GuestTableEntryState::PendingSessionCreated => __('Стол ожидает подтверждения официанта. Заказы пока не отправляются на кухню или бар.'),
            GuestTableEntryState::ActiveSessionExists => __('Стол уже открыт. На следующем этапе здесь появится запрос на присоединение.'),
            GuestTableEntryState::PendingSessionExists => __('Стол уже ожидает подтверждения официанта. На следующем этапе здесь появится присоединение к текущей сессии.'),
            GuestTableEntryState::GuestCreatedSessionsDisabled => __('Открытие стола гостем отключено. Пожалуйста, позовите официанта.'),
        };
    }
}
