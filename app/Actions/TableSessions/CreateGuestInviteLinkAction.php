<?php

declare(strict_types=1);

namespace App\Actions\TableSessions;

use App\Enums\TableSessionGuestStatus;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateGuestInviteLinkAction
{
    private const EXPIRY_MINUTES = 30;

    public function handle(TableSession $tableSession, TableSessionGuest $createdByGuest): CreatedGuestInviteLink
    {
        return DB::transaction(function () use ($tableSession, $createdByGuest): CreatedGuestInviteLink {
            $tableSession = $this->reloadTableSession($tableSession);
            $createdByGuest = $this->reloadGuest($createdByGuest);

            $this->ensureSessionCanInvite($tableSession);
            $this->ensureActiveGuestOwnsSession($tableSession, $createdByGuest);

            $token = $this->newUniqueInviteToken();
            $expiresAt = now()->addMinutes(self::EXPIRY_MINUTES);

            $tableSession
                ->forceFill([
                    'guest_invite_token_hash' => hash('sha256', $token),
                    'guest_invite_created_at' => now(),
                    'guest_invite_expires_at' => $expiresAt,
                    'guest_invite_created_by_guest_id' => $createdByGuest->id,
                ])
                ->save();

            return new CreatedGuestInviteLink($tableSession->refresh(), $token, $expiresAt);
        }, 3);
    }

    private function reloadTableSession(TableSession $tableSession): TableSession
    {
        return TableSession::query()
            ->select([
                'id',
                'branch_id',
                'service_point_id',
                'opened_by_guest_id',
                'status',
                'started_at',
                'ended_at',
                'guest_invite_token_hash',
                'guest_invite_created_at',
                'guest_invite_expires_at',
                'guest_invite_created_by_guest_id',
            ])
            ->with([
                'branch' => fn ($query) => $query
                    ->select(['id'])
                    ->with([
                        'settings' => fn ($query) => $query->select([
                            'id',
                            'branch_id',
                            'allow_guest_invite_links',
                        ]),
                    ]),
                'servicePoint' => fn ($query) => $query->select([
                    'id',
                    'is_active',
                ]),
            ])
            ->whereKey($tableSession->id)
            ->lockForUpdate()
            ->firstOrFail();
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
                'joined_at',
                'left_at',
            ])
            ->whereKey($guest->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureSessionCanInvite(TableSession $tableSession): void
    {
        if (! $tableSession->status->allowsGuestParticipation()) {
            throw ValidationException::withMessages([
                'guest_invite' => __('ui.actions.tablesessions.createguestinvitelinkaction.eta_sessiia_stola_uze'),
            ]);
        }

        if (! $tableSession->servicePoint->is_active) {
            throw ValidationException::withMessages([
                'guest_invite' => __('ui.actions.tablesessions.createguestinvitelinkaction.eto_mesto_seicas_nedos'),
            ]);
        }

        $settings = $this->settingsFor($tableSession->branch);

        if (! $settings->allow_guest_invite_links) {
            throw ValidationException::withMessages([
                'guest_invite' => __('ui.actions.tablesessions.createguestinvitelinkaction.priglaseniia_gostei_po'),
            ]);
        }
    }

    private function ensureActiveGuestOwnsSession(TableSession $tableSession, TableSessionGuest $guest): void
    {
        if ($guest->table_session_id !== $tableSession->id || $guest->status !== TableSessionGuestStatus::Active) {
            throw ValidationException::withMessages([
                'guest_invite' => __('ui.actions.tablesessions.createguestinvitelinkaction.tolko_aktivnyi_gost_za'),
            ]);
        }
    }

    private function settingsFor(?Branch $branch): BranchSetting
    {
        if (! $branch instanceof Branch) {
            throw ValidationException::withMessages([
                'guest_invite' => __('ui.actions.tablesessions.createguestinvitelinkaction.ne_udalos_proverit_nas'),
            ]);
        }

        if ($branch->settings instanceof BranchSetting) {
            return $branch->settings;
        }

        return $branch->settings()->create(BranchSetting::defaults($branch));
    }

    private function newUniqueInviteToken(): string
    {
        do {
            $token = Str::random(64);
        } while (TableSession::query()->where('guest_invite_token_hash', hash('sha256', $token))->exists());

        return $token;
    }
}
