<?php

use App\Actions\QrCodes\StoreQrCodeImageAction;
use App\Enums\BranchOrderFlowMode;
use App\Enums\BranchServiceMode;
use App\Enums\DraftOrderStatus;
use App\Enums\InvitationStatus;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\OrderStatus;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\ServicePointType;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionStatus;
use App\Enums\WaiterCallStatus;
use App\Models\AreaNode;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\Invitation;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\ManualPayment;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuCategoryTranslation;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use App\Models\MenuItemVariant;
use App\Models\MenuItemVariantTranslation;
use App\Models\MenuTranslation;
use App\Models\ModifierGroupTranslation;
use App\Models\ModifierOptionTranslation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\OrganizationUser;
use App\Models\QrCode;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use App\Models\WaiterCall;
use App\Services\QrCodeSvgRenderer;
use App\Support\DemoLogin\DemoAccountCatalog;
use Database\Seeders\DemoRestaurantSeeder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->publicStorageDiskName = 'public-'.getmypid().'-'.Str::uuid()->toString();

    Storage::set('public', Storage::fake($this->publicStorageDiskName));
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory(
        storage_path('framework/testing/disks/'.$this->publicStorageDiskName),
    );
});

test('demo account catalogue matches the seeded identity contract', function (): void {
    $catalogue = collect(DemoAccountCatalog::accounts())
        ->mapWithKeys(fn (array $account): array => [
            $account['email'] => [
                'name' => $account['name'],
                'role' => $account['role'],
            ],
        ])
        ->all();

    expect($catalogue)->toBe(demoRestaurantUsers());
});

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
        ->and(AreaNode::query()->where('branch_id', $branch->id)->whereIn('icon', ['layout-grid', 'martini'])->doesntExist())->toBeTrue()
        ->and(ServicePoint::query()->where('branch_id', $branch->id)->count())->toBe(7)
        ->and(ServicePoint::query()->where('branch_id', $branch->id)->whereIn('icon', ['square', 'martini'])->doesntExist())->toBeTrue()
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
        ->and(MenuCategory::query()->where('menu_id', $menu->id)->whereIn('icon', ['pizza', 'cup-soda', 'cake-slice'])->doesntExist())->toBeTrue()
        ->and(MenuItem::query()->where('menu_id', $menu->id)->count())->toBe(7)
        ->and(MenuItem::query()->where('menu_id', $menu->id)->get()->contains(fn (MenuItem $item): bool => $item->allergens !== []))->toBeTrue()
        ->and(MenuItem::query()->where('menu_id', $menu->id)->get()->contains(fn (MenuItem $item): bool => $item->dietary_labels !== []))->toBeTrue()
        ->and(MenuItemVariant::query()->whereHas('item', fn ($query) => $query->where('menu_id', $menu->id))->count())->toBe(11)
        ->and(MenuItemVariantTranslation::query()
            ->whereHas('variant.item', fn ($query) => $query->where('menu_id', $menu->id))
            ->count())->toBe(33);

    foreach (demoRestaurantUsers() as $email => $identity) {
        $user = User::query()->where('email', $email)->firstOrFail();
        $role = $identity['role'];

        expect($user->hasSystemRole($role))->toBeTrue()
            ->and($user->name)->toBe($identity['name']);

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

test('demo restaurant seeder creates an idempotent ready qr image for every service point', function () {
    $this->seed(DemoRestaurantSeeder::class);

    $qrCodes = QrCode::query()
        ->select(['id', 'service_point_id', 'public_token'])
        ->orderBy('service_point_id')
        ->get();
    $storeQrCodeImage = app(StoreQrCodeImageAction::class);
    $expectedFiles = $qrCodes
        ->map(fn (QrCode $qrCode): string => $storeQrCodeImage->pathFor($qrCode))
        ->all();

    expect($qrCodes)->toHaveCount(24)
        ->and(Storage::disk('public')->allFiles('qr'))->toEqualCanonicalizing($expectedFiles);

    $firstHashes = [];

    foreach ($qrCodes as $qrCode) {
        $path = $storeQrCodeImage->pathFor($qrCode);
        $expectedSvg = app(QrCodeSvgRenderer::class)->render(
            route('public.qr.show', ['token' => $qrCode->public_token]),
        );
        $svg = Storage::disk('public')->get($path);

        expect($path)->not->toContain($qrCode->public_token)
            ->and($svg)->toBe($expectedSvg)
            ->and($svg)->toContain('<svg')
            ->and($svg)->not->toContain('<script')
            ->and($svg)->not->toContain('<foreignObject');

        $firstHashes[$path] = hash('sha256', $svg);
    }

    $this->seed(DemoRestaurantSeeder::class);

    expect(Storage::disk('public')->allFiles('qr'))->toEqualCanonicalizing($expectedFiles);

    foreach ($firstHashes as $path => $hash) {
        expect(hash('sha256', Storage::disk('public')->get($path)))->toBe($hash);
    }
});

test('demo restaurant seeder creates several isolated companies with permanent qr files for every table', function (): void {
    $this->seed(DemoRestaurantSeeder::class);

    $organizations = Organization::query()
        ->select(['id', 'name'])
        ->withCount(['brands', 'branches'])
        ->orderBy('name')
        ->get();
    $tables = ServicePoint::query()
        ->select(['id', 'branch_id', 'type'])
        ->where('type', ServicePointType::Table->value)
        ->with('activeQrCode:id,service_point_id,public_token')
        ->orderBy('id')
        ->get();
    $storeQrCodeImage = app(StoreQrCodeImageAction::class);

    expect($organizations)->toHaveCount(3)
        ->and($organizations->pluck('brands_count')->min())->toBeGreaterThanOrEqual(1)
        ->and($organizations->pluck('branches_count')->min())->toBeGreaterThanOrEqual(1)
        ->and($tables)->not->toBeEmpty();

    foreach ($tables as $table) {
        expect($table->activeQrCode)->toBeInstanceOf(QrCode::class);
        Storage::disk('public')->assertExists($storeQrCodeImage->pathFor($table->activeQrCode));
    }
});

test('demo restaurant seeder covers every order draft and invitation lifecycle status', function (): void {
    $this->seed(DemoRestaurantSeeder::class);

    expect(Order::query()->distinct()->orderBy('status')->pluck('status')->all())
        ->toEqualCanonicalizing(OrderStatus::cases())
        ->and(DraftOrder::query()->distinct()->orderBy('status')->pluck('status')->all())
        ->toEqualCanonicalizing(DraftOrderStatus::cases())
        ->and(Invitation::query()->distinct()->orderBy('status')->pluck('status')->all())
        ->toEqualCanonicalizing(InvitationStatus::cases());
});

test('demo restaurant seeder never replaces an existing identity password', function (): void {
    $identity = DemoAccountCatalog::forRole(SystemRole::Waiter);
    $user = User::factory()->create([
        'name' => 'Existing Demo Waiter',
        'email' => $identity['email'],
        'password' => 'existing-local-password',
    ]);
    $passwordHash = $user->password;

    $this->seed(DemoRestaurantSeeder::class);

    expect($user->refresh()->password)->toBe($passwordHash)
        ->and(Hash::check('existing-local-password', $user->password))->toBeTrue();
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
        ->and(DraftOrderItem::query()->whereNotNull('menu_item_variant_id')->exists())->toBeTrue()
        ->and(OrderItem::query()->whereNotNull('menu_item_variant_id')->exists())->toBeTrue()
        ->and(KitchenTicketItem::query()->where('status', KitchenTicketItemStatus::New->value)->count())->toBeGreaterThanOrEqual(1)
        ->and(KitchenTicketItem::query()->where('status', KitchenTicketItemStatus::InProgress->value)->count())->toBeGreaterThanOrEqual(1)
        ->and(KitchenTicketItem::query()->where('status', KitchenTicketItemStatus::Ready->value)->count())->toBeGreaterThanOrEqual(1)
        ->and(WaiterCall::query()->where('branch_id', $branch->id)->where('status', WaiterCallStatus::Pending->value)->count())->toBeGreaterThanOrEqual(1)
        ->and(WaiterCall::query()->where('branch_id', $branch->id)->where('status', WaiterCallStatus::Handled->value)->count())->toBeGreaterThanOrEqual(1)
        ->and(ManualPayment::query()->where('branch_id', $branch->id)->count())->toBeGreaterThanOrEqual(2);

    $openSessions = TableSession::query()
        ->select(['id', 'service_point_id', 'status'])
        ->with('servicePoint:id,status')
        ->whereIn('status', TableSessionStatus::guestViewableValues())
        ->orderBy('id')
        ->get();

    foreach ($openSessions as $openSession) {
        $expectedServicePointStatus = match ($openSession->status) {
            TableSessionStatus::Pending => ServicePointStatus::WaitingWaiter,
            TableSessionStatus::Active => ServicePointStatus::Occupied,
            TableSessionStatus::WaitingWaiterConfirmation => ServicePointStatus::HasNewOrder,
            TableSessionStatus::PaymentRequested => ServicePointStatus::PaymentRequested,
            TableSessionStatus::Paid => ServicePointStatus::Paid,
            TableSessionStatus::Closed,
            TableSessionStatus::Cancelled => ServicePointStatus::Free,
        };

        expect($openSession->servicePoint->status)->toBe($expectedServicePointStatus);
    }
});

test('demo restaurant seeder creates a maximum operational graph for every seeded branch', function (): void {
    $this->seed(DemoRestaurantSeeder::class);

    $organizations = Organization::query()
        ->select(['id'])
        ->where('name', DemoRestaurantSeeder::ORGANIZATION_NAME)
        ->withCount(['memberships', 'brands', 'branches', 'servicePoints', 'orders'])
        ->orderBy('id')
        ->get();
    $organizationIds = $organizations->pluck('id');
    $branches = Branch::query()
        ->select(['id', 'organization_id'])
        ->whereIn('organization_id', $organizationIds)
        ->withCount([
            'staffAssignments',
            'areaNodes',
            'servicePoints',
            'menus',
            'tableSessions',
            'orders',
            'kitchenTickets',
            'orderStatusLogs',
        ])
        ->orderBy('id')
        ->get();
    $branchIds = $branches->pluck('id');
    $branchesWithBarMenuItems = MenuItem::query()
        ->select(['id', 'menu_id', 'kitchen_department_id'])
        ->whereHas('menu', fn ($query) => $query->whereIn('branch_id', $branchIds))
        ->whereHas('kitchenDepartment', fn ($query) => $query->where('type', KitchenDepartmentType::Bar->value))
        ->with('menu:id,branch_id')
        ->get()
        ->pluck('menu.branch_id')
        ->unique()
        ->values();
    $branchesWithQrCodes = QrCode::query()
        ->select(['id', 'service_point_id'])
        ->whereHas('servicePoint', fn ($query) => $query->whereIn('branch_id', $branchIds))
        ->with('servicePoint:id,branch_id')
        ->get()
        ->pluck('servicePoint.branch_id')
        ->unique()
        ->values();
    $branchesWithPayments = ManualPayment::query()
        ->whereIn('branch_id', $branchIds)
        ->pluck('branch_id')
        ->unique()
        ->values();
    $branchesWithAuditHistory = AuditLog::query()
        ->whereIn('branch_id', $branchIds)
        ->pluck('branch_id')
        ->unique()
        ->values();
    $branchesWithOrderHistory = OrderStatusLog::query()
        ->whereIn('branch_id', $branchIds)
        ->pluck('branch_id')
        ->unique()
        ->values();
    $barDepartments = KitchenDepartment::query()
        ->select(['id', 'branch_id', 'type'])
        ->whereIn('branch_id', $branchIds)
        ->where('type', KitchenDepartmentType::Bar->value)
        ->with(['kitchenTickets.items:id,kitchen_ticket_id,status'])
        ->orderBy('id')
        ->get();

    expect($organizations)->not->toBeEmpty()
        ->and($branches)->not->toBeEmpty()
        ->and($branchesWithBarMenuItems->all())->toEqualCanonicalizing($branchIds->all())
        ->and($branchesWithQrCodes->all())->toEqualCanonicalizing($branchIds->all())
        ->and($branchesWithPayments->all())->toEqualCanonicalizing($branchIds->all())
        ->and($branchesWithAuditHistory->all())->toEqualCanonicalizing($branchIds->all())
        ->and($branchesWithOrderHistory->all())->toEqualCanonicalizing($branchIds->all())
        ->and($barDepartments->pluck('branch_id')->unique()->values()->all())
        ->toEqualCanonicalizing($branchIds->all());

    foreach ($organizations as $organization) {
        expect($organization->memberships_count)->toBeGreaterThan(0)
            ->and($organization->brands_count)->toBeGreaterThan(0)
            ->and($organization->branches_count)->toBeGreaterThan(0)
            ->and($organization->service_points_count)->toBeGreaterThan(0)
            ->and($organization->orders_count)->toBeGreaterThan(0);
    }

    foreach ($branches as $branch) {
        expect($branch->staff_assignments_count)->toBeGreaterThan(0)
            ->and($branch->area_nodes_count)->toBeGreaterThan(0)
            ->and($branch->service_points_count)->toBeGreaterThan(0)
            ->and($branch->menus_count)->toBeGreaterThan(0)
            ->and($branch->table_sessions_count)->toBeGreaterThan(0)
            ->and($branch->orders_count)->toBeGreaterThan(0)
            ->and($branch->kitchen_tickets_count)->toBeGreaterThan(0)
            ->and($branch->order_status_logs_count)->toBeGreaterThan(0);
    }

    foreach ($barDepartments as $barDepartment) {
        $statuses = $barDepartment->kitchenTickets
            ->flatMap(fn ($ticket) => $ticket->items)
            ->pluck('status');

        expect($statuses)->toContain(
            KitchenTicketItemStatus::New,
            KitchenTicketItemStatus::InProgress,
            KitchenTicketItemStatus::Ready,
        );
    }
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
            ->and($branch->is_active)->toBe($branchName !== 'Coffee Bar Small Hall')
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

test('branch settings view translation keys exist in every locale', function () {
    $source = file_get_contents(resource_path('views/livewire/organizations/brands/branches/settings.blade.php'));
    preg_match_all('/__\(\s*([\'"])([a-z][a-z0-9_]*(?:\.[a-z0-9_]+)+)\1/u', $source, $matches);
    $keys = array_values(array_unique($matches[2]));

    foreach (demoBranchSettingsTranslationLines() as $locale => $lines) {
        foreach ($keys as $key) {
            expect($lines)->toHaveKey($key)
                ->and($lines[$key])->not->toBe('', "$locale translation [$key] is empty.");
        }
    }
});

test('demo restaurant seeder is idempotent', function () {
    $this->seed(DemoRestaurantSeeder::class);

    $firstCounts = demoSeedGraphCounts();

    expect($firstCounts)->toBe([
        'roles' => count(SystemRole::cases()),
        'organizations' => 3,
        'brands' => 5,
        'branches' => 6,
        'areas' => 12,
        'service_points' => 24,
        'qr_codes' => 24,
        'menus' => 6,
        'menu_translations' => 18,
        'menu_categories' => 11,
        'menu_category_translations' => 33,
        'menu_items' => 24,
        'menu_item_translations' => 72,
        'menu_item_variants' => 35,
        'menu_item_variant_translations' => 105,
        'modifier_group_translations' => 24,
        'modifier_option_translations' => 72,
        'table_sessions' => 20,
        'draft_orders' => 24,
        'orders' => 19,
        'order_items' => 49,
        'kitchen_tickets' => 13,
        'kitchen_ticket_items' => 23,
        'manual_payments' => 5,
        'order_status_logs' => 12,
        'audit_logs' => 4,
    ]);

    $firstOrderIds = Order::query()
        ->whereNotNull('metadata')
        ->orderBy('id')
        ->get(['id', 'metadata'])
        ->mapWithKeys(fn (Order $order): array => [(string) data_get($order->metadata, 'demo_key') => $order->id])
        ->all();
    $firstPaymentIds = ManualPayment::query()
        ->whereNotNull('metadata')
        ->orderBy('id')
        ->get(['id', 'metadata'])
        ->mapWithKeys(fn (ManualPayment $payment): array => [(string) data_get($payment->metadata, 'demo_key') => $payment->id])
        ->all();

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
    $branches = Branch::query()
        ->where('organization_id', $organization->id)
        ->withCount(['areaNodes', 'servicePoints', 'menus', 'orders'])
        ->orderBy('id')
        ->get();
    $branchesWithPayments = ManualPayment::query()
        ->whereIn('branch_id', $branches->pluck('id'))
        ->distinct()
        ->orderBy('branch_id')
        ->pluck('branch_id');

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
        ->and(OrganizationUser::query()->where('organization_id', $organization->id)->count())->toBe(count(demoRestaurantUsers()) + 2)
        ->and(BranchUser::query()->where('organization_id', $organization->id)->count())->toBe(demoExpectedBranchAssignmentCount() + 3)
        ->and(demoSeedGraphCounts())->toBe($firstCounts)
        ->and(Order::query()
            ->whereNotNull('metadata')
            ->orderBy('id')
            ->get(['id', 'metadata'])
            ->mapWithKeys(fn (Order $order): array => [(string) data_get($order->metadata, 'demo_key') => $order->id])
            ->all())->toBe($firstOrderIds)
        ->and(ManualPayment::query()
            ->whereNotNull('metadata')
            ->orderBy('id')
            ->get(['id', 'metadata'])
            ->mapWithKeys(fn (ManualPayment $payment): array => [(string) data_get($payment->metadata, 'demo_key') => $payment->id])
            ->all())->toBe($firstPaymentIds);

    expect($branchesWithPayments->all())->toEqualCanonicalizing($branches->pluck('id')->all());

    foreach ($branches as $seededBranch) {
        expect($seededBranch->area_nodes_count)->toBeGreaterThan(0)
            ->and($seededBranch->service_points_count)->toBeGreaterThan(0)
            ->and($seededBranch->menus_count)->toBeGreaterThan(0)
            ->and($seededBranch->orders_count)->toBeGreaterThan(0);
    }
});

test('demo restaurant seeder restores its soft deleted menu graph without duplicates', function () {
    $this->seed(DemoRestaurantSeeder::class);

    $branch = Branch::query()
        ->where('name', 'Sushi Master Center')
        ->firstOrFail();
    $menu = Menu::query()
        ->where('branch_id', $branch->id)
        ->where('name', 'Sushi Master Demo Menu')
        ->firstOrFail();
    $category = MenuCategory::query()
        ->where('menu_id', $menu->id)
        ->firstOrFail();
    $item = MenuItem::query()
        ->where('menu_id', $menu->id)
        ->firstOrFail();

    $item->delete();
    $category->delete();
    $menu->delete();

    $this->seed(DemoRestaurantSeeder::class);

    expect(Menu::query()->whereKey($menu->id)->exists())->toBeTrue()
        ->and(MenuCategory::query()->whereKey($category->id)->exists())->toBeTrue()
        ->and(MenuItem::query()->whereKey($item->id)->exists())->toBeTrue()
        ->and(Menu::withTrashed()
            ->where('branch_id', $branch->id)
            ->where('name', 'Sushi Master Demo Menu')
            ->count())->toBe(1)
        ->and(MenuCategory::withTrashed()
            ->where('menu_id', $menu->id)
            ->where('name', $category->name)
            ->count())->toBe(1)
        ->and(MenuItem::withTrashed()
            ->where('menu_id', $menu->id)
            ->where('name', $item->name)
            ->count())->toBe(1);
});

test('demo restaurant seeder does not claim an operational key from another restaurant', function () {
    $unrelatedServicePoint = ServicePoint::factory()->create();
    $unrelatedSession = TableSession::factory()
        ->forServicePoint($unrelatedServicePoint)
        ->active()
        ->create(['metadata' => ['demo_key' => 'active-draft']]);

    $this->seed(DemoRestaurantSeeder::class);

    expect($unrelatedSession->refresh()->branch_id)->toBe($unrelatedServicePoint->branch_id)
        ->and(TableSession::query()
            ->where('branch_id', '!=', $unrelatedServicePoint->branch_id)
            ->where('metadata->demo_key', 'active-draft')
            ->exists())->toBeTrue();
});

/**
 * @return array<string, int>
 */
function demoSeedGraphCounts(): array
{
    return [
        'roles' => Role::query()->count(),
        'organizations' => Organization::query()->count(),
        'brands' => Brand::query()->count(),
        'branches' => Branch::query()->count(),
        'areas' => AreaNode::query()->count(),
        'service_points' => ServicePoint::query()->count(),
        'qr_codes' => QrCode::query()->count(),
        'menus' => Menu::query()->count(),
        'menu_translations' => MenuTranslation::query()->count(),
        'menu_categories' => MenuCategory::query()->count(),
        'menu_category_translations' => MenuCategoryTranslation::query()->count(),
        'menu_items' => MenuItem::query()->count(),
        'menu_item_translations' => MenuItemTranslation::query()->count(),
        'menu_item_variants' => MenuItemVariant::query()->count(),
        'menu_item_variant_translations' => MenuItemVariantTranslation::query()->count(),
        'modifier_group_translations' => ModifierGroupTranslation::query()->count(),
        'modifier_option_translations' => ModifierOptionTranslation::query()->count(),
        'table_sessions' => TableSession::query()->count(),
        'draft_orders' => DraftOrder::query()->count(),
        'orders' => Order::query()->count(),
        'order_items' => OrderItem::query()->count(),
        'kitchen_tickets' => KitchenTicket::query()->count(),
        'kitchen_ticket_items' => KitchenTicketItem::query()->count(),
        'manual_payments' => ManualPayment::query()->count(),
        'order_status_logs' => OrderStatusLog::query()->count(),
        'audit_logs' => AuditLog::query()->count(),
    ];
}

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
        'service_charge_basis_points' => 0,
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
