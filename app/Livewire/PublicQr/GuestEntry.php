<?php

declare(strict_types=1);

namespace App\Livewire\PublicQr;

use App\Actions\Branches\GetBranchOpeningStatusAction;
use App\Actions\Branches\GetBranchPollingIntervalAction;
use App\Actions\Menus\GetGuestMenuForBranchAction;
use App\Actions\TableSessions\CreateGuestPendingTableSessionAction;
use App\Actions\TableSessions\CreateTableSessionJoinRequestAction;
use App\Enums\GuestTableEntryState;
use App\Enums\OrganizationSubscriptionStatus;
use App\Enums\QrCodeStatus;
use App\Enums\SupportedCurrency;
use App\Enums\SupportedLocale;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionJoinRequestStatus;
use App\Enums\TableSessionStatus;
use App\Models\BranchSetting;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;
use App\Models\TableSessionServicePoint;
use App\Support\GuestEntryPresenter;
use App\Support\Validation\RestaurantValidationRules;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class GuestEntry extends Component
{
    private GetGuestMenuForBranchAction $getGuestMenuForBranch;

    private GetBranchOpeningStatusAction $getBranchOpeningStatus;

    private GetBranchPollingIntervalAction $getBranchPollingInterval;

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

    /**
     * @var array{organization_name: string, brand_name: string, brand_initial: string, branch_id: int, branch_name: string, branch_city: string, branch_country: string, branch_address: string, branch_currency: string, default_language: string, default_language_label: string, default_currency: string, polling_interval_seconds: int, venue_name: string, public_description: string, logo_url: string|null, cover_image_url: string|null, phone: string|null, email: string|null, website_url: string|null, instagram_url: string|null, facebook_url: string|null, tiktok_url: string|null, has_contact_details: bool, opening_status_label: string, opening_status_detail: string, opening_status_tone: string, can_accept_orders: bool, service_point_name: string, service_point_display_number: string|null, service_point_type: string, area_name: string|null, short_code: string}
     */
    #[Locked]
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

    public function boot(
        GetGuestMenuForBranchAction $getGuestMenuForBranch,
        GetBranchOpeningStatusAction $getBranchOpeningStatus,
        GetBranchPollingIntervalAction $getBranchPollingInterval,
        GuestEntryPresenter $presenter,
    ): void {
        $this->getGuestMenuForBranch = $getGuestMenuForBranch;
        $this->getBranchOpeningStatus = $getBranchOpeningStatus;
        $this->getBranchPollingInterval = $getBranchPollingInterval;
        $this->presenter = $presenter;
    }

    public function mount(string $token, string $language = ''): void
    {
        $this->token = $token;
        $this->setCurrentInviteToken($this->inviteTokenFromRequest());
        $requestedLanguage = request()->query('lang');
        $hasRequestedLanguage = is_string($requestedLanguage) && SupportedLocale::isSupported($requestedLanguage);
        $this->language = $hasRequestedLanguage
            ? SupportedLocale::normalize($requestedLanguage)
            : SupportedLocale::normalize($language, App::currentLocale());
        $this->applyGuestLocale();

        $qrCode = $this->findQrCode($token);

        if (! $qrCode instanceof QrCode) {
            $this->showError(
                state: 'not_found',
                title: __('qr.errors.not_found.title'),
                message: __('qr.errors.not_found.description'),
            );

            return;
        }

        if ($qrCode->status === QrCodeStatus::Disabled) {
            $this->showError(
                state: 'disabled',
                title: __('qr.errors.disabled.title'),
                message: __('qr.errors.disabled.description'),
            );

            return;
        }

        if ($qrCode->status === QrCodeStatus::Revoked) {
            $this->showError(
                state: 'revoked',
                title: __('qr.errors.revoked.title'),
                message: __('qr.errors.revoked.description'),
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

        $branch = $servicePoint->branch;
        $brand = $branch->brand;
        $organization = $branch->organization;

        if ($organization->subscription?->status === OrganizationSubscriptionStatus::Inactive) {
            $this->showError(
                state: 'restaurant_unavailable',
                title: __('guest.table.restaurant_unavailable_title'),
                message: __('guest.table.restaurant_unavailable_message'),
            );

            return;
        }

        $this->language = $this->getGuestMenuForBranch->resolveLanguageForBranch(
            $branch->id,
            $hasRequestedLanguage ? $this->language : null,
        );
        $this->applyGuestLocale();

        $branchSettingsRelation = $branch->getRelation('settings');
        $branchSettings = $branchSettingsRelation instanceof BranchSetting ? $branchSettingsRelation : null;
        $openingStatus = $this->getBranchOpeningStatus->handle($branch);
        $defaultLanguage = SupportedLocale::normalize($branchSettings?->default_language);
        $defaultCurrency = SupportedCurrency::normalize(
            $branchSettings instanceof BranchSetting ? $branchSettings->default_currency : $branch->currency,
        );
        $languageLabels = SupportedLocale::labels();
        $venueName = $branch->publicDisplayName();
        $publicDescription = filled($branch->public_description)
            ? (string) $branch->public_description
            : __('guest.table.restaurant_description_placeholder');
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
        $this->message = ! $this->hasCurrentInviteToken
            ? __('guest.table.enter_name')
            : __('guest.table.invite_request_name');
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
            'polling_interval_seconds' => $this->getBranchPollingInterval->handle($branch->id),
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

        $qrCode = $this->findQrCode($this->token);

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
            && $this->canGuestAddItems($guest, $tableSession);
        $this->guestCanViewTable = $guest instanceof TableSessionGuest
            && $tableSession instanceof TableSession
            && $this->canGuestViewTable($guest, $tableSession);

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
            $this->guestCanAddItems = $this->canGuestAddItems($guest, $tableSession);
            $this->guestCanViewTable = $this->canGuestViewTable($guest, $tableSession);
            $this->entryState = $this->guestCanViewTable ? 'guest_restored' : 'guest_blocked';
            $this->entryMessage = $this->presenter->messageForGuestAccess($guest, $tableSession);
            $this->entryIssueCode = $this->presenter->guestAccessIssueCode($guest, $tableSession);
            $this->syncLandingServicePointFromTableSession($tableSession);

            return;
        }

        if ($joinRequest->status !== TableSessionJoinRequestStatus::Pending || $this->joinRequestIsExpired($joinRequest)) {
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

    private function canGuestAddItems(TableSessionGuest $guest, TableSession $tableSession): bool
    {
        if (in_array($tableSession->status, [TableSessionStatus::Closed, TableSessionStatus::Cancelled], true)) {
            return false;
        }

        return $guest->status === TableSessionGuestStatus::Active
            && (bool) $this->landing['can_accept_orders'];
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

