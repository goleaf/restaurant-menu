<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Livewire\Restaurants\IdentityEditor;
use App\Livewire\Restaurants\Index;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Dom\HTMLDocument;
use Illuminate\Database\Eloquent\Model;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

test('structure lists recover from archiving or restoring the last row without losing filters', function (string $kind, bool $archived): void {
    [$component, $rows, $pageName] = structureListFixture($kind, 21, $archived);
    $target = $rows[0];
    $component->set('filters.search', 'Visible')->set('filters.sort', 'name_desc')
        ->set('filters.lifecycle', $archived ? 'archived' : 'active')->call('setPage', 2, $pageName);
    expect(structureListNames($component))->toBe(['Visible 01']);
    structureListChangeLifecycle($component, $target, $kind);
    $component->assertHasNoErrors()->assertSet('paginators.'.$pageName, 1)
        ->assertSet('filters.search', 'Visible')->assertSet('filters.sort', 'name_desc')
        ->assertSet('filters.lifecycle', $archived ? 'archived' : 'active')->assertSet('objectId', null);
    expect(structureListNames($component))->toContain('Visible 21')->not->toContain('Visible 01');
    expect($target->fresh()->trashed())->toBe(! $archived);
    foreach (array_slice($rows, 1) as $row) {
        expect($row->fresh()->trashed())->toBe($archived);
    }
})->with(['organizations', 'brands', 'branches'])->with([false, true]);

test('structure lists retain populated later pages after a lifecycle mutation', function (string $kind): void {
    [$component, $rows, $pageName] = structureListFixture($kind, 22);
    $component->call('setPage', 2, $pageName);
    structureListChangeLifecycle($component, $rows[21], $kind);
    $component->assertSet('paginators.'.$pageName, 2);
    expect(structureListNames($component))->toBe(['Visible 21'])->and($rows[21]->fresh()->trashed())->toBeTrue();
})->with(['organizations', 'brands', 'branches']);

test('structure lists recover stale pages and distinguish empty search from an empty collection', function (string $kind): void {
    [$component, $rows, $pageName] = structureListFixture($kind, 1);
    $component->call('setPage', 99, $pageName)->assertSet('paginators.'.$pageName, 1)
        ->set('filters.search', 'No matching record')->assertSeeHtml('data-center-empty="search"')->set('filters.search', '');
    expect(structureListNames($component))->toBe(['Visible 01']);
    structureListChangeLifecycle($component, $rows[0], $kind);
    $component->assertSet('paginators.'.$pageName, 1);
    expect(structureListNames($component))->toBe([])->and($component->viewData('emptyState'))->not->toBe('search');
})->with(['organizations', 'brands', 'branches']);

test('structure list toolbar exposes one labelled offline-safe filter set and bounded rows', function (string $kind): void {
    [$component] = structureListFixture($kind, 2);
    $document = HTMLDocument::createFromString('<!doctype html><html><body>'.$component->html().'</body></html>');
    foreach (['search', 'lifecycle', 'sort'] as $name) {
        $binding = $name === 'search' ? 'wire:model.live.debounce.300ms' : 'wire:model.live';
        $controls = $document->querySelectorAll('[data-page="restaurant-center"] [name="filters.'.$name.'"]');
        expect($controls->length)->toBe(1);
        $control = $controls->item(0);
        $field = $control->parentElement;
        while ($field !== null && ! $field->hasAttribute('data-flux-field')) {
            $field = $field->parentElement;
        }
        expect($control->getAttribute($binding))->toBe('filters.'.$name)
            ->and($control->getAttribute('wire:offline.attr'))->toBe('disabled')
            ->and(trim($field?->querySelector('[data-flux-label]')?->textContent ?? ''))
            ->toBe(__(match ($name) {
                'search' => 'center.search', 'lifecycle' => 'center.lifecycle', default => 'center.sort'
            }));
    }
    expect(structureListNames($component))->toHaveCount(2)
        ->and($document->querySelectorAll('.rm-restaurant-center__row')->length)->toBe(2);
})->with(['organizations', 'brands', 'branches']);

test('structure mutations cannot target another tenant through the canonical editor', function (string $kind, bool $archived): void {
    [$component, $rows] = structureListFixture($kind, 1);
    $foreign = match ($kind) {
        'organizations' => Organization::factory()->create(['deleted_at' => $archived ? now() : null]),
        'brands' => Brand::factory()->create(['deleted_at' => $archived ? now() : null]),
        default => Branch::factory()->create(['deleted_at' => $archived ? now() : null]),
    };
    $singular = structureListKind($kind);
    Livewire::test(IdentityEditor::class, ['kind' => $singular, 'objectId' => $foreign->id])->assertForbidden();
    $editor = Livewire::test(IdentityEditor::class, ['kind' => $singular, 'objectId' => $rows[0]->id]);
    expect(fn () => $editor->set('objectId', $foreign->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
    expect($foreign->fresh()->trashed())->toBe($archived);
})->with(['organizations', 'brands', 'branches'])->with([false, true]);

test('structure filter changes preserve invalid canonical editor input and its error', function (string $kind): void {
    [$component, $rows] = structureListFixture($kind, 1);
    $editor = Livewire::test(IdentityEditor::class, ['kind' => structureListKind($kind), 'objectId' => $rows[0]->id])
        ->set('form.name', '')->call('save')->assertHasErrors(['form.name' => 'required']);
    $component->set('kind', structureListKind($kind))->set('objectId', $rows[0]->id)
        ->set('filters.sort', 'name_desc')->set('filters.search', 'Visible 01')
        ->assertSet('objectId', $rows[0]->id);
    $editor->call('$refresh')->assertSet('form.name', '')->assertHasErrors(['form.name']);
    expect($rows[0]->fresh()->name)->toBe('Visible 01');
})->with(['organizations', 'brands', 'branches']);

test('read-only structure membership cannot archive or restore a visible record', function (string $kind, bool $archived): void {
    [, $rows] = structureListFixture($kind, 1, $archived);
    $target = $rows[0];
    $organization = $target instanceof Organization ? $target : $target->organization;
    $member = User::factory()->create();
    OrganizationUser::factory()->forOrganization($organization)->forUser($member)
        ->forSystemRole(SystemRole::Waiter)->active()->create();
    $editor = Livewire::actingAs($member)->test(IdentityEditor::class, ['kind' => structureListKind($kind), 'objectId' => $target->id]);
    if ($archived) {
        $editor->assertForbidden();
    } else {
        $editor->set('confirmation', $target->name)->call('changeLifecycle')->assertForbidden();
    }
    expect($target->fresh()->trashed())->toBe($archived);
})->with(['organizations', 'brands', 'branches'])->with([false, true]);

test('structure context treats domain names as literal text and translates only interface labels', function (string $kind, string $name): void {
    $owner = User::factory()->create();
    $organization = Organization::factory()->for($owner, 'owner')->create(['name' => $name]);
    OrganizationUser::factory()->forOrganization($organization)->forUser($owner)->forSystemRole(SystemRole::Owner)->active()->create();
    $brand = Brand::factory()->for($organization)->create(['name' => $name]);
    $target = $kind === 'brands' ? $brand : Branch::factory()->for($organization)->for($brand)->create(['name' => $name]);
    $component = Livewire::actingAs($owner)->test(IdentityEditor::class, ['kind' => structureListKind($kind), 'objectId' => $target->id]);
    $document = HTMLDocument::createFromString('<!doctype html><html><body>'.$component->html().'</body></html>');
    expect(trim($document->querySelector('h2')->textContent))->toBe($name)
        ->and(trim($document->querySelector('[data-center-parent="organization"]')->textContent))->toBe($name);
    if ($kind === 'branches') {
        expect(trim($document->querySelector('[data-center-parent="brand"]')->textContent))->toBe($name);
    }
})->with(['brands', 'branches'])->with(['navigation.organizations', 'validation']);

/** @return array{Testable, list<Model>, string} */
function structureListFixture(string $kind, int $count, bool $archived = false): array
{
    $owner = User::factory()->create();
    $organization = Organization::factory()->for($owner, 'owner')->create(['name' => 'Context organization']);
    if ($kind !== 'organizations') {
        OrganizationUser::factory()->forOrganization($organization)->forUser($owner)->forSystemRole(SystemRole::Owner)->active()->create();
    }
    $brand = $kind === 'branches' ? Brand::factory()->for($organization)->create(['name' => 'Context brand']) : null;
    $rows = [];
    foreach (range(1, $count) as $number) {
        $attributes = ['name' => sprintf('Visible %02d', $number), 'deleted_at' => $archived ? now() : null];
        $row = match ($kind) {
            'organizations' => Organization::factory()->for($owner, 'owner')->create($attributes),
            'brands' => Brand::factory()->for($organization)->create($attributes),
            default => Branch::factory()->for($organization)->for($brand)->create($attributes),
        };
        if ($row instanceof Organization) {
            OrganizationUser::factory()->forOrganization($row)->forUser($owner)->forSystemRole(SystemRole::Owner)->active()->create();
        }
        $rows[] = $row;
    }
    $component = match ($kind) {
        'organizations' => Livewire::actingAs($owner)->test(Index::class)->set('filters.view', 'structure'),
        'brands' => Livewire::actingAs($owner)->test(Index::class, ['organization' => $organization]),
        default => Livewire::actingAs($owner)->test(Index::class, compact('organization', 'brand')),
    };
    $component->set('filters.search', 'Visible')->set('filters.lifecycle', $archived ? 'archived' : 'active');

    return [$component, $rows, $kind === 'branches' ? 'page' : $kind.'Page'];
}

function structureListKind(string $kind): string
{
    return match ($kind) {
        'organizations' => 'organization', 'brands' => 'brand', default => 'branch',
    };
}

/** @return list<string> */
function structureListNames(Testable $component): array
{
    return $component->viewData('rows')->getCollection()->pluck('name')->all();
}

function structureListChangeLifecycle(Testable $component, Model $target, string $kind): void
{
    Livewire::test(IdentityEditor::class, ['kind' => structureListKind($kind), 'objectId' => $target->getKey()])
        ->set('confirmation', $target->getAttribute('name'))->call('changeLifecycle')->assertHasNoErrors()->assertDispatched('restaurant-lifecycle-saved');
    $component->call('lifecycleSaved');
}
