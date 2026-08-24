<?php

declare(strict_types=1);

namespace App\Services\PublicQr;

use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;
use App\Models\TableSessionServicePoint;
use Illuminate\Database\Eloquent\Builder;

final class GuestEntryQueryService
{
    public function guestByToken(ServicePoint $servicePoint, string $guestToken): ?TableSessionGuest
    {
        return $this->guestByTokenWithinCurrentQr($servicePoint, $guestToken)
            ?? $this->guestByTokenFromTransferredQr($servicePoint, $guestToken);
    }

    private function guestByTokenWithinCurrentQr(ServicePoint $servicePoint, string $guestToken): ?TableSessionGuest
    {
        return $this->guestByTokenQuery($guestToken)
            ->whereIn('table_session_id', TableSession::query()
                ->select('id')
                ->guestViewable()
                ->forQrServicePoint($servicePoint))
            ->first();
    }

    private function guestByTokenFromTransferredQr(ServicePoint $servicePoint, string $guestToken): ?TableSessionGuest
    {
        $guest = $this->guestByTokenQuery($guestToken)
            ->whereIn('table_session_id', TableSession::query()
                ->select('id')
                ->guestViewable()
                ->where('branch_id', $servicePoint->branch_id))
            ->first();

        return $guest?->tableSession?->wasTransferredFrom($servicePoint) === true
            ? $guest
            : null;
    }

    /** @return Builder<TableSessionGuest> */
    private function guestByTokenQuery(string $guestToken): Builder
    {
        return TableSessionGuest::query()
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
            ->with([
                'tableSession' => fn ($query) => $query
                    ->select(['id', 'branch_id', 'service_point_id', 'status', 'ended_at', 'metadata'])
                    ->with([
                        'servicePoint' => fn ($servicePointQuery) => $servicePointQuery
                            ->select(['id', 'branch_id', 'area_node_id', 'type', 'name', 'display_number', 'is_active'])
                            ->with(['areaNode' => fn ($areaQuery) => $areaQuery->select(['id', 'branch_id', 'name'])]),
                    ]),
            ])
            ->where('guest_token', $guestToken);
    }

    public function joinRequestByToken(ServicePoint $servicePoint, string $guestToken): ?TableSessionJoinRequest
    {
        return $this->joinRequestByTokenWithinCurrentQr($servicePoint, $guestToken)
            ?? $this->joinRequestByTokenFromTransferredQr($servicePoint, $guestToken);
    }

    private function joinRequestByTokenWithinCurrentQr(ServicePoint $servicePoint, string $guestToken): ?TableSessionJoinRequest
    {
        return $this->joinRequestByTokenQuery($guestToken)
            ->whereIn('table_session_id', TableSession::query()
                ->select('id')
                ->guestViewable()
                ->forQrServicePoint($servicePoint))
            ->first();
    }

    private function joinRequestByTokenFromTransferredQr(ServicePoint $servicePoint, string $guestToken): ?TableSessionJoinRequest
    {
        $joinRequest = $this->joinRequestByTokenQuery($guestToken)
            ->whereIn('table_session_id', TableSession::query()
                ->select('id')
                ->guestViewable()
                ->where('branch_id', $servicePoint->branch_id))
            ->first();

        return $joinRequest?->tableSession?->wasTransferredFrom($servicePoint) === true
            ? $joinRequest
            : null;
    }

    /** @return Builder<TableSessionJoinRequest> */
    private function joinRequestByTokenQuery(string $guestToken): Builder
    {
        return TableSessionJoinRequest::query()
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
            ->with([
                'tableSession' => fn ($query) => $query
                    ->select(['id', 'branch_id', 'service_point_id', 'status', 'ended_at', 'metadata'])
                    ->with([
                        'servicePoint' => fn ($servicePointQuery) => $servicePointQuery
                            ->select(['id', 'branch_id', 'area_node_id', 'type', 'name', 'display_number', 'is_active'])
                            ->with(['areaNode' => fn ($areaQuery) => $areaQuery->select(['id', 'branch_id', 'name'])]),
                    ]),
            ])
            ->where('guest_token', $guestToken);
    }

    public function tableSessionByInviteToken(ServicePoint $servicePoint, string $inviteToken): ?TableSession
    {
        $tableSession = $this->inviteTableSessionQuery($inviteToken)
            ->forQrServicePoint($servicePoint)
            ->first();

        if ($tableSession instanceof TableSession) {
            return $tableSession;
        }

        $tableSession = $this->inviteTableSessionQuery($inviteToken)
            ->where('branch_id', $servicePoint->branch_id)
            ->first();

        return $tableSession?->wasTransferredFrom($servicePoint) === true
            ? $tableSession
            : null;
    }

    /** @return Builder<TableSession> */
    private function inviteTableSessionQuery(string $inviteToken): Builder
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
                'guest_invite_token_hash',
                'guest_invite_created_at',
                'guest_invite_expires_at',
                'guest_invite_created_by_guest_id',
                'metadata',
            ])
            ->with([
                'servicePoint' => fn ($servicePointQuery) => $servicePointQuery
                    ->select(['id', 'branch_id', 'area_node_id', 'type', 'name', 'display_number', 'is_active'])
                    ->with(['areaNode' => fn ($areaQuery) => $areaQuery->select(['id', 'branch_id', 'name'])]),
            ])
            ->guestViewable()
            ->where('guest_invite_token_hash', hash('sha256', $inviteToken))
            ->where('guest_invite_expires_at', '>', now());
    }

    public function tableSessionForGuestNameConflict(ServicePoint $servicePoint): ?TableSession
    {
        return $this->tableSessionForGuestNameConflictByStatus($servicePoint, TableSessionStatus::Active)
            ?? $this->tableSessionForGuestNameConflictByStatus($servicePoint, TableSessionStatus::Pending);
    }

    public function joinRequestByIdAndToken(int $joinRequestId, string $guestToken): ?TableSessionJoinRequest
    {
        return TableSessionJoinRequest::query()
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
            ->with([
                'tableSession' => fn ($query) => $query->select(['id', 'branch_id', 'service_point_id', 'status', 'ended_at']),
            ])
            ->whereKey($joinRequestId)
            ->where('guest_token', $guestToken)
            ->first();
    }

    public function joinRequestByCurrentState(
        int $joinRequestId,
        int $tableSessionId,
        string $guestName,
    ): ?TableSessionJoinRequest {
        return TableSessionJoinRequest::query()
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
            ->with([
                'tableSession' => fn ($query) => $query->select(['id', 'branch_id', 'service_point_id', 'status', 'ended_at']),
            ])
            ->whereKey($joinRequestId)
            ->where('table_session_id', $tableSessionId)
            ->where('guest_name', $guestName)
            ->first();
    }

    public function guestForJoinRequest(TableSessionJoinRequest $joinRequest): ?TableSessionGuest
    {
        return TableSessionGuest::query()
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
            ->with([
                'tableSession' => fn ($query) => $query->select(['id', 'branch_id', 'service_point_id', 'status', 'ended_at']),
            ])
            ->where('table_session_id', $joinRequest->table_session_id)
            ->where('guest_token', $joinRequest->guest_token)
            ->first();
    }

    public function servicePointForTableSession(TableSession $tableSession): ServicePoint
    {
        $tableSession->loadMissing([
            'servicePoint' => fn ($query) => $query
                ->select(['id', 'branch_id', 'area_node_id', 'type', 'name', 'display_number', 'is_active'])
                ->with(['areaNode' => fn ($areaQuery) => $areaQuery->select(['id', 'branch_id', 'name'])]),
        ]);

        return $tableSession->servicePoint;
    }

    /** @return list<string> */
    public function activeGuestNames(int $tableSessionId): array
    {
        return TableSessionGuest::query()
            ->select(['id', 'table_session_id', 'guest_name', 'status'])
            ->where('table_session_id', $tableSessionId)
            ->where('status', TableSessionGuestStatus::Active->value)
            ->orderBy('guest_name')
            ->orderBy('id')
            ->limit(100)
            ->pluck('guest_name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    private function tableSessionForGuestNameConflictByStatus(
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
}
