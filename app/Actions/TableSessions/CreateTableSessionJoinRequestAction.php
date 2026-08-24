<?php

namespace App\Actions\TableSessions;

use App\Enums\SupportedLocale;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionJoinRequestStatus;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;
use App\Notifications\JoinRequestCreatedNotification;
use App\Support\PlainText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateTableSessionJoinRequestAction
{
    private const MAX_PENDING_REQUESTS = 20;

    public function handle(
        TableSession $tableSession,
        string $guestName,
        ?string $guestToken = null,
        ?string $inviteToken = null,
        ?string $locale = null,
    ): ?TableSessionJoinRequest {
        $normalizedGuestName = PlainText::required($guestName, 80, squish: true);
        $guestToken = $this->guestToken($guestToken);
        $locale = SupportedLocale::normalize($locale);

        $joinRequest = DB::transaction(function () use ($tableSession, $normalizedGuestName, $guestToken, $inviteToken, $locale): ?TableSessionJoinRequest {
            $tableSession = $this->reloadTableSession($tableSession);

            if (! $tableSession->status->allowsGuestParticipation()) {
                return null;
            }

            if (! $tableSession->servicePoint->is_active) {
                return null;
            }

            if ($inviteToken !== null && ! $this->inviteTokenIsCurrent($tableSession, $inviteToken)) {
                return null;
            }

            if (! $tableSession->activeGuests()->exists()) {
                return null;
            }

            $existingJoinRequest = $tableSession->joinRequests()
                ->select([
                    'id',
                    'table_session_id',
                    'guest_name',
                    'guest_token',
                    'locale',
                    'status',
                    'approved_by_guest_id',
                    'rejected_by_guest_id',
                    'approved_by_user_id',
                    'rejected_by_user_id',
                    'expires_at',
                ])
                ->where('guest_token', $guestToken)
                ->first();

            if ($existingJoinRequest instanceof TableSessionJoinRequest) {
                return $existingJoinRequest;
            }

            $pendingRequestCount = $tableSession->joinRequests()
                ->where('status', TableSessionJoinRequestStatus::Pending->value)
                ->where(function ($query): void {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->count();

            if ($pendingRequestCount >= self::MAX_PENDING_REQUESTS) {
                return null;
            }

            $joinRequest = $tableSession->joinRequests()->make([
                'guest_name' => $normalizedGuestName,
                'locale' => $locale,
                'expires_at' => now()->addMinutes(30),
            ]);
            $joinRequest->forceFill([
                'guest_token' => $guestToken,
                'status' => TableSessionJoinRequestStatus::Pending,
            ])->save();

            return $joinRequest;
        }, 5);

        if ($joinRequest instanceof TableSessionJoinRequest && $joinRequest->wasRecentlyCreated) {
            DB::afterCommit(fn () => $this->notifyActiveGuests($joinRequest));
        }

        return $joinRequest;
    }

    private function reloadTableSession(TableSession $tableSession): TableSession
    {
        return TableSession::query()
            ->select([
                'id',
                'branch_id',
                'service_point_id',
                'active_service_point_id',
                'pending_service_point_id',
                'opened_by_user_id',
                'opened_by_guest_id',
                'status',
                'source',
                'started_at',
                'ended_at',
                'closed_by_user_id',
                'guest_invite_token_hash',
                'guest_invite_expires_at',
                'metadata',
                'created_at',
                'updated_at',
            ])
            ->with([
                'servicePoint' => fn ($query) => $query->select([
                    'id',
                    'is_active',
                ]),
            ])
            ->whereKey($tableSession->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function inviteTokenIsCurrent(TableSession $tableSession, string $inviteToken): bool
    {
        $storedHash = $tableSession->getAttribute('guest_invite_token_hash');

        return is_string($storedHash)
            && strlen($inviteToken) === 64
            && $tableSession->guest_invite_expires_at?->isFuture() === true
            && hash_equals($storedHash, hash('sha256', $inviteToken));
    }

    private function notifyActiveGuests(TableSessionJoinRequest $joinRequest): void
    {
        $recipients = TableSessionGuest::query()
            ->select([
                'id',
                'table_session_id',
                'guest_name',
                'guest_token',
                'locale',
                'status',
                'joined_at',
                'left_at',
            ])
            ->where('table_session_id', $joinRequest->table_session_id)
            ->where('status', TableSessionGuestStatus::Active->value)
            ->orderBy('guest_name')
            ->orderBy('id')
            ->limit(100)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new JoinRequestCreatedNotification($joinRequest));
    }

    private function guestToken(?string $guestToken): string
    {
        $guestToken ??= Str::random(64);

        if (strlen($guestToken) !== 64 || ! ctype_alnum($guestToken)) {
            throw new InvalidArgumentException('Guest credentials must contain 64 alphanumeric characters.');
        }

        return $guestToken;
    }
}
