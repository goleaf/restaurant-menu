<?php

declare(strict_types=1);

use App\Actions\Onboarding\ContinueRestaurantPreparationAction;
use App\Actions\Onboarding\GenerateOnboardingQrCodesAction;
use App\Actions\Onboarding\SaveOnboardingServicePointsAction;
use App\Actions\Onboarding\UseExistingSetupMenuAction;
use App\Actions\Onboarding\UseExistingSetupSpaceAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\QrCodes\StoreQrCodeImageAction;
use App\Enums\ServicePointType;
use App\Livewire\Onboarding\RestaurantSetup;
use App\Models\AreaNode;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\QrCode;
use App\Models\RestaurantOnboarding;
use App\Models\ServicePoint;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
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
    $points = ServicePoint::factory()->count(2)->for($this->branch)->create(['area_node_id' => $area->id, 'type' => ServicePointType::Table, 'is_active' => false]);
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

it('retries a QR image write failure after commit without recreating tables or permanent identities', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'restaurant-setup-qr-failure-');
    expect($path)->toBeString();
    $original = DB::getDefaultConnection();
    $connection = config('database.connections.sqlite');
    $connection['database'] = $path;
    $disk = Storage::fake('public');

    try {
        config(['database.connections.restaurant_setup_qr_write' => $connection, 'database.connections.restaurant_setup_qr_read' => $connection]);
        DB::setDefaultConnection('restaurant_setup_qr_write');
        expect(Artisan::call('migrate', ['--database' => 'restaurant_setup_qr_write', '--force' => true]))->toBe(0);
        $this->seed(SystemPermissionsSeeder::class);
        $actor = User::factory()->create();
        $organization = app(CreateOrganizationAction::class)->handle($actor, ['name' => 'QR failure business']);
        $brand = Brand::factory()->for($organization)->create();
        $branch = Branch::factory()->for($organization)->for($brand)->create(['is_active' => false]);
        $setup = app(ContinueRestaurantPreparationAction::class)->handle($actor, $branch);
        $area = AreaNode::factory()->for($branch)->create();
        $points = ServicePoint::factory()->count(2)->for($branch)->create(['area_node_id' => $area->id, 'type' => ServicePointType::Table]);
        app(UseExistingSetupSpaceAction::class)->handle($actor, $setup->id, $area->id, 0);
        $checkpointBefore = $setup->fresh()->getAttributes();
        $tablesBefore = $points->map(fn (ServicePoint $point): array => $point->fresh()->getAttributes())->all();
        $levels = [];
        $writes = 0;
        $proxy = Mockery::mock($disk);
        $proxy->shouldReceive('put')->andReturnUsing(function (...$arguments) use ($disk, &$levels, &$writes): bool {
            $levels[] = DB::connection()->transactionLevel();

            return ++$writes === 2 ? false : $disk->put(...$arguments);
        });
        Storage::set('public', $proxy);
        $action = app(GenerateOnboardingQrCodesAction::class);

        expect(fn () => $action->handle($actor, $setup->id))->toThrow(RuntimeException::class, 'Unable to store the QR image');
        $identities = QrCode::on('restaurant_setup_qr_read')->orderBy('id')->get()->map->getAttributes()->all();
        $auditCount = AuditLog::query()->count();
        expect($levels)->toBe([0, 0])->and($identities)->toHaveCount(2)
            ->and($disk->allFiles('qr'))->toHaveCount(1)
            ->and($setup->fresh()->getAttributes())->toBe($checkpointBefore);

        Storage::set('public', $disk);
        $action->handle($actor, $setup->id);
        $action->handle($actor, $setup->id);
        $qrCodes = QrCode::on('restaurant_setup_qr_read')->orderBy('id')->get();
        $paths = $qrCodes->map(fn (QrCode $qrCode): string => app(StoreQrCodeImageAction::class)->pathFor($qrCode))->all();
        $disk->assertExists($paths);

        expect($qrCodes->map->getAttributes()->all())->toBe($identities)
            ->and($points->map(fn (ServicePoint $point): array => $point->fresh()->getAttributes())->all())->toBe($tablesBefore)
            ->and(ServicePoint::query()->count())->toBe(2)->and($disk->allFiles('qr'))->toHaveCount(2)
            ->and($setup->fresh()->getAttributes())->toBe($checkpointBefore)
            ->and($setup->fresh()->completed_at)->toBeNull()->and($branch->fresh()->is_active)->toBeFalse()
            ->and(AuditLog::query()->count())->toBe($auditCount);
    } finally {
        Storage::set('public', $disk);
        DB::purge('restaurant_setup_qr_read');
        DB::purge('restaurant_setup_qr_write');
        DB::setDefaultConnection($original);
        File::delete([$path, $path.'-wal', $path.'-shm']);
    }
});
