<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Branches\CreateBranchAction;
use App\Actions\Brands\CreateBrandAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\AuditLogAction;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\RestaurantOnboarding;
use App\Models\User;
use App\Support\RestaurantSetupOptions;
use App\Support\Validation\Organizations\RestaurantCreationRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class CreateRestaurantSetupAction
{
    public function __construct(private CreateOrganizationAction $organizations, private CreateBrandAction $brands, private CreateBranchAction $branches, private RecordAuditLogAction $audit) {}

    /** @param array<string,mixed> $input */
    public function handle(User $actor, array $input, string $requestId, ?int $continuingId = null, bool $firstLaunch = true): RestaurantOnboarding
    {
        $data = Validator::make([...RestaurantCreationRules::normalize($input), 'requestId' => $requestId], [
            ...RestaurantCreationRules::rules(), 'requestId' => ['required', 'uuid'],
        ], attributes: [...RestaurantCreationRules::attributes(), 'requestId' => __('center.creation_request')])->validate();
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $data, $requestId, $hash, $continuingId, $firstLaunch): RestaurantOnboarding {
            $actor = User::query()->whereKey($actor->getKey())->firstOrFail();
            $prior = RestaurantOnboarding::query()->where('creation_key', $requestId)->lockForUpdate()->first();
            if ($prior !== null) {
                abort_unless($prior->user_id === $actor->id, 403);
                abort_if($continuingId !== null && $continuingId !== $prior->id, 409);
                Gate::forUser($actor)->authorize('view', $prior);
                if (! hash_equals((string) $prior->getAttribute('creation_hash'), $hash)) {
                    throw ValidationException::withMessages(['creation' => __('center.creation_conflict')]);
                }

                return $prior;
            }
            $attempt = $continuingId === null ? null : RestaurantOnboarding::query()->where('user_id', $actor->id)->whereKey($continuingId)->lockForUpdate()->firstOrFail();
            if ($attempt !== null) {
                Gate::forUser($actor)->authorize('update', $attempt);
                abort_if($attempt->branch_id !== null, 409);
                abort_if($attempt->organization_id !== null && (int) ($data['organizationId'] ?? 0) !== (int) $attempt->organization_id, 409);
                abort_if($attempt->brand_id !== null && (int) ($data['brandId'] ?? 0) !== (int) $attempt->brand_id, 409);
            }
            $first = empty($data['organizationId']);
            if ($first) {
                $firstLaunch = $firstLaunch || Gate::forUser($actor)->denies('createAdditionalBusiness', Organization::class);
                if ($firstLaunch) {
                    Gate::forUser($actor)->authorize('create', RestaurantOnboarding::class);
                }
                Gate::forUser($actor)->authorize('create', Organization::class);
                Validator::make($data, ['organizationName' => [Rule::unique(Organization::class, 'name')->where('owner_user_id', $actor->id)]], attributes: RestaurantCreationRules::attributes())->validate();
                $organization = $this->organizations->handle($actor, ['name' => $data['organizationName']]);
            } else {
                $organization = Organization::query()->whereKey((int) $data['organizationId'])->firstOrFail();
                Gate::forUser($actor)->authorize('createAdditional', [RestaurantOnboarding::class, $organization]);
            }
            if (empty($data['brandId'])) {
                Validator::make($data, ['brandName' => [Rule::unique(Brand::class, 'name')->where('organization_id', $organization->id)]], attributes: RestaurantCreationRules::attributes())->validate();
            }
            $brand = empty($data['brandId'])
                ? $this->brands->handle($organization, ['name' => $data['brandName']], $actor)
                : Brand::query()->where('organization_id', $organization->id)->whereKey((int) $data['brandId'])->firstOrFail();
            Validator::make($data, ['branchName' => [Rule::unique(Branch::class, 'name')->where('brand_id', $brand->id)]], attributes: RestaurantCreationRules::attributes())->validate();
            $branch = $this->branches->handle($brand, [
                'name' => $data['branchName'], 'address' => $data['branchAddress'], 'city' => $data['branchCity'],
                'country' => RestaurantSetupOptions::countryName($data['branchCountryCode']),
                'timezone' => $data['branchTimezone'], 'currency' => $data['branchCurrency'], 'is_active' => false,
            ], $actor);
            $setup = $attempt ?? new RestaurantOnboarding;
            $setup->forceFill(['user_id' => $actor->id, 'organization_id' => $organization->id,
                'brand_id' => $brand->id, 'branch_id' => $branch->id, 'purpose' => $first && $firstLaunch ? 'first' : 'additional',
                'creation_key' => $requestId, 'creation_hash' => $hash]);
            if (! $setup->save()) {
                throw new RuntimeException('Required restaurant setup could not be saved.');
            }
            $this->audit->handle(AuditLogAction::RestaurantSetupCreated, 'restaurant_onboarding', $setup->id, actorUser: $actor, organizationId: $organization->id, branchId: $branch->id, newValues: ['organization_id' => $organization->id, 'brand_id' => $brand->id, 'branch_id' => $branch->id, 'is_active' => false]);

            return $setup->refresh();
        }, attempts: 3);
    }
}
