<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\RestaurantOnboarding;
use App\Models\User;
use App\Services\Organizations\RestaurantCenterQuery;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;

it('returns authorized restaurants in a bounded page with actor-owned continuation only', function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $actor = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($actor, ['name' => 'Business']);
    $brand = Brand::factory()->for($organization)->create();
    $points = Branch::factory()->count(25)->sequence(fn ($sequence) => ['name' => 'Restaurant '.str_pad((string) $sequence->index, 2, '0', STR_PAD_LEFT)])->for($organization)->for($brand)->create();
    $other = Branch::factory()->create(['name' => 'Private other tenant']);
    $attempt = RestaurantOnboarding::factory()->create(['user_id' => $actor->id, 'organization_id' => $organization->id,
        'brand_id' => $brand->id, 'branch_id' => $points->first()->id]);
    $result = app(RestaurantCenterQuery::class)->restaurants($actor, []);
    expect($result->items())->toHaveCount(20)->and($result->hasMorePages())->toBeTrue();
    $all = collect($result->items());
    expect($all->pluck('id'))->not->toContain($other->id);
    $filtered = app(RestaurantCenterQuery::class)->restaurants($actor, ['organization' => (string) $organization->id, 'setup' => 'unfinished']);
    expect($filtered->items())->toHaveCount(1)->and($filtered->items()[0]->id)->toBe($attempt->branch_id);
});

it('keeps empty organizations and brands discoverable without exposing foreign structure', function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $actor = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($actor, ['name' => 'Empty allowed organization']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Empty allowed brand']);
    $foreign = Brand::factory()->create();
    $query = app(RestaurantCenterQuery::class);
    expect($query->organizations($actor, '')->pluck('id')->all())->toBe([$organization->id]);
    expect($query->brands($actor, $organization->id, '')->pluck('id')->all())->toBe([$brand->id]);
    expect(fn () => $query->brands($actor, $foreign->organization_id, ''))->toThrow(AuthorizationException::class);
});
