<?php

declare(strict_types=1);

namespace App\Support\PublicQr;

use App\Enums\GuestTableEntryState;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionJoinRequestStatus;
use App\Enums\TableSessionStatus;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;

final class GuestEntryPresenter
{
    public function normalizeGuestName(string $guestName): string
    {
        return str($guestName)->squish()->lower()->toString();
    }

    /**
     * @param  list<string>  $activeNames
     * @return list<string>
     */
    public function guestNameSuggestions(string $guestName, array $activeNames): array
    {
        $existingNameKeys = array_map($this->normalizeGuestName(...), $activeNames);
        $candidates = [
            $this->uniqueGuestNameSuggestion($guestName.' 2', $existingNameKeys),
            $this->uniqueGuestNameSuggestion($this->initialGuestNameSuggestion($guestName), $existingNameKeys),
        ];
        $suggestions = [];
        $suggestionKeys = [];
        $guestNameKey = $this->normalizeGuestName($guestName);

        foreach ($candidates as $candidate) {
            $candidate = str($candidate)->squish()->toString();
            $candidateKey = $this->normalizeGuestName($candidate);

            if ($candidate === '' || $candidateKey === $guestNameKey || in_array($candidateKey, $suggestionKeys, true)) {
                continue;
            }

            $suggestions[] = $candidate;
            $suggestionKeys[] = $candidateKey;
        }

        return array_slice($suggestions, 0, 2);
    }

    public function messageForGuestAccess(TableSessionGuest $guest, TableSession $tableSession): string
    {
        if ($this->tableSessionIsClosed($tableSession)) {
            return __('guest.table.session_closed');
        }

        return match ($guest->status) {
            TableSessionGuestStatus::Active => __('guest.table.entry_saved'),
            TableSessionGuestStatus::PendingApproval => __('guest.table.waiting_for_approval'),
            TableSessionGuestStatus::Rejected => __('guest.table.rejected_message'),
            TableSessionGuestStatus::Removed => __('guest.table.removed_message'),
            TableSessionGuestStatus::Left => __('guest.table.left_message'),
        };
    }

    public function guestCanAddItems(
        TableSessionGuest $guest,
        TableSession $tableSession,
        bool $branchCanAcceptOrders,
    ): bool {
        return $this->guestCanViewTable($guest, $tableSession) && $branchCanAcceptOrders;
    }

    public function guestCanViewTable(TableSessionGuest $guest, TableSession $tableSession): bool
    {
        return ! $this->tableSessionIsClosed($tableSession)
            && $guest->status === TableSessionGuestStatus::Active;
    }

    public function guestAccessIssueCode(TableSessionGuest $guest, TableSession $tableSession): string
    {
        if ($this->tableSessionIsClosed($tableSession)) {
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

    public function messageForJoinRequestAccess(TableSessionJoinRequest $joinRequest): string
    {
        if ($this->joinRequestIsExpired($joinRequest)) {
            return __('guest.table.join_request_expired');
        }

        return match ($joinRequest->status) {
            TableSessionJoinRequestStatus::Pending => __('guest.table.join_request_sent'),
            TableSessionJoinRequestStatus::Approved => __('guest.table.join_request_approved'),
            TableSessionJoinRequestStatus::Rejected => __('guest.table.rejected_message'),
            TableSessionJoinRequestStatus::Expired => __('guest.table.join_request_expired'),
        };
    }

    public function joinRequestAccessIssueCode(TableSessionJoinRequest $joinRequest): string
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

    public function messageForEntryState(GuestTableEntryState $state): string
    {
        return match ($state) {
            GuestTableEntryState::PendingSessionCreated => __('guest.table.pending_session_created'),
            GuestTableEntryState::ActiveSessionExists => __('guest.table.active_session_exists'),
            GuestTableEntryState::PendingSessionExists => __('guest.table.pending_session_exists'),
            GuestTableEntryState::JoinRequestCreated => __('guest.table.join_request_sent'),
            GuestTableEntryState::GuestCreatedSessionsDisabled => __('guest.table.guest_created_sessions_disabled'),
            GuestTableEntryState::ServicePointUnavailable => __('guest.table.service_point_unavailable'),
        };
    }

    public function issueCodeForEntryState(GuestTableEntryState $state): string
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
    public function entryIssueCard(string $state, string $issueCode, string $message, string $primaryUrl): array
    {
        if ($state !== 'ready' || $issueCode === '') {
            return $this->emptyErrorCard();
        }

        return [
            'visible' => true,
            'state' => $issueCode,
            'tone' => $this->toneForErrorState($issueCode),
            'kicker' => $this->kickerForEntryIssueCode($issueCode),
            'title' => $this->titleForEntryIssueCode($issueCode),
            'message' => $message,
            'support_text' => $this->supportTextForEntryIssueCode($issueCode),
            'primary_label' => __('guest.table.return_to_qr_page'),
            'primary_url' => $primaryUrl,
            'secondary_label' => null,
            'secondary_url' => null,
        ];
    }

    /**
     * @param  list<string>  $existingNameKeys
     */
    private function uniqueGuestNameSuggestion(string $candidate, array $existingNameKeys): string
    {
        $baseCandidate = str($candidate)->squish()->toString();
        $uniqueCandidate = $baseCandidate;
        $counter = 2;

        while (in_array($this->normalizeGuestName($uniqueCandidate), $existingNameKeys, true) && $counter <= 99) {
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

    public function joinRequestIsExpired(TableSessionJoinRequest $joinRequest): bool
    {
        return $joinRequest->status === TableSessionJoinRequestStatus::Pending
            && $joinRequest->expires_at !== null
            && $joinRequest->expires_at->isPast();
    }

    private function tableSessionIsClosed(TableSession $tableSession): bool
    {
        return in_array($tableSession->status, [TableSessionStatus::Closed, TableSessionStatus::Cancelled], true);
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

    private function kickerForEntryIssueCode(string $issueCode): string
    {
        return match ($issueCode) {
            'session_closed',
            'invite_closed' => __('guest.table.table_closed'),
            'guest_rejected',
            'guest_removed',
            'guest_left' => __('guest.table.guest_access'),
            'invite_expired',
            'join_request_unavailable' => __('guest.table.invite_link'),
            'service_point_unavailable' => __('guest.table.place_unavailable'),
            'guest_created_sessions_disabled',
            'invite_unavailable' => __('guest.table.ask_staff'),
            default => __('guest.table.guest_access'),
        };
    }

    private function titleForEntryIssueCode(string $issueCode): string
    {
        return match ($issueCode) {
            'session_closed',
            'invite_closed' => __('guest.table.closed_session_title'),
            'guest_rejected' => __('guest.table.rejected_title'),
            'guest_removed' => __('guest.table.removed_title'),
            'guest_left' => __('guest.table.guest_left_title'),
            'invite_expired',
            'join_request_unavailable' => __('guest.table.invite_expired_title'),
            'service_point_unavailable' => __('qr.errors.service_point_unavailable.title'),
            'guest_created_sessions_disabled' => __('guest.table.ask_waiter_open_table_title'),
            'invite_unavailable' => __('guest.table.no_active_guest_approvers_title'),
            default => __('guest.table.guest_access_unavailable_title'),
        };
    }

    private function supportTextForEntryIssueCode(string $issueCode): string
    {
        return match ($issueCode) {
            'session_closed',
            'invite_closed' => __('guest.table.closed_session_description'),
            'guest_rejected',
            'guest_removed',
            'guest_left' => __('guest.table.guest_entry_blocked_description'),
            'invite_expired',
            'join_request_unavailable' => __('guest.table.invite_expired_description'),
            'service_point_unavailable' => __('qr.errors.service_point_unavailable.description'),
            'guest_created_sessions_disabled',
            'invite_unavailable' => __('guest.table.staff_help_description'),
            default => __('guest.table.staff_help'),
        };
    }
}
