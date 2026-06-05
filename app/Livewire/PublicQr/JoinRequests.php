<?php

namespace App\Livewire\PublicQr;

use App\Actions\Branches\GetBranchPollingIntervalAction;
use App\Actions\TableSessions\ApproveTableSessionJoinRequestAction;
use App\Actions\TableSessions\RejectTableSessionJoinRequestAction;
use App\Enums\SupportedLocale;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionJoinRequestStatus;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Isolate;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Isolate]
class JoinRequests extends Component
{
    #[Locked]
    public int $tableSessionId = 0;

    #[Locked]
    public int $guestId = 0;

    #[Locked]
    public string $publicToken = '';

    public int $pollingIntervalSeconds = 1;

    public string $language = 'ru';

    public bool $canModerate = false;

    public string $notice = '';

    public string $noticeTone = 'info';

    /**
     * @var list<array{id: int, guest_name: string, created_label: string, expires_label: string|null}>
     */
    public array $pendingRequests = [];

    public function mount(int $tableSessionId, int $guestId, string $publicToken, int $pollingIntervalSeconds = 1, string $language = 'ru'): void
    {
        $this->tableSessionId = $tableSessionId;
        $this->guestId = $guestId;
        $this->publicToken = $publicToken;
        $this->pollingIntervalSeconds = GetBranchPollingIntervalAction::normalize($pollingIntervalSeconds);
        $this->language = SupportedLocale::normalize($language, 'ru');
        $this->applyLocale();

        $this->refreshJoinRequests();
    }

    public function refreshJoinRequests(): void
    {
        $this->applyLocale();

        $this->canModerate = $this->activeGuest() instanceof TableSessionGuest;

        if (! $this->canModerate) {
            $this->pendingRequests = [];

            return;
        }

        $this->pendingRequests = TableSessionJoinRequest::query()
            ->select([
                'id',
                'table_session_id',
                'guest_name',
                'status',
                'expires_at',
                'created_at',
            ])
            ->where('table_session_id', $this->tableSessionId)
            ->where('status', TableSessionJoinRequestStatus::Pending->value)
            ->where(function ($query): void {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->map(fn (TableSessionJoinRequest $joinRequest): array => [
                'id' => $joinRequest->id,
                'guest_name' => $joinRequest->guest_name,
                'created_label' => $joinRequest->created_at?->diffForHumans() ?? '',
                'expires_label' => $joinRequest->expires_at?->format('H:i'),
            ])
            ->all();
    }

    public function approve(int $joinRequestId, ApproveTableSessionJoinRequestAction $approveJoinRequest): void
    {
        $this->applyLocale();

        $guest = $this->activeGuest();
        $joinRequest = $this->pendingJoinRequest($joinRequestId);

        if (! $guest instanceof TableSessionGuest || ! $joinRequest instanceof TableSessionJoinRequest) {
            $this->showNotice(__('guest.table.approve_requires_active_guest'), 'warning');
            $this->refreshJoinRequests();

            return;
        }

        try {
            $approvedGuest = $approveJoinRequest->handle($joinRequest, $guest);

            $this->showNotice(__('guest.table.approved_notice', ['name' => $approvedGuest->guest_name]), 'success');
        } catch (ValidationException $exception) {
            $this->showNotice($this->firstValidationMessage($exception), 'warning');
        }

        $this->refreshJoinRequests();
    }

    public function reject(int $joinRequestId, RejectTableSessionJoinRequestAction $rejectJoinRequest): void
    {
        $this->applyLocale();

        $guest = $this->activeGuest();
        $joinRequest = $this->pendingJoinRequest($joinRequestId);

        if (! $guest instanceof TableSessionGuest || ! $joinRequest instanceof TableSessionJoinRequest) {
            $this->showNotice(__('guest.table.reject_requires_active_guest'), 'warning');
            $this->refreshJoinRequests();

            return;
        }

        $guestName = $joinRequest->guest_name;

        try {
            $rejectJoinRequest->handle($joinRequest, $guest);

            $this->showNotice(__('guest.table.rejected_notice', ['name' => $guestName]), 'warning');
        } catch (ValidationException $exception) {
            $this->showNotice($this->firstValidationMessage($exception), 'warning');
        }

        $this->refreshJoinRequests();
    }

    public function render(): View
    {
        $this->applyLocale();

        return view('livewire.public-qr.join-requests');
    }

    private function applyLocale(): void
    {
        App::setLocale($this->language);
    }

    private function activeGuest(): ?TableSessionGuest
    {
        $guestToken = $this->guestTokenFromCookie();

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
            ->whereKey($this->guestId)
            ->where('table_session_id', $this->tableSessionId)
            ->where('guest_token', $guestToken)
            ->where('status', TableSessionGuestStatus::Active->value)
            ->first();
    }

    private function pendingJoinRequest(int $joinRequestId): ?TableSessionJoinRequest
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
            ->whereKey($joinRequestId)
            ->where('table_session_id', $this->tableSessionId)
            ->where('status', TableSessionJoinRequestStatus::Pending->value)
            ->first();
    }

    private function guestTokenFromCookie(): ?string
    {
        if ($this->publicToken === '') {
            return null;
        }

        $guestToken = request()->cookie($this->guestTokenCookieName($this->publicToken));

        if (is_string($guestToken) && strlen($guestToken) === 64) {
            return $guestToken;
        }

        $guestToken = session('guest_entries.'.$this->publicToken.'.guest_token');

        if (! is_string($guestToken) || strlen($guestToken) !== 64) {
            return null;
        }

        return $guestToken;
    }

    private function guestTokenCookieName(string $publicToken): string
    {
        return 'guest_token_'.substr(hash('sha256', $publicToken), 0, 24);
    }

    private function showNotice(string $message, string $tone): void
    {
        $this->notice = $message;
        $this->noticeTone = $tone;
    }

    private function firstValidationMessage(ValidationException $exception): string
    {
        $messages = collect($exception->errors())->flatten();

        return (string) ($messages->first() ?? __('guest.table.join_request_failed'));
    }
}
