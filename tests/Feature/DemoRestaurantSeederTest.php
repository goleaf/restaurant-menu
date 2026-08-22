<?php

use App\Enums\BranchOrderFlowMode;
use App\Enums\BranchServiceMode;
use App\Enums\DraftOrderStatus;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\OrderStatus;
use App\Enums\QrCodeStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionStatus;
use App\Enums\WaiterCallStatus;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\KitchenTicketItem;
use App\Models\ManualPayment;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\OrganizationUser;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use App\Models\WaiterCall;
use Database\Factories\UserFactory;
use Database\Seeders\DemoRestaurantSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

test('demo restaurant seeder creates a runnable demo restaurant', function () {
    $this->seed(DemoRestaurantSeeder::class);

    $organization = Organization::query()
        ->where('name', 'Demo Food Group')
        ->firstOrFail();
    $brand = Brand::query()
        ->where('organization_id', $organization->id)
        ->where('name', 'Bella Pizza')
        ->firstOrFail();
    $branch = Branch::query()
        ->where('brand_id', $brand->id)
        ->where('name', 'Bella Pizza Old Town')
        ->firstOrFail();

    expect($branch->settings()->exists())->toBeTrue()
        ->and(AreaNode::query()->where('branch_id', $branch->id)->count())->toBe(3)
        ->and(ServicePoint::query()->where('branch_id', $branch->id)->count())->toBe(7)
        ->and(Menu::query()->where('branch_id', $branch->id)->where('name', 'Bella Pizza Demo Menu')->count())->toBe(1);

    $servicePointIds = ServicePoint::query()
        ->where('branch_id', $branch->id)
        ->orderBy('id')
        ->pluck('id');

    expect(QrCode::query()
        ->whereIn('service_point_id', $servicePointIds)
        ->where('status', QrCodeStatus::Active->value)
        ->count())->toBe(7);

    $menu = Menu::query()
        ->where('branch_id', $branch->id)
        ->where('name', 'Bella Pizza Demo Menu')
        ->firstOrFail();

    expect(MenuCategory::query()->where('menu_id', $menu->id)->count())->toBe(3)
        ->and(MenuItem::query()->where('menu_id', $menu->id)->count())->toBe(7);

    foreach (demoRestaurantUsers() as $email => $identity) {
        $user = User::query()->where('email', $email)->firstOrFail();
        $role = $identity['role'];

        expect($user->hasSystemRole($role))->toBeTrue()
            ->and($user->name)->toBe($identity['name'])
            ->and(Hash::check(UserFactory::DEMO_PASSWORD, $user->password))->toBeTrue();

        if ($role === SystemRole::Superadmin) {
            expect($user->canAccessOrganization($organization))->toBeTrue()
                ->and($user->canAccessBranch($branch, $organization))->toBeTrue()
                ->and(OrganizationUser::query()
                    ->where('organization_id', $organization->id)
                    ->where('user_id', $user->id)
                    ->exists())->toBeFalse()
                ->and(BranchUser::query()
                    ->where('branch_id', $branch->id)
                    ->where('user_id', $user->id)
                    ->exists())->toBeFalse();

            continue;
        }

        expect(OrganizationUser::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists())->toBeTrue()
            ->and($user->canAccessOrganization($organization))->toBeTrue();

        $expectedBranchNames = demoBranchAssignments()[$email] ?? [];
        $primaryBranchIsAssigned = in_array($branch->name, $expectedBranchNames, true);

        expect(BranchUser::query()
            ->where('branch_id', $branch->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists())->toBe($primaryBranchIsAssigned)
            ->and($user->canAccessBranch($branch, $organization))->toBe($primaryBranchIsAssigned);
    }

    foreach (demoRestaurantUsers() as $email => $identity) {
        if ($identity['role'] === SystemRole::Superadmin) {
            continue;
        }

        $user = User::query()->where('email', $email)->firstOrFail();

        expect(OrganizationUser::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->whereHas('role', fn ($query) => $query->where('code', $identity['role']->value))
            ->exists())->toBeTrue();

        if (! in_array($branch->name, demoBranchAssignments()[$email], true)) {
            continue;
        }

        expect(BranchUser::query()
            ->where('branch_id', $branch->id)
            ->where('user_id', $user->id)
            ->whereHas('role', fn ($query) => $query->where('code', $identity['role']->value))
            ->exists())->toBeTrue();
    }

    $waiter = User::query()->where('email', 'waiter@demo.test')->firstOrFail();
    expect($waiter->hasPermission(SystemPermission::ViewOrders, $organization))->toBeTrue()
        ->and($waiter->hasPermission(SystemPermission::ConfirmOrders, $organization))->toBeTrue()
        ->and($waiter->hasPermission(SystemPermission::ManageStaff, $organization))->toBeFalse()
        ->and(BranchUser::query()
            ->where('branch_id', $branch->id)
            ->where('user_id', $waiter->id)
            ->exists())->toBeTrue();

    $accountant = User::query()->where('email', 'accountant@demo.test')->firstOrFail();
    expect($accountant->hasPermission(SystemPermission::ViewReports, $organization))->toBeTrue()
        ->and($accountant->hasPermission(SystemPermission::ViewPayments, $organization))->toBeTrue()
        ->and($accountant->hasPermission(SystemPermission::ManageMenu, $organization))->toBeFalse();

    $marketer = User::query()->where('email', 'marketer@demo.test')->firstOrFail();
    expect($marketer->hasPermission(SystemPermission::ManageMenu, $organization))->toBeTrue()
        ->and($marketer->hasPermission(SystemPermission::ManagePayments, $organization))->toBeFalse();

    $qrCode = QrCode::query()
        ->whereIn('service_point_id', $servicePointIds)
        ->where('status', QrCodeStatus::Active->value)
        ->oldest('id')
        ->firstOrFail();

    $this->get(route('public.qr.show', ['token' => $qrCode->public_token]))
        ->assertSuccessful()
        ->assertSee('Bella Pizza')
        ->assertSee('Bella Pizza Old Town');
});

test('demo restaurant seeder provides representative operational workflows', function () {
    $this->seed(DemoRestaurantSeeder::class);

    $organization = Organization::query()
        ->where('name', 'Demo Food Group')
        ->firstOrFail();
    $branch = Branch::query()
        ->where('organization_id', $organization->id)
        ->where('name', 'Bella Pizza Old Town')
        ->firstOrFail();

    expect(OrganizationSubscription::query()->where('organization_id', $organization->id)->count())->toBe(1)
        ->and(TableSession::query()->where('branch_id', $branch->id)->where('status', TableSessionStatus::Active->value)->count())->toBeGreaterThanOrEqual(2)
        ->and(TableSession::query()->where('branch_id', $branch->id)->where('status', TableSessionStatus::PaymentRequested->value)->count())->toBeGreaterThanOrEqual(1)
        ->and(TableSession::query()->where('branch_id', $branch->id)->where('status', TableSessionStatus::Closed->value)->count())->toBeGreaterThanOrEqual(1)
        ->and(TableSessionGuest::query()->whereHas('tableSession', fn ($query) => $query->where('branch_id', $branch->id))->count())->toBeGreaterThanOrEqual(5)
        ->and(DraftOrder::query()->whereHas('tableSession', fn ($query) => $query->where('branch_id', $branch->id))->where('status', DraftOrderStatus::SentToWaiter->value)->count())->toBeGreaterThanOrEqual(1)
        ->and(Order::query()->where('branch_id', $branch->id)->where('status', OrderStatus::InProgress->value)->count())->toBeGreaterThanOrEqual(1)
        ->and(Order::query()->where('branch_id', $branch->id)->where('status', OrderStatus::PaymentRequested->value)->count())->toBeGreaterThanOrEqual(1)
        ->and(Order::query()->where('branch_id', $branch->id)->where('status', OrderStatus::Closed->value)->count())->toBeGreaterThanOrEqual(1)
        ->and(OrderItem::query()->whereHas('order', fn ($query) => $query->where('branch_id', $branch->id))->count())->toBeGreaterThanOrEqual(25)
        ->and(KitchenTicketItem::query()->where('status', KitchenTicketItemStatus::New->value)->count())->toBeGreaterThanOrEqual(1)
        ->and(KitchenTicketItem::query()->where('status', KitchenTicketItemStatus::InProgress->value)->count())->toBeGreaterThanOrEqual(1)
        ->and(KitchenTicketItem::query()->where('status', KitchenTicketItemStatus::Ready->value)->count())->toBeGreaterThanOrEqual(1)
        ->and(WaiterCall::query()->where('branch_id', $branch->id)->where('status', WaiterCallStatus::Pending->value)->count())->toBeGreaterThanOrEqual(1)
        ->and(WaiterCall::query()->where('branch_id', $branch->id)->where('status', WaiterCallStatus::Handled->value)->count())->toBeGreaterThanOrEqual(1)
        ->and(ManualPayment::query()->where('branch_id', $branch->id)->count())->toBeGreaterThanOrEqual(2);
});

test('demo restaurant seeder creates the complete organization brand branch hierarchy', function () {
    $this->seed(DemoRestaurantSeeder::class);

    $organization = Organization::query()
        ->where('name', 'Demo Food Group')
        ->firstOrFail();

    $brands = Brand::query()
        ->where('organization_id', $organization->id)
        ->with('branches.settings')
        ->orderBy('name')
        ->get();

    expect($brands->pluck('name')->all())->toEqualCanonicalizing([
        'Bella Pizza',
        'Coffee Bar Demo',
        'Sushi Master',
    ]);

    $branches = Branch::query()
        ->where('organization_id', $organization->id)
        ->with(['brand', 'settings'])
        ->orderBy('name')
        ->get()
        ->keyBy('name');

    expect($branches->keys()->all())->toEqualCanonicalizing(array_keys(demoBranchProfiles()));

    foreach (demoBranchProfiles() as $branchName => $profile) {
        $branch = $branches->get($branchName);

        expect($branch)->toBeInstanceOf(Branch::class)
            ->and($branch->brand->name)->toBe($profile['brand'])
            ->and($branch->organization_id)->toBe($organization->id)
            ->and($branch->public_name)->toBe($profile['public_name'])
            ->and($branch->address)->toBe($profile['address'])
            ->and($branch->city)->toBe($profile['city'])
            ->and($branch->country)->toBe($profile['country'])
            ->and($branch->timezone)->toBe($profile['timezone'])
            ->and($branch->currency)->toBe($profile['currency'])
            ->and($branch->phone)->toBe($profile['phone'])
            ->and($branch->email)->toBe($profile['email'])
            ->and($branch->website_url)->toBe($profile['website_url'])
            ->and($branch->public_description)->toBe($profile['public_description'])
            ->and($branch->is_active)->toBeTrue()
            ->and($branch->settings)->not->toBeNull()
            ->and($branch->settings->default_language)->toBe('en')
            ->and($branch->settings->default_currency)->toBe($profile['currency']);
    }
});

test('demo staff users are assigned to all or selected demo branches', function () {
    $this->seed(DemoRestaurantSeeder::class);

    $organization = Organization::query()
        ->where('name', 'Demo Food Group')
        ->firstOrFail();
    $branches = Branch::query()
        ->where('organization_id', $organization->id)
        ->orderBy('name')
        ->get()
        ->keyBy('name');

    foreach (demoBranchAssignments() as $email => $branchNames) {
        $user = User::query()->where('email', $email)->firstOrFail();

        expect(demoAssignedBranchNames($organization, $user))->toEqualCanonicalizing($branchNames);

        foreach ($branchNames as $branchName) {
            expect($user->canAccessBranch($branches->get($branchName), $organization))->toBeTrue();
        }
    }

    $waiter = User::query()->where('email', 'waiter@demo.test')->firstOrFail();
    expect($waiter->canAccessBranch($branches->get('Sushi Master Center'), $organization))->toBeFalse()
        ->and($waiter->canAccessBranch($branches->get('Coffee Bar Small Hall'), $organization))->toBeFalse();
});

test('demo restaurant seeder applies complete branch settings to every demo branch', function () {
    $this->seed(DemoRestaurantSeeder::class);

    $organization = Organization::query()
        ->where('name', 'Demo Food Group')
        ->firstOrFail();

    $branches = Branch::query()
        ->where('organization_id', $organization->id)
        ->with('settings')
        ->orderBy('name')
        ->get();

    expect($branches)->toHaveCount(4);

    foreach ($branches as $branch) {
        expect($branch->settings)->toBeInstanceOf(BranchSetting::class);

        foreach (demoBranchSettingValues($branch) as $field => $expectedValue) {
            expect($branch->settings->getAttribute($field))->toBe($expectedValue);
        }
    }

    $this->seed(DemoRestaurantSeeder::class);

    expect(BranchSetting::query()
        ->whereIn('branch_id', $branches->pluck('id'))
        ->count())->toBe(4);
});

test('branch settings have translated labels and descriptions', function () {
    foreach (demoBranchSettingsTranslationLines() as $locale => $lines) {
        foreach (demoBranchSettingTranslationFields() as $field) {
            expect($lines)->toHaveKey("fields.branch_settings.$field.label")
                ->and($lines["fields.branch_settings.$field.label"])->not->toBe('')
                ->and($lines)->toHaveKey("fields.branch_settings.$field.description")
                ->and($lines["fields.branch_settings.$field.description"])->not->toBe('');
        }
    }
});

test('demo restaurant seeder is idempotent', function () {
    $this->seed(DemoRestaurantSeeder::class);
    $this->seed(DemoRestaurantSeeder::class);

    $organization = Organization::query()
        ->where('name', 'Demo Food Group')
        ->firstOrFail();
    $brand = Brand::query()
        ->where('organization_id', $organization->id)
        ->where('name', 'Bella Pizza')
        ->firstOrFail();
    $branch = Branch::query()
        ->where('brand_id', $brand->id)
        ->where('name', 'Bella Pizza Old Town')
        ->firstOrFail();
    $menu = Menu::query()
        ->where('branch_id', $branch->id)
        ->where('name', 'Bella Pizza Demo Menu')
        ->firstOrFail();
    $servicePointIds = ServicePoint::query()
        ->where('branch_id', $branch->id)
        ->orderBy('id')
        ->pluck('id');

    expect(Organization::query()->where('name', 'Demo Food Group')->count())->toBe(1)
        ->and(Brand::query()->where('organization_id', $organization->id)->where('name', 'Bella Pizza')->count())->toBe(1)
        ->and(Brand::query()->where('organization_id', $organization->id)->count())->toBe(3)
        ->and(Branch::query()->where('organization_id', $organization->id)->count())->toBe(4)
        ->and(Branch::query()->where('brand_id', $brand->id)->where('name', 'Bella Pizza Old Town')->count())->toBe(1)
        ->and(AreaNode::query()->where('branch_id', $branch->id)->count())->toBe(3)
        ->and(ServicePoint::query()->where('branch_id', $branch->id)->count())->toBe(7)
        ->and(QrCode::query()
            ->whereIn('service_point_id', $servicePointIds)
            ->where('status', QrCodeStatus::Active->value)
            ->count())->toBe(7)
        ->and(Menu::query()->where('branch_id', $branch->id)->where('name', 'Bella Pizza Demo Menu')->count())->toBe(1)
        ->and(MenuCategory::query()->where('menu_id', $menu->id)->count())->toBe(3)
        ->and(MenuItem::query()->where('menu_id', $menu->id)->count())->toBe(7)
        ->and(User::query()->whereIn('email', array_keys(demoRestaurantUsers()))->count())->toBe(count(demoRestaurantUsers()))
        ->and(OrganizationUser::query()->where('organization_id', $organization->id)->count())->toBe(count(demoRestaurantUsers()) - 1)
        ->and(BranchUser::query()->where('organization_id', $organization->id)->count())->toBe(demoExpectedBranchAssignmentCount());
});

/**
 * @return array<string, array{name: string, role: SystemRole}>
 */
function demoRestaurantUsers(): array
{
    return [
        'superadmin@demo.test' => ['name' => 'Demo Superadmin', 'role' => SystemRole::Superadmin],
        'owner@demo.test' => ['name' => 'Demo Owner', 'role' => SystemRole::Owner],
        'director@demo.test' => ['name' => 'Demo Director', 'role' => SystemRole::Director],
        'admin@demo.test' => ['name' => 'Demo Restaurant Admin', 'role' => SystemRole::RestaurantAdmin],
        'manager@demo.test' => ['name' => 'Demo Shift Manager', 'role' => SystemRole::ShiftManager],
        'waiter@demo.test' => ['name' => 'Demo Waiter', 'role' => SystemRole::Waiter],
        'chef@demo.test' => ['name' => 'Demo Head Chef', 'role' => SystemRole::HeadChef],
        'cook@demo.test' => ['name' => 'Demo Cook', 'role' => SystemRole::Cook],
        'bartender@demo.test' => ['name' => 'Demo Bartender', 'role' => SystemRole::Bartender],
        'cashier@demo.test' => ['name' => 'Demo Cashier', 'role' => SystemRole::Cashier],
        'accountant@demo.test' => ['name' => 'Demo Accountant', 'role' => SystemRole::Accountant],
        'marketer@demo.test' => ['name' => 'Demo Marketer', 'role' => SystemRole::Marketer],
    ];
}

/**
 * @return array<string, array{
 *     brand: string,
 *     public_name: string,
 *     address: string,
 *     city: string,
 *     country: string,
 *     timezone: string,
 *     currency: string,
 *     phone: string,
 *     email: string,
 *     website_url: string,
 *     public_description: string
 * }>
 */
function demoBranchProfiles(): array
{
    return [
        'Bella Pizza Old Town' => [
            'brand' => 'Bella Pizza',
            'public_name' => 'Bella Pizza Old Town',
            'address' => 'Pilies g. 10',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
            'timezone' => 'Europe/Vilnius',
            'currency' => 'EUR',
            'phone' => '+370 600 10001',
            'email' => 'old-town@bella-pizza.demo.test',
            'website_url' => 'https://bella-pizza.demo.test/old-town',
            'public_description' => 'Classic pizza restaurant in the demo old town branch.',
        ],
        'Bella Pizza Terrace' => [
            'brand' => 'Bella Pizza',
            'public_name' => 'Bella Pizza Terrace',
            'address' => 'Gedimino pr. 20',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
            'timezone' => 'Europe/Vilnius',
            'currency' => 'EUR',
            'phone' => '+370 600 10002',
            'email' => 'terrace@bella-pizza.demo.test',
            'website_url' => 'https://bella-pizza.demo.test/terrace',
            'public_description' => 'Open-air pizza terrace for QR ordering and table service checks.',
        ],
        'Sushi Master Center' => [
            'brand' => 'Sushi Master',
            'public_name' => 'Sushi Master Center',
            'address' => 'Konstitucijos pr. 12',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
            'timezone' => 'Europe/Vilnius',
            'currency' => 'EUR',
            'phone' => '+370 600 20001',
            'email' => 'center@sushi-master.demo.test',
            'website_url' => 'https://sushi-master.demo.test/center',
            'public_description' => 'Compact sushi branch for kitchen department and pickup flow checks.',
        ],
        'Coffee Bar Small Hall' => [
            'brand' => 'Coffee Bar Demo',
            'public_name' => 'Coffee Bar Small Hall',
            'address' => 'Vokieciu g. 5',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
            'timezone' => 'Europe/Vilnius',
            'currency' => 'EUR',
            'phone' => '+370 600 30001',
            'email' => 'small-hall@coffee-bar.demo.test',
            'website_url' => 'https://coffee-bar.demo.test/small-hall',
            'public_description' => 'Small coffee bar branch for bar seats and quick payment checks.',
        ],
    ];
}

/**
 * @return array<string, list<string>>
 */
function demoBranchAssignments(): array
{
    $allBranches = array_keys(demoBranchProfiles());

    return [
        'owner@demo.test' => $allBranches,
        'director@demo.test' => $allBranches,
        'admin@demo.test' => $allBranches,
        'manager@demo.test' => $allBranches,
        'waiter@demo.test' => ['Bella Pizza Old Town', 'Bella Pizza Terrace'],
        'chef@demo.test' => ['Bella Pizza Old Town', 'Bella Pizza Terrace', 'Sushi Master Center'],
        'cook@demo.test' => ['Bella Pizza Old Town', 'Sushi Master Center'],
        'bartender@demo.test' => ['Bella Pizza Terrace', 'Coffee Bar Small Hall'],
        'cashier@demo.test' => ['Bella Pizza Old Town', 'Coffee Bar Small Hall'],
        'accountant@demo.test' => $allBranches,
        'marketer@demo.test' => $allBranches,
    ];
}

function demoExpectedBranchAssignmentCount(): int
{
    return collect(demoBranchAssignments())->sum(fn (array $branchNames): int => count($branchNames));
}

/**
 * @return array<string, mixed>
 */
function demoBranchSettingValues(Branch $branch): array
{
    $values = [
        'allow_guest_created_sessions' => true,
        'allow_waiter_opened_sessions' => true,
        'guest_join_requires_approval' => true,
        'allow_guest_invite_links' => true,
        'require_waiter_confirmation_for_orders' => true,
        'polling_interval_seconds' => 1,
        'inactivity_warning_minutes' => 45,
        'pending_session_expire_minutes' => 30,
        'default_language' => 'en',
        'default_currency' => $branch->currency,
        'service_charge_enabled' => false,
        'service_charge_percent' => '0.00',
        'tips_enabled' => false,
        'order_flow_mode' => BranchOrderFlowMode::WaiterConfirmation,
        'service_modes' => BranchServiceMode::defaultValues(),
    ];

    foreach (demoOptionalBranchSettingValues() as $field => $value) {
        if (Schema::hasColumn('branch_settings', $field)) {
            $values[$field] = $value;
        }
    }

    return $values;
}

/**
 * @return array<string, mixed>
 */
function demoOptionalBranchSettingValues(): array
{
    return [
        'allow_guest_bill_request' => true,
        'allow_guest_waiter_call' => true,
        'allow_repeat_orders_before_payment_request' => true,
        'manual_payment_only' => true,
    ];
}

/**
 * @return list<string>
 */
function demoBranchSettingTranslationFields(): array
{
    return [
        'allow_guest_created_sessions',
        'allow_waiter_opened_sessions',
        'guest_join_requires_approval',
        'allow_guest_invite_links',
        'require_waiter_confirmation_for_orders',
        'polling_interval_seconds',
        'inactivity_warning_minutes',
        'pending_session_expire_minutes',
        'default_language',
        'default_currency',
        'allow_guest_bill_request',
        'allow_guest_waiter_call',
        'allow_repeat_orders_before_payment_request',
        'manual_payment_only',
        'service_charge_enabled',
        'service_charge_percent',
        'tips_enabled',
        'order_flow_mode',
        'service_modes',
    ];
}

/**
 * @return array<string, array<string, string>>
 */
function demoBranchSettingsTranslationLines(): array
{
    return collect(['en', 'lt', 'ru'])
        ->mapWithKeys(function (string $locale): array {
            $path = base_path("lang/$locale.json");
            $lines = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

            return [$locale => $lines];
        })
        ->all();
}

/**
 * @return list<string>
 */
function demoAssignedBranchNames(Organization $organization, User $user): array
{
    return BranchUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $user->id)
        ->with('branch:id,name')
        ->get()
        ->map(fn (BranchUser $assignment): string => $assignment->branch->name)
        ->all();
}
