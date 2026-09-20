<?php

declare(strict_types=1);

namespace App\Actions\TableSessions;

use App\Enums\OrderStatus;
use App\Enums\TableSessionStatus;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type CleanupCandidate array{id: int, fingerprint: string, outcome: string, minutes_inactive: int, last_activity_at: string|null}
 * @phpstan-type CleanupPreview array{actor_id: int, branch_id: int, settings_fingerprint: string, generated_at: string, candidates: list<CleanupCandidate>, summary: array<string, int>, has_more: bool, signature: string}
 */
final class PreviewBranchSessionCleanupAction
{
    public const LIMIT = 100;

    public function __construct(private readonly BuildTableSessionInactivityStateAction $buildInactivityState) {}

    /** @return CleanupPreview */
    public function handle(User $actor, Branch $branch): array
    {
        $actor = User::query()->select(['id'])->whereKey($actor->id)->firstOrFail();
        $branch = Branch::query()->select(['id', 'organization_id', 'brand_id', 'timezone', 'is_active', 'deleted_at'])
            ->where('organization_id', $branch->getRawOriginal('organization_id'))
            ->where('brand_id', $branch->getRawOriginal('brand_id'))->whereKey($branch->id)->firstOrFail();
        Gate::forUser($actor)->authorize('manageSettings', $branch);
        $settings = self::settings($branch);
        self::ensureValidSettings($settings);
        $branch->setRelation('settings', $settings);
        $sessions = TableSession::query()
            ->select(['id', 'branch_id', 'service_point_id', 'status', 'started_at', 'created_at', 'updated_at'])
            ->withInactivityActivity()
            ->withExists([
                'orders as has_any_orders',
                'orders as has_unpaid_orders' => fn ($query) => $query->whereNotIn('status', [OrderStatus::Paid->value, OrderStatus::Closed->value, OrderStatus::Cancelled->value]),
                'draftOrders as has_draft_orders',
            ])
            ->where('branch_id', $branch->id)
            ->whereIn('status', [TableSessionStatus::Pending->value, TableSessionStatus::Active->value])
            ->orderBy('updated_at')->orderBy('id')->limit(self::LIMIT + 1)->get();
        $summary = ['checked' => 0, 'pending_candidates' => 0, 'active_warnings' => 0, 'skipped_unpaid_orders' => 0, 'skipped_existing_orders' => 0, 'skipped_existing_drafts' => 0, 'skipped_recent' => 0];
        $candidates = [];

        foreach ($sessions->take(self::LIMIT) as $session) {
            $session->setRelation('branch', $branch);
            $state = $this->buildInactivityState->handle($session, $settings);
            $outcome = match (true) {
                $state['should_warn'] => 'active_warnings',
                ! $state['should_expire_pending'] => 'skipped_recent',
                (bool) $session->getAttribute('has_unpaid_orders') => 'skipped_unpaid_orders',
                (bool) $session->getAttribute('has_any_orders') => 'skipped_existing_orders',
                (bool) $session->getAttribute('has_draft_orders') => 'skipped_existing_drafts',
                default => 'pending_candidates',
            };
            $summary['checked']++;
            $summary[$outcome]++;
            $candidates[] = ['id' => $session->id, 'fingerprint' => CleanupInactiveTableSessionsAction::fingerprint($session), 'outcome' => $outcome,
                'minutes_inactive' => $state['minutes_inactive'], 'last_activity_at' => $state['last_activity_at']];
        }

        $preview = ['actor_id' => $actor->id, 'branch_id' => $branch->id, 'settings_fingerprint' => self::settingsFingerprint($settings),
            'generated_at' => now()->toISOString(), 'candidates' => $candidates, 'summary' => $summary, 'has_more' => $sessions->count() > self::LIMIT];

        return [...$preview, 'signature' => self::signature($preview)];
    }

    public static function settings(Branch $branch): ?BranchSetting
    {
        return BranchSetting::query()->select(['id', 'branch_id', 'inactivity_warning_minutes', 'pending_session_expire_minutes'])
            ->where('branch_id', $branch->id)->first();
    }

    public static function settingsFingerprint(?BranchSetting $settings): string
    {
        $fields = ['inactivity_warning_minutes', 'pending_session_expire_minutes'];
        $values = $settings?->only($fields) ?? array_intersect_key(BranchSetting::defaults(), array_flip($fields));

        return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
    }

    public static function ensureValidSettings(?BranchSetting $settings): void
    {
        if ($settings === null) {
            return;
        }
        foreach (['inactivity_warning_minutes', 'pending_session_expire_minutes'] as $field) {
            if (filter_var($settings->getRawOriginal($field), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1440]]) === false) {
                throw ValidationException::withMessages(['cleanup' => __('settings_center.cleanup.invalid_thresholds')]);
            }
        }
    }

    /** @param array<string, mixed> $preview */
    public static function signature(array $preview): string
    {
        unset($preview['signature']);

        return hash_hmac('sha256', json_encode($preview, JSON_THROW_ON_ERROR), (string) config('app.key'));
    }
}
