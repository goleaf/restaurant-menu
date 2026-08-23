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

final class GuestEntryQueryService
{
    public function guestByToken(ServicePoint $servicePoint, string $guestToken): ?TableSessionGuest
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
                    ->select(['id', 'branch_id', 'service_point_id', 'status', 'ended_at'])
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

    public function joinRequestByToken(ServicePoint $servicePoint, string $guestToken): ?TableSessionJoinRequest
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
                    ->select(['id', 'branch_id', 'service_point_id', 'status', 'ended_at'])
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

    public function tableSessionByInviteToken(ServicePoint $servicePoint, string $inviteToken): ?TableSession
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
