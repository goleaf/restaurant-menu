<?php

use App\Models\User;

test('appearance preferences are displayed on the profile page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('id="profile-appearance"', false)
        ->assertSee(__('ui.settings.appearance.appearance_settings'))
        ->assertSee(__('ui.settings.appearance.update_the_appearance_settings_for_your_account'))
        ->assertSee('value="light"', false)
        ->assertSee('value="dark"', false)
        ->assertSee('value="system"', false)
        ->assertDontSee('href="'.route('appearance.edit').'"', false);
});

test('the previous appearance address redirects to profile settings', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('appearance.edit'))
        ->assertRedirectToRoute('profile.edit');
});

test('profile and appearance settings require authentication', function (string $route) {
    $this->get(route($route))->assertRedirectToRoute('login');
})->with(['profile.edit', 'appearance.edit']);
