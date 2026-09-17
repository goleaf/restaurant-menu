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

final class SaveBranchScheduleExceptionsAction
{
    public function __construct(private readonly RunAvailabilityCommandAction $commands, private readonly RecordAuditLogAction $audit) {}

    /** @param array<array-key, mixed> $exceptions */
    public function handle(User $actor, Branch $branch, array $exceptions, int $expectedVersion, string $expectedTimezone, string $requestId, ?string $expectedContext = null): Branch
    {
        $exceptions = ScheduleRules::exceptions($exceptions);
        $result = $this->commands->handle($actor, $branch, 'branch_exceptions', $branch->id, $requestId, compact('exceptions', 'expectedVersion', 'expectedTimezone', 'expectedContext'),
            static fn (User $currentActor, Branch $currentBranch) => Gate::forUser($currentActor)->authorize('manageSettings', $currentBranch),
            function (User $currentActor, Branch $currentBranch) use ($exceptions, $expectedVersion, $expectedTimezone, $expectedContext): array {
                if ($currentBranch->opening_hours_version !== $expectedVersion || $currentBranch->timezone !== $expectedTimezone) {
                    throw ValidationException::withMessages(['expectedVersion' => __('availability.errors.stale')]);
                }
                if ($expectedContext !== null && ! hash_equals($expectedContext, AvailabilityDependencyFingerprint::branch($currentBranch))) {
                    throw ValidationException::withMessages(['expectedVersion' => __('availability.errors.stale')]);
                }
                $exceptions = ScheduleRules::exceptions($exceptions, $currentBranch->timezone);
                $before = $currentBranch->scheduleExceptions()->select(['local_date', 'is_closed', 'intervals'])->get()->toArray();
                if (Branch::query()->whereKey($currentBranch->id)->where('opening_hours_version', $expectedVersion)->where('timezone', $expectedTimezone)
                    ->update(['opening_hours_version' => $expectedVersion + 1]) !== 1) {
                    throw ValidationException::withMessages(['expectedVersion' => __('availability.errors.stale')]);
                }
                $currentBranch->scheduleExceptions()->delete();
                foreach ($exceptions as $exception) {
                    $row = $currentBranch->scheduleExceptions()->create($exception);
                    if (! $row->exists) {
                        throw new RuntimeException('Schedule exception could not be stored.');
                    }
                }
                $currentBranch->forceFill(['opening_hours_version' => $expectedVersion + 1]);
                if (! $currentBranch->save()) {
                    throw new RuntimeException('Schedule revision could not be stored.');
                }
                $this->audit->handle(AuditLogAction::BranchAvailabilityChanged, 'branch', $currentBranch->id,
                    actorUser: $currentActor, organizationId: $currentBranch->organization_id, branchId: $currentBranch->id,
                    oldValues: ['scope' => 'date_exceptions', 'exceptions' => $before], newValues: ['scope' => 'date_exceptions', 'exceptions' => $exceptions]);

                return ['id' => $currentBranch->id, 'version' => $expectedVersion + 1];
            });

        return Branch::query()->whereKey($result['id'])->firstOrFail();
    }
}
