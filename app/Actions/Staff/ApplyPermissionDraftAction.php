<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Enums\PermissionOverrideState;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use App\Services\Staff\PermissionQueryService;
use App\Support\Validation\PermissionDraftRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class ApplyPermissionDraftAction
{
    public function __construct(
        private readonly PermissionQueryService $queries,
        private readonly SetUserPermissionOverrideAction $setOverride,
    ) {}

    /** @param array<array-key, mixed> $changes */
    public function handle(
        User $actor,
        Organization $organization,
        User $subject,
        array $changes,
        string $expectedFingerprint,
        ?string $reason = null,
        bool $confirmed = false,
        ?Branch $branch = null,
    ): void {
        DB::transaction(function () use ($actor, $organization, $subject, $changes, $expectedFingerprint, $reason, $confirmed, $branch): void {
            $actor = $actor->fresh() ?? abort(403);
            $subject = $subject->fresh() ?? abort(403);
            $organization = $organization->fresh() ?? abort(404);
            Gate::forUser($actor)->authorize('managePermissions', $organization);
            Gate::forUser($actor)->authorize('managePermissions', $this->queries->membership($organization, $subject));
            $preview = $this->queries->draftPreview($actor, $organization, $subject, $changes, $expectedFingerprint, $branch);
            Validator::make(['reason' => $reason === null ? null : trim($reason), 'confirmed' => $confirmed],
                PermissionDraftRules::confirmation($preview['requires_confirmation']), [], [
                    'reason' => __('permissions.forms.change_reason'), 'confirmed' => __('permissions.draft.confirm'),
                ])->validate();

            foreach ($preview['changes'] as $change) {
                if ($change['indirect']) {
                    continue;
                }
                $this->setOverride->handle($subject, $this->queries->permission($change['permission_id']), PermissionOverrideState::from($change['after']), $actor, $organization->id, $reason);
            }
        });
    }
}
