<?php

use App\Enums\AreaNodeType;
use App\Enums\DataExportType;
use App\Enums\ManualPaymentMethod;
use App\Enums\ManualPaymentScope;
use App\Enums\MenuStatus;
use App\Enums\OrderStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\ServicePointStatus;
use App\Enums\ServicePointType;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\ManualPayment;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Date;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('data exports require export data permission', function () {
    [$organization, , $branch] = createPrompt76ExportBranches();
    $user = User::factory()->create();

    $organization->users()->syncWithoutDetachingOrFail([
        $user->id => [
            'role_id' => Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail()->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ],
    ]);

    $this->get(route('restaurant.exports.index'))
        ->assertRedirect(route('login'));

    $this->actingAs($user)
        ->get(route('restaurant.exports.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('restaurant.exports.download', [$branch, DataExportType::Orders->value]))
        ->assertForbidden();
});

test('data export page shows only assigned export branches', function () {
    [$organization, $firstBranch, $secondBranch] = createPrompt76ExportBranches();
    $user = User::factory()->create(['name' => 'Branch Exporter']);

    attachPrompt76Exporter($user, $organization, $firstBranch);

    $this->actingAs($user)
        ->get(route('restaurant.exports.index'))
        ->assertOk()
        ->assertSee(__('reports.exports.title'))
        ->assertSee($firstBranch->name)
        ->assertDontSee($secondBranch->name)
        ->assertSee(__('reports.actions.export_type_csv', ['type' => __('reports.orders.title')]))
        ->assertSee(__('reports.actions.export_type_csv', ['type' => __('reports.payments.title')]))
        ->assertSee(__('reports.actions.export_type_csv', ['type' => __('reports.exports.menu')]))
        ->assertSee(__('reports.actions.export_type_csv', ['type' => __('reports.exports.tables')]));

    $this->actingAs($user)
        ->get(route('restaurant.exports.download', [$secondBranch, DataExportType::Orders->value]))
        ->assertForbidden();
});

test('orders csv export includes selected branch orders only', function () {
    [$organization, $branch, $otherBranch] = createPrompt76ExportBranches();
    $user = User::factory()->create(['name' => 'Orders Exporter']);
    attachPrompt76Exporter($user, $organization);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Window table',
            'display_number' => '7',
        ]);
    $otherServicePoint = ServicePoint::factory()
        ->for($otherBranch)
        ->create(['name' => 'Other table']);
    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->create();
    $otherTableSession = TableSession::factory()
        ->forServicePoint($otherServicePoint)
        ->active()
        ->create();
    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create();
    $oldDraftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create();
    $otherDraftOrder = DraftOrder::factory()
        ->for($otherTableSession)
        ->create();
    $order = Order::factory()->create([
        'branch_id' => $branch->id,
        'service_point_id' => $servicePoint->id,
        'table_session_id' => $tableSession->id,
        'draft_order_id' => $draftOrder->id,
        'status' => OrderStatus::Served,
        'confirmed_at' => CarbonImmutable::parse('2026-06-04 10:00:00'),
        'total_price' => '25.00',
        'currency' => 'EUR',
    ]);
    $oldOrder = Order::factory()->create([
        'branch_id' => $branch->id,
        'service_point_id' => $servicePoint->id,
        'table_session_id' => $tableSession->id,
        'draft_order_id' => $oldDraftOrder->id,
        'status' => OrderStatus::Served,
        'confirmed_at' => CarbonImmutable::parse('2026-04-01 10:00:00'),
        'total_price' => '11.00',
        'currency' => 'EUR',
    ]);
    $otherOrder = Order::factory()->create([
        'branch_id' => $otherBranch->id,
        'service_point_id' => $otherServicePoint->id,
        'table_session_id' => $otherTableSession->id,
        'draft_order_id' => $otherDraftOrder->id,
        'status' => OrderStatus::Served,
        'total_price' => '99.00',
    ]);

    OrderItem::factory()
        ->for($order)
        ->create([
            'guest_name' => 'Ana',
            'item_name' => 'Margherita',
            'quantity' => 2,
            'total_price' => '25.00',
        ]);
    OrderItem::factory()
        ->for($oldOrder)
        ->create([
            'guest_name' => 'Ben',
            'item_name' => 'Old branch soup',
            'quantity' => 1,
            'total_price' => '11.00',
        ]);
    OrderItem::factory()
        ->for($otherOrder)
        ->create(['item_name' => 'Other branch steak']);

    Date::setTestNow(CarbonImmutable::parse('2026-06-04 12:34:56'));

    try {
        $response = $this->actingAs($user)
            ->get(route('restaurant.exports.download', [$branch, DataExportType::Orders->value]))
            ->assertOk()
            ->assertDownload('restaurant-menu-orders-branch-'.$branch->id.'-2026-06-04-123456.csv');
    } finally {
        Date::setTestNow();
    }

    $content = $response->streamedContent();

    expect($content)
        ->toContain(csvColumns([
            __('reports.csv.order_id'),
            __('reports.filters.status'),
            __('reports.csv.branch'),
            __('reports.csv.service_point'),
        ]))
        ->toContain(__('reports.statuses.orders.served'))
        ->toContain('Window table #7')
        ->toContain('Ana: Margherita x2 = 25.00')
        ->not->toContain('Old branch soup')
        ->not->toContain('Other branch steak');
});

test('csv exports validate date ranges', function () {
    [$organization, $branch] = createPrompt76ExportBranches();
    $user = User::factory()->create(['name' => 'Date Range Exporter']);
    attachPrompt76Exporter($user, $organization);

    $this->actingAs($user)
        ->from(route('restaurant.exports.index'))
        ->get(route('restaurant.exports.download', [
            'branch' => $branch,
            'export' => DataExportType::Orders->value,
            'date_from' => '2026-01-01',
            'date_to' => '2026-03-10',
        ]))
        ->assertRedirect(route('restaurant.exports.index'))
        ->assertSessionHasErrors('date_to');
});

test('payments menu and tables csv exports stream branch data', function () {
    [$organization, $branch] = createPrompt76ExportBranches();
    $user = User::factory()->create(['name' => 'Full Exporter']);
    attachPrompt76Exporter($user, $organization);
    $area = AreaNode::factory()
        ->for($branch)
        ->create([
            'type' => AreaNodeType::Hall,
            'name' => 'Main Hall',
        ]);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($area, 'areaNode')
        ->create([
            'type' => ServicePointType::Table,
            'name' => 'Table Nine',
            'display_number' => '9',
            'internal_code' => 'SP-EXPORT-9',
            'status' => ServicePointStatus::PaymentRequested,
        ]);
    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->create();
    $recorder = User::factory()->create(['name' => 'Cashier Kate']);

    ManualPayment::factory()->create([
        'branch_id' => $branch->id,
        'service_point_id' => $servicePoint->id,
        'table_session_id' => $tableSession->id,
        'recorded_by_user_id' => $recorder->id,
        'scope' => ManualPaymentScope::Table,
        'payment_method' => ManualPaymentMethod::CardTerminal,
        'amount' => '42.00',
        'currency' => 'EUR',
        'paid_at' => CarbonImmutable::parse('2026-06-04 11:00:00'),
        'note' => 'Terminal approved',
    ]);
    ManualPayment::factory()->create([
        'branch_id' => $branch->id,
        'service_point_id' => $servicePoint->id,
        'table_session_id' => $tableSession->id,
        'recorded_by_user_id' => $recorder->id,
        'scope' => ManualPaymentScope::Table,
        'payment_method' => ManualPaymentMethod::Cash,
        'amount' => '9.00',
        'currency' => 'EUR',
        'paid_at' => CarbonImmutable::parse('2026-04-01 11:00:00'),
        'note' => 'Old cash payment',
    ]);

    $menu = Menu::factory()
        ->for($branch)
        ->create([
            'name' => 'Dinner Menu',
            'status' => MenuStatus::Active,
        ]);
    $category = MenuCategory::factory()
        ->for($menu)
        ->create(['name' => 'Pizza']);
    MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create([
            'name' => 'Pepperoni',
            'description' => 'Tomato and cheese',
            'price' => '13.50',
            'is_available' => true,
        ]);

    Date::setTestNow(CarbonImmutable::parse('2026-06-04 12:34:56'));

    try {
        $paymentContent = $this->actingAs($user)
            ->get(route('restaurant.exports.download', [$branch, DataExportType::Payments->value]))
            ->assertOk()
            ->assertDownload()
            ->streamedContent();
    } finally {
        Date::setTestNow();
    }

    expect($paymentContent)
        ->toContain(csvColumns([
            __('reports.csv.payment_id'),
            __('reports.csv.scope'),
            __('reports.payments.method'),
            __('reports.csv.branch'),
        ]))
        ->toContain(__('ui.payment_methods.card_terminal'))
        ->toContain('Cashier Kate')
        ->toContain('Terminal approved')
        ->not->toContain('Old cash payment');

    $menuContent = $this->actingAs($user)
        ->get(route('restaurant.exports.download', [$branch, DataExportType::Menu->value]))
        ->assertOk()
        ->assertDownload()
        ->streamedContent();

    expect($menuContent)
        ->toContain(csvColumns([
            __('reports.csv.menu_id'),
            __('reports.csv.menu_name'),
            __('reports.csv.menu_status'),
            __('reports.csv.category_id'),
        ]))
        ->toContain('Dinner Menu')
        ->toContain('Pepperoni')
        ->toContain('13.50');

    $tablesContent = $this->actingAs($user)
        ->get(route('restaurant.exports.download', [$branch, DataExportType::ServicePoints->value]))
        ->assertOk()
        ->assertDownload()
        ->streamedContent();

    expect($tablesContent)
        ->toContain(csvColumns([
            __('reports.csv.service_point_id'),
            __('reports.csv.branch'),
            __('reports.csv.area'),
            __('reports.csv.type'),
            __('reports.csv.name'),
        ]))
        ->toContain('Main Hall')
        ->toContain('Table Nine')
        ->toContain('SP-EXPORT-9');
});

/**
 * @param  list<string>  $columns
 */
function csvColumns(array $columns): string
{
    $handle = fopen('php://temp', 'r+');

    expect($handle)->not->toBeFalse();

    fputcsv($handle, $columns);
    rewind($handle);

    $content = stream_get_contents($handle);
    fclose($handle);

    return rtrim((string) $content, "\r\n");
}

function createPrompt76ExportBranches(): array
{
    $organization = Organization::factory()->create(['name' => 'Prompt 76 Group']);
    $brand = Brand::factory()
        ->for($organization)
        ->create(['name' => 'Prompt 76 Brand']);
    $firstBranch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 76 Old Town',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
        ]);
    $secondBranch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 76 Riverside',
            'city' => 'Kaunas',
            'country' => 'Lithuania',
        ]);

    return [$organization, $firstBranch, $secondBranch];
}

function attachPrompt76Exporter(User $user, Organization $organization, ?Branch $branch = null): Role
{
    $role = Role::query()
        ->where('code', SystemRole::Director->value)
        ->firstOrFail();
    $permission = Permission::query()
        ->where('code', SystemPermission::ExportData->value)
        ->firstOrFail();

    $role->permissions()->updateExistingPivot($permission->id, ['enabled' => true]);

    $organization->users()->syncWithoutDetachingOrFail([
        $user->id => [
            'role_id' => $role->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ],
    ]);

    if ($branch instanceof Branch) {
        $branchUser = new BranchUser;
        $branchUser->forceFill([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => OrganizationUserStatus::Active->value,
            'assigned_at' => now(),
            'assigned_by_user_id' => null,
        ])->save();
    }

    return $role;
}
