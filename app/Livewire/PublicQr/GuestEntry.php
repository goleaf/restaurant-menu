<?php

declare(strict_types=1);

namespace App\Livewire\PublicQr;

use App\Actions\PublicQr\BuildGuestEntryContextAction;
use App\Actions\TableSessions\CreateGuestPendingTableSessionAction;
use App\Actions\TableSessions\CreateTableSessionJoinRequestAction;
use App\Actions\TableSessions\ExpireTableSessionJoinRequestAction;
use App\Enums\GuestTableEntryState;
use App\Enums\QrCodeStatus;
use App\Enums\SupportedLocale;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionJoinRequestStatus;
use App\Enums\TableSessionStatus;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;
use App\Models\TableSessionServicePoint;
use App\Support\PublicQr\GuestEntryPresenter;
use App\Support\Validation\RestaurantValidationRules;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class GuestEntry extends Component
{
    private BuildGuestEntryContextAction $buildGuestEntryContext;

    private ExpireTableSessionJoinRequestAction $expireJoinRequest;

    private GuestEntryPresenter $presenter;

    #[Locked]
    public string $token = '';

    public string $state = 'not_found';

    public string $title = '';

    public string $message = '';

    public string $guestName = '';

    public ?string $preparedGuestName = null;

    public bool $hasGuestNameConflict = false;

    public ?string $guestNameConflictExistingName = null;

    /**
     * @var list<string>
     */
    public array $guestNameSuggestions = [];

    public bool $allowDuplicateGuestName = false;

    public string $entryState = '';

    public string $entryMessage = '';

    public string $entryIssueCode = '';

    #[Locked]
    public ?int $currentTableSessionId = null;

    #[Locked]
    public ?int $currentGuestId = null;

    #[Locked]
    public ?int $currentJoinRequestId = null;

    public bool $guestCanAddItems = false;

    public bool $guestCanViewTable = false;

    #[Locked]
    public bool $hasCurrentInviteToken = false;

    public string $language = '';

    /** @var array<string, mixed> */
    #[Locked]
    public array $landing = [];

    public function boot(
        BuildGuestEntryContextAction $buildGuestEntryContext,
        ExpireTableSessionJoinRequestAction $expireJoinRequest,
        GuestEntryPresenter $presenter,
    ): void {
        $this->buildGuestEntryContext = $buildGuestEntryContext;
        $this->expireJoinRequest = $expireJoinRequest;
        $this->presenter = $presenter;
    }

    public function mount(string $token, string $language = ''): void
    {
        $this->token = $token;
        $this->setCurrentInviteToken($this->inviteTokenFromRequest());
        $requestedLanguage = request()->query('lang');
        $hasQueryLanguage = is_string($requestedLanguage) && SupportedLocale::isSupported($requestedLanguage);
        $hasComponentLanguage = SupportedLocale::isSupported($language);
        $hasRequestedLanguage = $hasQueryLanguage || $hasComponentLanguage;
        $this->language = $hasQueryLanguage
            ? SupportedLocale::normalize($requestedLanguage)
            : SupportedLocale::normalize($language, App::currentLocale());
        $this->applyGuestLocale();

        $context = $this->buildGuestEntryContext->handle(
            $token,
            $this->language,
            $hasRequestedLanguage,
            $this->hasCurrentInviteToken,
        );

        $this->state = $context['state'];
        $this->title = $context['title'];
        $this->message = $context['message'];
        $this->language = $context['language'];

        if (is_array($context['landing'])) {
            $this->landing = $context['landing'];
        }

        $this->applyGuestLocale();

        if ($context['qr_code'] instanceof QrCode) {
            $this->restoreGuestFromCookie($context['qr_code']);
        }
    }

    public function updatedGuestName(): void
    {
        $this->clearGuestNameConflict();
    }

    public function chooseGuestNameSuggestion(int $suggestionIndex): void
    {
        if (! array_key_exists($suggestionIndex, $this->guestNameSuggestions)) {
            return;
        }

        $suggestion = $this->guestNameSuggestions[$suggestionIndex];

        $this->guestName = $suggestion;
        $this->preparedGuestName = $suggestion;
        $this->clearGuestNameConflict();
    }

    public function continueWithDuplicateGuestName(
        CreateGuestPendingTableSessionAction $createGuestPendingTableSession,
        CreateTableSessionJoinRequestAction $createTableSessionJoinRequest,
    ): void {
        if (! $this->hasGuestNameConflict) {
            return;
        }

        $this->allowDuplicateGuestName = true;

        $this->enterTable($createGuestPendingTableSession, $createTableSessionJoinRequest);
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

        $validated = $this->validate(RestaurantValidationRules::guestName('guestName'), [
            'guestName.required' => __('guest.table.enter_name_validation'),
            'guestName.min' => __('guest.table.guest_name_min'),
            'guestName.max' => __('guest.table.guest_name_max'),
        ]);

        $this->preparedGuestName = str($validated['guestName'])->squish()->toString();
        $this->guestName = $this->preparedGuestName;

        $qrCode = $this->buildGuestEntryContext->findQrCode($this->token);

        if (! $qrCode instanceof QrCode || $qrCode->status !== QrCodeStatus::Active) {
            $this->showError(
                state: 'not_found',
                title: __('qr.errors.not_found.title'),
                message: __('qr.errors.not_found.description'),
            );

            return;
        }

        $servicePoint = $qrCode->servicePoint;

        if (! $servicePoint instanceof ServicePoint || ! $servicePoint->is_active) {
            $this->showError(
                state: 'inactive_service_point',
                title: __('qr.errors.service_point_unavailable.title'),
                message: __('qr.errors.service_point_unavailable.description'),
            );

            return;
        }

        if ($this->currentInviteToken() !== null) {
            $this->enterTableFromInvite(
                servicePoint: $servicePoint,
                qrCode: $qrCode,
                createTableSessionJoinRequest: $createTableSessionJoinRequest,
            );

            return;
        }

        $conflictTableSession = $this->findTableSessionForGuestNameConflict($servicePoint);

        if ($conflictTableSession instanceof TableSession
            && $this->pauseForGuestNameConflict($conflictTableSession, $this->preparedGuestName)) {
            return;
        }

        $result = $createGuestPendingTableSession->handle($servicePoint, $this->preparedGuestName);
        $this->clearGuestNameConflict();
        $entryState = $result['state'];
        $tableSession = $result['table_session'];
        $guest = $result['guest'];
        $joinRequest = $result['join_request'];

        $this->entryState = $entryState->value;
        $this->entryMessage = $this->presenter->messageForEntryState($entryState);
        $this->entryIssueCode = $this->presenter->issueCodeForEntryState($entryState);
        $this->currentTableSessionId = $tableSession instanceof TableSession ? $tableSession->id : null;
        $this->currentGuestId = $guest instanceof TableSessionGuest ? $guest->id : null;
        $this->currentJoinRequestId = $joinRequest instanceof TableSessionJoinRequest ? $joinRequest->id : null;
        $this->guestCanAddItems = $guest instanceof TableSessionGuest
            && $tableSession instanceof TableSession
            && $this->presenter->guestCanAddItems($guest, $tableSession, (bool) ($this->landing['can_accept_orders'] ?? false));
        $this->guestCanViewTable = $guest instanceof TableSessionGuest
            && $tableSession instanceof TableSession
            && $this->presenter->guestCanViewTable($guest, $tableSession);

        if ($tableSession instanceof TableSession) {
            $this->syncLandingServicePointFromTableSession($tableSession);
        }

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
            $this->entryState = 'join_request_blocked';
            $this->entryIssueCode = 'join_request_unavailable';
            $this->guestCanAddItems = false;
            $this->guestCanViewTable = false;

            return;
        }

        $joinRequest = $this->expireJoinRequestIfNeeded($joinRequest);
        $this->entryMessage = $this->presenter->messageForJoinRequestAccess($joinRequest);

        if ($joinRequest->status === TableSessionJoinRequestStatus::Approved) {
            $guest = $this->findGuestForJoinRequest($joinRequest);

            if (! $guest instanceof TableSessionGuest || ! $guest->tableSession instanceof TableSession) {
                $this->entryState = 'join_request_blocked';
                $this->entryIssueCode = 'join_request_unavailable';
                $this->guestCanAddItems = false;
                $this->guestCanViewTable = false;

                return;
            }

            $tableSession = $guest->tableSession;

            $this->guestName = $guest->guest_name;
            $this->preparedGuestName = $guest->guest_name;
            $this->currentTableSessionId = $tableSession->id;
            $this->currentGuestId = $guest->id;
            $this->currentJoinRequestId = null;
            $this->guestCanAddItems = $this->presenter->guestCanAddItems($guest, $tableSession, (bool) ($this->landing['can_accept_orders'] ?? false));
            $this->guestCanViewTable = $this->presenter->guestCanViewTable($guest, $tableSession);
            $this->entryState = $this->guestCanViewTable ? 'guest_restored' : 'guest_blocked';
            $this->entryMessage = $this->presenter->messageForGuestAccess($guest, $tableSession);
            $this->entryIssueCode = $this->presenter->guestAccessIssueCode($guest, $tableSession);
            $this->syncLandingServicePointFromTableSession($tableSession);

            return;
        }

        if ($joinRequest->status !== TableSessionJoinRequestStatus::Pending || $this->presenter->joinRequestIsExpired($joinRequest)) {
            $this->entryState = 'join_request_blocked';
            $this->entryIssueCode = $this->presenter->joinRequestAccessIssueCode($joinRequest);
            $this->guestCanAddItems = false;
            $this->guestCanViewTable = false;

            return;
        }

        $this->entryState = 'join_request_restored';
        $this->entryIssueCode = '';
        $this->guestCanAddItems = false;
        $this->guestCanViewTable = false;
    }

    public function render(): View
    {
        $this->applyGuestLocale();

        return view('livewire.public-qr.guest-entry', [
            'entryIssueCard' => $this->presenter->entryIssueCard(
                $this->state,
                $this->entryIssueCode,
                $this->entryMessage,
                $this->currentPublicQrUrl(withoutInvite: true),
            ),
        ]);
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
        $this->guestCanAddItems = $this->presenter->guestCanAddItems($guest, $tableSession, (bool) ($this->landing['can_accept_orders'] ?? false));
        $this->guestCanViewTable = $this->presenter->guestCanViewTable($guest, $tableSession);
        $this->entryState = $this->guestCanViewTable ? 'guest_restored' : 'guest_blocked';
        $this->entryMessage = $this->presenter->messageForGuestAccess($guest, $tableSession);
        $this->entryIssueCode = $this->presenter->guestAccessIssueCode($guest, $tableSession);
        $this->syncLandingServicePointFromTableSession($tableSession);

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
        $joinRequest = $this->expireJoinRequestIfNeeded($joinRequest);

        $this->guestName = $joinRequest->guest_name;
        $this->preparedGuestName = $joinRequest->guest_name;
        $this->currentTableSessionId = $tableSession->id;
        $this->currentJoinRequestId = $joinRequest->id;
        $this->guestCanAddItems = false;
        $this->guestCanViewTable = false;
        $this->entryState = $joinRequest->status === TableSessionJoinRequestStatus::Pending
            ? 'join_request_restored'
            : 'join_request_blocked';
        $this->entryMessage = $this->presenter->messageForJoinRequestAccess($joinRequest);
        $this->entryIssueCode = $this->presenter->joinRequestAccessIssueCode($joinRequest);
        $this->syncLandingServicePointFromTableSession($tableSession);

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
        $inviteToken = $this->currentInviteToken();

        if ($inviteToken === null || $this->preparedGuestName === null) {
            return;
        }

        $tableSession = $this->findTableSessionByInviteToken($servicePoint, $inviteToken);

        if (! $tableSession instanceof TableSession) {
            $this->entryState = 'guest_invite_invalid';
            $this->entryIssueCode = 'invite_expired';
            $this->entryMessage = __('guest.table.invite_expired_message');
            $this->currentTableSessionId = null;
            $this->currentGuestId = null;
            $this->currentJoinRequestId = null;
            $this->guestCanAddItems = false;
            $this->guestCanViewTable = false;

            return;
        }

        $this->currentTableSessionId = $tableSession->id;
        $this->currentGuestId = null;
        $this->guestCanAddItems = false;
        $this->guestCanViewTable = false;
        $this->syncLandingServicePointFromTableSession($tableSession);

        if (in_array($tableSession->status, [TableSessionStatus::Closed, TableSessionStatus::Cancelled], true)) {
            $this->entryState = 'guest_invite_closed';
            $this->entryIssueCode = 'invite_closed';
            $this->entryMessage = __('guest.table.session_closed');
            $this->currentJoinRequestId = null;

            return;
        }

        if ($this->pauseForGuestNameConflict($tableSession, $this->preparedGuestName)) {
            return;
        }

        $joinRequest = $createTableSessionJoinRequest->handle($tableSession, $this->preparedGuestName);
        $this->clearGuestNameConflict();

        if (! $joinRequest instanceof TableSessionJoinRequest) {
            $this->entryState = 'guest_invite_unavailable';
            $this->entryIssueCode = 'invite_unavailable';
            $this->entryMessage = __('guest.table.no_active_guest_approvers');
            $this->currentJoinRequestId = null;

            return;
        }

        $this->entryState = GuestTableEntryState::JoinRequestCreated->value;
        $this->entryMessage = $this->presenter->messageForEntryState(GuestTableEntryState::JoinRequestCreated);
        $this->entryIssueCode = '';
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
                'tableSession' => fn ($query) => $query
                    ->select([
                        'id',
                        'branch_id',
                        'service_point_id',
                        'status',
                        'ended_at',
                    ])
                    ->with([
                        'servicePoint' => fn ($servicePointQuery) => $servicePointQuery
                            ->select(['id', 'branch_id', 'area_node_id', 'type', 'name', 'display_number', 'is_active'])
                            ->with(['areaNode' => fn ($areaQuery) => $areaQuery->select(['id', 'branch_id', 'name'])]),
                    ]),
            ])
            ->where('guest_token', $guestToken)
            ->whereHas('tableSession', fn ($query) => $query->where('branch_id', $servicePoint->branch_id))
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
                'tableSession' => fn ($query) => $query
                    ->select([
                        'id',
                        'branch_id',
                        'service_point_id',
                        'status',
                        'ended_at',
                    ])
                    ->with([
                        'servicePoint' => fn ($servicePointQuery) => $servicePointQuery
                            ->select(['id', 'branch_id', 'area_node_id', 'type', 'name', 'display_number', 'is_active'])
                            ->with(['areaNode' => fn ($areaQuery) => $areaQuery->select(['id', 'branch_id', 'name'])]),
                    ]),
            ])
            ->where('guest_token', $guestToken)
            ->whereHas('tableSession', fn ($query) => $query->where('branch_id', $servicePoint->branch_id))
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
            ->with([
                'servicePoint' => fn ($servicePointQuery) => $servicePointQuery
                    ->select(['id', 'branch_id', 'area_node_id', 'type', 'name', 'display_number', 'is_active'])
                    ->with(['areaNode' => fn ($areaQuery) => $areaQuery->select(['id', 'branch_id', 'name'])]),
            ])
            ->where('branch_id', $servicePoint->branch_id)
            ->where('guest_invite_token', $inviteToken)
            ->first();
    }

    private function findTableSessionForGuestNameConflict(ServicePoint $servicePoint): ?TableSession
    {
        return $this->findTableSessionForGuestNameConflictByStatus($servicePoint, TableSessionStatus::Active)
            ?? $this->findTableSessionForGuestNameConflictByStatus($servicePoint, TableSessionStatus::Pending);
    }

    private function findTableSessionForGuestNameConflictByStatus(
        ServicePoint $servicePoint,
        TableSessionStatus $status,
    ): ?TableSession {
        $tableSession = TableSession::query()
            ->select([
                'id',
                'branch_id',
                'service_point_id',
                'opened_by_guest_id',
                'status',
                'source',
                'started_at',
                'ended_at',
            ])
            ->where('branch_id', $servicePoint->branch_id)
            ->where('service_point_id', $servicePoint->id)
            ->where('status', $status->value)
            ->whereHas('activeGuests')
            ->orderBy('started_at')
            ->orderBy('id')
            ->first();

        if ($tableSession instanceof TableSession || $status !== TableSessionStatus::Active) {
            return $tableSession;
        }

        $link = TableSessionServicePoint::query()
            ->select(['id', 'table_session_id', 'service_point_id', 'unlinked_at'])
            ->with([
                'tableSession' => fn ($query) => $query
                    ->select([
                        'id',
                        'branch_id',
                        'service_point_id',
                        'opened_by_guest_id',
                        'status',
                        'source',
                        'started_at',
                        'ended_at',
                    ])
                    ->where('branch_id', $servicePoint->branch_id)
                    ->where('status', $status->value)
                    ->whereHas('activeGuests'),
            ])
            ->active()
            ->where('service_point_id', $servicePoint->id)
            ->first();

        return $link?->tableSession;
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

    private function syncLandingServicePointFromTableSession(TableSession $tableSession): void
    {
        $tableSession->loadMissing([
            'servicePoint' => fn ($query) => $query
                ->select(['id', 'branch_id', 'area_node_id', 'type', 'name', 'display_number', 'is_active'])
                ->with(['areaNode' => fn ($areaQuery) => $areaQuery->select(['id', 'branch_id', 'name'])]),
        ]);

        $servicePoint = $tableSession->servicePoint;

        $this->landing['service_point_name'] = $servicePoint->name;
        $this->landing['service_point_display_number'] = $servicePoint->display_number;
        $this->landing['service_point_type'] = $servicePoint->type->label();
        $this->landing['area_name'] = $servicePoint->areaNode?->name;
    }

    private function showError(string $state, string $title, string $message): void
    {
        $this->state = $state;
        $this->title = $title;
        $this->message = $message;
        $this->entryIssueCode = '';
    }

    private function inviteTokenFromRequest(): ?string
    {
        $inviteToken = request()->query('invite');

        if (! is_string($inviteToken) || strlen($inviteToken) !== 64 || ! ctype_alnum($inviteToken)) {
            return null;
        }

        return $inviteToken;
    }

    private function setCurrentInviteToken(?string $inviteToken): void
    {
        if ($inviteToken === null) {
            session()->forget($this->inviteTokenSessionKey());
            $this->hasCurrentInviteToken = false;

            return;
        }

        session()->put($this->inviteTokenSessionKey(), $inviteToken);
        $this->hasCurrentInviteToken = true;
    }

    private function currentInviteToken(): ?string
    {
        if (! $this->hasCurrentInviteToken) {
            return null;
        }

        $inviteToken = session($this->inviteTokenSessionKey());

        if (! is_string($inviteToken) || strlen($inviteToken) !== 64 || ! ctype_alnum($inviteToken)) {
            return null;
        }

        return $inviteToken;
    }

    private function inviteTokenSessionKey(): string
    {
        return 'guest_invites.'.substr(hash('sha256', $this->token), 0, 24).'.invite_token';
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

    private function pauseForGuestNameConflict(TableSession $tableSession, string $guestName): bool
    {
        if ($this->allowDuplicateGuestName) {
            $this->clearGuestNameConflict(resetAllowDuplicate: false);

            return false;
        }

        $conflict = $this->guestNameConflictForTableSession($tableSession, $guestName);

        if ($conflict === null) {
            $this->clearGuestNameConflict();

            return false;
        }

        $this->hasGuestNameConflict = true;
        $this->guestNameConflictExistingName = $conflict['existing_name'];
        $this->guestNameSuggestions = $this->presenter->guestNameSuggestions($guestName, $conflict['active_names']);
        $this->entryState = '';
        $this->entryMessage = '';
        $this->entryIssueCode = '';
        $this->currentTableSessionId = $tableSession->id;
        $this->currentGuestId = null;
        $this->currentJoinRequestId = null;
        $this->guestCanAddItems = false;
        $this->guestCanViewTable = false;

        return true;
    }

    /**
     * @return array{existing_name: string, active_names: list<string>}|null
     */
    private function guestNameConflictForTableSession(TableSession $tableSession, string $guestName): ?array
    {
        $activeNames = TableSessionGuest::query()
            ->select([
                'id',
                'table_session_id',
                'guest_name',
                'status',
            ])
            ->where('table_session_id', $tableSession->id)
            ->where('status', TableSessionGuestStatus::Active->value)
            ->orderBy('guest_name')
            ->orderBy('id')
            ->limit(100)
            ->pluck('guest_name')
            ->all();

        $normalizedGuestName = $this->presenter->normalizeGuestName($guestName);

        foreach ($activeNames as $activeName) {
            if ($this->presenter->normalizeGuestName((string) $activeName) === $normalizedGuestName) {
                return [
                    'existing_name' => (string) $activeName,
                    'active_names' => array_values(array_map('strval', $activeNames)),
                ];
            }
        }

        return null;
    }

    private function clearGuestNameConflict(bool $resetAllowDuplicate = true): void
    {
        $this->hasGuestNameConflict = false;
        $this->guestNameConflictExistingName = null;
        $this->guestNameSuggestions = [];

        if ($resetAllowDuplicate) {
            $this->allowDuplicateGuestName = false;
        }
    }

    private function expireJoinRequestIfNeeded(TableSessionJoinRequest $joinRequest): TableSessionJoinRequest
    {
        if (! $this->presenter->joinRequestIsExpired($joinRequest)) {
            return $joinRequest;
        }

        return $this->expireJoinRequest->handle($joinRequest);
    }

    private function currentPublicQrUrl(bool $withoutInvite = false): string
    {
        $parameters = [
            'token' => $this->token,
            'lang' => $this->language,
        ];

        $inviteToken = $this->currentInviteToken();

        if (! $withoutInvite && $inviteToken !== null) {
            $parameters['invite'] = $inviteToken;
        }

        return route('public.qr.show', $parameters);
    }

    private function applyGuestLocale(): void
    {
        $this->language = SupportedLocale::normalize($this->language, App::currentLocale());

        App::setLocale($this->language);
        session()->put('interface_locale', $this->language);
    }
}
