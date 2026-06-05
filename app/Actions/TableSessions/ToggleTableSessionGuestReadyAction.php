<?php

namespace App\Actions\TableSessions;

use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Models\TableSessionGuest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ToggleTableSessionGuestReadyAction
{
    public function handle(TableSessionGuest $guest): TableSessionGuest
    {
        return DB::transaction(function () use ($guest): TableSessionGuest {
            $guest = $this->reloadGuest($guest);

            $this->ensureGuestCanToggleReady($guest);

            $guest
                ->forceFill(['ready_at' => $guest->ready_at === null ? now() : null])
                ->save();

            return $guest->refresh();
        });
    }

    private function reloadGuest(TableSessionGuest $guest): TableSessionGuest
    {
        return TableSessionGuest::query()
            ->select([
                'id',
                'table_session_id',
                'guest_name',
                'guest_token',
                'status',
                'ready_at',
                'joined_at',
                'left_at',
            ])
            ->with([
                'tableSession' => fn ($query) => $query->select([
                    'id',
                    'status',
                    'ended_at',
                ]),
            ])
            ->whereKey($guest->id)
            ->firstOrFail();
    }

    private function ensureGuestCanToggleReady(TableSessionGuest $guest): void
    {
        $tableSession = $guest->tableSession;

        if ($guest->status !== TableSessionGuestStatus::Active
            || $tableSession === null
            || in_array($tableSession->status, [TableSessionStatus::Closed, TableSessionStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'ready_status' => __('ui.actions.tablesessions.toggletablesessionguestreadyaction.tolko_aktivnyi'),
            ]);
        }
    }
}
