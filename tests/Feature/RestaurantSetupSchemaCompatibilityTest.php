<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\RestaurantOnboarding;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

it('preserves legacy parents and historical completion through the schema upgrade', function (): void {
    $organization = Organization::factory()->create();
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $setup = RestaurantOnboarding::factory()->for($organization)->for($brand)->for($branch)->create(['completed_at' => now()->subMonth()]);
    $before = $setup->fresh()->only(['user_id', 'organization_id', 'brand_id', 'branch_id', 'completed_at']);
    $migration = require database_path('migrations/2026_09_17_100214_allow_multiple_restaurant_setup_attempts.php');
    $migration->down();
    $migration->up();
    expect($setup->fresh()->only(array_keys($before)))->toEqual($before)->and($setup->fresh()->setup_version)->toBe(0);
});

it('allows multiple private attempts under one brand but keeps one attempt per restaurant', function (): void {
    $actor = User::factory()->create();
    $brand = Brand::factory()->create();
    $first = Branch::factory()->for($brand)->create(['organization_id' => $brand->organization_id]);
    $second = Branch::factory()->for($brand)->create(['organization_id' => $brand->organization_id]);
    $data = ['user_id' => $actor->id, 'organization_id' => $brand->organization_id, 'brand_id' => $brand->id];
    RestaurantOnboarding::factory()->create([...$data, 'branch_id' => $first->id]);
    RestaurantOnboarding::factory()->create([...$data, 'branch_id' => $second->id]);
    expect($actor->restaurantOnboardings()->count())->toBe(2);
    expect(fn () => RestaurantOnboarding::factory()->create([...$data, 'branch_id' => $first->id]))->toThrow(QueryException::class);
});

it('retains creation receipts instead of silently dropping replay protection on rollback', function (): void {
    $key = (string) Str::uuid();
    $setup = RestaurantOnboarding::factory()->create(['creation_key' => $key, 'creation_hash' => hash('sha256', 'fixture')]);
    $migration = require database_path('migrations/2026_09_17_100214_allow_multiple_restaurant_setup_attempts.php');
    expect(fn () => $migration->down())->toThrow(RuntimeException::class);
    expect($setup->fresh()->creation_key)->toBe($key);
});
