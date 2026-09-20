<?php

declare(strict_types=1);

namespace App\Actions\TableSessions;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Branches\ExecuteBranchSettingsChangeAction;
use App\Enums\AuditLogAction;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** @phpstan-import-type CleanupPreview from PreviewBranchSessionCleanupAction */
final class ConfirmBranchSessionCleanupAction
{
    public function __construct(
        private readonly ExecuteBranchSettingsChangeAction $execute,
        private readonly CleanupInactiveTableSessionsAction $cleanup,
        private readonly RecordAuditLogAction $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $preview
     * @return array<string, mixed>
     */
    public function handle(User $actor, Branch $branch, array $preview, string $requestId): array
    {
        if (($preview['actor_id'] ?? null) !== $actor->id || ($preview['branch_id'] ?? null) !== $branch->id
            || ! is_string($preview['signature'] ?? null)
            || ! hash_equals(PreviewBranchSessionCleanupAction::signature($preview), $preview['signature'])
            || ! is_array($preview['candidates'] ?? null) || count($preview['candidates']) > PreviewBranchSessionCleanupAction::LIMIT) {
            throw ValidationException::withMessages(['cleanup' => __('settings_center.cleanup.invalid_preview')]);
        }

        /** @var CleanupPreview $preview */
        return $this->execute->handle($actor, $branch, 'session_cleanup', $requestId, $preview, function (User $actor, Branch $branch) use ($preview): array {
            $settings = PreviewBranchSessionCleanupAction::settings($branch);
            PreviewBranchSessionCleanupAction::ensureValidSettings($settings);
            if (! hash_equals(PreviewBranchSessionCleanupAction::settingsFingerprint($settings), $preview['settings_fingerprint'])) {
                throw ValidationException::withMessages(['cleanup' => __('settings_center.cleanup.changed')]);
            }
            $expected = [];
            foreach ($preview['candidates'] as $candidate) {
                if ($candidate['outcome'] === 'pending_candidates') {
                    $expected[$candidate['id']] = $candidate['fingerprint'];
                }
            }
            $result = $this->cleanup->handle($branch->id, $expected, $actor);
            $result['has_more'] = $preview['has_more'];
            $result['preview_checked'] = $preview['summary']['checked'];
            $result['preview_active_warnings'] = $preview['summary']['active_warnings'];
            if ($result['pending_cancelled'] > 0) {
                $this->audit->handle(AuditLogAction::TableSessionInactivityCleanup, Branch::class, $branch->id,
                    actorUser: $actor, organizationId: $branch->organization_id, branchId: $branch->id,
                    oldValues: ['pending_session_ids' => $result['cancelled_session_ids']],
                    newValues: ['reason' => 'pending_session_expired', 'cancelled_session_ids' => $result['cancelled_session_ids'],
                        'pending_session_expire_minutes' => $settings->pending_session_expire_minutes ?? BranchSetting::defaults($branch)['pending_session_expire_minutes']]);
            }

            return $result;
        });
    }
}
