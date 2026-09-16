<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Index as BranchIndex;
use App\Livewire\Organizations\Brands\Index as BrandIndex;
use App\Livewire\Organizations\Index as OrganizationIndex;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Dom\HTMLDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

test('structure lists recover from archiving or restoring the last row without losing filters', function (string $kind, bool $archived): void {
    [$component, $rows, $pageName] = structureListFixture($kind, 16, $archived);
    $target = $rows[0];
    $component->set('search', 'Visible')->set('sort', 'name_desc')
        ->set('lifecycle', $archived ? 'archived' : 'active')
        ->call('setPage', 2, $pageName)
        ->assertSee('Visible 01')->assertDontSee('Visible 16');

    if ($archived) {
        $component->call('restore', $target->id);
    } else {
        $component->call('confirmDelete', $target->id)->call('delete');
    }

    $component->assertHasNoErrors()->assertSet('paginators.'.$pageName, 1)
        ->assertSet('search', 'Visible')->assertSet('sort', 'name_desc')
        ->assertSet('lifecycle', $archived ? 'archived' : 'active')
        ->assertSee('Visible 16')->assertDontSee('Visible 01');
    expect($target->fresh()->trashed())->toBe(! $archived);
    foreach (array_slice($rows, 1) as $row) {
        expect($row->fresh()->trashed())->toBe($archived);
    }
})->with(['organizations', 'brands', 'branches'])->with([false, true]);

test('structure lists retain populated later pages after a lifecycle mutation', function (string $kind): void {
    [$component, $rows, $pageName] = structureListFixture($kind, 17);
    $component->call('setPage', 2, $pageName)
        ->call('confirmDelete', $rows[16]->id)->call('delete')
        ->assertSet('paginators.'.$pageName, 2)->assertSee('Visible 16');
    expect($rows[16]->fresh()->trashed())->toBeTrue();
})->with(['organizations', 'brands', 'branches']);

test('structure lists recover stale pages and distinguish empty search from an empty collection', function (string $kind): void {
    [$component, $rows, $pageName] = structureListFixture($kind, 1);
    $component->call('setPage', 99, $pageName)->assertSet('paginators.'.$pageName, 1)
        ->assertSee('Visible 01')->set('search', 'No matching record')
        ->assertSeeHtml('data-structure-empty="search"')
        ->set('search', '')->call('confirmDelete', $rows[0]->id)->call('delete')
        ->assertSet('paginators.'.$pageName, 1)
        ->assertSeeHtml('data-structure-empty="collection"');
})->with(['organizations', 'brands', 'branches']);

test('structure list toolbar exposes one labelled offline-safe filter set and the visible count', function (string $kind): void {
    [$component] = structureListFixture($kind, 2);
    $document = HTMLDocument::createFromString('<!doctype html><html><body>'.$component->html().'</body></html>');
    expect($document->querySelectorAll('[data-structure-list-toolbar]')->length)->toBe(1)
        ->and($document->querySelector('[data-structure-visible-count]')->textContent)
        ->toContain(__('structure.list.visible_count', ['count' => 2]));

    foreach (['search', 'lifecycle', 'sort'] as $name) {
        $controls = $document->querySelectorAll('[data-structure-list-toolbar] [name="'.$name.'"]');
        expect($controls->length)->toBe(1);
        $control = $controls->item(0);
        expect($control->hasAttribute('wire:offline.attr'))->toBeTrue()
            ->and($control->getAttribute('id'))->not->toBeEmpty()
            ->and($document->querySelector('[data-flux-label][for="'.$control->getAttribute('id').'"]'))->not->toBeNull();
    }

    expect($document->querySelector('[data-structure-list-loading]')->getAttribute('role'))->toBe('status');
})->with(['organizations', 'brands', 'branches']);

test('structure mutations cannot target another tenant through the list controls', function (string $kind, bool $archived): void {
    [$component] = structureListFixture($kind, 1);
    $foreign = match ($kind) {
        'organizations' => Organization::factory()->create(['deleted_at' => $archived ? now() : null]),
        'brands' => Brand::factory()->create(['deleted_at' => $archived ? now() : null]),
        default => Branch::factory()->create(['deleted_at' => $archived ? now() : null]),
    };

    if ($archived) {
        expect(fn () => $component->call('restore', $foreign->id))->toThrow(ModelNotFoundException::class);
    } else {
        $property = match ($kind) {
            'organizations' => 'deletingOrganizationId',
            'brands' => 'deletingBrandId',
            default => 'deletingBranchId',
        };
        expect(fn () => $component->set($property, $foreign->id)->call('delete'))->toThrow(ModelNotFoundException::class);
    }

    expect($foreign->fresh()->trashed())->toBe($archived);
})->with(['organizations', 'brands', 'branches'])->with([false, true]);

test('structure filter changes preserve invalid editor input and its error', function (string $kind): void {
    [$component, $rows] = structureListFixture($kind, 1);
    $component->call('startEditing', $rows[0]->id)->set('editingName', '')
        ->call('update')->assertHasErrors(['editingName' => 'required'])
        ->set('sort', 'name_desc')->set('search', 'Visible 01')
        ->assertSet('editingName', '')->assertHasErrors(['editingName']);
    expect($rows[0]->fresh()->name)->toBe('Visible 01');
})->with(['organizations', 'brands', 'branches']);

test('read-only structure membership cannot archive or restore a visible record', function (string $kind, bool $archived): void {
    [, $rows] = structureListFixture($kind, 1, $archived);
    $target = $rows[0];
    $organization = $target instanceof Organization ? $target : $target->organization;
    $member = User::factory()->create();
    OrganizationUser::factory()->forOrganization($organization)->forUser($member)
        ->forSystemRole(SystemRole::Waiter)->active()->create();
    $component = match ($kind) {
        'organizations' => Livewire::actingAs($member)->test(OrganizationIndex::class),
        'brands' => Livewire::actingAs($member)->test(BrandIndex::class, ['organization' => $organization]),
        default => Livewire::actingAs($member)->test(BranchIndex::class, ['organization' => $organization, 'brand' => $target->brand]),
    };

    if ($archived) {
        $component->call('restore', $target->id)->assertForbidden();
    } else {
        $property = match ($kind) {
            'organizations' => 'deletingOrganizationId',
            'brands' => 'deletingBrandId',
            default => 'deletingBranchId',
        };
        $component->set($property, $target->id)->call('delete')->assertForbidden();
    }

    expect($target->fresh()->trashed())->toBe($archived);
})->with(['organizations', 'brands', 'branches'])->with([false, true]);

test('structure breadcrumbs treat domain names as literal text and translate only navigation labels', function (string $kind, string $name): void {
    $owner = User::factory()->create();
    $organization = Organization::factory()->for($owner, 'owner')->create(['name' => $name]);
    OrganizationUser::factory()->forOrganization($organization)->forUser($owner)
        ->forSystemRole(SystemRole::Owner)->active()->create();
    $brand = Brand::factory()->for($organization)->create(['name' => $name]);
    $component = $kind === 'brands'
        ? Livewire::actingAs($owner)->test(BrandIndex::class, ['organization' => $organization])
        : Livewire::actingAs($owner)->test(BranchIndex::class, ['organization' => $organization, 'brand' => $brand]);
    $document = HTMLDocument::createFromString('<!doctype html><html><body>'.$component->html().'</body></html>');
    $breadcrumbs = $document->querySelector('.rm-page-header__breadcrumbs');

    expect(trim($breadcrumbs->querySelector('a')->textContent))->toBe(__('navigation.organizations'))
        ->and(trim($breadcrumbs->querySelector('[aria-current="page"]')->textContent))->toBe($name);
    if ($kind === 'branches') {
        expect(trim($breadcrumbs->querySelectorAll('a')->item(1)->textContent))->toBe($name);
    }
})->with(['brands', 'branches'])->with(['navigation.organizations', 'validation']);

/** @return array{Testable, list<Model>, string} */
function structureListFixture(string $kind, int $count, bool $archived = false): array
{
    $owner = User::factory()->create();
    $organization = Organization::factory()->for($owner, 'owner')->create(['name' => 'Context organization']);
    if ($kind !== 'organizations') {
        OrganizationUser::factory()->forOrganization($organization)->forUser($owner)
            ->forSystemRole(SystemRole::Owner)->active()->create();
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
            OrganizationUser::factory()->forOrganization($row)->forUser($owner)
                ->forSystemRole(SystemRole::Owner)->active()->create();
        }
        $rows[] = $row;
    }

    $component = match ($kind) {
        'organizations' => Livewire::actingAs($owner)->test(OrganizationIndex::class)->set('search', 'Visible'),
        'brands' => Livewire::actingAs($owner)->test(BrandIndex::class, ['organization' => $organization])->set('search', 'Visible'),
        default => Livewire::actingAs($owner)->test(BranchIndex::class, ['organization' => $organization, 'brand' => $brand])->set('search', 'Visible'),
    };

    return [$component, $rows, $kind.'Page'];
}
