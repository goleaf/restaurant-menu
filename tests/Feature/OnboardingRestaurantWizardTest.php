<?php

use App\Actions\Onboarding\GenerateOnboardingQrCodesAction;
use App\Actions\Onboarding\SaveOnboardingAreaAction;
use App\Actions\Onboarding\SaveOnboardingBranchAction;
use App\Actions\Onboarding\SaveOnboardingBrandAction;
use App\Actions\Onboarding\SaveOnboardingOrganizationAction;
use App\Actions\Onboarding\SaveOnboardingServicePointsAction;
use App\Actions\Onboarding\SaveOnboardingStarterMenuAction;
use App\Actions\QrCodes\GenerateQrCodeForServicePointAction;
use App\Actions\ServicePoints\CreateServicePointAction;
use App\Actions\ServicePoints\UpdateServicePointAction;
use App\Enums\MenuStatus;
use App\Enums\OrganizationSubscriptionStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointType;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Onboarding\RestaurantSetup;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\KitchenDepartment;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\QrCode;
use App\Models\RestaurantOnboarding;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\Onboarding\RestaurantSetupQueryService;
use App\Support\RestaurantSetupOptions;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('restaurant onboarding wizard requires authentication', function () {
    $this->get(route('onboarding.restaurant'))
        ->assertRedirect(route('login'));

    Livewire::test(RestaurantSetup::class)->assertUnauthorized();
});

test('restaurant onboarding with no checkpoint always remounts at the first step', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(RestaurantSetup::class)
        ->assertSet('onboardingId', null)
        ->assertSet('step', 1);

    Livewire::actingAs($user)->test(RestaurantSetup::class)
        ->assertSet('onboardingId', null)
        ->assertSet('step', 1);

    expect(RestaurantOnboarding::query()->where('user_id', $user->id)->doesntExist())->toBeTrue();
});

test('restaurant onboarding persists and resumes after organization creation', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(RestaurantSetup::class)
        ->set('form.organizationName', 'Persistent Food Group')
        ->call('createOrganization')
        ->assertHasNoErrors()
        ->assertSet('step', 2);

    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();

    expect($onboarding->organization_id)->not->toBeNull()
        ->and(Organization::query()->where('owner_user_id', $user->id)->count())->toBe(1);

    Livewire::actingAs($user)
        ->test(RestaurantSetup::class)
        ->assertSet('step', 2)
        ->assertSet('form.organizationName', 'Persistent Food Group')
        ->call('createOrganization')
        ->assertHasNoErrors()
        ->assertSet('step', 2);

    expect(Organization::query()->where('owner_user_id', $user->id)->count())->toBe(1);
});

test('restaurant onboarding persists and resumes after brand creation', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(RestaurantSetup::class)
        ->set('form.organizationName', 'Resume Brand Group')
        ->call('createOrganization')
        ->set('form.brandName', 'Resume Brand')
        ->call('createBrand')
        ->assertHasNoErrors()
        ->assertSet('step', 3);

    Livewire::actingAs($user)
        ->test(RestaurantSetup::class)
        ->assertSet('step', 3)
        ->assertSet('form.brandName', 'Resume Brand');

    expect(Brand::query()->where('name', 'Resume Brand')->count())->toBe(1);
});

test('restaurant onboarding resumes after every checkpoint and remains completed on revisit', function () {
    $user = User::factory()->create();

    foreach (range(2, 8) as $step) {
        restaurantOnboardingComponentAtStep($user, $step, 'Checkpoint')
            ->assertSet('step', $step);
    }

    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();

    expect($onboarding->completed_at)->not->toBeNull()
        ->and($onboarding->servicePoints()->count())->toBe(3)
        ->and(QrCode::query()->count())->toBe(3);

    Livewire::actingAs($user)->test(RestaurantSetup::class)
        ->assertSet('step', 8)
        ->assertSee(__('ui.onboarding.restaurant_setup.restoran_gotov_k_proverke'));
});

test('starter menu checkpoint without completion marker resumes at its idempotent save step', function () {
    $user = User::factory()->create();
    restaurantOnboardingComponentAtStep($user, 8, 'Interrupted Completion');
    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();
    $ids = $onboarding->only(['menu_id', 'menu_category_id', 'menu_item_id']);

    $onboarding->forceFill(['completed_at' => null])->save();

    Livewire::actingAs($user)->test(RestaurantSetup::class)
        ->assertSet('step', 7)
        ->assertSet('form.menuName', 'Interrupted Completion '.$user->id.' Menu')
        ->call('createStarterMenu')
        ->assertHasNoErrors()
        ->assertSet('step', 8);

    $onboarding->refresh();

    expect($onboarding->only(array_keys($ids)))->toBe($ids)
        ->and($onboarding->completed_at)->not->toBeNull()
        ->and(Menu::query()->where('branch_id', $onboarding->branch_id)->count())->toBe(1)
        ->and(MenuCategory::query()->where('menu_id', $onboarding->menu_id)->count())->toBe(1)
        ->and(MenuItem::query()->where('menu_id', $onboarding->menu_id)->count())->toBe(1);
});

test('completed onboarding keeps its original completion timestamp while rebuilding a hard deleted graph', function () {
    $user = User::factory()->create();
    restaurantOnboardingComponentAtStep($user, 8, 'Hard Delete Recovery');
    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();
    $completedAt = $onboarding->completed_at;

    Brand::query()->whereKey($onboarding->brand_id)->firstOrFail()->forceDelete();

    Livewire::actingAs($user)->test(RestaurantSetup::class)
        ->assertSet('step', 2)
        ->set('form.brandName', 'Rebuilt Brand')
        ->call('createBrand')
        ->assertHasNoErrors()
        ->assertSet('step', 3);

    expect($completedAt)->not->toBeNull()
        ->and($onboarding->fresh()?->completed_at)->toEqual($completedAt);

    restaurantOnboardingComponentAtStep($user, 8, 'Hard Delete Rebuilt')
        ->assertSet('step', 8);

    expect($onboarding->fresh()?->completed_at)->toEqual($completedAt);
});

test('stale onboarding requests update the same organization and brand', function () {
    $user = User::factory()->create();
    $first = Livewire::actingAs($user)->test(RestaurantSetup::class);
    $second = Livewire::actingAs($user)->test(RestaurantSetup::class);

    $first->set('form.organizationName', 'Stale Group')->call('createOrganization')->assertHasNoErrors();
    $second->set('form.organizationName', 'Stale Group')->call('createOrganization')->assertHasNoErrors();

    $firstBrand = Livewire::actingAs($user)->test(RestaurantSetup::class);
    $secondBrand = Livewire::actingAs($user)->test(RestaurantSetup::class);
    $firstBrand->set('form.brandName', 'Stale Brand')->call('createBrand')->assertHasNoErrors();
    $secondBrand->set('form.brandName', 'Stale Brand')->call('createBrand')->assertHasNoErrors();

    expect(RestaurantOnboarding::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(Organization::query()->where('owner_user_id', $user->id)->count())->toBe(1)
        ->and(Brand::query()->where('name', 'Stale Brand')->count())->toBe(1);
});

test('stale Livewire snapshots can retry every mutation without duplicating the restaurant graph', function () {
    $user = User::factory()->create();

    $first = Livewire::actingAs($user)->test(RestaurantSetup::class);
    $retry = Livewire::actingAs($user)->test(RestaurantSetup::class);
    $first->set('form.organizationName', 'Snapshot Group')->call('createOrganization')->assertHasNoErrors();
    $retry->set('form.organizationName', 'Snapshot Group')->call('createOrganization')->assertHasNoErrors();

    $first = Livewire::actingAs($user)->test(RestaurantSetup::class);
    $retry = Livewire::actingAs($user)->test(RestaurantSetup::class);
    $first->set('form.brandName', 'Snapshot Brand')->call('createBrand')->assertHasNoErrors();
    $retry->set('form.brandName', 'Snapshot Brand')->call('createBrand')->assertHasNoErrors();

    $branchData = [
        'form.branchName' => 'Snapshot Branch',
        'form.branchAddress' => '1 Snapshot Street',
        'form.branchCity' => 'Example City',
        'form.branchCountryCode' => 'US',
        'form.branchTimezone' => 'UTC',
        'form.branchCurrency' => 'USD',
    ];
    $first = Livewire::actingAs($user)->test(RestaurantSetup::class)->set($branchData);
    $retry = Livewire::actingAs($user)->test(RestaurantSetup::class)->set($branchData);
    $first->call('createBranch')->assertHasNoErrors();
    $retry->call('createBranch')->assertHasNoErrors();

    $first = Livewire::actingAs($user)->test(RestaurantSetup::class)
        ->set('form.areaName', 'Snapshot Hall')
        ->set('form.areaType', 'hall');
    $retry = Livewire::actingAs($user)->test(RestaurantSetup::class)
        ->set('form.areaName', 'Snapshot Hall')
        ->set('form.areaType', 'hall');
    $first->call('createArea')->assertHasNoErrors();
    $retry->call('createArea')->assertHasNoErrors();

    $first = Livewire::actingAs($user)->test(RestaurantSetup::class)
        ->set('form.tableCount', 2)
        ->set('form.tablePrefix', 'Snapshot Table')
        ->set('form.tableCapacity', 4);
    $retry = Livewire::actingAs($user)->test(RestaurantSetup::class)
        ->set('form.tableCount', 2)
        ->set('form.tablePrefix', 'Snapshot Table')
        ->set('form.tableCapacity', 4);
    $first->call('createServicePoints')->assertHasNoErrors();
    $retry->call('createServicePoints')->assertHasNoErrors();

    $first = Livewire::actingAs($user)->test(RestaurantSetup::class);
    $retry = Livewire::actingAs($user)->test(RestaurantSetup::class);
    $first->call('generateQrCodes')->assertHasNoErrors();
    $retry->call('generateQrCodes')->assertHasNoErrors();

    $menuData = [
        'form.menuName' => 'Snapshot Menu',
        'form.categoryName' => 'Snapshot Category',
        'form.itemName' => 'Snapshot Dish',
        'form.itemPrice' => '8.50',
    ];
    $first = Livewire::actingAs($user)->test(RestaurantSetup::class)->set($menuData);
    $retry = Livewire::actingAs($user)->test(RestaurantSetup::class)->set($menuData);
    $first->call('createStarterMenu')->assertHasNoErrors();
    $retry->call('createStarterMenu')->assertHasNoErrors();

    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();

    expect(restaurantOnboardingGraphCounts())->toBe([
        'onboardings' => 1,
        'organizations' => 1,
        'brands' => 1,
        'branches' => 1,
        'areas' => 1,
        'service_points' => 2,
        'qr_codes' => 2,
        'menus' => 1,
        'menu_categories' => 1,
        'menu_items' => 1,
    ])->and($onboarding->completed_at)->not->toBeNull()
        ->and($onboarding->servicePoints()->count())->toBe(2);
});

test('repeating every onboarding mutation preserves graph identities and the original completion transition', function () {
    $user = User::factory()->create();
    $prefix = 'Duplicate Request';
    $name = $prefix.' '.$user->id;

    restaurantOnboardingComponentAtStep($user, 8, $prefix);

    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();
    $completedAt = $onboarding->completed_at;
    $identityColumns = ['organization_id', 'brand_id', 'branch_id', 'area_node_id', 'menu_id', 'menu_category_id', 'menu_item_id'];
    $identities = $onboarding->only($identityColumns);
    $counts = restaurantOnboardingGraphCounts();
    $servicePointIds = $onboarding->servicePoints()->pluck('service_points.id')->all();
    $positions = $onboarding->servicePoints()->pluck('restaurant_onboarding_service_points.position')->map(fn ($position): int => (int) $position)->all();
    $qrTokens = QrCode::query()
        ->whereIn('service_point_id', $servicePointIds)
        ->where('status', QrCodeStatus::Active->value)
        ->orderBy('service_point_id')
        ->pluck('public_token', 'service_point_id')
        ->all();

    expect($completedAt)->not->toBeNull();
    Date::setTestNow($completedAt->addMinute());

    try {
        app(SaveOnboardingOrganizationAction::class)->handle($user, $onboarding->id, ['name' => $name.' Group']);
        app(SaveOnboardingBrandAction::class)->handle($user, $onboarding->id, ['name' => $name.' Brand']);
        app(SaveOnboardingBranchAction::class)->handle($user, $onboarding->id, [
            'name' => $name.' Branch',
            'address' => '1 Test Street',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
            'timezone' => 'Europe/Vilnius',
            'currency' => 'EUR',
            'is_active' => true,
        ]);
        app(SaveOnboardingAreaAction::class)->handle($user, $onboarding->id, [
            'parent_id' => null,
            'type' => 'hall',
            'name' => $name.' Hall',
            'icon' => '',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        app(SaveOnboardingServicePointsAction::class)->handle($user, $onboarding->id, [
            'tableCount' => 3,
            'tablePrefix' => $name.' Table',
            'tableCapacity' => 4,
        ]);
        app(GenerateOnboardingQrCodesAction::class)->handle($user, $onboarding->id);
        app(SaveOnboardingStarterMenuAction::class)->handle($user, $onboarding->id, [
            'menu_name' => $name.' Menu',
            'category_name' => $name.' Category',
            'item_name' => $name.' Dish',
            'item_price' => '8.50',
        ]);
    } finally {
        Date::setTestNow();
    }

    $onboarding->refresh();

    expect($onboarding->only($identityColumns))->toBe($identities)
        ->and(restaurantOnboardingGraphCounts())->toBe($counts)
        ->and($onboarding->servicePoints()->pluck('service_points.id')->all())->toBe($servicePointIds)
        ->and($onboarding->servicePoints()->pluck('restaurant_onboarding_service_points.position')->map(fn ($position): int => (int) $position)->all())->toBe($positions)
        ->and(QrCode::query()
            ->whereIn('service_point_id', $servicePointIds)
            ->where('status', QrCodeStatus::Active->value)
            ->orderBy('service_point_id')
            ->pluck('public_token', 'service_point_id')
            ->all())->toBe($qrTokens)
        ->and($onboarding->completed_at)->toEqual($completedAt);
});

test('back navigation edits onboarding entities without duplicate domain records', function () {
    $user = User::factory()->create();
    $component = restaurantOnboardingComponentAtStep($user, 8, 'Editable');
    $state = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();
    $ids = $state->only(['organization_id', 'brand_id', 'branch_id', 'area_node_id', 'menu_id', 'menu_category_id', 'menu_item_id']);

    $component->call('goToStep', 1)->set('form.organizationName', 'Edited Group')->call('createOrganization')->assertHasNoErrors();
    $component->call('goToStep', 2)->set('form.brandName', 'Edited Brand')->call('createBrand')->assertHasNoErrors();
    $component->call('goToStep', 3)->set('form.branchName', 'Edited Branch')->call('createBranch')->assertHasNoErrors();
    $component->call('goToStep', 4)->set('form.areaName', 'Edited Hall')->call('createArea')->assertHasNoErrors();
    $component->call('goToStep', 5)->set('form.tablePrefix', 'Edited Table')->call('createServicePoints')->assertHasNoErrors();
    $component->call('generateQrCodes')->assertHasNoErrors();
    $component->call('goToStep', 7)->set('form.itemName', 'Edited Dish')->call('createStarterMenu')->assertHasNoErrors();

    $state->refresh();
    expect($state->only(array_keys($ids)))->toBe($ids)
        ->and(Organization::query()->where('owner_user_id', $user->id)->count())->toBe(1)
        ->and(Brand::query()->where('organization_id', $state->organization_id)->count())->toBe(1)
        ->and(Branch::query()->where('brand_id', $state->brand_id)->count())->toBe(1)
        ->and(AreaNode::query()->where('branch_id', $state->branch_id)->count())->toBe(1)
        ->and(ServicePoint::query()->where('branch_id', $state->branch_id)->count())->toBe(3)
        ->and(QrCode::query()->count())->toBe(3)
        ->and(Menu::query()->where('branch_id', $state->branch_id)->count())->toBe(1)
        ->and(MenuCategory::query()->where('menu_id', $state->menu_id)->count())->toBe(1)
        ->and(MenuItem::query()->where('menu_id', $state->menu_id)->count())->toBe(1);
});

test('bulk onboarding service point creation rolls back the whole set on failure', function () {
    $user = User::factory()->create();
    restaurantOnboardingComponentAtStep($user, 5, 'Rollback');
    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();
    $failingCreator = new class extends CreateServicePointAction
    {
        private int $calls = 0;

        public function handle(Branch $branch, array $data): ServicePoint
        {
            $this->calls++;

            if ($this->calls === 2) {
                throw new RuntimeException('Simulated second table failure.');
            }

            return parent::handle($branch, $data);
        }
    };
    $action = new SaveOnboardingServicePointsAction($failingCreator, app(UpdateServicePointAction::class));

    expect(fn () => $action->handle($user, $onboarding->id, ['tableCount' => 3, 'tablePrefix' => 'Rollback Table', 'tableCapacity' => 4]))
        ->toThrow(RuntimeException::class, 'Simulated second table failure.');

    expect(ServicePoint::query()->where('branch_id', $onboarding->branch_id)->count())->toBe(0)
        ->and($onboarding->servicePoints()->count())->toBe(0);

    app(SaveOnboardingServicePointsAction::class)->handle($user, $onboarding->id, [
        'tableCount' => 3,
        'tablePrefix' => 'Rollback Table',
        'tableCapacity' => 4,
    ]);

    expect(ServicePoint::query()->where('branch_id', $onboarding->branch_id)->count())->toBe(3)
        ->and($onboarding->servicePoints()->count())->toBe(3)
        ->and($onboarding->servicePoints()->pluck('restaurant_onboarding_service_points.position')->map(fn ($position): int => (int) $position)->all())
        ->toBe([1, 2, 3]);
});

test('onboarding entity creation rolls back when its checkpoint transition fails', function (string $checkpointColumn, int $requiredStep) {
    $user = User::factory()->create();
    $prefix = 'Transition Rollback '.$checkpointColumn;

    if ($requiredStep > 1) {
        restaurantOnboardingComponentAtStep($user, $requiredStep, $prefix);
    }

    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->first();
    $counts = restaurantOnboardingGraphCounts();
    $subscriptionCount = OrganizationSubscription::query()->count();
    $branchSettingCount = BranchSetting::query()->count();
    $kitchenDepartmentCount = KitchenDepartment::query()->count();
    $roleCount = $user->roles()->count();
    $membershipCount = $user->organizationMemberships()->count();
    $eventName = 'eloquent.saving: '.RestaurantOnboarding::class;

    Event::listen($eventName, function (RestaurantOnboarding $checkpoint) use ($checkpointColumn): void {
        if ($checkpoint->isDirty($checkpointColumn) && $checkpoint->getAttribute($checkpointColumn) !== null) {
            throw new RuntimeException('Simulated checkpoint transition failure.');
        }
    });

    $operation = fn () => match ($checkpointColumn) {
        'organization_id' => app(SaveOnboardingOrganizationAction::class)->handle($user, null, ['name' => $prefix.' Group']),
        'brand_id' => app(SaveOnboardingBrandAction::class)->handle($user, $onboarding?->id ?? 0, ['name' => $prefix.' Brand']),
        'branch_id' => app(SaveOnboardingBranchAction::class)->handle($user, $onboarding?->id ?? 0, [
            'name' => $prefix.' Branch', 'address' => '1 Test Street', 'city' => 'Vilnius', 'country' => 'Lithuania',
            'timezone' => 'Europe/Vilnius', 'currency' => 'EUR', 'is_active' => true,
        ]),
        'area_node_id' => app(SaveOnboardingAreaAction::class)->handle($user, $onboarding?->id ?? 0, [
            'parent_id' => null, 'type' => 'hall', 'name' => $prefix.' Hall', 'icon' => null, 'sort_order' => 0, 'is_active' => true,
        ]),
    };

    try {
        expect($operation)->toThrow(RuntimeException::class, 'Simulated checkpoint transition failure.');
    } finally {
        Event::forget($eventName);
    }

    expect(restaurantOnboardingGraphCounts())->toBe($counts)
        ->and(OrganizationSubscription::query()->count())->toBe($subscriptionCount)
        ->and(BranchSetting::query()->count())->toBe($branchSettingCount)
        ->and(KitchenDepartment::query()->count())->toBe($kitchenDepartmentCount)
        ->and($user->roles()->count())->toBe($roleCount)
        ->and($user->organizationMemberships()->count())->toBe($membershipCount);

    if ($onboarding instanceof RestaurantOnboarding) {
        expect($onboarding->fresh()?->getAttribute($checkpointColumn))->toBeNull();
    } else {
        expect(RestaurantOnboarding::query()->where('user_id', $user->id)->doesntExist())->toBeTrue();
    }

    $recovered = $operation();
    $countsAfterRecovery = restaurantOnboardingGraphCounts();
    $repeated = $operation();

    expect($recovered->getAttribute($checkpointColumn))->not->toBeNull()
        ->and($repeated->id)->toBe($recovered->id)
        ->and($repeated->getAttribute($checkpointColumn))->toBe($recovered->getAttribute($checkpointColumn))
        ->and(restaurantOnboardingGraphCounts())->toBe($countsAfterRecovery);
})->with([
    'organization' => ['organization_id', 1],
    'brand' => ['brand_id', 2],
    'branch' => ['branch_id', 3],
    'area' => ['area_node_id', 4],
]);

test('bulk onboarding QR generation rolls back a partial batch and the same action can retry', function () {
    $user = User::factory()->create();
    restaurantOnboardingComponentAtStep($user, 6, 'QR Rollback');
    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();
    $failingGenerator = new class extends GenerateQrCodeForServicePointAction
    {
        private int $calls = 0;

        public function handle(ServicePoint $servicePoint, ?User $createdBy = null): QrCode
        {
            $this->calls++;

            if ($this->calls === 2) {
                throw new RuntimeException('Simulated second QR failure.');
            }

            return parent::handle($servicePoint, $createdBy);
        }
    };
    $action = new GenerateOnboardingQrCodesAction($failingGenerator);

    expect(fn () => $action->handle($user, $onboarding->id))
        ->toThrow(RuntimeException::class, 'Simulated second QR failure.');

    expect(QrCode::query()->count())->toBe(0);

    $action->handle($user, $onboarding->id);

    expect(QrCode::query()->count())->toBe(3)
        ->and(QrCode::query()->where('status', QrCodeStatus::Active->value)->count())->toBe(3)
        ->and(QrCode::query()->distinct()->count('active_service_point_id'))->toBe(3);
});

test('starter menu graph and completion roll back together and retry reuses one graph', function () {
    $user = User::factory()->create();
    restaurantOnboardingComponentAtStep($user, 7, 'Menu Rollback');
    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();
    $counts = restaurantOnboardingGraphCounts();
    $eventName = 'eloquent.creating: '.MenuItem::class;
    $data = [
        'menu_name' => 'Rollback Menu',
        'category_name' => 'Rollback Category',
        'item_name' => 'Rollback Dish',
        'item_price' => '10.25',
    ];

    Event::listen($eventName, fn (): never => throw new RuntimeException('Simulated starter item failure.'));

    try {
        expect(fn () => app(SaveOnboardingStarterMenuAction::class)->handle($user, $onboarding->id, $data))
            ->toThrow(RuntimeException::class, 'Simulated starter item failure.');
    } finally {
        Event::forget($eventName);
    }

    expect(restaurantOnboardingGraphCounts())->toBe($counts)
        ->and($onboarding->fresh()?->menu_id)->toBeNull()
        ->and($onboarding->fresh()?->menu_category_id)->toBeNull()
        ->and($onboarding->fresh()?->menu_item_id)->toBeNull()
        ->and($onboarding->fresh()?->completed_at)->toBeNull();

    app(SaveOnboardingStarterMenuAction::class)->handle($user, $onboarding->id, $data);
    app(SaveOnboardingStarterMenuAction::class)->handle($user, $onboarding->id, $data);

    expect(Menu::query()->where('branch_id', $onboarding->branch_id)->count())->toBe(1)
        ->and(MenuCategory::query()->where('menu_id', $onboarding->fresh()?->menu_id)->count())->toBe(1)
        ->and(MenuItem::query()->where('menu_id', $onboarding->fresh()?->menu_id)->count())->toBe(1)
        ->and(MenuItem::query()->whereKey($onboarding->fresh()?->menu_item_id)->value('price_cents'))->toBe(1025);
});

test('onboarding qr generation safely recovers from a partially completed set', function () {
    $user = User::factory()->create();
    restaurantOnboardingComponentAtStep($user, 6, 'Partial QR');
    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();
    $firstPoint = $onboarding->servicePoints()->select(['service_points.id', 'service_points.branch_id'])->firstOrFail();

    app(GenerateQrCodeForServicePointAction::class)->handle($firstPoint, $user);

    Livewire::actingAs($user)->test(RestaurantSetup::class)
        ->assertSet('step', 6)
        ->call('generateQrCodes')
        ->assertHasNoErrors()
        ->assertSet('step', 7);

    expect(QrCode::query()->count())->toBe(3);
});

test('onboarding server state is locked and another tenant cannot invoke its action', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    restaurantOnboardingComponentAtStep($owner, 2, 'Owner Locked');
    $onboarding = RestaurantOnboarding::query()->where('user_id', $owner->id)->firstOrFail();
    $component = Livewire::actingAs($intruder)->test(RestaurantSetup::class);

    expect(fn () => $component->set('step', 8))->toThrow(CannotUpdateLockedPropertyException::class)
        ->and(fn () => $component->set('onboardingId', $onboarding->id))->toThrow(CannotUpdateLockedPropertyException::class)
        ->and(fn () => app(SaveOnboardingBrandAction::class)->handle($intruder, $onboarding->id, ['name' => 'Injected']))
        ->toThrow(ModelNotFoundException::class);
});

test('existing tenant staff cannot start or inject a restaurant onboarding checkpoint', function (SystemRole $role) {
    $owner = User::factory()->create();
    restaurantOnboardingComponentAtStep($owner, 2, 'Staff Guard '.$role->value);
    $organization = Organization::query()->where('owner_user_id', $owner->id)->firstOrFail();
    $onboarding = RestaurantOnboarding::query()->where('user_id', $owner->id)->firstOrFail();
    $staff = User::factory()->create();

    OrganizationUser::factory()
        ->forOrganization($organization)
        ->forUser($staff)
        ->forSystemRole($role)
        ->active()
        ->create();

    Livewire::actingAs($staff)->test(RestaurantSetup::class)->assertForbidden();

    expect(fn () => app(SaveOnboardingBrandAction::class)->handle($staff, $onboarding->id, ['name' => 'Injected']))
        ->toThrow(ModelNotFoundException::class);
})->with([
    'director' => SystemRole::Director,
    'restaurant administrator' => SystemRole::RestaurantAdmin,
    'shift manager' => SystemRole::ShiftManager,
    'waiter' => SystemRole::Waiter,
    'head chef' => SystemRole::HeadChef,
    'cook' => SystemRole::Cook,
    'bartender' => SystemRole::Bartender,
    'cashier' => SystemRole::Cashier,
    'accountant' => SystemRole::Accountant,
    'marketer' => SystemRole::Marketer,
]);

test('staff system identities without a tenant row cannot mint an owner onboarding context', function (SystemRole $role) {
    $staff = User::factory()->create();
    assignRestaurantOnboardingSystemRole($staff, $role);

    Livewire::actingAs($staff)->test(RestaurantSetup::class)->assertForbidden();

    expect(fn () => app(SaveOnboardingOrganizationAction::class)->handle($staff, null, ['name' => 'Injected Owner Context']))
        ->toThrow(AuthorizationException::class)
        ->and(RestaurantOnboarding::query()->where('user_id', $staff->id)->doesntExist())->toBeTrue()
        ->and(Organization::query()->where('owner_user_id', $staff->id)->doesntExist())->toBeTrue();
})->with([
    'superadmin' => SystemRole::Superadmin,
    'director' => SystemRole::Director,
    'restaurant administrator' => SystemRole::RestaurantAdmin,
    'shift manager' => SystemRole::ShiftManager,
    'waiter' => SystemRole::Waiter,
    'head chef' => SystemRole::HeadChef,
    'cook' => SystemRole::Cook,
    'bartender' => SystemRole::Bartender,
    'cashier' => SystemRole::Cashier,
    'accountant' => SystemRole::Accountant,
    'marketer' => SystemRole::Marketer,
]);

test('a new owner identity without a tenant membership can start onboarding', function () {
    $owner = User::factory()->create();
    assignRestaurantOnboardingSystemRole($owner, SystemRole::Owner);

    Livewire::actingAs($owner)
        ->test(RestaurantSetup::class)
        ->set('form.organizationName', 'Eligible Owner Group')
        ->call('createOrganization')
        ->assertHasNoErrors()
        ->assertSet('step', 2);

    expect(RestaurantOnboarding::query()->where('user_id', $owner->id)->count())->toBe(1)
        ->and(Organization::query()->where('owner_user_id', $owner->id)->count())->toBe(1);
});

test('suspended and removed checkpoint owners cannot resume or mutate onboarding', function (OrganizationUserStatus $status) {
    $owner = User::factory()->create();
    restaurantOnboardingComponentAtStep($owner, 2, 'Inactive Owner '.$status->value);
    $onboarding = RestaurantOnboarding::query()->where('user_id', $owner->id)->firstOrFail();

    OrganizationUser::query()
        ->where('organization_id', $onboarding->organization_id)
        ->where('user_id', $owner->id)
        ->update(['status' => $status->value]);

    Livewire::actingAs($owner)->test(RestaurantSetup::class)->assertForbidden();

    expect(fn () => app(SaveOnboardingBrandAction::class)->handle($owner, $onboarding->id, ['name' => 'Forbidden Brand']))
        ->toThrow(AuthorizationException::class)
        ->and(Brand::query()->where('organization_id', $onboarding->organization_id)->doesntExist())->toBeTrue();
})->with([
    'suspended owner' => OrganizationUserStatus::Suspended,
    'removed owner' => OrganizationUserStatus::Removed,
]);

test('an owner cannot resume onboarding while the organization subscription is inactive', function () {
    $owner = User::factory()->create();
    restaurantOnboardingComponentAtStep($owner, 2, 'Inactive Subscription');
    $onboarding = RestaurantOnboarding::query()->where('user_id', $owner->id)->firstOrFail();

    OrganizationSubscription::query()
        ->where('organization_id', $onboarding->organization_id)
        ->update(['status' => OrganizationSubscriptionStatus::Inactive->value]);

    Livewire::actingAs($owner)->test(RestaurantSetup::class)->assertForbidden();

    expect(fn () => app(SaveOnboardingBrandAction::class)->handle($owner, $onboarding->id, ['name' => 'Forbidden Brand']))
        ->toThrow(AuthorizationException::class)
        ->and(Brand::query()->where('organization_id', $onboarding->organization_id)->doesntExist())->toBeTrue();
});

test('soft deletion does not bypass an inactive onboarding subscription', function () {
    $owner = User::factory()->create();
    restaurantOnboardingComponentAtStep($owner, 2, 'Inactive Deleted Subscription');
    $onboarding = RestaurantOnboarding::query()->where('user_id', $owner->id)->firstOrFail();
    $organization = Organization::query()->whereKey($onboarding->organization_id)->firstOrFail();

    OrganizationSubscription::query()
        ->where('organization_id', $organization->id)
        ->update(['status' => OrganizationSubscriptionStatus::Inactive->value]);
    $organization->deleteOrFail();

    Livewire::actingAs($owner)->test(RestaurantSetup::class)->assertForbidden();
});

test('a stale Livewire snapshot reauthorizes before rendering after owner membership is revoked', function () {
    $owner = User::factory()->create();
    $component = restaurantOnboardingComponentAtStep($owner, 8, 'Revoked Snapshot Secret');
    $onboarding = RestaurantOnboarding::query()->where('user_id', $owner->id)->firstOrFail();

    OrganizationUser::query()
        ->where('organization_id', $onboarding->organization_id)
        ->where('user_id', $owner->id)
        ->update(['status' => OrganizationUserStatus::Suspended->value]);

    $component->call('goToStep', 1)->assertForbidden();
});

test('a stale Livewire snapshot reauthorizes before rendering after subscription suspension', function () {
    $owner = User::factory()->create();
    $component = restaurantOnboardingComponentAtStep($owner, 8, 'Suspended Snapshot Secret');
    $onboarding = RestaurantOnboarding::query()->where('user_id', $owner->id)->firstOrFail();

    OrganizationSubscription::query()
        ->where('organization_id', $onboarding->organization_id)
        ->update(['status' => OrganizationSubscriptionStatus::Inactive->value]);

    $component->call('goToStep', 1)->assertForbidden();
});

test('onboarding presentation never hydrates a cross tenant service point or QR identity', function () {
    $owner = User::factory()->create();
    restaurantOnboardingComponentAtStep($owner, 6, 'Scoped Presentation');
    $onboarding = RestaurantOnboarding::query()->where('user_id', $owner->id)->firstOrFail();
    $foreignOrganization = Organization::factory()->create(['name' => 'Foreign Secret Organization']);
    $foreignBrand = Brand::factory()->for($foreignOrganization)->create(['name' => 'Foreign Secret Brand']);
    $foreignBranch = Branch::factory()->forBrand($foreignBrand)->create(['name' => 'Foreign Secret Branch']);
    $foreignArea = AreaNode::factory()->forBranch($foreignBranch)->create(['name' => 'Foreign Secret Area']);
    $foreignPoint = ServicePoint::factory()->forBranch($foreignBranch)->inAreaNode($foreignArea)->create(['name' => 'Foreign Secret Table']);
    $foreignQr = QrCode::factory()->for($foreignPoint)->create([
        'public_token' => str_repeat('F', 64),
        'short_code' => 'QR-FOREIGN',
    ]);

    $onboarding->servicePoints()->attach($foreignPoint->id, ['position' => 4]);

    $state = app(RestaurantSetupQueryService::class)->presentation($owner, $onboarding->id);
    $serializedPresentation = json_encode($state, JSON_THROW_ON_ERROR);

    expect($state['step'])->toBe(5)
        ->and($state['form']['tableCount'])->toBe(3)
        ->and($state['onboarding']?->servicePoints->contains('id', $foreignPoint->id))->toBeFalse()
        ->and($serializedPresentation)->not->toContain(
            $foreignOrganization->name,
            $foreignBrand->name,
            $foreignBranch->name,
            $foreignArea->name,
            $foreignPoint->name,
            $foreignQr->public_token,
            $foreignQr->short_code,
        );
});

test('onboarding presentation never hydrates a forged cross tenant parent or menu chain', function () {
    $owner = User::factory()->create();
    restaurantOnboardingComponentAtStep($owner, 2, 'Scoped Parent Presentation');
    $onboarding = RestaurantOnboarding::query()->where('user_id', $owner->id)->firstOrFail();
    $foreignOrganization = Organization::factory()->create(['name' => 'Forged Organization Name']);
    $foreignBrand = Brand::factory()->for($foreignOrganization)->create(['name' => 'Forged Brand Name']);
    $foreignBranch = Branch::factory()->forBrand($foreignBrand)->create(['name' => 'Forged Branch Name']);
    $foreignArea = AreaNode::factory()->forBranch($foreignBranch)->create(['name' => 'Forged Area Name']);
    $foreignMenu = Menu::factory()->forBranch($foreignBranch)->create(['name' => 'Forged Menu Name']);
    $foreignCategory = MenuCategory::factory()->create(['menu_id' => $foreignMenu->id, 'name' => 'Forged Category Name']);
    $foreignItem = MenuItem::factory()->create([
        'menu_id' => $foreignMenu->id,
        'category_id' => $foreignCategory->id,
        'name' => 'Forged Dish Name',
    ]);

    $onboarding->forceFill([
        'organization_id' => $foreignOrganization->id,
        'brand_id' => $foreignBrand->id,
        'branch_id' => $foreignBranch->id,
        'area_node_id' => $foreignArea->id,
        'menu_id' => $foreignMenu->id,
        'menu_category_id' => $foreignCategory->id,
        'menu_item_id' => $foreignItem->id,
    ])->save();

    $state = app(RestaurantSetupQueryService::class)->presentation($owner, $onboarding->id);
    $serializedPresentation = json_encode($state, JSON_THROW_ON_ERROR);

    expect($state['step'])->toBe(1)
        ->and($state['onboarding']?->organization)->toBeNull()
        ->and($state['onboarding']?->brand)->toBeNull()
        ->and($state['onboarding']?->branch)->toBeNull()
        ->and($state['onboarding']?->areaNode)->toBeNull()
        ->and($state['onboarding']?->menu)->toBeNull()
        ->and($state['onboarding']?->menuCategory)->toBeNull()
        ->and($state['onboarding']?->menuItem)->toBeNull()
        ->and($serializedPresentation)->not->toContain(
            $foreignOrganization->name,
            $foreignBrand->name,
            $foreignBranch->name,
            $foreignArea->name,
            $foreignMenu->name,
            $foreignCategory->name,
            $foreignItem->name,
        );
});

test('direct Livewire actions cannot skip onboarding prerequisites', function (int $availableStep, string $action) {
    $owner = User::factory()->create();
    $component = restaurantOnboardingComponentAtStep($owner, $availableStep, 'Prerequisite '.$action);
    $countsBefore = restaurantOnboardingGraphCounts();

    $component->call($action)->assertStatus(409);

    expect(restaurantOnboardingGraphCounts())->toBe($countsBefore);
})->with([
    'brand requires organization' => [1, 'createBrand'],
    'branch requires brand' => [2, 'createBranch'],
    'area requires branch' => [3, 'createArea'],
    'tables require area' => [4, 'createServicePoints'],
    'QR requires tables' => [5, 'generateQrCodes'],
    'starter menu requires QR' => [6, 'createStarterMenu'],
]);

test('forged future-step navigation cannot advance server-derived progress', function () {
    $owner = User::factory()->create();

    restaurantOnboardingComponentAtStep($owner, 2, 'Future Navigation')
        ->call('goToStep', 8)
        ->assertSet('step', 2)
        ->call('goToStep', 0)
        ->assertSet('step', 2);
});

test('malformed future-step arguments fail closed without changing progress', function (mixed $step) {
    $owner = User::factory()->create();

    restaurantOnboardingComponentAtStep($owner, 2, 'Malformed Navigation')
        ->call('goToStep', $step)
        ->assertSet('step', 2);
})->with([
    'non numeric string' => 'future',
    'fractional number' => 2.5,
    'array payload' => [['step' => 8]],
    'null payload' => null,
]);

test('server-owned onboarding domain identifiers cannot be injected as public state', function (string $property) {
    $owner = User::factory()->create();
    $component = Livewire::actingAs($owner)->test(RestaurantSetup::class);

    expect(fn () => $component->set($property, 999_999))
        ->toThrow(PublicPropertyNotFoundException::class);
})->with([
    'organization id' => 'organizationId',
    'brand id' => 'brandId',
    'branch id' => 'branchId',
    'area id' => 'areaNodeId',
    'service point ids' => 'servicePointIds',
    'QR code ids' => 'qrCodeIds',
    'menu id' => 'menuId',
    'menu category id' => 'menuCategoryId',
    'menu item id' => 'menuItemId',
]);

test('direct Livewire mutations enforce each onboarding domain capability', function (SystemPermission $permission, int $step, string $action) {
    $owner = User::factory()->create();
    $component = restaurantOnboardingComponentAtStep($owner, $step, 'Capability '.$permission->value);
    $permissionModel = Permission::query()->where('code', $permission->value)->firstOrFail();
    $owner->permissionOverrides()->syncWithoutDetaching([$permissionModel->id => ['enabled' => false]]);

    if ($action === 'createArea') {
        $component->set('form.areaName', 'Forbidden Area')->set('form.areaType', 'hall');
    }

    if ($action === 'createBranch') {
        $component
            ->set('form.branchName', 'Forbidden Branch')
            ->set('form.branchAddress', '1 Forbidden Street')
            ->set('form.branchCity', 'Vilnius')
            ->set('form.branchCountryCode', 'LT')
            ->set('form.branchTimezone', 'Europe/Vilnius')
            ->set('form.branchCurrency', 'EUR');
    }

    if ($action === 'createServicePoints') {
        $component->set('form.tableCount', 3)->set('form.tablePrefix', 'Forbidden Table')->set('form.tableCapacity', 4);
    }

    if ($action === 'createStarterMenu') {
        $component
            ->set('form.menuName', 'Forbidden Menu')
            ->set('form.categoryName', 'Forbidden Category')
            ->set('form.itemName', 'Forbidden Dish')
            ->set('form.itemPrice', '9.00');
    }

    $countsBefore = restaurantOnboardingGraphCounts();

    $component->call($action)->assertForbidden();

    expect(restaurantOnboardingGraphCounts())->toBe($countsBefore);
})->with([
    'manage branches' => [SystemPermission::ManageBranches, 3, 'createBranch'],
    'manage zones' => [SystemPermission::ManageZones, 4, 'createArea'],
    'manage service points' => [SystemPermission::ManageServicePoints, 5, 'createServicePoints'],
    'generate QR' => [SystemPermission::GenerateQr, 6, 'generateQrCodes'],
    'manage menu' => [SystemPermission::ManageMenu, 7, 'createStarterMenu'],
    'change prices' => [SystemPermission::ChangePrices, 7, 'createStarterMenu'],
    'change availability' => [SystemPermission::ChangeAvailability, 7, 'createStarterMenu'],
]);

test('checkpoint branch access follows active branch assignments on every hydration', function (string $assignment) {
    $owner = User::factory()->create();
    restaurantOnboardingComponentAtStep($owner, 8, 'Assignment Guard '.$assignment);
    $onboarding = RestaurantOnboarding::query()->where('user_id', $owner->id)->firstOrFail();
    $checkpointBranch = Branch::query()->whereKey($onboarding->branch_id)->firstOrFail();
    $ownerRole = Role::query()->where('code', SystemRole::Owner->value)->firstOrFail();

    $assignedBranch = $checkpointBranch;

    if ($assignment === 'other branch') {
        $brand = Brand::query()->whereKey($onboarding->brand_id)->firstOrFail();
        $assignedBranch = Branch::factory()->forBrand($brand)->create();
    }

    $assignmentFactory = BranchUser::factory()
        ->forBranch($assignedBranch)
        ->forUser($owner)
        ->forRole($ownerRole);

    match ($assignment) {
        'suspended' => $assignmentFactory->suspended()->create(),
        'removed' => $assignmentFactory->removed()->create(),
        default => $assignmentFactory->active()->create(),
    };

    expect($owner->fresh()->canAccessBranch($checkpointBranch))->toBeFalse();

    Livewire::actingAs($owner)->test(RestaurantSetup::class)->assertForbidden();
})->with(['other branch', 'suspended', 'removed']);

test('a missing subscription fails closed for an existing onboarding checkpoint', function () {
    $owner = User::factory()->create();
    restaurantOnboardingComponentAtStep($owner, 2, 'Missing Subscription');
    $onboarding = RestaurantOnboarding::query()->where('user_id', $owner->id)->firstOrFail();

    OrganizationSubscription::query()
        ->where('organization_id', $onboarding->organization_id)
        ->firstOrFail()
        ->deleteOrFail();

    Livewire::actingAs($owner)->test(RestaurantSetup::class)->assertForbidden();
});

test('onboarding read service fails closed for another users checkpoint identifier', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    restaurantOnboardingComponentAtStep($owner, 8, 'Read Service Secret');
    $onboarding = RestaurantOnboarding::query()->where('user_id', $owner->id)->firstOrFail();

    $state = app(RestaurantSetupQueryService::class)->presentation($intruder, $onboarding->id);

    expect($state['onboarding'])->toBeNull()
        ->and($state['step'])->toBe(1)
        ->and($state['summary'])->toBe([
            'organization' => null,
            'brand' => null,
            'branch' => null,
            'area' => null,
            'service_points' => 0,
            'qr_codes' => 0,
            'menu' => null,
            'guest_url' => null,
            'branch_url' => null,
            'menu_url' => null,
            'print_url' => null,
        ]);
});

test('onboarding exposes only its form and locked server-owned navigation identifiers', function () {
    $componentReflection = new ReflectionClass(RestaurantSetup::class);
    $publicProperties = collect($componentReflection->getProperties(ReflectionProperty::IS_PUBLIC))
        ->filter(fn (ReflectionProperty $property): bool => $property->getDeclaringClass()->getName() === RestaurantSetup::class)
        ->map(fn (ReflectionProperty $property): string => $property->getName())
        ->sort()
        ->values()
        ->all();

    $lockedProperties = collect(['onboardingId', 'step'])
        ->filter(fn (string $property): bool => $componentReflection->getProperty($property)->getAttributes(Locked::class) !== [])
        ->values()
        ->all();

    expect($publicProperties)->toBe(['form', 'onboardingId', 'step'])
        ->and($lockedProperties)->toBe(['onboardingId', 'step']);
});

test('onboarding summary exposes only the opaque public QR identity and no secondary secrets', function () {
    $owner = User::factory()->create();
    $component = restaurantOnboardingComponentAtStep($owner, 8, 'QR Identity Summary');
    $onboarding = RestaurantOnboarding::query()->where('user_id', $owner->id)->firstOrFail();
    $servicePoint = $onboarding->servicePoints()->firstOrFail();
    $qrCode = $servicePoint->activeQrCode()->firstOrFail();
    $summary = $component->get('summary');
    $presentation = app(RestaurantSetupQueryService::class)->presentation($owner, $onboarding->id);
    $preparedQrCode = $presentation['onboarding']?->servicePoints->first()?->activeQrCode;

    expect(array_keys($summary))->toBe([
        'organization',
        'brand',
        'branch',
        'area',
        'service_points',
        'qr_codes',
        'menu',
        'guest_url',
        'branch_url',
        'menu_url',
        'print_url',
    ])->and($summary['guest_url'])->toBe(route('public.qr.show', ['token' => $qrCode->public_token]))
        ->and(parse_url($summary['guest_url'], PHP_URL_PATH))->toBe('/q/'.$qrCode->public_token)
        ->and(json_encode($summary, JSON_THROW_ON_ERROR))->not->toContain(
            $qrCode->short_code,
            'organization_id',
            'brand_id',
            'branch_id',
            'area_node_id',
            'menu_id',
            'menu_category_id',
            'menu_item_id',
            'guest_token',
            'invite_token',
            'invitation_token',
            'service_point_id',
            'qr_code_id',
            'short_code',
            'created_by_user_id',
            'revoked_by_user_id',
        )
        ->and($preparedQrCode?->getAttributes())->not->toHaveKey('short_code')
        ->and($component->html())->not->toContain($qrCode->short_code);
});

test('checkpoint recovery policy is scoped to its exact owner and resource reference', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    restaurantOnboardingComponentAtStep($owner, 3, 'Restore Policy');
    $onboarding = RestaurantOnboarding::query()->where('user_id', $owner->id)->firstOrFail();
    $brand = Brand::query()->whereKey($onboarding->brand_id)->firstOrFail();
    $foreignBrand = Brand::factory()->create();

    $brand->deleteOrFail();

    expect(Gate::forUser($owner)->allows('restoreCheckpointResource', [$onboarding, $brand]))->toBeTrue()
        ->and(Gate::forUser($intruder)->denies('restoreCheckpointResource', [$onboarding, $brand]))->toBeTrue()
        ->and(Gate::forUser($owner)->denies('restoreCheckpointResource', [$onboarding, $foreignBrand]))->toBeTrue();
});

test('soft deleted onboarding resources are restored through their scoped checkpoint', function (string $resource, int $expectedStep, string $action) {
    $user = User::factory()->create();
    restaurantOnboardingComponentAtStep($user, 8, 'Recovery '.$resource);
    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();
    $formValues = app(RestaurantSetupQueryService::class)->presentation($user, $onboarding->id)['form'];
    $model = match ($resource) {
        'organization' => Organization::query()->whereKey($onboarding->organization_id)->firstOrFail(),
        'brand' => Brand::query()->whereKey($onboarding->brand_id)->firstOrFail(),
        'branch' => Branch::query()->whereKey($onboarding->branch_id)->firstOrFail(),
        'area' => AreaNode::query()->whereKey($onboarding->area_node_id)->firstOrFail(),
        'service point' => $onboarding->servicePoints()->firstOrFail(),
        'menu' => Menu::query()->whereKey($onboarding->menu_id)->firstOrFail(),
        'category' => MenuCategory::query()->whereKey($onboarding->menu_category_id)->firstOrFail(),
        'item' => MenuItem::query()->whereKey($onboarding->menu_item_id)->firstOrFail(),
    };
    $model->deleteOrFail();

    if ($resource === 'service point') {
        expect($onboarding->servicePoints()->count())->toBe(2);
    }

    $component = Livewire::actingAs($user)->test(RestaurantSetup::class)->assertSet('step', $expectedStep);

    foreach ($formValues as $property => $value) {
        $component->assertSet('form.'.$property, $value);
    }

    $component->call($action)
        ->assertHasNoErrors()
        ->assertSet('step', 8);

    expect($model->fresh())->not->toBeNull()
        ->and($model->fresh()?->deleted_at)->toBeNull();
})->with([
    'organization' => ['organization', 1, 'createOrganization'],
    'brand' => ['brand', 2, 'createBrand'],
    'branch' => ['branch', 3, 'createBranch'],
    'area' => ['area', 4, 'createArea'],
    'service point' => ['service point', 5, 'createServicePoints'],
    'menu' => ['menu', 7, 'createStarterMenu'],
    'category' => ['category', 7, 'createStarterMenu'],
    'item' => ['item', 7, 'createStarterMenu'],
]);

test('completed onboarding remains completed when operational resources are disabled later', function (string $resource) {
    $user = User::factory()->create();
    restaurantOnboardingComponentAtStep($user, 8, 'Disabled '.$resource);
    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();
    $ids = $onboarding->only(['organization_id', 'brand_id', 'branch_id', 'area_node_id', 'menu_id', 'menu_category_id', 'menu_item_id']);

    match ($resource) {
        'branch' => Branch::query()->whereKey($onboarding->branch_id)->firstOrFail()->forceFill(['is_active' => false])->save(),
        'area' => AreaNode::query()->whereKey($onboarding->area_node_id)->firstOrFail()->forceFill(['is_active' => false])->save(),
        'service point' => $onboarding->servicePoints()->firstOrFail()->forceFill(['is_active' => false])->save(),
        'menu' => Menu::query()->whereKey($onboarding->menu_id)->firstOrFail()->forceFill(['status' => MenuStatus::Archived])->save(),
        'category' => MenuCategory::query()->whereKey($onboarding->menu_category_id)->firstOrFail()->forceFill(['is_active' => false])->save(),
        'item' => MenuItem::query()->whereKey($onboarding->menu_item_id)->firstOrFail()->forceFill(['is_available' => false])->save(),
    };

    Livewire::actingAs($user)->test(RestaurantSetup::class)
        ->assertSet('step', 8)
        ->assertSee(__('ui.onboarding.restaurant_setup.restoran_gotov_k_proverke'));

    expect($onboarding->fresh()?->only(array_keys($ids)))->toBe($ids)
        ->and($onboarding->fresh()?->completed_at)->not->toBeNull();
})->with(['branch', 'area', 'service point', 'menu', 'category', 'item']);

test('partial onboarding keeps its structural checkpoint when an operational resource is disabled', function (string $resource, int $step) {
    $user = User::factory()->create();
    restaurantOnboardingComponentAtStep($user, $step, 'Partial Disabled '.$resource);
    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();

    match ($resource) {
        'branch' => Branch::query()->whereKey($onboarding->branch_id)->firstOrFail()->forceFill(['is_active' => false])->save(),
        'area' => AreaNode::query()->whereKey($onboarding->area_node_id)->firstOrFail()->forceFill(['is_active' => false])->save(),
        'service point' => $onboarding->servicePoints()->firstOrFail()->forceFill(['is_active' => false])->save(),
    };

    Livewire::actingAs($user)->test(RestaurantSetup::class)->assertSet('step', $step);

    expect(Organization::query()->whereKey($onboarding->organization_id)->count())->toBe(1)
        ->and(Brand::query()->whereKey($onboarding->brand_id)->count())->toBe(1)
        ->and(Branch::query()->whereKey($onboarding->branch_id)->count())->toBe(1);
})->with([
    'branch' => ['branch', 4],
    'area' => ['area', 5],
    'service point' => ['service point', 6],
]);

test('disabled permanent QR checkpoint resumes generation without duplicating active identities', function () {
    $user = User::factory()->create();
    restaurantOnboardingComponentAtStep($user, 8, 'Disabled QR');
    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();
    $qr = $onboarding->servicePoints()->firstOrFail()->activeQrCode()->firstOrFail();

    $qr->forceFill(['status' => QrCodeStatus::Disabled])->save();

    Livewire::actingAs($user)->test(RestaurantSetup::class)
        ->assertSet('step', 6)
        ->call('generateQrCodes')
        ->assertHasNoErrors()
        ->assertSet('step', 8);

    expect(QrCode::query()->where('status', QrCodeStatus::Active->value)->count())->toBe(3)
        ->and(QrCode::query()->count())->toBe(4)
        ->and($onboarding->fresh()?->completed_at)->not->toBeNull();
});

test('hard deleted permanent QR identity is regenerated and completed onboarding resumes', function () {
    $user = User::factory()->create();
    restaurantOnboardingComponentAtStep($user, 8, 'Deleted QR');
    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();
    $servicePoint = $onboarding->servicePoints()->firstOrFail();
    $deletedQrCodeId = $servicePoint->activeQrCode()->firstOrFail()->id;

    QrCode::query()->whereKey($deletedQrCodeId)->firstOrFail()->deleteOrFail();

    Livewire::actingAs($user)->test(RestaurantSetup::class)
        ->assertSet('step', 6)
        ->call('generateQrCodes')
        ->assertHasNoErrors()
        ->assertSet('step', 8);

    expect(QrCode::query()->whereKey($deletedQrCodeId)->doesntExist())->toBeTrue()
        ->and(QrCode::query()->where('status', QrCodeStatus::Active->value)->count())->toBe(3)
        ->and($onboarding->servicePoints()->whereDoesntHave('activeQrCode')->doesntExist())->toBeTrue()
        ->and($onboarding->fresh()?->completed_at)->not->toBeNull();
});

test('hard deleted starter item is recreated without duplicating its menu graph', function () {
    $user = User::factory()->create();
    restaurantOnboardingComponentAtStep($user, 8, 'Deleted Starter Item');
    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();
    $menuId = $onboarding->menu_id;
    $categoryId = $onboarding->menu_category_id;
    $deletedItemId = $onboarding->menu_item_id;

    MenuItem::query()->whereKey($deletedItemId)->firstOrFail()->forceDelete();

    Livewire::actingAs($user)->test(RestaurantSetup::class)
        ->assertSet('step', 7)
        ->set('form.itemName', 'Recovered starter dish')
        ->set('form.itemPrice', '9.25')
        ->call('createStarterMenu')
        ->assertHasNoErrors()
        ->assertSet('step', 8);

    $onboarding->refresh();

    expect($onboarding->menu_id)->toBe($menuId)
        ->and($onboarding->menu_category_id)->toBe($categoryId)
        ->and($onboarding->menu_item_id)->not->toBeNull()->not->toBe($deletedItemId)
        ->and(Menu::query()->whereKey($menuId)->count())->toBe(1)
        ->and(MenuCategory::query()->where('menu_id', $menuId)->count())->toBe(1)
        ->and(MenuItem::query()->where('menu_id', $menuId)->count())->toBe(1)
        ->and($onboarding->completed_at)->not->toBeNull();
});

test('hard deleted checkpoint area reuses its linked service points and permanent QR identities', function () {
    $user = User::factory()->create();
    restaurantOnboardingComponentAtStep($user, 8, 'Deleted Area Graph');
    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();
    $servicePointIds = $onboarding->servicePoints()->pluck('service_points.id')->all();
    $qrTokens = QrCode::query()
        ->whereIn('service_point_id', $servicePointIds)
        ->orderBy('service_point_id')
        ->pluck('public_token', 'service_point_id')
        ->all();

    AreaNode::query()->whereKey($onboarding->area_node_id)->firstOrFail()->forceDelete();

    Livewire::actingAs($user)->test(RestaurantSetup::class)
        ->assertSet('step', 4)
        ->set('form.areaName', 'Recovered Hall')
        ->set('form.areaType', 'hall')
        ->call('createArea')
        ->assertHasNoErrors()
        ->assertSet('step', 5)
        ->call('createServicePoints')
        ->assertHasNoErrors()
        ->assertSet('step', 8);

    $onboarding->refresh();

    expect(ServicePoint::query()->where('branch_id', $onboarding->branch_id)->count())->toBe(3)
        ->and($onboarding->servicePoints()->pluck('service_points.id')->all())->toBe($servicePointIds)
        ->and($onboarding->servicePoints()->where('area_node_id', $onboarding->area_node_id)->count())->toBe(3)
        ->and(QrCode::query()
            ->whereIn('service_point_id', $servicePointIds)
            ->orderBy('service_point_id')
            ->pluck('public_token', 'service_point_id')
            ->all())->toBe($qrTokens);
});

test('hard deleted final checkpoint table is detected and rebuilt to the persisted expected count', function () {
    $user = User::factory()->create();
    restaurantOnboardingComponentAtStep($user, 8, 'Deleted Final Table');
    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();
    $lastPoint = $onboarding->servicePoints()->orderByPivot('position', 'desc')->firstOrFail();

    $lastPoint->forceDelete();

    Livewire::actingAs($user)->test(RestaurantSetup::class)
        ->assertSet('step', 5)
        ->assertSet('form.tableCount', 3)
        ->call('createServicePoints')
        ->assertHasNoErrors()
        ->assertSet('step', 6);

    expect($onboarding->fresh()?->expected_service_point_count)->toBe(3)
        ->and($onboarding->servicePoints()->count())->toBe(3)
        ->and($onboarding->servicePoints()
            ->pluck('restaurant_onboarding_service_points.position')
            ->map(fn ($position): int => (int) $position)
            ->all())->toBe([1, 2, 3]);
});

test('cross-tenant brand checkpoint corruption is rejected before any record changes', function () {
    $user = User::factory()->create();
    restaurantOnboardingComponentAtStep($user, 2, 'Stale Brand Parent');
    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();
    $foreignOrganization = Organization::factory()->create();
    $foreignBrand = Brand::factory()->for($foreignOrganization)->create(['name' => 'Foreign Brand']);
    $ownedBrandCount = Brand::query()->where('organization_id', $onboarding->organization_id)->count();

    $onboarding->forceFill(['brand_id' => $foreignBrand->id])->save();

    $component = Livewire::actingAs($user)->test(RestaurantSetup::class)
        ->assertSet('step', 2)
        ->set('form.brandName', 'Must Not Be Created');

    expect(fn () => $component->call('createBrand'))
        ->toThrow(ModelNotFoundException::class);

    $onboarding->refresh();

    expect($onboarding->brand_id)->toBe($foreignBrand->id)
        ->and(Brand::query()->where('organization_id', $onboarding->organization_id)->count())->toBe($ownedBrandCount)
        ->and($foreignBrand->fresh()?->name)->toBe('Foreign Brand');
});

test('wrong-area onboarding table link is replaced without moving the existing table', function () {
    $user = User::factory()->create();
    restaurantOnboardingComponentAtStep($user, 6, 'Corrupt Table Link');
    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();
    $branch = Branch::query()->whereKey($onboarding->branch_id)->firstOrFail();
    $otherArea = AreaNode::factory()->forBranch($branch)->create(['name' => 'Other Area']);
    $otherPoint = ServicePoint::factory()->forBranch($branch)->inAreaNode($otherArea)->create(['name' => 'Do Not Move']);

    $onboarding->servicePoints()->attach($otherPoint->id, ['position' => 4]);

    Livewire::actingAs($user)->test(RestaurantSetup::class)
        ->assertSet('step', 5)
        ->assertSet('form.tableCount', 4)
        ->call('createServicePoints')
        ->assertHasNoErrors()
        ->assertSet('step', 6);

    expect($otherPoint->fresh()?->area_node_id)->toBe($otherArea->id)
        ->and($otherPoint->fresh()?->name)->toBe('Do Not Move')
        ->and($onboarding->servicePoints()->whereKey($otherPoint->id)->doesntExist())->toBeTrue()
        ->and($onboarding->servicePoints()->count())->toBe(4)
        ->and($onboarding->servicePoints()->where('area_node_id', $onboarding->area_node_id)->count())->toBe(4);
});

test('cross-branch onboarding table corruption is rejected before any record changes', function () {
    $user = User::factory()->create();
    restaurantOnboardingComponentAtStep($user, 6, 'Cross Branch Table');
    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();
    $foreignOrganization = Organization::factory()->create();
    $foreignBrand = Brand::factory()->for($foreignOrganization)->create();
    $foreignBranch = Branch::factory()->forBrand($foreignBrand)->create();
    $foreignArea = AreaNode::factory()->forBranch($foreignBranch)->create();
    $foreignPoint = ServicePoint::factory()->forBranch($foreignBranch)->inAreaNode($foreignArea)->create(['name' => 'Foreign Table']);
    $ownedNames = $onboarding->servicePoints()->pluck('name', 'service_points.id')->all();

    $onboarding->servicePoints()->attach($foreignPoint->id, ['position' => 4]);

    expect(fn () => app(SaveOnboardingServicePointsAction::class)->handle($user, $onboarding->id, [
        'tableCount' => 4,
        'tablePrefix' => 'Injected',
        'tableCapacity' => 9,
    ]))->toThrow(NotFoundHttpException::class);

    expect($foreignPoint->fresh()?->name)->toBe('Foreign Table')
        ->and($foreignPoint->fresh()?->area_node_id)->toBe($foreignArea->id)
        ->and($onboarding->servicePoints()->whereKey($foreignPoint->id)->exists())->toBeTrue()
        ->and($onboarding->servicePoints()->whereIn('service_points.id', array_keys($ownedNames))->pluck('name', 'service_points.id')->all())->toBe($ownedNames);
});

test('cross-branch table corruption fails before hidden-link count validation', function () {
    $user = User::factory()->create();
    restaurantOnboardingComponentAtStep($user, 6, 'Hidden Cross Branch Table');
    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();
    $foreignOrganization = Organization::factory()->create();
    $foreignBrand = Brand::factory()->for($foreignOrganization)->create();
    $foreignBranch = Branch::factory()->forBrand($foreignBrand)->create();
    $foreignArea = AreaNode::factory()->forBranch($foreignBranch)->create();
    $foreignPoint = ServicePoint::factory()->forBranch($foreignBranch)->inAreaNode($foreignArea)->create();

    $onboarding->servicePoints()->attach($foreignPoint->id, ['position' => 4]);

    Livewire::actingAs($user)->test(RestaurantSetup::class)
        ->assertSet('step', 5)
        ->assertSet('form.tableCount', 3)
        ->call('createServicePoints')
        ->assertNotFound();

    expect($onboarding->servicePoints()->whereKey($foreignPoint->id)->exists())->toBeTrue();
});

test('late onboarding actions reject a malformed checkpoint table set', function (string $corruption, string $operation) {
    $user = User::factory()->create();
    $targetStep = $operation === 'qr' ? 6 : 7;
    restaurantOnboardingComponentAtStep($user, $targetStep, 'Malformed Set '.$corruption.' '.$operation);
    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();
    $point = $onboarding->servicePoints()->orderByPivot('position', 'desc')->firstOrFail();

    if ($corruption === 'type') {
        $point->forceFill(['type' => ServicePointType::BarSeat])->save();
    } else {
        $onboarding->servicePoints()->updateExistingPivot($point->id, ['position' => 4]);
    }

    $countsBefore = restaurantOnboardingGraphCounts();
    $mutation = fn () => $operation === 'qr'
        ? app(GenerateOnboardingQrCodesAction::class)->handle($user, $onboarding->id)
        : app(SaveOnboardingStarterMenuAction::class)->handle($user, $onboarding->id, [
            'menu_name' => 'Rejected Menu',
            'category_name' => 'Rejected Category',
            'item_name' => 'Rejected Dish',
            'item_price' => '9.00',
        ]);

    expect($mutation)->toThrow(HttpException::class)
        ->and(restaurantOnboardingGraphCounts())->toBe($countsBefore)
        ->and($onboarding->fresh()?->completed_at)->toBeNull();

    Livewire::actingAs($user)->test(RestaurantSetup::class)->assertSet('step', 5);
})->with([
    'non-table before QR' => ['type', 'qr'],
    'position gap before QR' => ['position', 'qr'],
    'non-table before menu' => ['type', 'menu'],
    'position gap before menu' => ['position', 'menu'],
]);

test('late onboarding actions reject a stale cross-tenant branch before writing', function (string $operation) {
    $user = User::factory()->create();
    restaurantOnboardingComponentAtStep($user, 8, 'Stale Tenant '.$operation);
    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();
    $foreignOrganization = Organization::factory()->create();
    $foreignBrand = Brand::factory()->for($foreignOrganization)->create();
    $foreignBranch = Branch::factory()->forBrand($foreignBrand)->create(['name' => 'Untouched Foreign Branch']);
    $before = $foreignBranch->only(['name', 'address', 'city', 'country', 'timezone', 'currency', 'is_active']);
    $foreignCounts = [
        'areas' => AreaNode::query()->where('branch_id', $foreignBranch->id)->count(),
        'points' => ServicePoint::query()->where('branch_id', $foreignBranch->id)->count(),
        'menus' => Menu::query()->where('branch_id', $foreignBranch->id)->count(),
        'qr' => QrCode::query()->whereHas('servicePoint', fn ($query) => $query->where('branch_id', $foreignBranch->id))->count(),
    ];

    $onboarding->forceFill(['branch_id' => $foreignBranch->id])->save();

    $mutation = fn () => match ($operation) {
        'area' => app(SaveOnboardingAreaAction::class)->handle($user, $onboarding->id, [
            'parent_id' => null, 'type' => 'hall', 'name' => 'Injected Area', 'icon' => null, 'sort_order' => 0, 'is_active' => true,
        ]),
        'tables' => app(SaveOnboardingServicePointsAction::class)->handle($user, $onboarding->id, [
            'tableCount' => 3, 'tablePrefix' => 'Injected Table', 'tableCapacity' => 4,
        ]),
        'qr' => app(GenerateOnboardingQrCodesAction::class)->handle($user, $onboarding->id),
        'menu' => app(SaveOnboardingStarterMenuAction::class)->handle($user, $onboarding->id, [
            'menu_name' => 'Injected Menu', 'category_name' => 'Injected Category', 'item_name' => 'Injected Dish', 'item_price' => '9.00',
        ]),
    };

    expect($mutation)->toThrow(ModelNotFoundException::class)
        ->and($foreignBranch->fresh()?->only(array_keys($before)))->toBe($before)
        ->and(AreaNode::query()->where('branch_id', $foreignBranch->id)->count())->toBe($foreignCounts['areas'])
        ->and(ServicePoint::query()->where('branch_id', $foreignBranch->id)->count())->toBe($foreignCounts['points'])
        ->and(Menu::query()->where('branch_id', $foreignBranch->id)->count())->toBe($foreignCounts['menus'])
        ->and(QrCode::query()->whereHas('servicePoint', fn ($query) => $query->where('branch_id', $foreignBranch->id))->count())->toBe($foreignCounts['qr']);
})->with(['area', 'tables', 'qr', 'menu']);

test('stale starter-menu references fail safely without changing either menu graph', function (string $resource) {
    $user = User::factory()->create();
    restaurantOnboardingComponentAtStep($user, 8, 'Stale Menu '.$resource);
    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();
    $menu = Menu::query()->whereKey($onboarding->menu_id)->firstOrFail();
    $category = MenuCategory::query()->whereKey($onboarding->menu_category_id)->firstOrFail();
    $item = MenuItem::query()->whereKey($onboarding->menu_item_id)->firstOrFail();
    $original = [
        'menu' => $menu->only(['name', 'status', 'sort_order']),
        'category' => $category->only(['name', 'description', 'icon', 'sort_order', 'is_active']),
        'item' => $item->only(['name', 'price_cents', 'is_available', 'sort_order']),
    ];
    $foreignOrganization = Organization::factory()->create();
    $foreignBrand = Brand::factory()->for($foreignOrganization)->create();
    $foreignBranch = Branch::factory()->forBrand($foreignBrand)->create();
    $foreignMenu = Menu::factory()->forBranch($foreignBranch)->create(['name' => 'Foreign Menu']);
    $foreignCategory = MenuCategory::factory()->create(['menu_id' => $foreignMenu->id, 'name' => 'Foreign Category']);
    $foreignItem = MenuItem::factory()->create([
        'menu_id' => $foreignMenu->id,
        'category_id' => $foreignCategory->id,
        'name' => 'Foreign Dish',
    ]);

    $onboarding->forceFill(match ($resource) {
        'menu' => ['menu_id' => $foreignMenu->id],
        'category' => ['menu_category_id' => $foreignCategory->id],
        'item' => ['menu_item_id' => $foreignItem->id],
    })->save();

    expect(fn () => app(SaveOnboardingStarterMenuAction::class)->handle($user, $onboarding->id, [
        'menu_name' => 'Injected Menu',
        'category_name' => 'Injected Category',
        'item_name' => 'Injected Dish',
        'item_price' => '9.00',
    ]))->toThrow(ModelNotFoundException::class)
        ->and($menu->fresh()?->only(array_keys($original['menu'])))->toBe($original['menu'])
        ->and($category->fresh()?->only(array_keys($original['category'])))->toBe($original['category'])
        ->and($item->fresh()?->only(array_keys($original['item'])))->toBe($original['item'])
        ->and($foreignMenu->fresh()?->name)->toBe('Foreign Menu')
        ->and($foreignCategory->fresh()?->name)->toBe('Foreign Category')
        ->and($foreignItem->fresh()?->name)->toBe('Foreign Dish');
})->with(['menu', 'category', 'item']);

test('starter-menu recovery authorizes availability before reactivating domain records', function () {
    $user = User::factory()->create();
    restaurantOnboardingComponentAtStep($user, 8, 'Availability Guard');
    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();
    $menu = Menu::query()->whereKey($onboarding->menu_id)->firstOrFail();
    $category = MenuCategory::query()->whereKey($onboarding->menu_category_id)->firstOrFail();
    $item = MenuItem::query()->whereKey($onboarding->menu_item_id)->firstOrFail();
    $permission = Permission::query()->where('code', SystemPermission::ChangeAvailability->value)->firstOrFail();

    $menu->forceFill(['status' => MenuStatus::Archived])->save();
    $category->forceFill(['is_active' => false])->save();
    $item->forceFill(['is_available' => false])->save();
    $user->permissionOverrides()->syncWithoutDetaching([$permission->id => ['enabled' => false]]);

    expect(fn () => app(SaveOnboardingStarterMenuAction::class)->handle($user, $onboarding->id, [
        'menu_name' => 'Must Not Change',
        'category_name' => 'Must Not Change',
        'item_name' => 'Must Not Change',
        'item_price' => '9.00',
    ]))->toThrow(AuthorizationException::class)
        ->and($menu->fresh()?->status)->toBe(MenuStatus::Archived)
        ->and($category->fresh()?->is_active)->toBeFalse()
        ->and($item->fresh()?->is_available)->toBeFalse();
});

test('stale Livewire snapshot rechecks corrupted table links before QR mutation', function () {
    $user = User::factory()->create();
    $component = restaurantOnboardingComponentAtStep($user, 6, 'Stale Snapshot');
    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();
    $foreignOrganization = Organization::factory()->create();
    $foreignBrand = Brand::factory()->for($foreignOrganization)->create();
    $foreignBranch = Branch::factory()->forBrand($foreignBrand)->create();
    $foreignArea = AreaNode::factory()->forBranch($foreignBranch)->create();
    $foreignPoint = ServicePoint::factory()->forBranch($foreignBranch)->inAreaNode($foreignArea)->create();
    $qrCount = QrCode::query()->count();

    $onboarding->servicePoints()->attach($foreignPoint->id, ['position' => 4]);

    $component->call('generateQrCodes')->assertStatus(409);

    expect(QrCode::query()->count())->toBe($qrCount)
        ->and($foreignPoint->qrCodes()->count())->toBe(0);
});

test('corrupted table positions are normalized before onboarding continues', function () {
    $user = User::factory()->create();
    restaurantOnboardingComponentAtStep($user, 6, 'Position Recovery');
    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();
    $lastPoint = $onboarding->servicePoints()->orderByPivot('position', 'desc')->firstOrFail();

    $onboarding->servicePoints()->updateExistingPivot($lastPoint->id, ['position' => 4]);

    Livewire::actingAs($user)->test(RestaurantSetup::class)
        ->assertSet('step', 5)
        ->assertSet('form.tableCount', 3)
        ->call('createServicePoints')
        ->assertHasNoErrors()
        ->assertSet('step', 6);

    expect($onboarding->servicePoints()->pluck('restaurant_onboarding_service_points.position')->map(fn ($position): int => (int) $position)->all())
        ->toBe([1, 2, 3]);
});

test('restaurant onboarding checkpoint is unique per user', function () {
    $user = User::factory()->create();
    RestaurantOnboarding::factory()->for($user)->create();

    expect(fn () => RestaurantOnboarding::factory()->for($user)->create())->toThrow(QueryException::class);
});

test('onboarding mutation surface contains no legacy checkpoint-free bulk actions', function () {
    expect(file_exists(app_path('Actions/Onboarding/CreateOnboardingServicePointsAction.php')))->toBeFalse()
        ->and(file_exists(app_path('Actions/Onboarding/GenerateQrCodesForServicePointsAction.php')))->toBeFalse()
        ->and(file_exists(app_path('Actions/Onboarding/CreateStarterMenuAction.php')))->toBeFalse();
});

test('restaurant onboarding converts comma decimal money without float arithmetic', function () {
    $user = User::factory()->create();

    restaurantOnboardingComponentAtStep($user, 7, 'Comma Money')
        ->set('form.categoryName', 'Comma Category')
        ->set('form.itemName', 'Comma Dish')
        ->set('form.itemPrice', '8,50')
        ->call('createStarterMenu')
        ->assertHasNoErrors();

    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();
    expect(MenuItem::query()->whereKey($onboarding->menu_item_id)->value('price_cents'))->toBe(850);
});

test('restaurant onboarding defaults and validation are localized with translation parity', function (string $locale) {
    app()->setLocale($locale);
    config()->set('app.timezone', 'America/Toronto');
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(RestaurantSetup::class)
        ->assertSet('form.areaName', __('ui.onboarding.restaurant_setup.defaults.area_name'))
        ->assertSet('form.tablePrefix', __('ui.onboarding.restaurant_setup.defaults.table_prefix'))
        ->assertSet('form.menuName', __('ui.onboarding.restaurant_setup.defaults.menu_name'))
        ->assertSet('form.categoryName', __('ui.onboarding.restaurant_setup.defaults.category_name'))
        ->assertSet('form.itemName', __('ui.onboarding.restaurant_setup.defaults.item_name'))
        ->assertSet('form.branchCountryCode', '')
        ->assertSet('form.branchTimezone', 'America/Toronto')
        ->assertSet('form.branchCurrency', '')
        ->call('createOrganization')
        ->assertHasErrors(['form.organizationName' => 'required']);

    $keys = collect(['en', 'lt', 'ru'])->mapWithKeys(fn (string $language): array => [
        $language => array_keys(json_decode((string) file_get_contents(lang_path($language.'.json')), true, flags: JSON_THROW_ON_ERROR)),
    ]);
    expect($keys['lt'])->toBe($keys['en'])->and($keys['ru'])->toBe($keys['en']);
})->with(['en', 'lt', 'ru']);

test('completed onboarding renders translated copy without raw translation keys', function (string $locale) {
    app()->setLocale($locale);
    $user = User::factory()->create();
    restaurantOnboardingComponentAtStep($user, 8, 'Translated Completion '.$locale);

    Livewire::actingAs($user)->test(RestaurantSetup::class)
        ->assertSet('step', 8)
        ->assertSee(__('ui.onboarding.restaurant_setup.restoran_gotov_k_proverke'))
        ->assertDontSee('ui.onboarding.restaurant_setup.', escape: false)
        ->assertDontSee('ui.livewire.onboarding.restaurantsetup.', escape: false);
})->with(['en', 'lt', 'ru']);

test('every onboarding form step rejects its missing required input without advancing or writing', function (int $step, string $property, string $action) {
    $user = User::factory()->create();
    $component = restaurantOnboardingComponentAtStep($user, $step, 'Required Field');
    $countsBefore = restaurantOnboardingGraphCounts();

    $component
        ->set($property, '')
        ->call($action)
        ->assertHasErrors([$property => 'required'])
        ->assertSet('step', $step);

    expect(restaurantOnboardingGraphCounts())->toBe($countsBefore);
})->with([
    'organization form' => [1, 'form.organizationName', 'createOrganization'],
    'brand form' => [2, 'form.brandName', 'createBrand'],
    'branch form' => [3, 'form.branchName', 'createBranch'],
    'area form' => [4, 'form.areaName', 'createArea'],
    'service-point form' => [5, 'form.tablePrefix', 'createServicePoints'],
    'starter-menu form' => [7, 'form.itemPrice', 'createStarterMenu'],
]);

test('restaurant onboarding accepts every documented maximum boundary exactly', function () {
    $user = User::factory()->create();
    $organizationName = str_repeat('O', 120);
    $brandName = str_repeat('B', 120);
    $branchName = str_repeat('R', 160);
    $branchAddress = str_repeat('A', 255);
    $branchCity = str_repeat('C', 120);
    $areaName = str_repeat('H', 160);
    $tablePrefix = str_repeat('T', 40);
    $menuName = str_repeat('M', 160);
    $categoryName = str_repeat('G', 160);
    $itemName = str_repeat('I', 180);

    Livewire::actingAs($user)
        ->test(RestaurantSetup::class)
        ->set('form.organizationName', $organizationName)
        ->call('createOrganization')
        ->set('form.brandName', $brandName)
        ->call('createBrand')
        ->set('form.branchName', $branchName)
        ->set('form.branchAddress', $branchAddress)
        ->set('form.branchCity', $branchCity)
        ->set('form.branchCountryCode', 'US')
        ->set('form.branchTimezone', 'UTC')
        ->set('form.branchCurrency', 'USD')
        ->call('createBranch')
        ->set('form.areaName', $areaName)
        ->set('form.areaType', 'hall')
        ->set('form.areaIcon', 'rectangle-group')
        ->call('createArea')
        ->set('form.tableCount', 20)
        ->set('form.tablePrefix', $tablePrefix)
        ->set('form.tableCapacity', 50)
        ->call('createServicePoints')
        ->call('generateQrCodes')
        ->set('form.menuName', $menuName)
        ->set('form.categoryName', $categoryName)
        ->set('form.itemName', $itemName)
        ->set('form.itemPrice', '999999.99')
        ->call('createStarterMenu')
        ->assertHasNoErrors()
        ->assertSet('step', 8);

    $onboarding = RestaurantOnboarding::query()->where('user_id', $user->id)->firstOrFail();

    expect(Organization::query()->whereKey($onboarding->organization_id)->value('name'))->toBe($organizationName)
        ->and(Brand::query()->whereKey($onboarding->brand_id)->value('name'))->toBe($brandName)
        ->and(Branch::query()->whereKey($onboarding->branch_id)->firstOrFail())
        ->name->toBe($branchName)
        ->address->toBe($branchAddress)
        ->city->toBe($branchCity)
        ->currency->toBe('USD')
        ->and(AreaNode::query()->whereKey($onboarding->area_node_id)->value('name'))->toBe($areaName)
        ->and($onboarding->servicePoints()->count())->toBe(20)
        ->and($onboarding->servicePoints()->where('capacity', 50)->count())->toBe(20)
        ->and(QrCode::query()->whereIn('service_point_id', $onboarding->servicePoints()->select('service_points.id'))->count())->toBe(20)
        ->and(Menu::query()->whereKey($onboarding->menu_id)->value('name'))->toBe($menuName)
        ->and(MenuCategory::query()->whereKey($onboarding->menu_category_id)->value('name'))->toBe($categoryName)
        ->and(MenuItem::query()->whereKey($onboarding->menu_item_id)->firstOrFail())
        ->name->toBe($itemName)
        ->price_cents->toBe(99_999_999);
});

test('restaurant onboarding falls back to UTC when the configured application timezone is invalid', function () {
    expect(RestaurantSetupOptions::defaultTimezone('America/Toronto'))->toBe('America/Toronto')
        ->and(RestaurantSetupOptions::defaultTimezone('Invalid/Timezone'))->toBe('UTC');
});

test('restaurant onboarding copy uses restaurant terminology and neutral international examples', function () {
    $translations = collect(['en', 'lt', 'ru'])->mapWithKeys(fn (string $locale): array => [
        $locale => json_decode((string) file_get_contents(lang_path($locale.'.json')), true, flags: JSON_THROW_ON_ERROR),
    ]);
    $neutralExamples = [
        'en' => ['Your city', 'Two-letter country code', 'Region/City'],
        'lt' => ['Jūsų miestas', 'Dviejų raidžių šalies kodas', 'Regionas/Miestas'],
        'ru' => ['Ваш город', 'Двухбуквенный код страны', 'Регион/Город'],
    ];

    foreach ($translations as $locale => $lines) {
        expect($lines)
            ->toHaveKeys([
                'ui.onboarding.restaurant_setup.defaults.area_name',
                'ui.onboarding.restaurant_setup.defaults.table_prefix',
                'ui.onboarding.restaurant_setup.defaults.menu_name',
                'ui.onboarding.restaurant_setup.defaults.category_name',
                'ui.onboarding.restaurant_setup.defaults.item_name',
            ])
            ->and($lines['ui.onboarding.restaurant_setup.branch_city_placeholder'])->toBe($neutralExamples[$locale][0])
            ->and($lines['ui.onboarding.restaurant_setup.country_placeholder'])->toBe($neutralExamples[$locale][1])
            ->and($lines['ui.onboarding.restaurant_setup.timezone_placeholder'])->toBe($neutralExamples[$locale][2]);
    }

    $lithuanianOnboardingCopy = collect($translations['lt'])
        ->filter(fn (string $value, string $key): bool => str_starts_with($key, 'ui.onboarding.restaurant_setup.') || str_starts_with($key, 'ui.livewire.onboarding.restaurantsetup.'))
        ->implode(' ');

    expect($lithuanianOnboardingCopy)
        ->not->toMatch('/\blentel(?:ė|ės|ę|ei|ių|ėms|ėmis|ėse)?\b/ui')
        ->not->toContain('Pirmas kursas', 'testo meniu');
});

test('restaurant onboarding production PHP contains no hardcoded localized business defaults', function () {
    $source = collect([
        app_path('Actions/Onboarding'),
        app_path('Livewire/Forms/Onboarding'),
        app_path('Livewire/Onboarding'),
        app_path('Services/Onboarding'),
    ])
        ->flatMap(fn (string $directory): array => File::allFiles($directory))
        ->map(fn (SplFileInfo $file): string => File::get($file->getPathname()))
        ->implode("\n");
    $source .= File::get(app_path('Support/RestaurantSetupOptions.php'));

    expect($source)->not->toContain(
        'Главный зал',
        'Стол',
        'Основное меню',
        'Тестовое блюдо',
    );
});

test('restaurant onboarding QR counts follow each locale plural rules', function () {
    $expected = [
        'en' => [
            1 => '1 permanent QR code will be created.',
            2 => '2 permanent QR codes will be created.',
            10 => '10 permanent QR codes will be created.',
            21 => '21 permanent QR codes will be created.',
        ],
        'lt' => [
            1 => 'Bus sukurtas 1 nuolatinis QR kodas.',
            2 => 'Bus sukurti 2 nuolatiniai QR kodai.',
            10 => 'Bus sukurta 10 nuolatinių QR kodų.',
            21 => 'Bus sukurtas 21 nuolatinis QR kodas.',
        ],
        'ru' => [
            1 => 'Будет создан 1 постоянный QR-код.',
            2 => 'Будут созданы 2 постоянных QR-кода.',
            10 => 'Будет создано 10 постоянных QR-кодов.',
            21 => 'Будет создан 21 постоянный QR-код.',
        ],
    ];

    foreach ($expected as $locale => $counts) {
        app()->setLocale($locale);

        foreach ($counts as $count => $text) {
            expect(trans_choice(
                'ui.onboarding.restaurant_setup.1_budet_sozdan_postoiannyi_qr_2_budet_sozdan',
                $count,
                ['count' => $count],
            ))->toBe($text);
        }
    }
});

test('restaurant onboarding mount stays within a bounded query budget', function () {
    $user = User::factory()->create();

    $initialQueries = countDatabaseQueries(fn () => Livewire::actingAs($user)->test(RestaurantSetup::class));
    restaurantOnboardingComponentAtStep($user, 8, 'Query Budget');
    $completedQueries = countDatabaseQueries(fn () => Livewire::actingAs($user)->test(RestaurantSetup::class));

    expect($initialQueries)->toBeLessThanOrEqual(12)
        ->and($completedQueries)->toBeLessThanOrEqual(30);
});

test('new user can create restaurant setup from onboarding wizard', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(RestaurantSetup::class)
        ->assertSee(__('ui.onboarding.restaurant_setup.nastroit_restoran'))
        ->assertSee(__('ui.onboarding.restaurant_setup.nazvanie_kompanii'))
        ->set('form.organizationName', 'Prompt 74 Food Group')
        ->call('createOrganization')
        ->assertHasNoErrors()
        ->assertSet('step', 2)
        ->assertSee(__('ui.onboarding.restaurant_setup.nazvanie_restorana'))
        ->set('form.brandName', 'Prompt 74 Bistro')
        ->call('createBrand')
        ->assertHasNoErrors()
        ->assertSet('step', 3)
        ->set('form.branchName', 'Prompt 74 Bistro Old Town')
        ->set('form.branchAddress', 'Pilies 1')
        ->set('form.branchCity', 'Vilnius')
        ->set('form.branchCountryCode', 'LT')
        ->set('form.branchTimezone', 'Europe/Vilnius')
        ->set('form.branchCurrency', 'EUR')
        ->call('createBranch')
        ->assertHasNoErrors()
        ->assertSet('step', 4)
        ->set('form.areaName', 'Главный зал')
        ->set('form.areaType', 'hall')
        ->call('createArea')
        ->assertHasNoErrors()
        ->assertSet('step', 5)
        ->set('form.tableCount', 3)
        ->set('form.tablePrefix', 'Стол')
        ->set('form.tableCapacity', 4)
        ->call('createServicePoints')
        ->assertHasNoErrors()
        ->assertSet('step', 6)
        ->call('generateQrCodes')
        ->assertHasNoErrors()
        ->assertSet('step', 7)
        ->set('form.menuName', 'Основное меню')
        ->set('form.categoryName', 'Завтраки')
        ->set('form.itemName', 'Сырники')
        ->set('form.itemPrice', '8.50')
        ->call('createStarterMenu')
        ->assertHasNoErrors()
        ->assertSet('step', 8)
        ->assertSee(__('ui.onboarding.restaurant_setup.otkryt_gostevoe_meniu'));

    expect(Organization::query()->where('name', 'Prompt 74 Food Group')->count())->toBe(1)
        ->and(Brand::query()->where('name', 'Prompt 74 Bistro')->count())->toBe(1)
        ->and(Branch::query()->where('name', 'Prompt 74 Bistro Old Town')->count())->toBe(1)
        ->and(Branch::query()->where('name', 'Prompt 74 Bistro Old Town')->value('country'))->toBe('Lithuania')
        ->and(AreaNode::query()->where('name', 'Главный зал')->count())->toBe(1)
        ->and(ServicePoint::query()->where('name', 'like', 'Стол%')->count())->toBe(3)
        ->and(QrCode::query()->count())->toBe(3)
        ->and(Menu::query()->where('name', 'Основное меню')->count())->toBe(1)
        ->and(MenuCategory::query()->where('name', 'Завтраки')->count())->toBe(1)
        ->and(MenuItem::query()->where('name', 'Сырники')->value('price_cents'))->toBe(850);

    $qrCountBeforeSecondClick = QrCode::query()->count();

    $component
        ->call('generateQrCodes')
        ->assertHasNoErrors();

    expect(QrCode::query()->count())->toBe($qrCountBeforeSecondClick);

    $component
        ->call('goToStep', 1)
        ->assertSet('step', 1)
        ->call('goToStep', 99)
        ->assertSet('step', 1);

    $qrCode = QrCode::query()
        ->select(['id', 'public_token'])
        ->oldest('id')
        ->firstOrFail();
    $publicUrl = route('public.qr.show', ['token' => $qrCode->public_token]);
    $publicPathSegments = explode('/', trim((string) parse_url($publicUrl, PHP_URL_PATH), '/'));

    expect($publicPathSegments)->toBe(['q', $qrCode->public_token]);

    $this->get(route('public.qr.show', ['token' => $qrCode->public_token], false))
        ->assertOk()
        ->assertSee('Prompt 74 Bistro');
});

test('restaurant onboarding exposes finite localized option catalogues', function () {
    expect(RestaurantSetupOptions::countryCodes())
        ->toHaveCount(249)
        ->toContain('LT', 'US', 'JP')
        ->and(RestaurantSetupOptions::countryOptions('en'))
        ->toHaveKey('LT')
        ->and(RestaurantSetupOptions::timezoneOptions())
        ->toHaveKey('Europe/Vilnius')
        ->and(RestaurantSetupOptions::areaTypeOptions())
        ->toHaveKeys(['hall', 'terrace', 'bar_area'])
        ->and(RestaurantSetupOptions::areaIconOptions())
        ->toHaveKeys(['rectangle-group', 'sun', 'sparkles'])
        ->and(RestaurantSetupOptions::currencyOptions())
        ->toHaveKeys(['EUR', 'USD', 'GBP']);

    foreach (['en', 'lt', 'ru'] as $locale) {
        expect(RestaurantSetupOptions::countryCode(RestaurantSetupOptions::countryName('LT', $locale)))->toBe('LT');
    }
});

test('restaurant onboarding area catalogues use Lithuanian restaurant terminology', function () {
    app()->setLocale('lt');

    expect(RestaurantSetupOptions::areaTypeOptions())
        ->toMatchArray([
            'group' => 'Zonų grupė',
            'floor' => 'Aukštas',
            'hall' => 'Salė',
            'terrace' => 'Terasa',
            'vip_room' => 'VIP salė',
            'banquet_hall' => 'Pokylių salė',
            'pickup_area' => 'Atsiėmimo zona',
            'delivery_area' => 'Pristatymo zona',
            'custom' => 'Kita zona',
        ])
        ->and(implode(' ', RestaurantSetupOptions::areaTypeOptions()))->not->toMatch('/\p{Cyrillic}/u')
        ->and(implode(' ', RestaurantSetupOptions::areaIconOptions()))->not->toMatch('/\p{Cyrillic}/u');
});

test('restaurant onboarding renders controls with domain-correct html semantics', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(RestaurantSetup::class)
        ->assertSeeHtml('name="organization_name"')
        ->assertSeeHtml('autocomplete="organization"');

    $component = restaurantOnboardingComponentAtStep($user, 3)
        ->assertSeeHtml('name="branch_country_code"')
        ->assertSeeHtml('list="restaurant-country-options"')
        ->assertSeeHtml('autocomplete="country"')
        ->assertSeeHtml('id="branch-country-code-help"')
        ->assertSeeHtml('aria-describedby="branch-country-code-help branch-country-code-error"')
        ->assertSeeHtml('name="branch_timezone"')
        ->assertSeeHtml('list="restaurant-timezone-options"')
        ->assertSeeHtml('id="branch-timezone-help"')
        ->assertSeeHtml('aria-describedby="branch-timezone-help branch-timezone-error"');

    restaurantOnboardingComponentAtStep($user, 8)
        ->call('goToStep', 7)
        ->assertSeeHtml('name="item_price"')
        ->assertSeeHtml('inputmode="decimal"')
        ->assertSeeHtml('step="0.01"');
});

test('restaurant onboarding renders precise resilient interaction states', function () {
    $source = File::get(resource_path('views/livewire/onboarding/restaurant-setup.blade.php'));
    $actions = [
        'createOrganization',
        'createBrand',
        'createBranch',
        'createArea',
        'createServicePoints',
        'generateQrCodes',
        'createStarterMenu',
    ];

    foreach ($actions as $action) {
        expect($source)
            ->toContain('wire:target="'.$action.'"')
            ->toContain('wire:loading.attr="aria-busy"')
            ->toContain('wire:offline.attr="disabled"');
    }

    expect($source)
        ->toContain('wire:key="restaurant-onboarding-step-1"')
        ->toContain('wire:key="restaurant-onboarding-step-8"')
        ->toContain('wire:key="onboarding-country-{{ $countryCode }}"')
        ->toContain('wire:key="onboarding-timezone-{{ $timezoneIdentifier }}"')
        ->not->toContain('wire:model.live="form.');

    foreach (['en', 'lt', 'ru'] as $locale) {
        $translations = json_decode((string) File::get(lang_path($locale.'.json')), true, flags: JSON_THROW_ON_ERROR);

        expect($translations)
            ->toHaveKeys([
                'ui.onboarding.restaurant_setup.saving_step',
                'ui.onboarding.restaurant_setup.generating_qr',
            ]);
    }

    $user = User::factory()->create();

    restaurantOnboardingComponentAtStep($user, 2)
        ->assertSeeHtml('data-onboarding-mobile-summary')
        ->assertSee(__('ui.onboarding.restaurant_setup.cto_uze_sozdano'));
});

test('restaurant onboarding normalizes plain text before persistence', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(RestaurantSetup::class)
        ->set('form.organizationName', "  <b>North\nFork</b>  ")
        ->call('createOrganization')
        ->assertHasNoErrors();

    expect(Organization::query()->where('owner_user_id', $user->id)->value('name'))
        ->toBe('North Fork');
});

test('restaurant onboarding uses localized human validation attributes', function () {
    app()->setLocale('ru');

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(RestaurantSetup::class)
        ->set('form.organizationName', '')
        ->call('createOrganization')
        ->assertHasErrors(['form.organizationName' => 'required'])
        ->assertDispatched('onboarding-validation-failed')
        ->assertSeeHtml('aria-invalid="true"')
        ->assertSeeHtml('aria-describedby="organization-name-help organization-name-error"')
        ->assertSeeHtml('id="organization-name-error"')
        ->assertSee(__('ui.onboarding.restaurant_setup.validation.required', [
            'attribute' => __('validation.attributes.organization_name'),
        ]));
});

test('restaurant onboarding localizes international branch validation messages', function (string $locale) {
    app()->setLocale($locale);
    $user = User::factory()->create();

    restaurantOnboardingComponentAtStep($user, 3)
        ->set('form.branchName', 'International Branch')
        ->set('form.branchAddress', '123 Market Street')
        ->set('form.branchCity', 'Example City')
        ->set('form.branchCountryCode', 'US')
        ->set('form.branchTimezone', 'Invalid/Timezone')
        ->set('form.branchCurrency', 'BTC')
        ->call('createBranch')
        ->assertHasErrors([
            'form.branchTimezone' => 'timezone',
            'form.branchCurrency' => 'in',
        ])
        ->assertSee(__('ui.onboarding.restaurant_setup.validation.timezone'))
        ->assertSee(__('ui.onboarding.restaurant_setup.validation.in', [
            'attribute' => __('validation.attributes.branch_currency'),
        ]));
})->with(['en', 'lt', 'ru']);

test('restaurant onboarding normalizes ISO country and supported currency codes', function () {
    $user = User::factory()->create();

    restaurantOnboardingComponentAtStep($user, 3)
        ->set('form.branchName', 'Normalized Branch')
        ->set('form.branchAddress', '123 Market Street')
        ->set('form.branchCity', 'Example City')
        ->set('form.branchCountryCode', 'lt')
        ->set('form.branchTimezone', 'Europe/Vilnius')
        ->set('form.branchCurrency', 'eur')
        ->call('createBranch')
        ->assertHasNoErrors();

    expect(Branch::query()->where('name', 'Normalized Branch')->firstOrFail())
        ->country->toBe('Lithuania')
        ->currency->toBe('EUR')
        ->timezone->toBe('Europe/Vilnius');
});

test('restaurant onboarding rejects invalid branch boundaries', function (string $property, mixed $value, string $rule) {
    $user = User::factory()->create();

    restaurantOnboardingComponentAtStep($user, 3)
        ->set('form.branchName', 'Old Town')
        ->set('form.branchAddress', 'Pilies 1')
        ->set('form.branchCity', 'Vilnius')
        ->set('form.branchCountryCode', 'LT')
        ->set('form.branchTimezone', 'Europe/Vilnius')
        ->set('form.branchCurrency', 'EUR')
        ->set($property, $value)
        ->assertSet($property, $value)
        ->call('createBranch')
        ->assertHasErrors([$property => $rule]);
})->with([
    'blank address' => ['form.branchAddress', '', 'required'],
    'oversized city' => ['form.branchCity', str_repeat('a', 121), 'max'],
    'unsupported country' => ['form.branchCountryCode', 'ZZ', 'in'],
    'invalid time zone' => ['form.branchTimezone', 'Europe/Not_A_Zone', 'timezone'],
    'unsupported currency' => ['form.branchCurrency', 'BTC', 'in'],
]);

test('restaurant onboarding rejects invalid area boundaries', function (string $property, mixed $value, string $rule) {
    $user = User::factory()->create();

    restaurantOnboardingComponentAtStep($user, 4)
        ->set('form.areaName', 'Main hall')
        ->set('form.areaType', 'hall')
        ->set('form.areaIcon', 'rectangle-group')
        ->set($property, $value)
        ->call('createArea')
        ->assertHasErrors([$property => $rule]);
})->with([
    'unsupported area type' => ['form.areaType', 'spaceship', 'in'],
    'unsupported icon' => ['form.areaIcon', 'unregistered-icon', 'in'],
]);

test('restaurant onboarding rejects invalid table boundaries', function (string $property, mixed $value, string $rule) {
    $user = User::factory()->create();

    restaurantOnboardingComponentAtStep($user, 5)
        ->set('form.tableCount', 4)
        ->set('form.tablePrefix', 'Table')
        ->set('form.tableCapacity', 4)
        ->set($property, $value)
        ->call('createServicePoints')
        ->assertHasErrors([$property => $rule]);
})->with([
    'zero tables' => ['form.tableCount', 0, 'min'],
    'too many tables' => ['form.tableCount', 21, 'max'],
    'blank prefix' => ['form.tablePrefix', '', 'required'],
    'too many seats' => ['form.tableCapacity', 51, 'max'],
]);

test('restaurant onboarding validates hostile numeric field payloads without a server error', function (string $property, mixed $value, string $rule) {
    $user = User::factory()->create();
    $component = restaurantOnboardingComponentAtStep($user, 5, 'Hostile Numeric Payload');
    $countsBefore = restaurantOnboardingGraphCounts();

    $component
        ->set('form.tableCount', 4)
        ->set('form.tablePrefix', 'Table')
        ->set('form.tableCapacity', 4)
        ->set($property, $value)
        ->call('createServicePoints')
        ->assertHasErrors([$property => $rule])
        ->assertSet('step', 5);

    expect(restaurantOnboardingGraphCounts())->toBe($countsBefore);
})->with([
    'blank table count' => ['form.tableCount', null, 'required'],
    'text table count' => ['form.tableCount', 'four', 'integer'],
    'array table count' => ['form.tableCount', [4], 'integer'],
    'blank table capacity' => ['form.tableCapacity', null, 'required'],
    'text table capacity' => ['form.tableCapacity', 'four', 'integer'],
    'array table capacity' => ['form.tableCapacity', [4], 'integer'],
]);

test('restaurant onboarding validates non scalar form payloads at every input step', function (int $step, string $property, string $action) {
    $user = User::factory()->create();
    $component = restaurantOnboardingComponentAtStep($user, $step, 'Hostile Form Payload');
    $countsBefore = restaurantOnboardingGraphCounts();

    $component
        ->set($property, ['forged'])
        ->call($action)
        ->assertHasErrors([$property => 'required'])
        ->assertSet('step', $step);

    expect(restaurantOnboardingGraphCounts())->toBe($countsBefore);
})->with([
    'organization' => [1, 'form.organizationName', 'createOrganization'],
    'brand' => [2, 'form.brandName', 'createBrand'],
    'branch' => [3, 'form.branchCountryCode', 'createBranch'],
    'area' => [4, 'form.areaType', 'createArea'],
    'service points' => [5, 'form.tablePrefix', 'createServicePoints'],
    'starter menu' => [7, 'form.itemPrice', 'createStarterMenu'],
]);

test('restaurant onboarding rejects boolean payloads for text fields', function (int $step, string $property, string $action) {
    $user = User::factory()->create();
    $component = restaurantOnboardingComponentAtStep($user, $step, 'Boolean Form Payload');
    $countsBefore = restaurantOnboardingGraphCounts();

    $component
        ->set($property, true)
        ->call($action)
        ->assertHasErrors([$property => 'required'])
        ->assertSet('step', $step);

    expect(restaurantOnboardingGraphCounts())->toBe($countsBefore);
})->with([
    'organization name' => [1, 'form.organizationName', 'createOrganization'],
    'brand name' => [2, 'form.brandName', 'createBrand'],
    'branch name' => [3, 'form.branchName', 'createBranch'],
    'area name' => [4, 'form.areaName', 'createArea'],
    'table prefix' => [5, 'form.tablePrefix', 'createServicePoints'],
    'starter item name' => [7, 'form.itemName', 'createStarterMenu'],
]);

test('restaurant onboarding rejects invalid menu boundaries', function (string $property, mixed $value, string $rule) {
    $user = User::factory()->create();

    restaurantOnboardingComponentAtStep($user, 7)
        ->set('form.menuName', 'Main menu')
        ->set('form.categoryName', 'Mains')
        ->set('form.itemName', 'Soup')
        ->set('form.itemPrice', '10.00')
        ->set($property, $value)
        ->call('createStarterMenu')
        ->assertHasErrors([$property => $rule]);
})->with([
    'blank menu name' => ['form.menuName', '', 'required'],
    'oversized dish name' => ['form.itemName', str_repeat('a', 181), 'max'],
    'negative price' => ['form.itemPrice', '-0.01', 'min'],
    'fractional precision' => ['form.itemPrice', '1.999', 'decimal'],
]);

test('restaurant onboarding rejects binary float money payloads before conversion', function () {
    $user = User::factory()->create();
    $component = restaurantOnboardingComponentAtStep($user, 7, 'Float Money Payload');
    $countsBefore = restaurantOnboardingGraphCounts();

    $component
        ->set('form.itemPrice', 8.50)
        ->call('createStarterMenu')
        ->assertHasErrors(['form.itemPrice' => 'required'])
        ->assertSet('step', 7);

    expect(restaurantOnboardingGraphCounts())->toBe($countsBefore);
});

test('onboarding summary does not expose another users setup ids', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    restaurantOnboardingComponentAtStep($owner, 8, 'Hidden Prompt 74');
    $ownerOnboarding = RestaurantOnboarding::query()->where('user_id', $owner->id)->firstOrFail();

    $intruderComponent = Livewire::actingAs($intruder)
        ->test(RestaurantSetup::class)
        ->assertDontSee('Hidden Prompt 74 Group')
        ->assertDontSee('Hidden Prompt 74 Brand')
        ->assertDontSee('Hidden Prompt 74 Branch')
        ->assertDontSee('Hidden Prompt 74 Hall')
        ->assertDontSee('Hidden Prompt 74 Menu');

    expect(fn () => $intruderComponent->set('onboardingId', $ownerOnboarding->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

function restaurantOnboardingComponentAtStep(User $user, int $targetStep, string $prefix = 'Test Onboarding'): Testable
{
    $component = Livewire::actingAs($user)->test(RestaurantSetup::class);
    $name = $prefix.' '.$user->id;

    if ($component->get('step') < 2 && $targetStep >= 2) {
        $component->set('form.organizationName', $name.' Group')->call('createOrganization')->assertHasNoErrors();
        $component = Livewire::actingAs($user)->test(RestaurantSetup::class)->assertSet('step', 2);
    }

    if ($component->get('step') < 3 && $targetStep >= 3) {
        $component->set('form.brandName', $name.' Brand')->call('createBrand')->assertHasNoErrors();
        $component = Livewire::actingAs($user)->test(RestaurantSetup::class)->assertSet('step', 3);
    }

    if ($component->get('step') < 4 && $targetStep >= 4) {
        $component
            ->set('form.branchName', $name.' Branch')
            ->set('form.branchAddress', '1 Test Street')
            ->set('form.branchCity', 'Vilnius')
            ->set('form.branchCountryCode', 'LT')
            ->set('form.branchTimezone', 'Europe/Vilnius')
            ->set('form.branchCurrency', 'EUR')
            ->call('createBranch')->assertHasNoErrors();
        $component = Livewire::actingAs($user)->test(RestaurantSetup::class)->assertSet('step', 4);
    }

    if ($component->get('step') < 5 && $targetStep >= 5) {
        $component->set('form.areaName', $name.' Hall')->set('form.areaType', 'hall')->call('createArea')->assertHasNoErrors();
        $component = Livewire::actingAs($user)->test(RestaurantSetup::class)->assertSet('step', 5);
    }

    if ($component->get('step') < 6 && $targetStep >= 6) {
        $component->set('form.tableCount', 3)->set('form.tablePrefix', $name.' Table')->set('form.tableCapacity', 4)->call('createServicePoints')->assertHasNoErrors();
        $component = Livewire::actingAs($user)->test(RestaurantSetup::class)->assertSet('step', 6);
    }

    if ($component->get('step') < 7 && $targetStep >= 7) {
        $component->call('generateQrCodes')->assertHasNoErrors();
        $component = Livewire::actingAs($user)->test(RestaurantSetup::class)->assertSet('step', 7);
    }

    if ($component->get('step') < 8 && $targetStep >= 8) {
        $component
            ->set('form.menuName', $name.' Menu')
            ->set('form.categoryName', $name.' Category')
            ->set('form.itemName', $name.' Dish')
            ->set('form.itemPrice', '8.50')
            ->call('createStarterMenu')->assertHasNoErrors();
        $component = Livewire::actingAs($user)->test(RestaurantSetup::class)->assertSet('step', 8);
    }

    return $component;
}

function assignRestaurantOnboardingSystemRole(User $user, SystemRole $systemRole): void
{
    $role = Role::query()->where('code', $systemRole->value)->firstOrFail();

    $user->roles()->syncWithoutDetachingOrFail([$role->id]);
}

/** @return array<string, int> */
function restaurantOnboardingGraphCounts(): array
{
    return [
        'onboardings' => RestaurantOnboarding::query()->count(),
        'organizations' => Organization::query()->count(),
        'brands' => Brand::query()->count(),
        'branches' => Branch::query()->count(),
        'areas' => AreaNode::query()->count(),
        'service_points' => ServicePoint::query()->count(),
        'qr_codes' => QrCode::query()->count(),
        'menus' => Menu::query()->count(),
        'menu_categories' => MenuCategory::query()->count(),
        'menu_items' => MenuItem::query()->count(),
    ];
}
