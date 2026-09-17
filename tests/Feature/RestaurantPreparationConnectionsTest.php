<?php

declare(strict_types=1);

use App\Actions\Onboarding\ContinueRestaurantPreparationAction;
use App\Actions\Onboarding\SaveOnboardingServicePointsAction;
use App\Actions\Onboarding\UseExistingSetupMenuAction;
use App\Actions\Onboarding\UseExistingSetupSpaceAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Livewire\Onboarding\RestaurantSetup;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\RestaurantOnboarding;
use App\Models\ServicePoint;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->actor = User::factory()->create();
    $this->organization = app(CreateOrganizationAction::class)->handle($this->actor, ['name' => 'Existing business']);
    $this->brand = Brand::factory()->for($this->organization)->create();
    $this->branch = Branch::factory()->for($this->organization)->for($this->brand)->create(['is_active' => false]);
    $this->setup = app(ContinueRestaurantPreparationAction::class)->handle($this->actor, $this->branch);
});

it('continues a restaurant created outside onboarding without inventing completion or changing its identity', function (): void {
    $before = $this->branch->fresh()->getAttributes();
    $again = app(ContinueRestaurantPreparationAction::class)->handle($this->actor, $this->branch);
    expect($again->id)->toBe($this->setup->id)->and($again->completed_at)->toBeNull()
        ->and($this->branch->fresh()->getAttributes())->toBe($before)
        ->and(RestaurantOnboarding::query()->count())->toBe(1);
});

it('connects an existing room and tables once without changing their configured state', function (): void {
    $area = AreaNode::factory()->for($this->branch)->create(['is_active' => false]);
    $points = ServicePoint::factory()->count(2)->for($this->branch)->create(['area_node_id' => $area->id, 'is_active' => false]);
    $before = $points->map(fn ($point): array => $point->fresh()->getAttributes())->all();
    $action = app(UseExistingSetupSpaceAction::class);
    $action->handle($this->actor, $this->setup->id, $area->id, 0);
    $again = $action->handle($this->actor, $this->setup->id, $area->id, 0);
    expect($again->setup_version)->toBe(1)->and($again->servicePoints()->pluck('service_points.id')->all())->toBe($points->modelKeys())
        ->and($points->map(fn ($point): array => $point->fresh()->getAttributes())->all())->toBe($before)
        ->and($area->fresh()->is_active)->toBeFalse();
});

it('refuses foreign rooms and stale selections without writing a checkpoint', function (): void {
    $action = app(UseExistingSetupSpaceAction::class);
    $foreign = AreaNode::factory()->create();
    expect(fn () => $action->handle($this->actor, $this->setup->id, $foreign->id, 0))->toThrow(ModelNotFoundException::class);
    $area = AreaNode::factory()->for($this->branch)->create();
    expect(fn () => $action->handle($this->actor, $this->setup->id, $area->id, 99))->toThrow(ValidationException::class);
    expect($this->setup->fresh()->area_node_id)->toBeNull()->and($this->setup->servicePoints()->count())->toBe(0);
});

it('rolls back room links when the checkpoint write is refused', function (): void {
    $area = AreaNode::factory()->for($this->branch)->create();
    ServicePoint::factory()->for($this->branch)->create(['area_node_id' => $area->id]);
    Event::listen('eloquent.updating: '.RestaurantOnboarding::class, fn (): bool => false);
    expect(fn () => app(UseExistingSetupSpaceAction::class)->handle($this->actor, $this->setup->id, $area->id, 0))->toThrow(RuntimeException::class);
    expect($this->setup->fresh()->area_node_id)->toBeNull()->and($this->setup->servicePoints()->count())->toBe(0);
});

it('uses an existing menu without changing its content or publication and rejects replacement from a stale form', function (): void {
    $menu = Menu::factory()->for($this->branch)->create();
    $category = MenuCategory::factory()->for($menu)->create();
    $item = MenuItem::factory()->for($menu)->create(['category_id' => $category->id, 'description' => 'Preserved EN LT RU content', 'sort_order' => 31, 'is_available' => false]);
    $before = [$menu->fresh()->getAttributes(), $item->fresh()->getAttributes()];
    $action = app(UseExistingSetupMenuAction::class);
    $action->handle($this->actor, $this->setup->id, $menu->id, 0);
    $again = $action->handle($this->actor, $this->setup->id, $menu->id, 0);
    expect($again->setup_version)->toBe(1)->and([$menu->fresh()->getAttributes(), $item->fresh()->getAttributes()])->toBe($before);
    $other = Menu::factory()->for($this->branch)->create();
    expect(fn () => $action->handle($this->actor, $this->setup->id, $other->id, 0))->toThrow(ValidationException::class);
    expect($this->setup->fresh()->menu_id)->toBe($menu->id);
});

it('bounds direct table creation before allocating records', function (mixed $count): void {
    $area = AreaNode::factory()->for($this->branch)->create();
    $this->setup->forceFill(['area_node_id' => $area->id])->save();
    expect(fn () => app(SaveOnboardingServicePointsAction::class)->handle($this->actor, $this->setup->id, ['tableCount' => $count, 'tablePrefix' => 'Table', 'tableCapacity' => 4]))->toThrow(ValidationException::class);
    expect(ServicePoint::query()->count())->toBe(0);
})->with([0, 21, true, 'invalid']);

it('shows the existing current readiness source separately from recorded completion', function (): void {
    $this->setup->update(['completed_at' => now()->subMonth()]);
    Livewire::actingAs($this->actor)->test(RestaurantSetup::class, ['setup' => $this->setup->id])
        ->call('goToStep', 4)->assertSee(__('center.completed_history'))->assertSee(__('readiness.branch'));
    expect($this->branch->fresh()->is_active)->toBeFalse();
});
