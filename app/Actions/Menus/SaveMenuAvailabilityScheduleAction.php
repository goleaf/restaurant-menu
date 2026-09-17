<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Availability\RunAvailabilityCommandAction;
use App\Enums\AuditLogAction;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\User;
use App\Support\Availability\AvailabilityDependencyFingerprint;
use App\Support\Validation\Availability\ScheduleRules;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class SaveMenuAvailabilityScheduleAction
{
    public function __construct(private readonly RunAvailabilityCommandAction $commands, private readonly RecordAuditLogAction $audit) {}

    /** @param array<array-key, mixed> $weeklyIntervals */
    public function handle(User $actor, Branch $branch, Menu $menu, array $weeklyIntervals, bool $closed, int $expectedVersion, string $expectedTimezone, string $requestId, ?string $expectedContext = null): Menu
    {
        $weeklyIntervals = ScheduleRules::menu($weeklyIntervals);
        $result = $this->commands->handle($actor, $branch, 'menu_schedule', $menu->id, $requestId, compact('weeklyIntervals', 'closed', 'expectedVersion', 'expectedTimezone', 'expectedContext'),
            static function (User $currentActor, Branch $currentBranch) use ($menu): void {
                $current = Menu::query()->where('branch_id', $currentBranch->id)->whereKey($menu->id)->firstOrFail();
                Gate::forUser($currentActor)->authorize('update', $current);
            },
            function (User $currentActor, Branch $currentBranch) use ($menu, $weeklyIntervals, $closed, $expectedVersion, $expectedTimezone, $expectedContext): array {
                $current = Menu::query()->where('branch_id', $currentBranch->id)->whereKey($menu->id)->lockForUpdate()->firstOrFail();
                if ($current->schedule_version !== $expectedVersion || $currentBranch->timezone !== $expectedTimezone) {
                    throw ValidationException::withMessages(['expectedVersion' => __('availability.errors.stale')]);
                }
                $current->setRelation('branch', $currentBranch);
                if ($expectedContext !== null && ! hash_equals($expectedContext, AvailabilityDependencyFingerprint::menu($current))) {
                    throw ValidationException::withMessages(['expectedVersion' => __('availability.errors.stale')]);
                }
                $before = $current->availabilitySchedules()->select(['day_of_week', 'starts_at', 'ends_at'])->get()->toArray();
                if (Menu::query()->whereKey($current->id)->where('schedule_version', $expectedVersion)->update(['schedule_version' => $expectedVersion + 1]) !== 1) {
                    throw ValidationException::withMessages(['expectedVersion' => __('availability.errors.stale')]);
                }
                $oldClosed = $current->schedule_is_closed;
                $current->forceFill(['schedule_is_closed' => $closed, 'schedule_version' => $expectedVersion + 1]);
                if (! $current->save()) {
                    throw new RuntimeException('Menu schedule state could not be stored.');
                }
                $current->availabilitySchedules()->delete();
                foreach ($weeklyIntervals as $interval) {
                    $row = $current->availabilitySchedules()->create($interval);
                    if (! $row->exists) {
                        throw new RuntimeException('Menu schedule interval could not be stored.');
                    }
                }
                $this->audit->handle(AuditLogAction::MenuScheduleChanged, 'menu', $current->id,
                    actorUser: $currentActor, organizationId: $currentBranch->organization_id, branchId: $currentBranch->id,
                    oldValues: ['scope' => 'menu_schedule', 'closed' => $oldClosed, 'intervals' => $before],
                    newValues: ['scope' => 'menu_schedule', 'closed' => $closed, 'intervals' => $weeklyIntervals]);

                return ['id' => $current->id, 'version' => $expectedVersion + 1];
            });

        return Menu::query()->where('branch_id', $branch->id)->whereKey($result['id'])->firstOrFail();
    }
}
