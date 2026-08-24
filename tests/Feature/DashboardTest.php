<?php

use App\Enums\SystemRole;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response
        ->assertOk()
        ->assertSee('data-primary-workspace="restaurant"', false);
});

test('new owner sees restaurant onboarding in the application navigation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('onboarding.restaurant'), false);
});

test('existing tenant staff never receive an onboarding link that resolves to forbidden', function () {
    $this->seed(SystemPermissionsSeeder::class);

    $staff = User::factory()->create();
    $organization = Organization::factory()->create();
    $waiterRole = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();

    OrganizationUser::factory()
        ->forOrganization($organization)
        ->forUser($staff)
        ->forRole($waiterRole)
        ->active()
        ->create();

    $this->actingAs($staff)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('onboarding.restaurant'), false);
});
