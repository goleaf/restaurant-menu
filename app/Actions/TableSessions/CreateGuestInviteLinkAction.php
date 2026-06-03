<?php

namespace App\Actions\TableSessions;

use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateGuestInviteLinkAction
{
    public function handle(TableSession $tableSession, TableSessionGuest $createdByGuest): TableSession
    {
        return DB::transaction(function () use ($tableSession, $createdByGuest): TableSession {
            $tableSession = $this->reloadTableSession($tableSession);
            $createdByGuest = $this->reloadGuest($createdByGuest);

            $this->ensureSessionCanInvite($tableSession);
            $this->ensureActiveGuestOwnsSession($tableSession, $createdByGuest);

            if (is_string($tableSession->guest_invite_token) && strlen($tableSession->guest_invite_token) === 64) {
                return $tableSession;
            }

            $tableSession
                ->forceFill([
                    'guest_invite_token' => $this->newUniqueInviteToken(),
                    'guest_invite_created_at' => now(),
                    'guest_invite_created_by_guest_id' => $createdByGuest->id,
                ])
                ->save();

            return $tableSession->refresh();
        });
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
                'guest_invite_token',
                'guest_invite_created_at',
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
            ])
            ->whereKey($tableSession->id)
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
            ->firstOrFail();
    }

    private function ensureSessionCanInvite(TableSession $tableSession): void
    {
        if (in_array($tableSession->status, [TableSessionStatus::Closed, TableSessionStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'guest_invite' => __('Эта сессия стола уже закрыта. Новых гостей пригласить нельзя.'),
            ]);
        }

        $settings = $this->settingsFor($tableSession->branch);

        if (! $settings->allow_guest_invite_links) {
            throw ValidationException::withMessages([
                'guest_invite' => __('Приглашения гостей по ссылке отключены для этого филиала.'),
            ]);
        }
    }

    private function ensureActiveGuestOwnsSession(TableSession $tableSession, TableSessionGuest $guest): void
    {
        if ($guest->table_session_id !== $tableSession->id || $guest->status !== TableSessionGuestStatus::Active) {
            throw ValidationException::withMessages([
                'guest_invite' => __('Только активный гость за этим столом может пригласить нового гостя.'),
            ]);
        }
    }

    private function settingsFor(?Branch $branch): BranchSetting
    {
        if (! $branch instanceof Branch) {
            throw ValidationException::withMessages([
                'guest_invite' => __('Не удалось проверить настройки филиала для приглашения.'),
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
        } while (TableSession::query()->where('guest_invite_token', $token)->exists());

        return $token;
    }
}
