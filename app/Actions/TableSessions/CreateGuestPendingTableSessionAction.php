<?php

namespace App\Actions\TableSessions;

use App\Enums\GuestTableEntryState;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionSource;
use App\Enums\TableSessionStatus;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;
use App\Models\TableSessionServicePoint;
use App\Support\PlainText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateGuestPendingTableSessionAction
{
    public function __construct(
        public CreateTableSessionJoinRequestAction $createTableSessionJoinRequest,
    ) {}

    /**
     * @return array{state: GuestTableEntryState, table_session: TableSession|null, guest: TableSessionGuest|null, join_request: TableSessionJoinRequest|null}
     */
    public function handle(ServicePoint $servicePoint, string $guestName): array
    {
        $normalizedGuestName = PlainText::required($guestName, 80, squish: true);

        return DB::transaction(function () use ($servicePoint, $normalizedGuestName): array {
            $servicePoint = $this->reloadServicePoint($servicePoint);

            if (! $servicePoint->is_active) {
                return $this->result(GuestTableEntryState::ServicePointUnavailable);
            }

            $activeTableSession = $this->findTableSession($servicePoint, TableSessionStatus::Active);

            if ($activeTableSession instanceof TableSession) {
                $joinRequest = $this->createTableSessionJoinRequest->handle($activeTableSession, $normalizedGuestName);

                return $this->result(
                    $joinRequest instanceof TableSessionJoinRequest
                        ? GuestTableEntryState::JoinRequestCreated
                        : GuestTableEntryState::ActiveSessionExists,
                    $activeTableSession,
                    joinRequest: $joinRequest,
                );
            }

            $pendingTableSession = $this->findTableSession($servicePoint, TableSessionStatus::Pending);

            if ($pendingTableSession instanceof TableSession) {
                $joinRequest = $this->createTableSessionJoinRequest->handle($pendingTableSession, $normalizedGuestName);

                return $this->result(
                    $joinRequest instanceof TableSessionJoinRequest
                        ? GuestTableEntryState::JoinRequestCreated
                        : GuestTableEntryState::PendingSessionExists,
                    $pendingTableSession,
                    joinRequest: $joinRequest,
                );
            }

            $settings = $this->settingsFor($servicePoint->branch);

            if (! $settings->allow_guest_created_sessions) {
                return $this->result(GuestTableEntryState::GuestCreatedSessionsDisabled);
            }

            $tableSession = $servicePoint->tableSessions()->make([
                'source' => TableSessionSource::GuestCreated,
                'started_at' => now(),
                'metadata' => [],
            ]);
            $tableSession->forceFill([
                'branch_id' => $servicePoint->branch_id,
                'status' => TableSessionStatus::Pending,
            ])->save();

            $guest = $tableSession->guests()->make([
                'guest_name' => $normalizedGuestName,
                'joined_at' => now(),
                'metadata' => [],
            ]);
            $guest->forceFill([
                'guest_token' => Str::random(64),
                'status' => TableSessionGuestStatus::Active,
            ])->save();

            $tableSession
                ->forceFill(['opened_by_guest_id' => $guest->id])
                ->save();

            return $this->result(
                GuestTableEntryState::PendingSessionCreated,
                $tableSession->refresh(),
                $guest->refresh(),
            );
        });
    }

    private function reloadServicePoint(ServicePoint $servicePoint): ServicePoint
    {
        return ServicePoint::query()
            ->select([
                'id',
                'branch_id',
                'area_node_id',
                'type',
                'name',
                'display_number',
                'status',
                'is_active',
            ])
            ->with([
                'branch' => fn ($query) => $query
                    ->select([
                        'id',
                        'organization_id',
                        'brand_id',
                        'name',
                        'currency',
                    ])
                    ->with([
                        'settings' => fn ($query) => $query->select([
                            'id',
                            'branch_id',
                            'allow_guest_created_sessions',
                            'default_currency',
                            'default_language',
                        ]),
                    ]),
            ])
            ->whereKey($servicePoint->id)
            ->firstOrFail();
    }

    private function findTableSession(ServicePoint $servicePoint, TableSessionStatus $status): ?TableSession
    {
        $tableSession = $servicePoint
            ->tableSessions()
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
                'metadata',
                'created_at',
                'updated_at',
            ])
            ->where('status', $status->value)
            ->orderBy('started_at')
            ->orderBy('id')
            ->first();

        if ($tableSession instanceof TableSession || $status !== TableSessionStatus::Active) {
            return $tableSession;
        }

        return $this->findLinkedActiveTableSession($servicePoint);
    }

    private function findLinkedActiveTableSession(ServicePoint $servicePoint): ?TableSession
    {
        $link = TableSessionServicePoint::query()
            ->select(['id', 'table_session_id', 'service_point_id', 'unlinked_at'])
            ->with([
                'tableSession' => fn ($query) => $query
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
                        'metadata',
                        'created_at',
                        'updated_at',
                    ])
                    ->where('status', TableSessionStatus::Active->value),
            ])
            ->active()
            ->where('service_point_id', $servicePoint->id)
            ->first();

        return $link?->tableSession;
    }

    private function settingsFor(Branch $branch): BranchSetting
    {
        if ($branch->settings instanceof BranchSetting) {
            return $branch->settings;
        }

        return $branch->settings()->create(BranchSetting::defaults($branch));
    }

    /**
     * @return array{state: GuestTableEntryState, table_session: TableSession|null, guest: TableSessionGuest|null, join_request: TableSessionJoinRequest|null}
     */
    private function result(
        GuestTableEntryState $state,
        ?TableSession $tableSession = null,
        ?TableSessionGuest $guest = null,
        ?TableSessionJoinRequest $joinRequest = null,
    ): array {
        return [
            'state' => $state,
            'table_session' => $tableSession,
            'guest' => $guest,
            'join_request' => $joinRequest,
        ];
    }
}
