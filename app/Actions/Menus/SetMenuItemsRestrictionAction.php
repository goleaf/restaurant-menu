<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Actions\Availability\RunAvailabilityCommandAction;
use App\Actions\Branches\ForgetBranchCacheAction;
use App\Enums\MenuOperationKind;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\MenuOperation;
use App\Models\User;
use App\Services\Availability\AvailabilityEvaluator;
use App\Support\Availability\AvailabilityDependencyFingerprint;
use App\Support\Availability\MenuRestrictionAuditContext;
use App\Support\Validation\Availability\BranchLocalDateTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class SetMenuItemsRestrictionAction
{
    public function __construct(
        private readonly RunAvailabilityCommandAction $commands,
        private readonly ForgetBranchCacheAction $forgetCache,
    ) {}

    /** @param list<array{id: int, version: int}> $targets @param array<int,string>|null $expectedContexts @return array<string, mixed> */
    public function handle(User $actor, Branch $branch, array $targets, string $operation, ?string $untilLocal, string $expectedTimezone, string $requestId, ?string $reason = null, ?array $expectedContexts = null): array
    {
        Validator::make(compact('targets', 'operation', 'untilLocal', 'expectedTimezone', 'reason'), [
            'targets' => ['required', 'array', 'min:1', 'max:100'],
            'targets.*' => ['required', 'array:id,version'],
            'targets.*.id' => ['required', 'integer', 'min:1', 'distinct:strict'],
            'targets.*.version' => ['required', 'integer', 'min:0'],
            'operation' => ['required', Rule::in(['stop', 'resume', 'hide', 'unhide'])],
            'untilLocal' => ['nullable', Rule::requiredIf($operation === 'hide'), new BranchLocalDateTime($expectedTimezone)],
            'expectedTimezone' => ['required', 'timezone:all'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], attributes: [
            'targets' => __('availability.fields.items'), 'operation' => __('availability.fields.operation'),
            'untilLocal' => __('availability.fields.deadline'), 'expectedTimezone' => __('availability.fields.timezone'),
            'reason' => __('availability.fields.reason'), 'targets.*.id' => __('availability.fields.item'),
            'targets.*.version' => __('availability.fields.version'),
        ])->validate();

        return $this->commands->handle($actor, $branch, 'item_restriction', null, $requestId,
            compact('targets', 'operation', 'untilLocal', 'expectedTimezone', 'reason', 'expectedContexts'),
            function (User $currentActor, Branch $currentBranch): void {
                Gate::forUser($currentActor)->authorize('changeMenuAvailability', $currentBranch);
            }, function (User $currentActor, Branch $currentBranch) use ($targets, $operation, $untilLocal, $expectedTimezone, $reason, $expectedContexts): array {
                if ($currentBranch->timezone !== $expectedTimezone) {
                    throw ValidationException::withMessages(['expectedTimezone' => __('availability.errors.timezone_changed')]);
                }
                $instant = CarbonImmutable::now();
                $until = $operation === 'hide' && $untilLocal !== null ? BranchLocalDateTime::parse($untilLocal, $currentBranch->timezone)->utc() : null;
                if ($until !== null && $until->lessThanOrEqualTo($instant)) {
                    throw ValidationException::withMessages(['untilLocal' => __('availability.errors.future_deadline')]);
                }
                $items = MenuItem::query()->select(['id', 'menu_id', 'category_id', 'name', 'is_available', 'hidden_until', 'availability_version', 'deleted_at'])
                    ->whereIn('id', array_column($targets, 'id'))
                    ->whereHas('menu', fn ($query) => $query->where('branch_id', $currentBranch->id))
                    ->when($expectedContexts !== null, fn ($query) => $query->with(AvailabilityEvaluator::itemRelations()))
                    ->orderBy('id')->get()->keyBy('id');
                if ($items->count() !== count($targets)) {
                    throw ValidationException::withMessages(['targets' => __('availability.errors.items_changed')]);
                }
                foreach ($targets as $target) {
                    if ($items->get($target['id'])?->availability_version !== $target['version']) {
                        throw ValidationException::withMessages(['targets' => __('availability.errors.stale_restriction')]);
                    }
                    if ($expectedContexts !== null && (! is_string($expectedContexts[$target['id']] ?? null)
                        || ! hash_equals($expectedContexts[$target['id']], AvailabilityDependencyFingerprint::item($items->get($target['id']))))) {
                        throw ValidationException::withMessages(['targets' => __('availability.errors.stale_restriction')]);
                    }
                }
                $changed = [];
                foreach ($items as $item) {
                    match ($operation) {
                        'stop' => $item->is_available = false,
                        'resume' => $item->is_available = true,
                        'hide' => $item->hidden_until = $until,
                        'unhide' => $item->hidden_until = null,
                        default => throw new RuntimeException('Invalid validated restriction operation.'),
                    };
                    if (! $item->isDirty(['is_available', 'hidden_until'])) {
                        continue;
                    }
                    $item->availability_version++;
                    $item->restrictionAuditContext = new MenuRestrictionAuditContext($currentActor, $currentBranch->organization_id, $currentBranch->id, $operation, $reason);
                    if (! $item->save()) {
                        throw new RuntimeException('Menu restriction could not be saved.');
                    }
                    $item->restrictionAuditContext = null;
                    $changed[] = $item->id;
                }
                if ($changed !== []) {
                    MenuOperation::query()->where('kind', MenuOperationKind::DuplicateItem)->whereIn('target_id', $changed)
                        ->whereNotNull('active_scope')->where('source_changed', false)->update(['source_changed' => true]);
                    $this->forgetCache->handle($currentBranch->id);
                }

                return ['changed_ids' => $changed, 'operation' => $operation, 'evaluated_at' => $instant->toIso8601String()];
            });
    }
}
