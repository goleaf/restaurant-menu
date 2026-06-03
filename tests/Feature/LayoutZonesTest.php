<?php

use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemRolesSeeder;

test('guest interface placeholder is public and mobile first', function () {
    $this->get(route('guest.home'))
        ->assertOk()
        ->assertSee('data-layout="guest"', false)
        ->assertSee('Guest interface')
        ->assertSee('min-h-svh');
});

test('auth pages use the auth layout zone', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('data-layout="auth"', false)
        ->assertSee('Log in');
});

test('restaurant dashboard requires authentication', function () {
    $this->get(route('restaurant.dashboard'))
        ->assertRedirect(route('login'));
});

test('restaurant dashboard placeholder is available to authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('restaurant.dashboard'))
        ->assertOk()
        ->assertSee('data-layout="restaurant-dashboard"', false)
        ->assertSee('Restaurant dashboard');
});

test('superadmin dashboard requires authentication', function () {
    $this->get(route('superadmin.dashboard'))
        ->assertRedirect(route('login'));
});

test('superadmin dashboard is blocked for regular authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('superadmin.dashboard'))
        ->assertForbidden();
});

test('superadmin dashboard is available to superadmin users', function () {
    $this->seed(SystemRolesSeeder::class);

    $user = User::factory()->create();
    $role = Role::query()
        ->where('code', SystemRole::Superadmin->value)
        ->firstOrFail();

    $user->roles()->syncWithoutDetachingOrFail([$role->id]);

    $this->actingAs($user)
        ->get(route('superadmin.dashboard'))
        ->assertOk()
        ->assertSee('data-layout="platform-dashboard"', false)
        ->assertSee('Platform dashboard');
});
