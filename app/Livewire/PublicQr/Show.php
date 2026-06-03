<?php

namespace App\Livewire\PublicQr;

use App\Actions\TableSessions\CreateGuestInviteLinkAction;
use App\Actions\TableSessions\CreateGuestPendingTableSessionAction;
use App\Actions\TableSessions\CreateTableSessionJoinRequestAction;
use App\Enums\GuestTableEntryState;
use App\Enums\QrCodeStatus;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionJoinRequestStatus;
use App\Enums\TableSessionStatus;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;
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

    public ?int $currentJoinRequestId = null;

    public bool $guestCanAddItems = false;

    public ?string $currentInviteToken = null;

    public string $guestInviteUrl = '';

    public string $guestInviteTitle = '';

    public string $guestInviteText = '';

    public string $guestInviteMessage = '';

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
        $this->currentInviteToken = $this->inviteTokenFromRequest();

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
        $this->message = $this->currentInviteToken === null
            ? __('Enter your name to continue.')
            : __('Введите имя, чтобы попроситься к этому столу.');
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

    public function enterTable(
        CreateGuestPendingTableSessionAction $createGuestPendingTableSession,
        CreateTableSessionJoinRequestAction $createTableSessionJoinRequest,
    ): void {
        if ($this->state !== 'ready') {
            return;
        }

        if ($this->currentGuestId !== null || $this->currentJoinRequestId !== null) {
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

        if ($this->currentInviteToken !== null) {
            $this->enterTableFromInvite(
                servicePoint: $servicePoint,
                qrCode: $qrCode,
                createTableSessionJoinRequest: $createTableSessionJoinRequest,
            );

            return;
        }

        $result = $createGuestPendingTableSession->handle($servicePoint, $this->preparedGuestName);
        $entryState = $result['state'];
        $tableSession = $result['table_session'];
        $guest = $result['guest'];
        $joinRequest = $result['join_request'];

        $this->entryState = $entryState->value;
        $this->entryMessage = $this->messageForEntryState($entryState);
        $this->currentTableSessionId = $tableSession instanceof TableSession ? $tableSession->id : null;
        $this->currentGuestId = $guest instanceof TableSessionGuest ? $guest->id : null;
        $this->currentJoinRequestId = $joinRequest instanceof TableSessionJoinRequest ? $joinRequest->id : null;
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

        if ($joinRequest instanceof TableSessionJoinRequest && $tableSession instanceof TableSession) {
            Cookie::queue($this->guestTokenCookieName($qrCode->public_token), $joinRequest->guest_token, 60 * 24 * 30);

            session()->put('guest_entries.'.$qrCode->public_token, [
                'table_session_id' => $tableSession->id,
                'join_request_id' => $joinRequest->id,
                'guest_token' => $joinRequest->guest_token,
            ]);
        }
    }

    public function createGuestInviteLink(CreateGuestInviteLinkAction $createGuestInviteLink): void
    {
        if ($this->state !== 'ready' || $this->currentTableSessionId === null || $this->currentGuestId === null) {
            return;
        }

        $tableSession = $this->findCurrentTableSessionForInvite();
        $guest = $this->findCurrentActiveGuestForInvite();

        if (! $tableSession instanceof TableSession || ! $guest instanceof TableSessionGuest) {
            $this->guestInviteMessage = __('Только активный гость за этим столом может пригласить нового гостя.');

            return;
        }

        try {
            $tableSession = $createGuestInviteLink->handle($tableSession, $guest);
        } catch (ValidationException $exception) {
            $this->guestInviteMessage = $this->firstValidationMessage($exception);

            return;
        }

        $this->fillGuestInviteShareState($tableSession);
    }

    public function refreshJoinRequestStatus(): void
    {
        if ($this->state !== 'ready' || $this->currentJoinRequestId === null) {
            return;
        }

        $guestToken = $this->guestTokenFromCurrentCookie();
        $joinRequest = $guestToken === null
            ? null
            : $this->findJoinRequestByIdAndToken($this->currentJoinRequestId, $guestToken);

        $joinRequest ??= $this->findJoinRequestByCurrentState($this->currentJoinRequestId);

        if (! $joinRequest instanceof TableSessionJoinRequest || ! $joinRequest->tableSession instanceof TableSession) {
            return;
        }

        $this->entryMessage = $this->messageForJoinRequestAccess($joinRequest);

        if ($joinRequest->status === TableSessionJoinRequestStatus::Approved) {
            $guest = $this->findGuestForJoinRequest($joinRequest);

            if (! $guest instanceof TableSessionGuest || ! $guest->tableSession instanceof TableSession) {
                $this->entryState = 'join_request_blocked';
                $this->guestCanAddItems = false;

                return;
            }

            $tableSession = $guest->tableSession;

            $this->guestName = $guest->guest_name;
            $this->preparedGuestName = $guest->guest_name;
            $this->currentTableSessionId = $tableSession->id;
            $this->currentGuestId = $guest->id;
            $this->currentJoinRequestId = null;
            $this->guestCanAddItems = $this->canGuestAddItems($guest, $tableSession);
            $this->entryState = $this->guestCanAddItems ? 'guest_restored' : 'guest_blocked';
            $this->entryMessage = $this->messageForGuestAccess($guest, $tableSession);

            return;
        }

        if ($joinRequest->status !== TableSessionJoinRequestStatus::Pending || $this->joinRequestIsExpired($joinRequest)) {
            $this->entryState = 'join_request_blocked';
            $this->guestCanAddItems = false;

            return;
        }

        $this->entryState = 'join_request_restored';
        $this->guestCanAddItems = false;
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
            $this->restoreJoinRequestFromToken($servicePoint, $guestToken);

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

        session()->put('guest_entries.'.$qrCode->public_token, [
            'table_session_id' => $tableSession->id,
            'guest_id' => $guest->id,
            'guest_token' => $guest->guest_token,
        ]);
    }

    private function restoreJoinRequestFromToken(ServicePoint $servicePoint, string $guestToken): void
    {
        $joinRequest = $this->findJoinRequestByToken($servicePoint, $guestToken);

        if (! $joinRequest instanceof TableSessionJoinRequest || ! $joinRequest->tableSession instanceof TableSession) {
            return;
        }

        $tableSession = $joinRequest->tableSession;

        $this->guestName = $joinRequest->guest_name;
        $this->preparedGuestName = $joinRequest->guest_name;
        $this->currentTableSessionId = $tableSession->id;
        $this->currentJoinRequestId = $joinRequest->id;
        $this->guestCanAddItems = false;
        $this->entryState = $joinRequest->status === TableSessionJoinRequestStatus::Pending
            ? 'join_request_restored'
            : 'join_request_blocked';
        $this->entryMessage = $this->messageForJoinRequestAccess($joinRequest);

        session()->put('guest_entries.'.$this->token, [
            'table_session_id' => $tableSession->id,
            'join_request_id' => $joinRequest->id,
            'guest_token' => $joinRequest->guest_token,
        ]);
    }

    private function enterTableFromInvite(
        ServicePoint $servicePoint,
        QrCode $qrCode,
        CreateTableSessionJoinRequestAction $createTableSessionJoinRequest,
    ): void {
        if ($this->currentInviteToken === null || $this->preparedGuestName === null) {
            return;
        }

        $tableSession = $this->findTableSessionByInviteToken($servicePoint, $this->currentInviteToken);

        if (! $tableSession instanceof TableSession) {
            $this->entryState = 'guest_invite_invalid';
            $this->entryMessage = __('Ссылка приглашения больше не активна. Пожалуйста, попросите гостей отправить новую ссылку или отсканируйте QR-код на месте.');
            $this->currentTableSessionId = null;
            $this->currentGuestId = null;
            $this->currentJoinRequestId = null;
            $this->guestCanAddItems = false;

            return;
        }

        $this->currentTableSessionId = $tableSession->id;
        $this->currentGuestId = null;
        $this->guestCanAddItems = false;

        if (in_array($tableSession->status, [TableSessionStatus::Closed, TableSessionStatus::Cancelled], true)) {
            $this->entryState = 'guest_invite_closed';
            $this->entryMessage = __('Эта сессия стола уже закрыта. Пожалуйста, попросите гостей отправить новую ссылку.');
            $this->currentJoinRequestId = null;

            return;
        }

        $joinRequest = $createTableSessionJoinRequest->handle($tableSession, $this->preparedGuestName);

        if (! $joinRequest instanceof TableSessionJoinRequest) {
            $this->entryState = 'guest_invite_unavailable';
            $this->entryMessage = __('Сейчас за столом нет активных гостей для подтверждения входа.');
            $this->currentJoinRequestId = null;

            return;
        }

        $this->entryState = GuestTableEntryState::JoinRequestCreated->value;
        $this->entryMessage = $this->messageForEntryState(GuestTableEntryState::JoinRequestCreated);
        $this->currentJoinRequestId = $joinRequest->id;

        Cookie::queue($this->guestTokenCookieName($qrCode->public_token), $joinRequest->guest_token, 60 * 24 * 30);

        session()->put('guest_entries.'.$qrCode->public_token, [
            'table_session_id' => $tableSession->id,
            'join_request_id' => $joinRequest->id,
            'guest_token' => $joinRequest->guest_token,
        ]);
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

    private function findJoinRequestByToken(ServicePoint $servicePoint, string $guestToken): ?TableSessionJoinRequest
    {
        return TableSessionJoinRequest::query()
            ->select([
                'id',
                'table_session_id',
                'guest_name',
                'guest_token',
                'status',
                'approved_by_guest_id',
                'rejected_by_guest_id',
                'approved_by_user_id',
                'rejected_by_user_id',
                'expires_at',
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

    private function findTableSessionByInviteToken(ServicePoint $servicePoint, string $inviteToken): ?TableSession
    {
        return TableSession::query()
            ->select([
                'id',
                'branch_id',
                'service_point_id',
                'opened_by_guest_id',
                'status',
                'source',
                'started_at',
                'ended_at',
                'guest_invite_token',
                'guest_invite_created_at',
                'guest_invite_created_by_guest_id',
            ])
            ->where('branch_id', $servicePoint->branch_id)
            ->where('service_point_id', $servicePoint->id)
            ->where('guest_invite_token', $inviteToken)
            ->first();
    }

    private function findCurrentTableSessionForInvite(): ?TableSession
    {
        if ($this->currentTableSessionId === null) {
            return null;
        }

        return TableSession::query()
            ->select([
                'id',
                'branch_id',
                'service_point_id',
                'opened_by_guest_id',
                'status',
                'source',
                'started_at',
                'ended_at',
                'guest_invite_token',
                'guest_invite_created_at',
                'guest_invite_created_by_guest_id',
            ])
            ->whereKey($this->currentTableSessionId)
            ->first();
    }

    private function findCurrentActiveGuestForInvite(): ?TableSessionGuest
    {
        if ($this->currentGuestId === null || $this->currentTableSessionId === null) {
            return null;
        }

        $guestToken = $this->guestTokenFromCurrentCookie();

        if ($guestToken === null) {
            return null;
        }

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
            ->whereKey($this->currentGuestId)
            ->where('table_session_id', $this->currentTableSessionId)
            ->where('guest_token', $guestToken)
            ->where('status', TableSessionGuestStatus::Active->value)
            ->first();
    }

    private function findJoinRequestByIdAndToken(int $joinRequestId, string $guestToken): ?TableSessionJoinRequest
    {
        return TableSessionJoinRequest::query()
            ->select([
                'id',
                'table_session_id',
                'guest_name',
                'guest_token',
                'status',
                'approved_by_guest_id',
                'rejected_by_guest_id',
                'approved_by_user_id',
                'rejected_by_user_id',
                'expires_at',
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
            ->whereKey($joinRequestId)
            ->where('guest_token', $guestToken)
            ->first();
    }

    private function findJoinRequestByCurrentState(int $joinRequestId): ?TableSessionJoinRequest
    {
        if ($this->currentTableSessionId === null || $this->preparedGuestName === null) {
            return null;
        }

        return TableSessionJoinRequest::query()
            ->select([
                'id',
                'table_session_id',
                'guest_name',
                'guest_token',
                'status',
                'approved_by_guest_id',
                'rejected_by_guest_id',
                'approved_by_user_id',
                'rejected_by_user_id',
                'expires_at',
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
            ->whereKey($joinRequestId)
            ->where('table_session_id', $this->currentTableSessionId)
            ->where('guest_name', $this->preparedGuestName)
            ->first();
    }

    private function findGuestForJoinRequest(TableSessionJoinRequest $joinRequest): ?TableSessionGuest
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
            ->where('table_session_id', $joinRequest->table_session_id)
            ->where('guest_token', $joinRequest->guest_token)
            ->first();
    }

    private function showError(string $state, string $title, string $message): void
    {
        $this->state = $state;
        $this->title = $title;
        $this->message = $message;
    }

    private function inviteTokenFromRequest(): ?string
    {
        $inviteToken = request()->query('invite');

        if (! is_string($inviteToken) || strlen($inviteToken) !== 64 || ! ctype_alnum($inviteToken)) {
            return null;
        }

        return $inviteToken;
    }

    private function fillGuestInviteShareState(TableSession $tableSession): void
    {
        if (! is_string($tableSession->guest_invite_token) || strlen($tableSession->guest_invite_token) !== 64) {
            $this->guestInviteMessage = __('Не удалось создать ссылку приглашения.');

            return;
        }

        $this->guestInviteUrl = route('public.qr.show', [
            'token' => $this->token,
            'invite' => $tableSession->guest_invite_token,
        ]);
        $this->guestInviteTitle = __('Приглашение за стол');
        $this->guestInviteText = __('Присоединяйтесь к столу в :venue. Откройте ссылку и введите имя, чтобы текущие гости подтвердили вход.', [
            'venue' => $this->landing['venue_name'] ?: config('app.name', 'Restaurant'),
        ]);
        $this->guestInviteMessage = __('Ссылка приглашения готова.');
    }

    private function guestTokenFromCurrentCookie(): ?string
    {
        $guestToken = request()->cookie($this->guestTokenCookieName($this->token));

        if (is_string($guestToken) && strlen($guestToken) === 64) {
            return $guestToken;
        }

        $guestToken = session('guest_entries.'.$this->token.'.guest_token');

        if (! is_string($guestToken) || strlen($guestToken) !== 64) {
            return null;
        }

        return $guestToken;
    }

    private function guestTokenCookieName(string $publicToken): string
    {
        return 'guest_token_'.substr(hash('sha256', $publicToken), 0, 24);
    }

    private function firstValidationMessage(ValidationException $exception): string
    {
        $messages = collect($exception->errors())->flatten();

        return (string) ($messages->first() ?? __('Не удалось создать ссылку приглашения.'));
    }

    private function canGuestAddItems(TableSessionGuest $guest, TableSession $tableSession): bool
    {
        if (in_array($tableSession->status, [TableSessionStatus::Closed, TableSessionStatus::Cancelled], true)) {
            return false;
        }

        return $guest->status === TableSessionGuestStatus::Active;
    }

    private function joinRequestIsExpired(TableSessionJoinRequest $joinRequest): bool
    {
        return $joinRequest->status === TableSessionJoinRequestStatus::Pending
            && $joinRequest->expires_at !== null
            && $joinRequest->expires_at->isPast();
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

    private function messageForJoinRequestAccess(TableSessionJoinRequest $joinRequest): string
    {
        if ($joinRequest->status === TableSessionJoinRequestStatus::Pending
            && $joinRequest->expires_at !== null
            && $joinRequest->expires_at->isPast()) {
            return __('Ваш запрос на присоединение истёк. Пожалуйста, отправьте новый запрос.');
        }

        return match ($joinRequest->status) {
            TableSessionJoinRequestStatus::Pending => __('Запрос на присоединение отправлен. Текущие гости должны подтвердить вход.'),
            TableSessionJoinRequestStatus::Approved => __('Ваш запрос на присоединение подтверждён.'),
            TableSessionJoinRequestStatus::Rejected => __('Ваш запрос на присоединение отклонён.'),
            TableSessionJoinRequestStatus::Expired => __('Ваш запрос на присоединение истёк. Пожалуйста, отправьте новый запрос.'),
        };
    }

    private function messageForEntryState(GuestTableEntryState $state): string
    {
        return match ($state) {
            GuestTableEntryState::PendingSessionCreated => __('Стол ожидает подтверждения официанта. Заказы пока не отправляются на кухню или бар.'),
            GuestTableEntryState::ActiveSessionExists => __('Стол уже открыт. На следующем этапе здесь появится запрос на присоединение.'),
            GuestTableEntryState::PendingSessionExists => __('Стол уже ожидает подтверждения официанта. На следующем этапе здесь появится присоединение к текущей сессии.'),
            GuestTableEntryState::JoinRequestCreated => __('Запрос на присоединение отправлен. Текущие гости должны подтвердить вход.'),
            GuestTableEntryState::GuestCreatedSessionsDisabled => __('Открытие стола гостем отключено. Пожалуйста, позовите официанта.'),
        };
    }
}
