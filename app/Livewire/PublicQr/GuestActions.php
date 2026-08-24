<?php

declare(strict_types=1);

namespace App\Livewire\PublicQr;

use App\Actions\TableSessions\CreatedGuestInviteLink;
use App\Actions\TableSessions\CreateGuestInviteLinkAction;
use App\Actions\TableSessions\LeaveTableSessionAction;
use App\Actions\TableSessions\RequestWaiterForTableSessionAction;
use App\Enums\SupportedLocale;
use App\Enums\WaiterCallStatus;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Services\PublicQr\PublicQrQueryService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class GuestActions extends Component
{
    private PublicQrQueryService $publicQrQueries;

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

    public string $leaveTableMessage = '';

    public function boot(PublicQrQueryService $publicQrQueries): void
    {
        $this->publicQrQueries = $publicQrQueries;
    }

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
        $this->applyGuestLocale();

        $tableSession = $this->findCurrentTableSession();
        $guest = $this->findCurrentActiveGuest();

        if (! $tableSession instanceof TableSession || ! $guest instanceof TableSessionGuest) {
            $this->guestInviteMessage = __('guest.table.invite_requires_active_guest');

            return;
        }

        try {
            $createdInvite = $createGuestInviteLink->handle($tableSession, $guest);
        } catch (ValidationException $exception) {
            $this->guestInviteMessage = $this->firstValidationMessage($exception);

            return;
        }

        $this->fillGuestInviteShareState($createdInvite);
    }

    public function requestWaiter(RequestWaiterForTableSessionAction $requestWaiter): void
    {
        $this->applyGuestLocale();

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

    public function leaveTable(LeaveTableSessionAction $leaveTableSession): void
    {
        $this->applyGuestLocale();
        $this->leaveTableMessage = '';
        $guest = $this->findCurrentActiveGuest();
        $guestToken = $this->guestTokenFromCurrentCookie();

        if (! $guest instanceof TableSessionGuest || $guestToken === null) {
            $this->leaveTableMessage = __('guest.table.leave_requires_active_guest');

            return;
        }

        try {
            $leaveTableSession->handle($guest, $guestToken);
        } catch (ValidationException $exception) {
            $this->leaveTableMessage = $this->firstValidationMessage($exception);

            return;
        }

        session()->forget('guest_entries.'.$this->publicToken);
        Cookie::queue(Cookie::forget($this->guestTokenCookieName()));
        $this->guestInviteUrl = '';
        $this->redirectRoute('public.qr.show', ['token' => $this->publicToken], navigate: true);
    }

    public function render(): View
    {
        $this->applyGuestLocale();

        return view('livewire.public-qr.guest-actions');
    }

    private function findCurrentTableSession(): ?TableSession
    {
        return $this->publicQrQueries->activeTableSessionForQr($this->publicToken, $this->tableSessionId);
    }

    private function findCurrentActiveGuest(): ?TableSessionGuest
    {
        $guestToken = $this->guestTokenFromCurrentCookie();

        if ($guestToken === null) {
            return null;
        }

        return $this->publicQrQueries->activeGuest($this->currentGuestId, $this->tableSessionId, $guestToken);
    }

    private function fillGuestInviteShareState(CreatedGuestInviteLink $createdInvite): void
    {
        if (strlen($createdInvite->token) !== 64) {
            $this->guestInviteMessage = __('guest.table.invite_failed');

            return;
        }

        $this->guestInviteUrl = route('public.qr.show', [
            'token' => $this->publicToken,
            'invite' => $createdInvite->token,
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
