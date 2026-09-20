<?php

declare(strict_types=1);

namespace App\Actions\Branches;

use App\Models\Branch;
use App\Models\BranchSettingsChange;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class ExecuteBranchSettingsChangeAction
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  Closure(User, Branch): array<string, mixed>  $apply
     * @return array<string, mixed>
     */
    public function handle(User $actor, Branch $branch, string $group, string $requestId, array $payload, Closure $apply, bool $allowStructuralUpdate = false): array
    {
        Validator::make(['requestId' => $requestId], ['requestId' => ['required', 'uuid']], attributes: ['requestId' => __('settings.fields.request')])->validate();
        $hash = hash('sha256', json_encode([$group, $payload], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $branch, $group, $requestId, $hash, $apply, $allowStructuralUpdate): array {
            $actor = User::query()->select(['id'])->whereKey($actor->id)->firstOrFail();
            $branch = Branch::query()
                ->select(['id', 'organization_id', 'brand_id', 'name', 'public_name', 'public_description', 'public_translations',
                    'logo_path', 'cover_image_path', 'address', 'phone', 'email', 'website_url', 'instagram_url', 'facebook_url', 'tiktok_url',
                    'city', 'country', 'timezone', 'currency', 'is_active', 'is_temporarily_closed', 'temporary_closed_reason',
                    'temporary_closed_until', 'pause_version', 'opening_hours_version', 'created_at', 'updated_at', 'deleted_at'])
                ->where('organization_id', $branch->getRawOriginal('organization_id'))
                ->where('brand_id', $branch->getRawOriginal('brand_id'))
                ->whereKey($branch->id)->firstOrFail();
            $gate = Gate::forUser($actor);
            if (! $allowStructuralUpdate || $gate->denies('update', $branch)) {
                $gate->authorize('manageSettings', $branch);
            }

            $receipt = BranchSettingsChange::query()->select(['id', 'request_id', 'branch_id', 'user_id', 'group', 'payload_hash', 'result'])->where('request_id', $requestId)->first();
            if ($receipt instanceof BranchSettingsChange) {
                if ($receipt->branch_id !== $branch->id || $receipt->user_id !== $actor->id
                    || $receipt->group !== $group || ! hash_equals($receipt->payload_hash, $hash)) {
                    throw ValidationException::withMessages(['settings' => __('settings.errors.request_conflict')]);
                }

                return $receipt->result;
            }

            $result = $apply($actor, $branch);
            $receipt = new BranchSettingsChange;
            $receipt->forceFill([
                'request_id' => $requestId, 'branch_id' => $branch->id, 'user_id' => $actor->id,
                'group' => $group, 'payload_hash' => $hash, 'result' => $result,
            ]);
            if (! $receipt->save()) {
                throw new RuntimeException('The settings operation receipt could not be saved.');
            }

            return $result;
        }, attempts: 3);
    }
}
