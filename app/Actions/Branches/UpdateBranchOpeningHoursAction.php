<?php

declare(strict_types=1);

namespace App\Actions\Branches;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Availability\RunAvailabilityCommandAction;
use App\Enums\AuditLogAction;
use App\Models\Branch;
use App\Models\User;
use App\Support\Availability\AvailabilityDependencyFingerprint;
use App\Support\Validation\Availability\ScheduleRules;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class UpdateBranchOpeningHoursAction
{
    public function __construct(private readonly RunAvailabilityCommandAction $commands, private readonly RecordAuditLogAction $audit) {}

    /** @param array<array-key, mixed> $weeklyHours */
    public function handle(User $actor, Branch $branch, array $weeklyHours, bool $isConfigured, int $expectedVersion, string $expectedTimezone, string $requestId, ?string $expectedContext = null): Branch
    {
        $days = ScheduleRules::branch($weeklyHours, $isConfigured);
        $payload = compact('days', 'isConfigured', 'expectedVersion', 'expectedTimezone', 'expectedContext');
        $result = $this->commands->handle($actor, $branch, 'branch_hours', $branch->id, $requestId, $payload,
            static fn (User $currentActor, Branch $currentBranch) => Gate::forUser($currentActor)->authorize('manageSettings', $currentBranch),
            function (User $currentActor, Branch $currentBranch) use ($days, $expectedVersion, $expectedTimezone, $expectedContext): array {
                if ($currentBranch->opening_hours_version !== $expectedVersion || $currentBranch->timezone !== $expectedTimezone) {
                    throw ValidationException::withMessages(['expectedVersion' => __('availability.errors.stale')]);
                }
                if ($expectedContext !== null && ! hash_equals($expectedContext, AvailabilityDependencyFingerprint::branch($currentBranch))) {
                    throw ValidationException::withMessages(['expectedVersion' => __('availability.errors.stale')]);
                }
                $before = $currentBranch->openingHours()->select(['day_of_week', 'is_closed', 'opens_at', 'closes_at', 'sort_order'])->get()->toArray();
                if (Branch::query()->whereKey($currentBranch->id)->where('opening_hours_version', $expectedVersion)->where('timezone', $expectedTimezone)
                    ->update(['opening_hours_version' => $expectedVersion + 1]) !== 1) {
                    throw ValidationException::withMessages(['expectedVersion' => __('availability.errors.stale')]);
                }
                $currentBranch->openingHours()->delete();
                foreach ($days as $day) {
                    $intervals = $day['is_closed'] ? [['opens_at' => null, 'closes_at' => null]] : $day['intervals'];
                    foreach ($intervals as $index => $interval) {
                        $row = $currentBranch->openingHours()->create(['day_of_week' => $day['day_of_week'], 'is_closed' => $day['is_closed'],
                            'opens_at' => $interval['opens_at'], 'closes_at' => $interval['closes_at'], 'sort_order' => ($index + 1) * 10]);
                        if (! $row->exists) {
                            throw new RuntimeException('Opening interval could not be stored.');
                        }
                    }
                }
                $currentBranch->forceFill(['opening_hours_version' => $expectedVersion + 1]);
                if (! $currentBranch->save()) {
                    throw new RuntimeException('Schedule revision could not be stored.');
                }
                $this->audit->handle(AuditLogAction::BranchAvailabilityChanged, 'branch', $currentBranch->id,
                    actorUser: $currentActor, organizationId: $currentBranch->organization_id, branchId: $currentBranch->id,
                    oldValues: ['scope' => 'weekly_hours', 'intervals' => $before], newValues: ['scope' => 'weekly_hours', 'days' => $days]);

                return ['id' => $currentBranch->id, 'version' => $expectedVersion + 1];
            });

        return Branch::query()->whereKey($result['id'])->firstOrFail();
    }
}
