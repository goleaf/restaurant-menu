<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Livewire\Onboarding\RestaurantSetup;
use App\Livewire\Restaurants\IdentityEditor;
use App\Livewire\Restaurants\Index;
use App\Livewire\Restaurants\StructureCreate;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\RestaurantOnboarding;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Dom\HTMLDocument;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->actor = User::factory()->create();
    $this->organization = app(CreateOrganizationAction::class)->handle($this->actor, ['name' => 'Interface organization']);
    $this->brand = Brand::factory()->for($this->organization)->create();
    $this->branch = Branch::factory()->for($this->organization)->for($this->brand)->create();
    $this->setup = RestaurantOnboarding::factory()->for($this->actor)->for($this->organization)->for($this->brand)->for($this->branch)->create();
});

it('exposes four persistent setup groups with a current step and backward controls', function (): void {
    $page = Livewire::actingAs($this->actor)->test(RestaurantSetup::class, ['setup' => $this->setup->id]);

    foreach ([1, 2, 3, 4] as $step) {
        $page->call('goToStep', $step)->assertHasNoErrors();
        $document = HTMLDocument::createFromString('<!doctype html><html><body>'.$page->html().'</body></html>');
        $navigation = $document->querySelector('nav[aria-label]');

        expect($navigation->querySelectorAll('button')->length)->toBe(4)
            ->and($navigation->querySelectorAll('[aria-current="step"]')->length)->toBe(1)
            ->and($navigation->querySelector('[aria-current="step"]')->getAttribute('aria-controls'))->toBe('restaurant-setup-group-'.$step);

        foreach ([1, 2, 3, 4] as $group) {
            $panel = $document->querySelector('#restaurant-setup-group-'.$group);
            expect($panel)->not->toBeNull()
                ->and($panel->getAttribute('wire:show'))->toBe('step === '.$group)
                ->and($panel->getAttribute('tabindex'))->toBe('-1');
            if ($group > 1) {
                expect($panel->querySelector('[data-setup-back]')->getAttribute('wire:click'))->toBe('goToStep('.($group - 1).')');
            }
        }
    }
});

it('labels every remote option search and preserves the draft-friendly setup form boundaries', function (): void {
    $document = HTMLDocument::createFromString('<!doctype html><html><body>'.Livewire::actingAs($this->actor)->test(RestaurantSetup::class)->html().'</body></html>');

    foreach (['organizationSearch' => 'center.search_organizations', 'brandSearch' => 'center.search_brands', 'areaSearch' => 'center.search_rooms', 'menuSearch' => 'center.search_menus'] as $model => $label) {
        $search = $document->querySelector('input[wire\\:model\\.live\\.debounce\\.300ms="'.$model.'"]');
        expect($search)->not->toBeNull()
            ->and($search->getAttribute('aria-label'))->toBe(__($label));
    }

    foreach ($document->querySelectorAll('form[wire\\:submit]') as $form) {
        expect($form->hasAttribute('novalidate'))->toBeTrue()
            ->and($form->getAttribute('wire:target'))->toBe($form->getAttribute('wire:submit'))
            ->and($form->getAttribute('wire:loading.attr'))->toBe('aria-busy');
    }
});

it('offers labeled server searches and sorting without embedding logo editors in restaurant rows', function (): void {
    $document = HTMLDocument::createFromString('<!doctype html><html><body>'.Livewire::actingAs($this->actor)->test(Index::class)->html().'</body></html>');

    foreach (['organizationSearch' => 'center.search_organizations', 'brandSearch' => 'center.search_brands'] as $model => $label) {
        $search = $document->querySelector('input[wire\\:model\\.live\\.debounce\\.300ms="'.$model.'"]');
        expect($search)->not->toBeNull()
            ->and($search->getAttribute('aria-label'))->toBe(__($label));
    }

    expect($document->querySelector('[wire\\:model\\.live="filters.sort"]'))->not->toBeNull()
        ->and($document->querySelector('.rm-restaurant-center__row form'))->toBeNull()
        ->and($document->querySelector('[data-flux-tabs]')->getAttribute('aria-label'))->not->toBe('');
});

it('gives the destructive lifecycle dialog an explicit translated safe close control', function (): void {
    $document = HTMLDocument::createFromString('<!doctype html><html><body>'.Livewire::actingAs($this->actor)
        ->test(IdentityEditor::class, ['kind' => 'branch', 'objectId' => $this->branch->id])->html().'</body></html>');
    $dialog = $document->querySelector('[data-modal="structure-lifecycle"]');

    expect($dialog)->not->toBeNull()
        ->and($dialog->querySelector('[aria-label="'.__('ui.accessibility.close_dialog').'"]'))->not->toBeNull()
        ->and($dialog->querySelector('[autofocus]'))->not->toBeNull();
});

it('targets the actual invalid Flux listbox button when it is the only failed field', function (): void {
    $page = Livewire::actingAs($this->actor)->test(RestaurantSetup::class)
        ->set('form.organizationId', $this->organization->id)->set('form.brandId', $this->brand->id)
        ->set('form.branchName', 'New restaurant')->set('form.branchAddress', 'Example 42')->set('form.branchCity', 'Vilnius')
        ->set('form.branchTimezone', 'Europe/Vilnius')->set('form.branchCurrency', 'EUR')
        ->call('createRestaurant')->assertHasErrors(['form.branchCountryCode' => 'required']);
    $document = HTMLDocument::createFromString('<!doctype html><html><body>'.$page->html().'</body></html>');
    $root = $document->querySelector('[data-page="restaurant-setup"]');
    preg_match("/invalidSelector: '([^']+)'/", $root->getAttribute('x-data'), $configuration);
    $target = $root->querySelector($configuration[1]);

    expect($target)->not->toBeNull()
        ->and($target->localName)->toBe('button', substr($target->outerHTML, 0, 700))
        ->and($target->hasAttribute('data-flux-select-button'))->toBeTrue()
        ->and($target->hasAttribute('data-invalid'))->toBeTrue();
});

it('opens the canonical rooms editor for a saved room before any tables or QR exist', function (): void {
    $page = Livewire::actingAs($this->actor)->test(RestaurantSetup::class, ['setup' => $this->setup->id])
        ->set('form.areaName', 'Saved empty room')->call('createArea')->assertHasNoErrors();
    $url = route('organizations.brands.branches.service-points.index', [$this->organization, $this->brand, $this->branch]);

    $page->assertSeeHtml('href="'.$url.'"');
    expect($this->setup->servicePoints()->count())->toBe(0);
});

it('associates rejected center fields with readable error text', function (string $surface): void {
    $page = match ($surface) {
        'identity' => Livewire::actingAs($this->actor)->test(IdentityEditor::class, ['kind' => 'branch', 'objectId' => $this->branch->id])
            ->set('form.name', '')->call('save')->assertHasErrors(['form.name']),
        'structure' => Livewire::actingAs($this->actor)->test(StructureCreate::class, ['kind' => 'brand', 'organizationId' => $this->organization->id])
            ->call('save')->assertHasErrors(['form.name']),
        'logo' => Livewire::actingAs($this->actor)->test(IdentityEditor::class, ['kind' => 'branch', 'objectId' => $this->branch->id])
            ->call('saveLogo')->assertHasErrors(['logo']),
        'lifecycle' => Livewire::actingAs($this->actor)->test(IdentityEditor::class, ['kind' => 'branch', 'objectId' => $this->branch->id])
            ->set('confirmation', 'Incorrect restaurant')->call('changeLifecycle')->assertHasErrors(['confirmation']),
        'setup' => Livewire::actingAs($this->actor)->test(RestaurantSetup::class)
            ->set('form.organizationId', $this->organization->id)->set('form.brandId', $this->brand->id)
            ->call('createRestaurant')->assertHasErrors(['form.branchName', 'form.branchCountryCode']),
    };
    $document = HTMLDocument::createFromString('<!doctype html><html><body>'.$page->html().'</body></html>');
    $controls = $document->querySelectorAll('input[aria-invalid="true"], textarea[aria-invalid="true"], select[aria-invalid="true"], button[data-invalid], [data-flux-file-upload-dropzone][aria-invalid="true"]');

    expect($controls->length)->toBeGreaterThan(0);
    foreach ($controls as $control) {
        $description = $control->getAttribute('aria-describedby');
        expect($description)->toBeString($control->outerHTML)->not->toBe('');
        $error = $document->getElementById($description);
        expect($error)->not->toBeNull($control->outerHTML)
            ->and($error->hasAttribute('data-flux-error'))->toBeTrue()
            ->and(trim($error->textContent))->not->toBe('')
            ->and($error->classList->contains('text-danger!'))->toBeTrue();
    }
})->with(['identity', 'structure', 'logo', 'lifecycle', 'setup']);

it('provides stable heading bindings for each native center dialog', function (): void {
    $this->branch->update(['logo_path' => 'logos/restaurant-test.png']);
    foreach ([
        Livewire::actingAs($this->actor)->test(Index::class),
        Livewire::actingAs($this->actor)->test(RestaurantSetup::class, ['setup' => $this->setup->id]),
        Livewire::actingAs($this->actor)->test(IdentityEditor::class, ['kind' => 'branch', 'objectId' => $this->branch->id]),
    ] as $page) {
        $document = HTMLDocument::createFromString('<!doctype html><html><body>'.$page->html().'</body></html>');
        $dialogs = $document->querySelectorAll('dialog');
        expect($dialogs->length)->toBeGreaterThan(0);
        foreach ($dialogs as $dialog) {
            $heading = $dialog->querySelector('[x-bind="dialogLabel"][id]');
            expect($heading)->not->toBeNull($dialog->getAttribute('data-modal'))
                ->and(trim($heading->textContent))->not->toBe('');
        }
    }
});

it('keeps distinct canonical parent names literal in the selected restaurant card', function (): void {
    $this->organization->update(['name' => 'navigation.organizations']);
    $this->brand->update(['name' => 'validation']);
    $page = Livewire::actingAs($this->actor)->test(IdentityEditor::class, ['kind' => 'branch', 'objectId' => $this->branch->id]);
    $document = HTMLDocument::createFromString('<!doctype html><html><body>'.$page->html().'</body></html>');

    expect($document->querySelector('[data-center-parent="organization"]')?->textContent)->toBe('navigation.organizations')
        ->and($document->querySelector('[data-center-parent="brand"]')?->textContent)->toBe('validation');
});

it('shows a decorative restaurant thumbnail or placeholder without row editors', function (): void {
    $this->branch->update(['logo_path' => null]);
    $page = Livewire::actingAs($this->actor)->test(Index::class);
    $document = HTMLDocument::createFromString('<!doctype html><html><body>'.$page->html().'</body></html>');
    $row = $document->querySelector('.rm-restaurant-center__row');

    expect($row->querySelector('[data-center-thumbnail][aria-hidden="true"]'))->not->toBeNull()
        ->and($row->querySelector('form, input[type="file"]'))->toBeNull();

    $this->branch->update(['logo_path' => 'logos/restaurant-test.png']);
    $page->call('$refresh');
    $document = HTMLDocument::createFromString('<!doctype html><html><body>'.$page->html().'</body></html>');
    $thumbnail = $document->querySelector('.rm-restaurant-center__row [data-center-thumbnail] img');
    expect($thumbnail)->not->toBeNull()
        ->and($thumbnail->getAttribute('src'))->toBe($this->branch->logoUrl())
        ->and($thumbnail->getAttribute('alt'))->toBe('');
});
