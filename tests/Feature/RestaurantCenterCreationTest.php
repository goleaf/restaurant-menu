<?php

declare(strict_types=1);

use App\Actions\Onboarding\CreateRestaurantSetupAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\OrganizationSubscriptionStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\RestaurantOnboarding;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->actor = User::factory()->create();
    $this->organization = app(CreateOrganizationAction::class)->handle($this->actor, ['name' => 'Existing organization']);
    $this->brand = Brand::factory()->for($this->organization)->create(['name' => 'Existing brand']);
    $this->first = Branch::factory()->for($this->organization)->for($this->brand)->create(['name' => 'First restaurant', 'is_active' => false]);
    $this->prior = RestaurantOnboarding::factory()->for($this->actor, 'user')->create([
        'organization_id' => $this->organization->id, 'brand_id' => $this->brand->id, 'branch_id' => $this->first->id,
        'completed_at' => now()->subMonth(),
    ]);
    $this->data = ['organizationId' => $this->organization->id, 'brandId' => $this->brand->id,
        'organizationName' => '', 'brandName' => '', 'branchName' => 'Second restaurant',
        'branchAddress' => 'Example 12', 'branchCity' => 'Vilnius', 'branchCountryCode' => 'LT',
        'branchTimezone' => 'Europe/Vilnius', 'branchCurrency' => 'EUR'];
});

it('creates a second private setup without changing the first restaurant or its parents', function (): void {
    $before = [$this->first->fresh()->getAttributes(), $this->prior->fresh()->getAttributes(), $this->organization->fresh()->getAttributes(), $this->brand->fresh()->getAttributes()];
    $setup = app(CreateRestaurantSetupAction::class)->handle($this->actor, $this->data, (string) Str::uuid());
    expect($setup->branch_id)->not->toBe($this->first->id)
        ->and($setup->user_id)->toBe($this->actor->id)
        ->and($setup->organization_id)->toBe($this->organization->id)
        ->and($setup->brand_id)->toBe($this->brand->id)
        ->and($setup->completed_at)->toBeNull()
        ->and(RestaurantOnboarding::query()->count())->toBe(2)
        ->and([$this->first->fresh()->getAttributes(), $this->prior->fresh()->getAttributes(), $this->organization->fresh()->getAttributes(), $this->brand->fresh()->getAttributes()])->toBe($before)
        ->and($setup->branch->settings)->not->toBeNull();
});

it('replays the same create response and rejects changed input for its request identity', function (): void {
    $key = (string) Str::uuid();
    $action = app(CreateRestaurantSetupAction::class);
    $first = $action->handle($this->actor, $this->data, $key);
    expect($action->handle($this->actor, $this->data, $key)->id)->toBe($first->id);
    expect(fn () => $action->handle($this->actor, [...$this->data, 'branchName' => 'Different'], $key))->toThrow(ValidationException::class);
    expect(Branch::query()->count())->toBe(2)->and(RestaurantOnboarding::query()->count())->toBe(2);
});

it('reauthorizes replay after membership revocation', function (): void {
    $key = (string) Str::uuid();
    $action = app(CreateRestaurantSetupAction::class);
    $action->handle($this->actor, $this->data, $key);
    $this->organization->memberships()->where('user_id', $this->actor->id)->update(['status' => 'suspended']);
    expect(fn () => $action->handle($this->actor, $this->data, $key))->toThrow(AuthorizationException::class);
    expect(Branch::query()->count())->toBe(2);
});

it('does not create under a foreign brand or suspended subscription', function (string $case): void {
    if ($case === 'foreign') {
        $this->data['brandId'] = Brand::factory()->create()->id;
    } else {
        $this->organization->subscription()->update(['status' => OrganizationSubscriptionStatus::Inactive]);
    }
    try {
        app(CreateRestaurantSetupAction::class)->handle($this->actor, $this->data, (string) Str::uuid());
        $this->fail('Unauthorized creation succeeded.');
    } catch (ModelNotFoundException|AuthorizationException) {
        expect(Branch::query()->count())->toBe(1)->and(RestaurantOnboarding::query()->count())->toBe(1);
    }
})->with(['foreign', 'subscription']);

it('rolls back branch settings and departments if the setup receipt cannot be saved', function (): void {
    RestaurantOnboarding::creating(fn (): bool => false);
    try {
        expect(fn () => app(CreateRestaurantSetupAction::class)->handle($this->actor, $this->data, (string) Str::uuid()))->toThrow(RuntimeException::class);
        expect(Branch::query()->count())->toBe(1)->and(RestaurantOnboarding::query()->count())->toBe(1);
    } finally {
        RestaurantOnboarding::flushEventListeners();
    }
});

it('creates the first business graph once without publishing the new restaurant', function (): void {
    $newActor = User::factory()->create();
    $data = [...$this->data, 'organizationId' => null, 'brandId' => null, 'organizationName' => 'New organization', 'brandName' => 'New brand'];
    $key = (string) Str::uuid();
    $action = app(CreateRestaurantSetupAction::class);
    $setup = $action->handle($newActor, $data, $key);
    expect($setup->branch->is_active)->toBeFalse()->and($setup->completed_at)->toBeNull()
        ->and($action->handle($newActor, $data, $key)->id)->toBe($setup->id)
        ->and($setup->organization->owner_user_id)->toBe($newActor->id);
});

it('keeps database uniqueness errors inside localized validation', function (): void {
    $data = [...$this->data, 'branchName' => $this->first->name];
    expect(fn () => app(CreateRestaurantSetupAction::class)->handle($this->actor, $data, (string) Str::uuid()))->toThrow(ValidationException::class);
    expect(Branch::query()->count())->toBe(1);
});

it('rolls back creation when audit persistence fails', function (): void {
    AuditLog::creating(fn (): bool => false);
    try {
        expect(fn () => app(CreateRestaurantSetupAction::class)->handle($this->actor, $this->data, (string) Str::uuid()))->toThrow(RuntimeException::class);
        expect(Branch::query()->count())->toBe(1)->and(RestaurantOnboarding::query()->count())->toBe(1);
    } finally {
        AuditLog::flushEventListeners();
    }
});
