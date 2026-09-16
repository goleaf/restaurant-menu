<?php

use App\Enums\SystemRole;
use App\Livewire\Local\ComponentReference;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

function componentReferenceAdministrator(): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::query()->where('code', SystemRole::Superadmin->value)->firstOrFail());

    return $user;
}

test('component reference requires an authenticated platform administrator', function () {
    $this->get('/local/components')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get('/local/components')->assertForbidden();
    $this->actingAs(componentReferenceAdministrator())->get('/local/components')
        ->assertOk()->assertSee('data-component-reference', false);
});

test('component reference rejects production even for an administrator', function () {
    $this->actingAs(componentReferenceAdministrator());
    $this->app->instance('env', 'production');

    $this->get('/local/components')->assertNotFound();
    Livewire::test(ComponentReference::class)->assertNotFound();
});

test('reference validation preserves unrelated input and closes only its editor on success', function () {
    $this->actingAs(componentReferenceAdministrator());
    Livewire::test(ComponentReference::class)
        ->set('note', 'Unfinished separate example')
        ->set('name', '')
        ->call('saveExample')
        ->assertHasErrors(['name' => 'required'])
        ->assertSeeHtml('aria-describedby="reference-name-error"')
        ->assertSet('note', 'Unfinished separate example')
        ->set('name', 'Example restaurant')
        ->call('saveExample')
        ->assertHasNoErrors()
        ->assertSet('note', 'Unfinished separate example')
        ->assertDispatched('modal-close', name: 'component-reference-edit')
        ->assertDispatched('toast-show');
});

test('reference reauthorizes every hydrated interaction', function () {
    $user = componentReferenceAdministrator();
    $this->actingAs($user);
    $component = Livewire::test(ComponentReference::class);
    $user->roles()->detach();
    $user->unsetRelation('roles');

    $component->call('saveExample')->assertForbidden();
});

test('reference demonstrates a visible associated error and translated loading state', function () {
    $this->actingAs(componentReferenceAdministrator());

    $html = Livewire::test(ComponentReference::class)->html();
    $document = new DOMDocument;
    @$document->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    $xpath = new DOMXPath($document);
    $error = $xpath->query('//*[@id="reference-invalid-error"]')->item(0);

    expect($error?->textContent)->toContain(__('ui.reference.name_required'));
    expect($xpath->query('//*[@name="note"]')->item(0)?->getAttribute('aria-describedby'))
        ->toContain('reference-note-help');
    expect($html)->not->toContain('ui.state.loading.title');
});

test('the restricted reference exposes installed Pro controls with the application locale', function (string $locale) {
    $this->actingAs(componentReferenceAdministrator());
    app()->setLocale($locale);

    $html = Livewire::test(ComponentReference::class)->html();

    foreach (['accordion', 'autocomplete', 'calendar', 'chart', 'command', 'composer', 'context', 'date-picker', 'editor', 'file-upload', 'kanban', 'pillbox', 'popover', 'select', 'slider', 'tabs', 'time-picker', 'timeline'] as $family) {
        expect(str_contains($html, 'data-pro-reference="'.$family.'"'))->toBeTrue($family);
    }

    expect($html)->toContain('locale="'.$locale.'"', 'time-format="24-hour"')
        ->not->toContain('ui.reference.pro.');
})->with(['en', 'lt', 'ru']);
