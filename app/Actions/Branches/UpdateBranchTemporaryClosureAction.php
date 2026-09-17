<?php

declare(strict_types=1);

namespace App\Actions\Branches;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Availability\RunAvailabilityCommandAction;
use App\Enums\AuditLogAction;
use App\Models\Branch;
use App\Models\User;
use App\Support\Availability\AvailabilityDependencyFingerprint;
use App\Support\PlainText;
use App\Support\Validation\Availability\BranchLocalDateTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class UpdateBranchTemporaryClosureAction
{
    public function __construct(private readonly RunAvailabilityCommandAction $commands, private readonly RecordAuditLogAction $audit) {}

    public function handle(User $actor, Branch $branch, bool $closed, ?string $reason, ?string $untilLocal, int $expectedVersion, string $expectedTimezone, string $requestId, ?int $durationMinutes = null, ?string $expectedContext = null): Branch
    {
        $values = Validator::make(compact('closed', 'reason', 'untilLocal', 'expectedVersion', 'expectedTimezone', 'durationMinutes'), [
            'closed' => ['boolean'],
            'reason' => [Rule::requiredIf($closed), 'nullable', 'string', 'max:255'],
            'untilLocal' => ['nullable', 'string', 'max:16'],
            'expectedVersion' => ['required', 'integer', 'min:0'],
            'expectedTimezone' => ['required', 'timezone'],
            'durationMinutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
        ], attributes: [
            'closed' => __('availability.fields.closed'),
            'reason' => __('availability.fields.reason'),
            'untilLocal' => __('availability.fields.deadline'),
            'expectedVersion' => __('availability.fields.version'),
            'expectedTimezone' => __('availability.fields.timezone'),
            'durationMinutes' => __('availability.fields.duration'),
        ])->validate();
        if ($untilLocal !== null && $untilLocal !== '' && $durationMinutes !== null) {
            throw ValidationException::withMessages(['untilLocal' => __('availability.errors.deadline_conflict')]);
        }
        $result = $this->commands->handle($actor, $branch, 'branch_pause', $branch->id, $requestId, [...$values, 'expectedContext' => $expectedContext],
            static fn (User $currentActor, Branch $currentBranch) => Gate::forUser($currentActor)->authorize('manageSettings', $currentBranch),
            function (User $currentActor, Branch $currentBranch) use ($closed, $reason, $untilLocal, $durationMinutes, $expectedVersion, $expectedTimezone, $expectedContext): array {
                if ($currentBranch->pause_version !== $expectedVersion || $currentBranch->timezone !== $expectedTimezone) {
                    throw ValidationException::withMessages(['expectedVersion' => __('availability.errors.stale')]);
                }
                if ($expectedContext !== null && ! hash_equals($expectedContext, AvailabilityDependencyFingerprint::branch($currentBranch))) {
                    throw ValidationException::withMessages(['expectedVersion' => __('availability.errors.stale')]);
                }
                $instant = CarbonImmutable::now();
                $until = null;
                if ($closed && $durationMinutes !== null) {
                    $until = $instant->addMinutes($durationMinutes);
                } elseif ($closed && $untilLocal !== null && $untilLocal !== '') {
                    Validator::make(['untilLocal' => $untilLocal], ['untilLocal' => [new BranchLocalDateTime($expectedTimezone, $instant)]])->validate();
                    $until = BranchLocalDateTime::parse($untilLocal, $expectedTimezone);
                }
                $before = ['scope' => 'pause', 'is_temporarily_closed' => $currentBranch->is_temporarily_closed,
                    'temporary_closed_reason' => $currentBranch->temporary_closed_reason, 'temporary_closed_until' => $currentBranch->getRawOriginal('temporary_closed_until')];
                $nextReason = $closed ? PlainText::optional($reason, 255, squish: true) : null;
                if ($currentBranch->is_temporarily_closed === $closed && $currentBranch->temporary_closed_reason === $nextReason
                    && $currentBranch->getRawOriginal('temporary_closed_until') === $until?->utc()->format('Y-m-d H:i:s')) {
                    return ['id' => $currentBranch->id, 'version' => $expectedVersion];
                }
                if (Branch::query()->whereKey($currentBranch->id)->where('pause_version', $expectedVersion)->where('timezone', $expectedTimezone)
                    ->update(['pause_version' => $expectedVersion + 1]) !== 1) {
                    throw ValidationException::withMessages(['expectedVersion' => __('availability.errors.stale')]);
                }
                $currentBranch->forceFill(['is_temporarily_closed' => $closed, 'temporary_closed_reason' => $closed ? PlainText::optional($reason, 255, squish: true) : null,
                    'temporary_closed_until' => $until?->utc()->format('Y-m-d H:i:s'), 'pause_version' => $expectedVersion + 1]);
                if (! $currentBranch->save()) {
                    throw new RuntimeException('Branch pause could not be stored.');
                }
                $this->audit->handle(AuditLogAction::BranchAvailabilityChanged, 'branch', $currentBranch->id,
                    actorUser: $currentActor, organizationId: $currentBranch->organization_id, branchId: $currentBranch->id,
                    oldValues: $before, newValues: ['scope' => 'pause', 'is_temporarily_closed' => $closed,
                        'temporary_closed_reason' => $currentBranch->temporary_closed_reason, 'temporary_closed_until' => $until?->toIso8601String()]);

                return ['id' => $currentBranch->id, 'version' => $expectedVersion + 1];
            });

        return Branch::query()->whereKey($result['id'])->firstOrFail();
    }
}
