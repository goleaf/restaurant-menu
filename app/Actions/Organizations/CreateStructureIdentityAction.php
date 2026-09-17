<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Actions\Brands\CreateBrandAction;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\StructureCreationReceipt;
use App\Models\User;
use App\Support\Validation\Organizations\OrganizationRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class CreateStructureIdentityAction
{
    public function __construct(private CreateOrganizationAction $organizations, private CreateBrandAction $brands) {}

    /** @param array<string,mixed> $input */
    public function handle(User $actor, string $kind, ?int $organizationId, array $input, string $requestId): Organization|Brand
    {
        $data = Validator::make([
            'name' => is_string($input['name'] ?? null) ? trim($input['name']) : ($input['name'] ?? null),
            'kind' => $kind, 'organizationId' => $organizationId, 'requestId' => $requestId,
        ], [
            ...OrganizationRules::organizationName(), 'kind' => ['required', Rule::in(['organization', 'brand'])],
            'organizationId' => [Rule::requiredIf($kind === 'brand'), Rule::prohibitedIf($kind === 'organization'), 'nullable', 'integer', 'min:1'],
            'requestId' => ['required', 'uuid'],
        ], attributes: ['name' => __('center.name'), 'organizationId' => __('center.organization'), 'requestId' => __('center.creation_request')])->validate();
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $kind, $organizationId, $data, $requestId, $hash): Organization|Brand {
            $actor = User::query()->whereKey($actor->getKey())->firstOrFail();
            $parent = $kind === 'brand' ? Organization::query()->whereKey($organizationId)->firstOrFail() : null;
            Gate::forUser($actor)->authorize('create', $parent === null ? Organization::class : [Brand::class, $parent]);
            $prior = StructureCreationReceipt::query()->where('actor_id', $actor->id)->where('request_key', $requestId)->lockForUpdate()->first();
            if ($prior !== null) {
                if (! hash_equals($prior->payload_hash, $hash)) {
                    throw ValidationException::withMessages(['creation' => __('center.creation_conflict')]);
                }
                $resource = $kind === 'organization'
                    ? Organization::query()->whereKey($prior->resource_id)->firstOrFail()
                    : Brand::query()->where('organization_id', $organizationId)->whereKey($prior->resource_id)->firstOrFail();
                Gate::forUser($actor)->authorize('update', $resource);

                return $resource;
            }
            Validator::make(['form' => ['name' => $data['name']]], [
                'form.name' => [Rule::unique($kind === 'organization' ? Organization::class : Brand::class, 'name')
                    ->where($kind === 'organization' ? 'owner_user_id' : 'organization_id', $parent->id ?? $actor->id)],
            ], attributes: ['form.name' => __('center.name')])->validate();
            $resource = $parent === null
                ? $this->organizations->handle($actor, ['name' => $data['name']])
                : $this->brands->handle($parent, ['name' => $data['name']], $actor);
            if (! $resource->exists) {
                throw new RuntimeException('Required structure identity could not be saved.');
            }
            $receipt = new StructureCreationReceipt([
                'actor_id' => $actor->id, 'request_key' => $requestId, 'payload_hash' => $hash, 'kind' => $kind,
                'organization_id' => $parent->id ?? $resource->id, 'resource_id' => $resource->id,
            ]);
            if (! $receipt->save()) {
                throw new RuntimeException('Required structure creation receipt could not be saved.');
            }

            return $resource;
        }, attempts: 3);
    }
}
