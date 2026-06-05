<?php

namespace App\Livewire\PublicQr;

use App\Actions\Branches\GetBranchOpeningStatusAction;
use App\Actions\Branches\GetBranchPollingIntervalAction;
use App\Actions\Menus\GetGuestMenuForBranchAction;
use App\Actions\TableSessions\CreateGuestInviteLinkAction;
use App\Actions\TableSessions\CreateGuestPendingTableSessionAction;
use App\Actions\TableSessions\CreateTableSessionJoinRequestAction;
use App\Actions\TableSessions\RequestWaiterForTableSessionAction;
use App\Enums\GuestTableEntryState;
use App\Enums\OrganizationSubscriptionStatus;
use App\Enums\QrCodeStatus;
use App\Enums\SupportedCurrency;
use App\Enums\SupportedLocale;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionJoinRequestStatus;
use App\Enums\TableSessionStatus;
use App\Enums\WaiterCallStatus;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
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

    public ?int $currentTableSessionId = null;

    public ?int $currentGuestId = null;

    public ?int $currentJoinRequestId = null;

    public bool $guestCanAddItems = false;

    public bool $guestCanViewTable = false;

    public ?string $currentInviteToken = null;

    #[Url(as: 'lang')]
    public string $language = '';

    /**
     * @var array<string, string>
     */
    public array $languageOptions = [];

    public string $guestInviteUrl = '';

    public string $guestInviteTitle = '';

    public string $guestInviteText = '';

    public string $guestInviteMessage = '';

    public string $waiterCallMessage = '';

    public bool $waiterCallPending = false;

    /**
     * @var array{organization_name: string, brand_name: string, brand_initial: string, branch_id: int, branch_name: string, branch_city: string, branch_country: string, branch_address: string, branch_currency: string, default_language: string, default_language_label: string, default_currency: string, polling_interval_seconds: int, venue_name: string, public_description: string, logo_url: string|null, cover_image_url: string|null, phone: string|null, email: string|null, website_url: string|null, instagram_url: string|null, facebook_url: string|null, tiktok_url: string|null, has_contact_details: bool, opening_status_label: string, opening_status_detail: string, opening_status_tone: string, can_accept_orders: bool, service_point_name: string, service_point_display_number: string|null, service_point_type: string, area_name: string|null, short_code: string}
     */
    public array $landing = [
        'organization_name' => '',
        'brand_name' => '',
        'brand_initial' => '',
        'branch_id' => 0,
        'branch_name' => '',
        'branch_city' => '',
        'branch_country' => '',
        'branch_address' => '',
        'branch_currency' => 'EUR',
        'default_language' => 'en',
        'default_language_label' => 'English',
        'default_currency' => 'EUR',
        'polling_interval_seconds' => 1,
        'venue_name' => '',
        'public_description' => '',
        'logo_url' => null,
        'cover_image_url' => null,
        'phone' => null,
        'email' => null,
        'website_url' => null,
        'instagram_url' => null,
        'facebook_url' => null,
        'tiktok_url' => null,
        'has_contact_details' => false,
        'opening_status_label' => '',
        'opening_status_detail' => '',
        'opening_status_tone' => 'muted',
        'can_accept_orders' => true,
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
        $this->languageOptions = SupportedLocale::labels();
        $requestedLanguage = request()->query('lang');
        $hasRequestedLanguage = is_string($requestedLanguage) && SupportedLocale::isSupported($requestedLanguage);
        $this->language = $hasRequestedLanguage
            ? SupportedLocale::normalize($requestedLanguage)
            : SupportedLocale::normalize(null, App::currentLocale());
        $this->applyGuestLocale();

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

        if ($organization->subscription?->status === OrganizationSubscriptionStatus::Inactive) {
            $this->showError(
                state: 'restaurant_unavailable',
                title: __('Restaurant is temporarily unavailable'),
                message: __('Please ask the staff when service will resume.'),
            );

            return;
        }

        $this->language = app(GetGuestMenuForBranchAction::class)->resolveLanguageForBranch(
            $branch->id,
            $hasRequestedLanguage ? $this->language : null,
        );
        $this->applyGuestLocale();

        $branchSettings = $branch->settings;
        $openingStatus = app(GetBranchOpeningStatusAction::class)->handle($branch);
        $defaultLanguage = SupportedLocale::normalize($branchSettings?->default_language);
        $defaultCurrency = SupportedCurrency::normalize($branchSettings?->default_currency ?? $branch->currency);
        $languageLabels = SupportedLocale::labels();
        $venueName = $branch->publicDisplayName();
        $publicDescription = filled($branch->public_description)
            ? (string) $branch->public_description
            : __('Restaurant details will appear here soon.');
        $logoUrl = $branch->logoUrl() ?? $brand->logoUrl() ?? $organization->logoUrl();
        $contactLinks = [
            'phone' => $this->nullableLandingString($branch->phone),
            'email' => $this->nullableLandingString($branch->email),
            'website_url' => $this->nullableLandingString($branch->website_url),
            'instagram_url' => $this->nullableLandingString($branch->instagram_url),
            'facebook_url' => $this->nullableLandingString($branch->facebook_url),
            'tiktok_url' => $this->nullableLandingString($branch->tiktok_url),
        ];

        $this->state = 'ready';
        $this->title = $venueName;
        $this->message = $this->currentInviteToken === null
            ? __('Enter your name to continue.')
            : __('Введите имя, чтобы попроситься к этому столу.');
        $this->landing = [
            'organization_name' => $organization->name,
            'brand_name' => $brand->name,
            'brand_initial' => str($brand->name)->substr(0, 1)->upper()->toString(),
            'branch_id' => $branch->id,
            'branch_name' => $branch->name,
            'branch_city' => $branch->city,
            'branch_country' => $branch->country,
            'branch_address' => (string) $branch->address,
            'branch_currency' => $defaultCurrency,
            'default_language' => $defaultLanguage,
            'default_language_label' => $languageLabels[$defaultLanguage] ?? $defaultLanguage,
            'default_currency' => $defaultCurrency,
            'polling_interval_seconds' => app(GetBranchPollingIntervalAction::class)->handle($branch->id),
            'venue_name' => $venueName,
            'public_description' => $publicDescription,
            'logo_url' => $logoUrl,
            'cover_image_url' => $branch->coverImageUrl(),
            'phone' => $contactLinks['phone'],
            'email' => $contactLinks['email'],
            'website_url' => $contactLinks['website_url'],
            'instagram_url' => $contactLinks['instagram_url'],
            'facebook_url' => $contactLinks['facebook_url'],
            'tiktok_url' => $contactLinks['tiktok_url'],
            'has_contact_details' => collect($contactLinks)->filter()->isNotEmpty(),
            'opening_status_label' => $openingStatus['label'],
            'opening_status_detail' => $openingStatus['detail'],
            'opening_status_tone' => $openingStatus['tone'],
            'can_accept_orders' => $openingStatus['can_accept_orders'],
            'service_point_name' => $servicePoint->name,
            'service_point_display_number' => $servicePoint->display_number,
            'service_point_type' => $servicePoint->type->label(),
            'area_name' => $servicePoint->areaNode?->name,
            'short_code' => $qrCode->short_code,
        ];

        $this->restoreGuestFromCookie($qrCode);
    }

    public function updatedLanguage(): void
    {
        $branchId = (int) ($this->landing['branch_id'] ?? 0);
        $this->language = $branchId > 0
            ? app(GetGuestMenuForBranchAction::class)->resolveLanguageForBranch($branchId, $this->language)
            : SupportedLocale::normalize($this->language);

        $this->applyGuestLocale();
        $this->refreshLandingMessage();
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
        $this->entryMessage = $this->messageForEntryState($entryState);
        $this->entryIssueCode = $this->issueCodeForEntryState($entryState);
        $this->currentTableSessionId = $tableSession instanceof TableSession ? $tableSession->id : null;
        $this->currentGuestId = $guest instanceof TableSessionGuest ? $guest->id : null;
        $this->currentJoinRequestId = $joinRequest instanceof TableSessionJoinRequest ? $joinRequest->id : null;
        $this->guestCanAddItems = $guest instanceof TableSessionGuest
            && $tableSession instanceof TableSession
            && $this->canGuestAddItems($guest, $tableSession);
        $this->guestCanViewTable = $guest instanceof TableSessionGuest
            && $tableSession instanceof TableSession
            && $this->canGuestViewTable($guest, $tableSession);

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

    public function requestWaiter(RequestWaiterForTableSessionAction $requestWaiter): void
    {
        if ($this->state !== 'ready' || $this->currentTableSessionId === null || $this->currentGuestId === null) {
            return;
        }

        $tableSession = $this->findCurrentTableSessionForInvite();
        $guest = $this->findCurrentActiveGuestForInvite();

        if (! $tableSession instanceof TableSession || ! $guest instanceof TableSessionGuest) {
            $this->waiterCallMessage = __('Только активный гость за этим столом может позвать официанта.');
            $this->waiterCallPending = false;

            return;
        }

        try {
            $waiterCall = $requestWaiter->handle($tableSession, $guest);
        } catch (ValidationException $exception) {
            $this->waiterCallMessage = $this->firstValidationMessage($exception);
            $this->waiterCallPending = false;

            return;
        }

        $this->waiterCallPending = $waiterCall->status === WaiterCallStatus::Pending;
        $this->waiterCallMessage = __('Официант получил вызов. Пожалуйста, подождите.');
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
        $this->entryMessage = $this->messageForJoinRequestAccess($joinRequest);

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
            $this->guestCanAddItems = $this->canGuestAddItems($guest, $tableSession);
            $this->guestCanViewTable = $this->canGuestViewTable($guest, $tableSession);
            $this->entryState = $this->guestCanViewTable ? 'guest_restored' : 'guest_blocked';
            $this->entryMessage = $this->messageForGuestAccess($guest, $tableSession);
            $this->entryIssueCode = $this->guestAccessIssueCode($guest, $tableSession);
            $this->syncLandingServicePointFromTableSession($tableSession);

            return;
        }

        if ($joinRequest->status !== TableSessionJoinRequestStatus::Pending || $this->joinRequestIsExpired($joinRequest)) {
            $this->entryState = 'join_request_blocked';
            $this->entryIssueCode = $this->joinRequestAccessIssueCode($joinRequest);
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

        return view('livewire.public-qr.show', [
            'entryIssueCard' => $this->entryIssueCard(),
            'pageErrorCard' => $this->pageErrorCard(),
        ]);
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
                                'public_name',
                                'public_description',
                                'logo_path',
                                'cover_image_path',
                                'address',
                                'phone',
                                'email',
                                'website_url',
                                'instagram_url',
                                'facebook_url',
                                'tiktok_url',
                                'city',
                                'country',
                                'timezone',
                                'currency',
                                'is_temporarily_closed',
                                'temporary_closed_reason',
                                'temporary_closed_until',
                            ])
                            ->with([
                                'settings' => fn ($query) => $query->select([
                                    'id',
                                    'branch_id',
                                    'default_language',
                                    'default_currency',
                                ]),
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
                                ])->with([
                                    'subscription' => fn ($query) => $query->select([
                                        'id',
                                        'organization_id',
                                        'status',
                                    ]),
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
        $this->guestCanViewTable = $this->canGuestViewTable($guest, $tableSession);
        $this->entryState = $this->guestCanViewTable ? 'guest_restored' : 'guest_blocked';
        $this->entryMessage = $this->messageForGuestAccess($guest, $tableSession);
        $this->entryIssueCode = $this->guestAccessIssueCode($guest, $tableSession);
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
        $this->entryMessage = $this->messageForJoinRequestAccess($joinRequest);
        $this->entryIssueCode = $this->joinRequestAccessIssueCode($joinRequest);
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
        if ($this->currentInviteToken === null || $this->preparedGuestName === null) {
            return;
        }

        $tableSession = $this->findTableSessionByInviteToken($servicePoint, $this->currentInviteToken);

        if (! $tableSession instanceof TableSession) {
            $this->entryState = 'guest_invite_invalid';
            $this->entryIssueCode = 'invite_expired';
            $this->entryMessage = __('Ссылка приглашения больше не активна. Пожалуйста, попросите гостей отправить новую ссылку или отсканируйте QR-код на месте.');
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
            $this->entryMessage = __('Эта сессия стола уже закрыта. Пожалуйста, попросите гостей отправить новую ссылку.');
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
            $this->entryMessage = __('Сейчас за столом нет активных гостей для подтверждения входа.');
            $this->currentJoinRequestId = null;

            return;
        }

        $this->entryState = GuestTableEntryState::JoinRequestCreated->value;
        $this->entryMessage = $this->messageForEntryState(GuestTableEntryState::JoinRequestCreated);
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
            ])
            ->where('branch_id', $servicePoint->branch_id)
            ->where('service_point_id', $servicePoint->id)
            ->where('status', $status->value)
            ->whereHas('activeGuests')
            ->orderBy('started_at')
            ->orderBy('id')
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

    private function syncLandingServicePointFromTableSession(TableSession $tableSession): void
    {
        $tableSession->loadMissing([
            'servicePoint' => fn ($query) => $query
                ->select(['id', 'branch_id', 'area_node_id', 'type', 'name', 'display_number', 'is_active'])
                ->with(['areaNode' => fn ($areaQuery) => $areaQuery->select(['id', 'branch_id', 'name'])]),
        ]);

        $servicePoint = $tableSession->servicePoint;

        if (! $servicePoint instanceof ServicePoint) {
            return;
        }

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

    private function nullableLandingString(mixed $value): ?string
    {
        if (! is_string($value) || blank($value)) {
            return null;
        }

        return $value;
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
            'lang' => $this->language,
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
        $this->guestNameSuggestions = $this->guestNameSuggestions($guestName, $conflict['active_names']);
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

        $normalizedGuestName = $this->normalizeGuestNameForComparison($guestName);

        foreach ($activeNames as $activeName) {
            if ($this->normalizeGuestNameForComparison((string) $activeName) === $normalizedGuestName) {
                return [
                    'existing_name' => (string) $activeName,
                    'active_names' => array_values(array_map('strval', $activeNames)),
                ];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $activeNames
     * @return list<string>
     */
    private function guestNameSuggestions(string $guestName, array $activeNames): array
    {
        $existingNameKeys = array_map(
            fn (string $activeName): string => $this->normalizeGuestNameForComparison($activeName),
            $activeNames,
        );

        $candidates = [
            $this->uniqueGuestNameSuggestion($guestName.' 2', $existingNameKeys),
            $this->uniqueGuestNameSuggestion($this->initialGuestNameSuggestion($guestName), $existingNameKeys),
        ];

        $suggestions = [];
        $suggestionKeys = [];
        $guestNameKey = $this->normalizeGuestNameForComparison($guestName);

        foreach ($candidates as $candidate) {
            $candidate = str($candidate)->squish()->toString();
            $candidateKey = $this->normalizeGuestNameForComparison($candidate);

            if ($candidate === '' || $candidateKey === $guestNameKey || in_array($candidateKey, $suggestionKeys, true)) {
                continue;
            }

            $suggestions[] = $candidate;
            $suggestionKeys[] = $candidateKey;
        }

        return array_slice($suggestions, 0, 2);
    }

    /**
     * @param  list<string>  $existingNameKeys
     */
    private function uniqueGuestNameSuggestion(string $candidate, array $existingNameKeys): string
    {
        $baseCandidate = str($candidate)->squish()->toString();
        $uniqueCandidate = $baseCandidate;
        $counter = 2;

        while (in_array($this->normalizeGuestNameForComparison($uniqueCandidate), $existingNameKeys, true) && $counter <= 99) {
            $uniqueCandidate = $baseCandidate.' '.$counter;
            $counter++;
        }

        return $uniqueCandidate;
    }

    private function initialGuestNameSuggestion(string $guestName): string
    {
        $parts = preg_split('/\s+/u', str($guestName)->squish()->toString()) ?: [];
        $firstName = (string) ($parts[0] ?? $guestName);
        $initialSource = (string) ($parts[1] ?? ($this->guestNameLooksCyrillic($firstName) ? 'К' : 'K'));
        $initial = str(mb_substr($initialSource, 0, 1))->upper()->toString();

        return trim($firstName.' '.$initial.'.');
    }

    private function guestNameLooksCyrillic(string $guestName): bool
    {
        return preg_match('/\p{Cyrillic}/u', $guestName) === 1;
    }

    private function normalizeGuestNameForComparison(string $guestName): string
    {
        return str($guestName)->squish()->lower()->toString();
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

    private function canGuestAddItems(TableSessionGuest $guest, TableSession $tableSession): bool
    {
        if (in_array($tableSession->status, [TableSessionStatus::Closed, TableSessionStatus::Cancelled], true)) {
            return false;
        }

        return $guest->status === TableSessionGuestStatus::Active
            && (bool) ($this->landing['can_accept_orders'] ?? true);
    }

    private function canGuestViewTable(TableSessionGuest $guest, TableSession $tableSession): bool
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

    private function expireJoinRequestIfNeeded(TableSessionJoinRequest $joinRequest): TableSessionJoinRequest
    {
        if (! $this->joinRequestIsExpired($joinRequest)) {
            return $joinRequest;
        }

        $joinRequest
            ->forceFill(['status' => TableSessionJoinRequestStatus::Expired])
            ->save();

        return $joinRequest->refresh();
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

    private function guestAccessIssueCode(TableSessionGuest $guest, TableSession $tableSession): string
    {
        if (in_array($tableSession->status, [TableSessionStatus::Closed, TableSessionStatus::Cancelled], true)) {
            return 'session_closed';
        }

        return match ($guest->status) {
            TableSessionGuestStatus::Active,
            TableSessionGuestStatus::PendingApproval => '',
            TableSessionGuestStatus::Rejected => 'guest_rejected',
            TableSessionGuestStatus::Removed => 'guest_removed',
            TableSessionGuestStatus::Left => 'guest_left',
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

    private function joinRequestAccessIssueCode(TableSessionJoinRequest $joinRequest): string
    {
        if ($joinRequest->status === TableSessionJoinRequestStatus::Pending && ! $this->joinRequestIsExpired($joinRequest)) {
            return '';
        }

        return match ($joinRequest->status) {
            TableSessionJoinRequestStatus::Pending,
            TableSessionJoinRequestStatus::Expired => 'invite_expired',
            TableSessionJoinRequestStatus::Rejected => 'guest_rejected',
            TableSessionJoinRequestStatus::Approved => '',
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
            GuestTableEntryState::ServicePointUnavailable => __('Это место сейчас недоступно. Пожалуйста, обратитесь к персоналу.'),
        };
    }

    private function issueCodeForEntryState(GuestTableEntryState $state): string
    {
        return match ($state) {
            GuestTableEntryState::GuestCreatedSessionsDisabled => 'guest_created_sessions_disabled',
            GuestTableEntryState::ServicePointUnavailable => 'service_point_unavailable',
            default => '',
        };
    }

    /**
     * @return array{visible: bool, state: string, tone: string, kicker: string, title: string, message: string, support_text: string, primary_label: string|null, primary_url: string|null, secondary_label: string|null, secondary_url: string|null}
     */
    private function pageErrorCard(): array
    {
        if ($this->state === 'ready') {
            return $this->emptyErrorCard();
        }

        $state = $this->state !== '' ? $this->state : 'not_found';

        return [
            'visible' => true,
            'state' => $state,
            'tone' => $this->toneForErrorState($state),
            'kicker' => $this->kickerForPageErrorState($state),
            'title' => $this->title,
            'message' => $this->message,
            'support_text' => $this->supportTextForPageErrorState($state),
            'primary_label' => $state === 'not_found' ? __('Open start page') : __('Try again'),
            'primary_url' => $state === 'not_found' ? route('guest.home') : $this->currentPublicQrUrl(),
            'secondary_label' => null,
            'secondary_url' => null,
        ];
    }

    /**
     * @return array{visible: bool, state: string, tone: string, kicker: string, title: string, message: string, support_text: string, primary_label: string|null, primary_url: string|null, secondary_label: string|null, secondary_url: string|null}
     */
    private function entryIssueCard(): array
    {
        if ($this->state !== 'ready' || $this->entryIssueCode === '') {
            return $this->emptyErrorCard();
        }

        return [
            'visible' => true,
            'state' => $this->entryIssueCode,
            'tone' => $this->toneForErrorState($this->entryIssueCode),
            'kicker' => $this->kickerForEntryIssueCode($this->entryIssueCode),
            'title' => $this->titleForEntryIssueCode($this->entryIssueCode),
            'message' => $this->entryMessage,
            'support_text' => $this->supportTextForEntryIssueCode($this->entryIssueCode),
            'primary_label' => __('Return to QR page'),
            'primary_url' => $this->currentPublicQrUrl(withoutInvite: true),
            'secondary_label' => null,
            'secondary_url' => null,
        ];
    }

    /**
     * @return array{visible: bool, state: string, tone: string, kicker: string, title: string, message: string, support_text: string, primary_label: string|null, primary_url: string|null, secondary_label: string|null, secondary_url: string|null}
     */
    private function emptyErrorCard(): array
    {
        return [
            'visible' => false,
            'state' => '',
            'tone' => 'zinc',
            'kicker' => '',
            'title' => '',
            'message' => '',
            'support_text' => '',
            'primary_label' => null,
            'primary_url' => null,
            'secondary_label' => null,
            'secondary_url' => null,
        ];
    }

    private function toneForErrorState(string $state): string
    {
        return match ($state) {
            'disabled',
            'inactive_service_point',
            'restaurant_unavailable',
            'service_point_unavailable',
            'guest_created_sessions_disabled',
            'invite_unavailable' => 'amber',
            'not_found',
            'revoked',
            'guest_rejected',
            'guest_removed',
            'guest_left',
            'session_closed',
            'invite_expired',
            'invite_closed',
            'join_request_unavailable' => 'rose',
            default => 'zinc',
        };
    }

    private function kickerForPageErrorState(string $state): string
    {
        return match ($state) {
            'not_found' => __('QR access'),
            'disabled',
            'revoked' => __('QR access paused'),
            'inactive_service_point' => __('Place unavailable'),
            'restaurant_unavailable' => __('Restaurant unavailable'),
            default => __('Guest access'),
        };
    }

    private function supportTextForPageErrorState(string $state): string
    {
        return match ($state) {
            'not_found',
            'revoked' => __('Show this screen to the staff so they can give you the correct QR.'),
            'disabled',
            'inactive_service_point',
            'restaurant_unavailable' => __('Show this screen to the staff. They can reopen access when the place is ready.'),
            default => __('Show this screen to the staff if you need help.'),
        };
    }

    private function kickerForEntryIssueCode(string $issueCode): string
    {
        return match ($issueCode) {
            'session_closed',
            'invite_closed' => __('Table closed'),
            'guest_rejected',
            'guest_removed',
            'guest_left' => __('Guest access'),
            'invite_expired',
            'join_request_unavailable' => __('Invite link'),
            'service_point_unavailable' => __('Place unavailable'),
            'guest_created_sessions_disabled',
            'invite_unavailable' => __('Ask the staff'),
            default => __('Guest access'),
        };
    }

    private function titleForEntryIssueCode(string $issueCode): string
    {
        return match ($issueCode) {
            'session_closed',
            'invite_closed' => __('This table session is closed'),
            'guest_rejected' => __('Guest access was not approved'),
            'guest_removed' => __('Guest access was removed'),
            'guest_left' => __('You have left this table'),
            'invite_expired',
            'join_request_unavailable' => __('Invite link has expired'),
            'service_point_unavailable' => __('This place is temporarily unavailable'),
            'guest_created_sessions_disabled' => __('Please ask a waiter to open this table'),
            'invite_unavailable' => __('No active guests can approve this invite'),
            default => __('Guest access is unavailable'),
        };
    }

    private function supportTextForEntryIssueCode(string $issueCode): string
    {
        return match ($issueCode) {
            'session_closed',
            'invite_closed' => __('A closed table keeps its old orders, but it cannot accept new guest actions.'),
            'guest_rejected',
            'guest_removed',
            'guest_left' => __('You cannot add items from this guest entry. Please ask the table or staff for help.'),
            'invite_expired',
            'join_request_unavailable' => __('Ask an active guest to share a new invite link, or scan the QR code at the table.'),
            'service_point_unavailable' => __('Ordering from this place is paused until staff reopens it.'),
            'guest_created_sessions_disabled',
            'invite_unavailable' => __('A staff member can help you continue from this table.'),
            default => __('Please ask the staff for help.'),
        };
    }

    private function currentPublicQrUrl(bool $withoutInvite = false): string
    {
        $parameters = [
            'token' => $this->token,
            'lang' => $this->language,
        ];

        if (! $withoutInvite && $this->currentInviteToken !== null) {
            $parameters['invite'] = $this->currentInviteToken;
        }

        return route('public.qr.show', $parameters);
    }

    private function applyGuestLocale(): void
    {
        $this->language = SupportedLocale::normalize($this->language, App::currentLocale());

        App::setLocale($this->language);
        session()->put('interface_locale', $this->language);
    }

    private function refreshLandingMessage(): void
    {
        if ($this->state !== 'ready') {
            return;
        }

        $this->message = $this->currentInviteToken === null
            ? __('Enter your name to continue.')
            : __('Введите имя, чтобы попроситься к этому столу.');
    }
}
