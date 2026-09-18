<?php

declare(strict_types=1);

use App\Actions\Branches\DeleteBranchAction;
use App\Actions\Branches\RestoreBranchAction;
use App\Actions\Branches\UpdateBranchAction;
use App\Actions\Organizations\ChangeStructureLifecycleAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Livewire\Onboarding\RestaurantSetup;
use App\Livewire\Restaurants\IdentityEditor;
use App\Livewire\Restaurants\Index;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\RestaurantOnboarding;
use App\Models\User;
use App\Services\Organizations\RestaurantCenterQuery;
use Database\Seeders\SystemPermissionsSeeder;
use Dom\HTMLDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->actor = User::factory()->create();
    $this->organization = app(CreateOrganizationAction::class)->handle($this->actor, ['name' => 'Organization']);
    $this->brand = Brand::factory()->for($this->organization)->create();
    $this->first = Branch::factory()->for($this->organization)->for($this->brand)->create();
    $this->prior = RestaurantOnboarding::factory()->for($this->actor)->for($this->organization)->for($this->brand)->for($this->first)->create();
});

it('opens creation without selecting or changing an existing attempt and creates once in the same card', function (): void {
    $before = $this->prior->fresh()->getAttributes();
    $page = Livewire::actingAs($this->actor)->test(RestaurantSetup::class)
        ->assertSet('onboardingId', null)
        ->set('form.organizationId', $this->organization->id)->set('form.brandId', $this->brand->id)
        ->set('form.branchName', 'Second')->set('form.branchAddress', 'Address 2')->set('form.branchCity', 'Vilnius')
        ->set('form.branchCountryCode', 'LT')->set('form.branchTimezone', 'Europe/Vilnius')->set('form.branchCurrency', 'EUR')
        ->call('createRestaurant')->assertHasNoErrors();
    expect($page->get('onboardingId'))->not->toBeNull()->not->toBe($this->prior->id);
    $page->call('createRestaurant')->assertHasNoErrors();
    expect(Branch::query()->count())->toBe(2)->and($this->prior->fresh()->getAttributes())->toBe($before);
});

it('opens only the explicit private attempt and allows menu before rooms without losing other input', function (): void {
    Livewire::actingAs($this->actor)->test(RestaurantSetup::class, ['setup' => $this->prior->id])
        ->set('form.areaName', 'Unsaved room')->call('goToStep', 3)
        ->set('form.menuName', 'Lunch')->set('form.categoryName', 'Soups')->set('form.itemName', 'Soup')->set('form.itemPrice', '4.50')
        ->call('createStarterMenu')->assertHasNoErrors()->assertSet('form.areaName', 'Unsaved room');
    expect($this->prior->fresh()->menu_id)->not->toBeNull()->and($this->prior->fresh()->completed_at)->toBeNull();
    Livewire::actingAs(User::factory()->create())->test(RestaurantSetup::class, ['setup' => $this->prior->id])->assertNotFound();
});

it('renders the single center and preserves a filtered list around the selected restaurant', function (): void {
    $this->actingAs($this->actor)->get(route('restaurants.index', ['q' => $this->first->name, 'kind' => 'branch', 'object' => $this->first->id]))->assertOk()->assertSee('data-page="restaurant-center"', false);
    Livewire::actingAs($this->actor)->test(Index::class)->set('filters.search', 'not existing')->assertSee(__('center.no_results'));
});

it('accepts empty nullable URL filters without changing their transport state', function (): void {
    Livewire::actingAs($this->actor)->test(Index::class)
        ->set(['filters.view' => 'structure', 'filters.search' => null, 'filters.organizationId' => null, 'filters.brandId' => null])
        ->assertHasNoErrors()->assertSee($this->organization->name)
        ->assertSet('filters.search', null)->assertSet('filters.organizationId', null);
});

it('rejects a stale identity and keeps local input', function (): void {
    $first = Livewire::actingAs($this->actor)->test(IdentityEditor::class, ['kind' => 'branch', 'objectId' => $this->first->id]);
    Livewire::actingAs($this->actor)->test(IdentityEditor::class, ['kind' => 'branch', 'objectId' => $this->first->id])->set('form.name', 'Current')->call('save')->assertHasNoErrors();
    $first->set('form.name', 'Unsaved')->call('save')->assertHasErrors('form.name')->assertSet('form.name', 'Unsaved');
    expect($this->first->fresh()->name)->toBe('Current');
});

it('refuses identity saving after account change or permission revocation', function (): void {
    $page = Livewire::actingAs($this->actor)->test(IdentityEditor::class, ['kind' => 'branch', 'objectId' => $this->first->id]);
    $page->set('form.name', 'Unauthorized');
    $this->organization->memberships()->where('user_id', $this->actor->id)->update(['status' => 'suspended']);
    $page->call('save')->assertForbidden();
    expect($this->first->fresh()->name)->not->toBe('Unauthorized');
});

it('prepares rooms and tables once without rewriting their identities or restoring archived records', function (): void {
    $page = Livewire::actingAs($this->actor)->test(RestaurantSetup::class, ['setup' => $this->prior->id])
        ->set('form.areaName', 'Hall')->call('createArea')->assertHasNoErrors()
        ->set('form.tablePrefix', 'Table')->set('form.tableCount', 2)->call('createServicePoints')->assertHasNoErrors();
    $points = $this->prior->servicePoints()->get();
    $before = $points->map->getAttributes()->all();
    $page->call('createServicePoints')->assertHasNoErrors();
    expect($this->prior->servicePoints()->get()->map->getAttributes()->all())->toBe($before);
    $points->first()->delete();
    $page->call('createServicePoints')->assertHasErrors('form.tableCount');
    expect($points->first()->fresh()->trashed())->toBeTrue();
});

it('archives and restores one restaurant without publishing it', function (): void {
    $page = Livewire::actingAs($this->actor)->test(IdentityEditor::class, ['kind' => 'branch', 'objectId' => $this->first->id])
        ->set('confirmation', $this->first->name)->call('changeLifecycle')->assertHasNoErrors();
    expect($this->first->fresh()->trashed())->toBeTrue();
    $page->set('confirmation', $this->first->name)->call('changeLifecycle')->assertHasNoErrors();
    expect($this->first->fresh()->trashed())->toBeFalse()->and($this->first->fresh()->is_active)->toBeFalse();
});

it('retains a legacy partial attempt and its existing parents when completing restaurant identity', function (): void {
    $this->prior->forceFill(['branch_id' => null])->save();
    $page = Livewire::actingAs($this->actor)->test(RestaurantSetup::class, ['setup' => $this->prior->id])
        ->set('form.branchName', 'Continued')->set('form.branchAddress', 'Road 1')->set('form.branchCity', 'Vilnius')
        ->set('form.branchCountryCode', 'LT')->set('form.branchTimezone', 'UTC')->set('form.branchCurrency', 'EUR')
        ->call('createRestaurant')->assertHasNoErrors()->assertSet('onboardingId', $this->prior->id);
    expect(RestaurantOnboarding::query()->count())->toBe(1)->and($this->prior->fresh()->organization_id)->toBe($this->organization->id)
        ->and($this->prior->fresh()->brand_id)->toBe($this->brand->id);
});

it('does not mark deferred groups complete or enable any resource by visiting review', function (): void {
    Livewire::actingAs($this->actor)->test(RestaurantSetup::class, ['setup' => $this->prior->id])
        ->call('goToStep', 4)->call('complete')->assertHasErrors('preparation');
    expect($this->prior->fresh()->completed_at)->toBeNull();
});

it('does not retain separately callable legacy structure editors', function (): void {
    foreach (['Organizations/Index', 'Organizations/Brands/Index', 'Organizations/Brands/Branches/Index'] as $path) {
        expect(file_exists(app_path('Livewire/'.$path.'.php')))->toBeFalse()
            ->and(view()->exists('livewire.'.strtolower(str_replace('/', '.', $path))))->toBeFalse();
    }
});

it('keeps the canonical center bounded on the thirty restaurant fixture', function (): void {
    Branch::factory()->count(29)->for($this->organization)->for($this->brand)
        ->sequence(fn ($sequence): array => ['name' => 'Measured restaurant '.str_pad((string) $sequence->index, 3, '0', STR_PAD_LEFT)])->create();
    $models = 0;
    Event::listen('eloquent.retrieved: *', function () use (&$models): void {
        $models++;
    });
    gc_collect_cycles();
    memory_reset_peak_usage();
    $memory = memory_get_usage();
    $start = hrtime(true);
    try {
        $queries = countDatabaseQueries(function () use (&$page): void {
            $page = Livewire::actingAs($this->actor)->test(Index::class, ['organization' => $this->organization, 'brand' => $this->brand]);
        });
        $result = ['sql' => $queries, 'models' => $models, 'peak_memory_delta_bytes' => memory_get_peak_usage() - $memory,
            'html_bytes' => strlen($page->html()), 'snapshot_bytes' => strlen(json_encode($page->snapshot, JSON_THROW_ON_ERROR)), 'milliseconds' => round((hrtime(true) - $start) / 1000000, 2)];
    } finally {
        Event::forget('eloquent.retrieved: *');
    }
    fwrite(STDOUT, "\nRestaurant center fixture (30 restaurants): ".json_encode($result, JSON_THROW_ON_ERROR)."\n");
    expect($result['snapshot_bytes'])->toBeLessThan(1500)
        ->and($result['html_bytes'])->toBeLessThan(150000)
        ->and($result['sql'])->toBeLessThanOrEqual(60)
        ->and($page->viewData('rows')->items())->toHaveCount(20);
});

it('does not turn an outdated archive confirmation into restoration', function (): void {
    $page = Livewire::actingAs($this->actor)->test(IdentityEditor::class, ['kind' => 'branch', 'objectId' => $this->first->id]);
    app(DeleteBranchAction::class)->handle($this->actor, $this->organization, $this->brand, $this->first);
    expect($this->first->fresh()->trashed())->toBeTrue();
    $page->assertSet('wasArchived', false);
    $page->set('confirmation', $this->first->name)->call('changeLifecycle')->assertHasErrors('confirmation');
    expect($this->first->fresh()->trashed())->toBeTrue();
});

it('does not turn an outdated restore confirmation into archiving', function (): void {
    app(DeleteBranchAction::class)->handle($this->actor, $this->organization, $this->brand, $this->first);
    $page = Livewire::actingAs($this->actor)->test(IdentityEditor::class, ['kind' => 'branch', 'objectId' => $this->first->id]);
    app(RestoreBranchAction::class)->handle($this->actor, $this->organization, $this->brand, $this->first);
    expect($this->first->fresh()->trashed())->toBeFalse();
    $page->assertSet('wasArchived', true);
    $page->set('confirmation', $this->first->name)->call('changeLifecycle')->assertHasErrors('confirmation');
    expect($this->first->fresh()->trashed())->toBeFalse();
});

it('reports a refused structure lifecycle write instead of reporting success', function (string $kind, bool $restore): void {
    $resource = match ($kind) {
        'organization' => $this->organization, 'brand' => $this->brand, default => $this->first
    };
    if ($restore) {
        $resource->delete();
    }
    $resource->refresh();
    $event = 'eloquent.'.($restore ? 'restoring' : 'deleting').': '.$resource::class;
    Event::listen($event, fn (): bool => false);
    try {
        expect(fn () => app(ChangeStructureLifecycleAction::class)->handle($this->actor, $resource, $resource->identityFingerprint(), $resource->name, $restore))->toThrow(RuntimeException::class);
    } finally {
        Event::forget($event);
    }
    expect($resource->fresh()->trashed())->toBe($restore);
})->with(['organization', 'brand', 'branch'])->with([false, true]);

it('keeps a populated later page and returns to existing results when that page becomes empty', function (): void {
    $rows = Branch::factory()->count(21)->for($this->organization)->for($this->brand)
        ->sequence(fn ($sequence): array => ['name' => 'Visible '.str_pad((string) $sequence->index, 3, '0', STR_PAD_LEFT)])->create();
    $page = Livewire::actingAs($this->actor)->test(Index::class)->set('filters.search', 'Visible')->call('setPage', 2);
    $page->assertSee('Visible 020');
    $rows->last()->delete();
    $page->call('lifecycleSaved')->assertSet('paginators.page', 1)->assertSet('filters.search', 'Visible')->assertSee('Visible 000');
    $page->call('setPage', 99)->assertSet('paginators.page', 1);
});

it('keeps the current page after a lifecycle change if there are still rows on that page', function (): void {
    $rows = Branch::factory()->count(22)->for($this->organization)->for($this->brand)
        ->sequence(fn ($sequence): array => ['name' => 'Visible '.str_pad((string) $sequence->index, 3, '0', STR_PAD_LEFT)])->create();
    $page = Livewire::actingAs($this->actor)->test(Index::class)->set('filters.search', 'Visible')->call('setPage', 2);
    $rows->last()->delete();
    $page->call('lifecycleSaved')->assertSet('paginators.page', 2)->assertSee('Visible 020');
});

it('saves the selected logo independently and preserves unsaved identity input', function (string $kind): void {
    Storage::fake('public');
    $resource = match ($kind) {
        'organization' => $this->organization, 'brand' => $this->brand, default => $this->first
    };
    $name = $resource->name;
    $page = Livewire::actingAs($this->actor)->test(IdentityEditor::class, ['kind' => $kind, 'objectId' => $resource->id])
        ->set('form.name', 'Unsaved title')->set('logo', UploadedFile::fake()->image('logo.jpg', 120, 120))
        ->call('saveLogo')->assertHasNoErrors()->assertSet('form.name', 'Unsaved title');
    expect($resource->fresh()->name)->toBe($name);
    Storage::disk('public')->assertExists($resource->fresh()->logo_path);
    $page->set('form.name', '')->call('save')->assertHasErrors('form.name')
        ->set('logo', UploadedFile::fake()->image('next.jpg', 120, 120))->call('saveLogo')->assertHasErrors('form.name');
})->with(['organization', 'brand', 'branch']);

it('rejects outdated logo deletion without removing the new image or unsaved text', function (): void {
    Storage::fake('public');
    $page = Livewire::actingAs($this->actor)->test(IdentityEditor::class, ['kind' => 'branch', 'objectId' => $this->first->id])->set('form.name', 'Unsaved title');
    Livewire::actingAs($this->actor)->test(IdentityEditor::class, ['kind' => 'branch', 'objectId' => $this->first->id])
        ->set('logo', UploadedFile::fake()->image('logo.jpg', 120, 120))->call('saveLogo')->assertHasNoErrors();
    $path = $this->first->fresh()->logo_path;
    $page->call('removeLogo')->assertHasErrors('logo')->assertSet('form.name', 'Unsaved title');
    expect($this->first->fresh()->logo_path)->toBe($path);
    Storage::disk('public')->assertExists($path);
});

it('renders valid active filter values from the filter contract', function (): void {
    $page = Livewire::actingAs($this->actor)->test(Index::class);
    $document = HTMLDocument::createFromString('<!doctype html><html><body>'.$page->html().'</body></html>');
    foreach (['filters.active', 'filters.lifecycle'] as $binding) {
        $control = $document->querySelector('[wire\\:model\\.live="'.$binding.'"]');
        expect($control)->not->toBeNull();
        $values = array_map(fn ($node) => $node->getAttribute('value'), iterator_to_array($control->querySelectorAll('[value]')));
        expect($values)->toContain('active')->not->toContain('filters.active');
    }
});

it('center refresh requires an audited reason before suspending from the canonical editor', function (): void {
    $this->first->update(['is_active' => true]);
    $page = Livewire::actingAs($this->actor)->test(IdentityEditor::class, ['kind' => 'branch', 'objectId' => $this->first->id])
        ->set('form.name', 'Unsaved suspension draft')->set('form.isActive', false)
        ->call('save')->assertHasErrors('form.suspensionReason')->assertSet('form.name', 'Unsaved suspension draft');
    expect($this->first->fresh()->is_active)->toBeTrue()->and($this->first->fresh()->name)->not->toBe('Unsaved suspension draft');
    $page->set('form.suspensionReason', 'x')->call('save')->assertHasErrors('form.suspensionReason');
    $page->set('form.suspensionReason', '  Seasonal closure  ')->call('save')->assertHasNoErrors();
    expect($this->first->fresh()->is_active)->toBeFalse()->and($this->first->fresh()->name)->toBe('Unsaved suspension draft');
    $audit = AuditLog::query()->where('entity_type', 'branch')->where('entity_id', $this->first->id)->latest('id')->firstOrFail();
    expect($audit->new_values['reason'])->toBe('Seasonal closure');
});

it('center refresh protects direct suspension callers before any identity write', function (?string $reason): void {
    $this->first->update(['is_active' => true]);
    $before = $this->first->fresh()->getAttributes();
    $data = $this->first->only(['name', 'address', 'city', 'country', 'timezone', 'currency']);
    expect(fn () => app(UpdateBranchAction::class)->handle($this->first, [...$data, 'name' => 'Must not persist', 'is_active' => false], $this->actor, $reason))
        ->toThrow(ValidationException::class);
    expect($this->first->fresh()->getAttributes())->toBe($before);
})->with([null, '', '  ', 'x', str_repeat('x', 501)]);

it('center refresh opens authorized archived organization children without creation lookup failure', function (): void {
    $this->organization->delete();
    $this->actingAs($this->actor)->get(route('restaurants.index', ['view' => 'structure', 'organization' => $this->organization->id]))
        ->assertOk()->assertSee($this->brand->name);
    $this->actingAs(User::factory()->create())->get(route('restaurants.index', ['view' => 'structure', 'organization' => $this->organization->id]))->assertForbidden();
});

it('center refresh sorts restaurants and preserves contextual filters while clearing a failed search', function (): void {
    $this->first->update(['name' => 'Alpha']);
    Branch::factory()->for($this->organization)->for($this->brand)->create(['name' => 'Zulu']);
    Livewire::actingAs($this->actor)->test(Index::class)
        ->set('filters.organizationId', (string) $this->organization->id)->set('filters.brandId', (string) $this->brand->id)
        ->set('filters.sort', 'name_desc')
        ->assertViewHas('rows', fn ($rows) => $rows->pluck('name')->all() === ['Zulu', 'Alpha'])
        ->set('filters.search', 'No match')->assertViewHas('emptyState', 'search')
        ->call('clearFilters')->assertSet('filters.search', '')->assertSet('filters.sort', 'name_desc')
        ->assertSet('filters.organizationId', (string) $this->organization->id)->assertSet('filters.brandId', (string) $this->brand->id)
        ->assertSee('Zulu');
});

it('center refresh searches bounded parent options beyond the first page and clears a changed parent', function (): void {
    Brand::factory()->count(22)->for($this->organization)->sequence(fn ($sequence) => ['name' => sprintf('Brand %02d', $sequence->index)])->create();
    $target = Brand::factory()->for($this->organization)->create(['name' => 'ZZZ selected brand']);
    Livewire::actingAs($this->actor)->test(Index::class)->set('filters.organizationId', (string) $this->organization->id)
        ->set('brandSearch', 'ZZZ')->assertViewHas('brands', fn ($rows) => $rows === [$target->id => $target->name])
        ->set('filters.brandId', (string) $target->id)->set('brandSearch', 'Brand')
        ->assertViewHas('brands', fn ($rows) => count($rows) === 21 && isset($rows[$target->id]))
        ->set('filters.organizationId', '')->assertSet('filters.brandId', '')->assertSet('brandSearch', '');
});

it('center refresh distinguishes empty structure from denied access without exposing foreign names', function (): void {
    $newActor = User::factory()->create();
    Livewire::actingAs($newActor)->test(Index::class)->assertViewHas('emptyState', 'empty')->assertViewHas('canCreateRestaurant', true)->assertDontSee($this->first->name);
    $this->organization->memberships()->where('user_id', $this->actor->id)->update(['status' => 'suspended']);
    Livewire::actingAs($this->actor)->test(Index::class)->assertViewHas('emptyState', 'no_access')->assertViewHas('canCreateRestaurant', false)->assertDontSee($this->first->name);
    expect(app(RestaurantCenterQuery::class)->canCreateRestaurant($this->actor, (string) $this->organization->id))->toBeFalse();
    Gate::before(fn (User $user, string $ability, array $arguments): ?bool => $ability === 'create' && ($arguments[0] ?? null) === Organization::class ? false : null);
    Livewire::actingAs($this->actor)->test(Index::class)->assertViewHas('emptyState', 'no_access')->assertViewHas('canCreateRestaurant', false);
});
