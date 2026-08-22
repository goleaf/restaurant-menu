<?php

declare(strict_types=1);

namespace App\Livewire\PublicQr;

use App\Actions\TableSessions\CreateGuestInviteLinkAction;
use App\Actions\TableSessions\RequestWaiterForTableSessionAction;
use App\Enums\QrCodeStatus;
use App\Enums\SupportedLocale;
use App\Enums\TableSessionGuestStatus;
use App\Enums\WaiterCallStatus;
use App\Models\QrCode;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class GuestActions extends Component
{
    #[Locked]
    public int $tableSessionId;

    #[Locked]
    public int $currentGuestId;

    #[Locked]
    public string $publicToken;

    #[Locked]
    public int $pollingIntervalSeconds = 1;

    public string $language = 'en';

    public string $venueName = '';

    public string $guestInviteUrl = '';

    public string $guestInviteTitle = '';

    public string $guestInviteText = '';

    public string $guestInviteMessage = '';

    public string $waiterCallMessage = '';

    public bool $waiterCallPending = false;

    public function mount(
        int $tableSessionId,
        int $currentGuestId,
        string $publicToken,
        int $pollingIntervalSeconds = 1,
        string $language = 'en',
        string $venueName = '',
    ): void {
        $this->tableSessionId = $tableSessionId;
        $this->currentGuestId = $currentGuestId;
        $this->publicToken = $publicToken;
        $this->pollingIntervalSeconds = max(1, min($pollingIntervalSeconds, 60));
        $this->language = SupportedLocale::normalize($language, 'en');
        $this->venueName = $venueName;
        $this->applyGuestLocale();
    }

    public function createGuestInviteLink(CreateGuestInviteLinkAction $createGuestInviteLink): void
    {
        $tableSession = $this->findCurrentTableSession();
        $guest = $this->findCurrentActiveGuest();

        if (! $tableSession instanceof TableSession || ! $guest instanceof TableSessionGuest) {
            $this->guestInviteMessage = __('guest.table.invite_requires_active_guest');

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
        $tableSession = $this->findCurrentTableSession();
        $guest = $this->findCurrentActiveGuest();

        if (! $tableSession instanceof TableSession || ! $guest instanceof TableSessionGuest) {
            $this->waiterCallMessage = __('guest.table.waiter_requires_active_guest');
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
        $this->waiterCallMessage = __('guest.table.waiter_called');
    }

    public function render(): View
    {
        $this->applyGuestLocale();

        return view('livewire.public-qr.guest-actions');
    }

    private function findCurrentTableSession(): ?TableSession
    {
        $branchId = QrCode::query()
            ->select(['id', 'service_point_id', 'public_token', 'status'])
            ->with(['servicePoint' => fn ($query) => $query->select(['id', 'branch_id', 'is_active'])])
            ->where('public_token', $this->publicToken)
            ->where('status', QrCodeStatus::Active->value)
            ->first()
            ?->servicePoint
            ?->branch_id;

        if (! is_int($branchId)) {
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
            ->whereKey($this->tableSessionId)
            ->where('branch_id', $branchId)
            ->first();
    }

    private function findCurrentActiveGuest(): ?TableSessionGuest
    {
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
            ->where('table_session_id', $this->tableSessionId)
            ->where('guest_token', $guestToken)
            ->where('status', TableSessionGuestStatus::Active->value)
            ->first();
    }

    private function fillGuestInviteShareState(TableSession $tableSession): void
    {
        if (! is_string($tableSession->guest_invite_token) || strlen($tableSession->guest_invite_token) !== 64) {
            $this->guestInviteMessage = __('guest.table.invite_failed');

            return;
        }

        $this->guestInviteUrl = route('public.qr.show', [
            'token' => $this->publicToken,
            'invite' => $tableSession->guest_invite_token,
            'lang' => $this->language,
        ]);
        $this->guestInviteTitle = __('guest.table.invite_title');
        $this->guestInviteText = __('guest.table.invite_text', [
            'venue' => $this->venueName !== '' ? $this->venueName : config('app.name', 'Restaurant'),
        ]);
        $this->guestInviteMessage = __('guest.table.invite_link_ready');
    }

    private function guestTokenFromCurrentCookie(): ?string
    {
        $guestToken = request()->cookie($this->guestTokenCookieName());

        if (is_string($guestToken) && strlen($guestToken) === 64) {
            return $guestToken;
        }

        $guestToken = session('guest_entries.'.$this->publicToken.'.guest_token');

        if (! is_string($guestToken) || strlen($guestToken) !== 64) {
            return null;
        }

        return $guestToken;
    }

    private function guestTokenCookieName(): string
    {
        return 'guest_token_'.substr(hash('sha256', $this->publicToken), 0, 24);
    }

    private function firstValidationMessage(ValidationException $exception): string
    {
        $messages = collect($exception->errors())->flatten();

        return (string) ($messages->first() ?? __('guest.table.invite_failed'));
    }

    private function applyGuestLocale(): void
    {
        $this->language = SupportedLocale::normalize($this->language, App::currentLocale());
        App::setLocale($this->language);
    }
}
