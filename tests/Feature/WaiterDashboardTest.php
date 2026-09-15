<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Waiter\BuildWaiterDashboardAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\DraftOrderStatus;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionStatus;
use App\Livewire\Waiter\Dashboard as WaiterDashboard;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use App\Models\WaiterCall;
use Database\Seeders\SystemPermissionsSeeder;
use Dom\HTMLDocument;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('waiter dashboard requires authentication', function () {
    $this->get(route('restaurant.waiter.dashboard'))
        ->assertRedirect(route('login'));
});

test('waiter dashboard requires view orders permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('restaurant.waiter.dashboard'))
        ->assertForbidden();
});

test('waiter zone filters expose the selected scope through Flux pressed buttons', function () {
    [$organization] = createPrompt52Branch();
    $waiter = User::factory()->create();
    attachPrompt52Waiter($waiter, $organization);

    $component = Livewire::actingAs($waiter)->test(WaiterDashboard::class);

    foreach (['mine', 'all'] as $scope) {
        $component->call('setZoneScope', $scope)->assertHasNoErrors();
        $document = HTMLDocument::createFromString('<!doctype html><html><body>'.$component->html().'</body></html>');
        $selected = $document->querySelector('[data-zone-scope="'.$scope.'"]');

        expect($selected)->not->toBeNull()
            ->and($selected?->getAttribute('aria-pressed'))->toBe('true')
            ->and($selected?->hasAttribute('data-flux-button'))->toBeTrue();
        expect($document->querySelectorAll('[data-zone-scope][aria-pressed="true"]')->length)->toBe(1);
    }
});

test('waiter dashboard exposes accessible persistent notification sound controls', function () {
    [$organization] = createPrompt52Branch();
    $waiter = User::factory()->create();
    attachPrompt52Waiter($waiter, $organization);

    Livewire::actingAs($waiter)
        ->test(WaiterDashboard::class)
        ->assertSee('data-waiter-sounds', false)
        ->assertSee('data-waiter-sound-controls', false)
        ->assertSee('data-waiter-sound-toggle', false)
        ->assertSee('data-waiter-sound-test', false)
        ->assertSee('aria-pressed="false"', false)
        ->assertSee(__('ui.waiter.dashboard.enable_sounds'))
        ->assertSee(__('ui.waiter.dashboard.test_sound'))
        ->assertDontSee('playNotice()', false)
        ->assertDontSee('new AudioContext', false);
});

test('waiter zone attention badges interpolate their count in every interface locale', function (string $locale, string $expectedLabel): void {
    [$organization, , $branch] = createPrompt52Branch();
    $waiter = User::factory()->create(['locale' => $locale]);
    attachPrompt52Waiter($waiter, $organization);
    $area = AreaNode::factory()->for($branch)->create(['name' => 'Terrace']);

    $servicePoints = ServicePoint::factory()
        ->count(2)
        ->for($branch)
        ->for($area, 'areaNode')
        ->create(['status' => ServicePointStatus::WaitingWaiter]);

    foreach ($servicePoints as $servicePoint) {
        $session = TableSession::factory()->forServicePoint($servicePoint)->active()->create();
        WaiterCall::factory()->forTableSession($session)->create();
    }

    app()->setLocale($locale);

    Livewire::actingAs($waiter)
        ->test(WaiterDashboard::class)
        ->assertSet('waiterCallCount', 2)
        ->assertSee($expectedLabel)
        ->assertDontSee(':count')
        ->assertDontSee('2 '.$expectedLabel);
})->with([
    'English' => ['en', 'Needs attention: 2'],
    'Lithuanian' => ['lt', 'Reikia dėmesio: 2'],
    'Russian' => ['ru', 'Требуют внимания: 2'],
]);

test('waiter dashboard shows branch service points sessions and sent drafts', function () {
    [$organization, $brand, $branch] = createPrompt52Branch();
    $waiter = User::factory()->create();
    attachPrompt52Waiter($waiter, $organization);

    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Window table',
            'display_number' => '12',
            'status' => ServicePointStatus::HasNewOrder,
        ]);

    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->create();

    $guest = TableSessionGuest::factory()
        ->for($tableSession)
        ->create(['guest_name' => 'Anna']);

    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create([
            'status' => DraftOrderStatus::SentToWaiter,
            'sent_to_waiter_at' => now(),
            'sent_by_guest_id' => $guest->id,
        ]);

    DraftOrderItem::factory()
        ->for($draftOrder, 'draftOrder')
        ->for($guest, 'guest')
        ->create([
            'menu_item_id' => null,
            'item_name' => 'Pasta',
            'quantity' => 2,
            'unit_price_cents' => 975,
            'total_price_cents' => 1950,
        ]);

    Livewire::actingAs($waiter)
        ->test(WaiterDashboard::class)
        ->assertSet('servicePointCount', 1)
        ->assertSet('activeSessionCount', 1)
        ->assertSet('newDraftCount', 1)
        ->assertSet('branches.0.has_activity', true)
        ->assertSee($organization->name)
        ->assertSee($brand->name)
        ->assertSee($branch->name)
        ->assertSee('Window table')
        ->assertDontSee('truncate', false)
        ->assertSee('Has new order')
        ->assertSee('Waiting review')
        ->assertSee('Anna')
        ->assertSee('€19.50');

    $this->actingAs($waiter)
        ->get(route('restaurant.waiter.dashboard'))
        ->assertOk()
        ->assertSee('wire:poll.visible.1s="refreshDashboard"', false);
});

test('waiter inactivity uses the loaded selected branch with a constant query budget', function (int $sessionsPerBranch): void {
    $this->freezeTime();
    [$organization, $brand, $branch] = createPrompt52Branch();
    $otherBranch = Branch::factory()->for($organization)->for($brand)->create(['timezone' => 'Asia/Tokyo']);
    BranchSetting::factory()->for($branch)->create(['inactivity_warning_minutes' => 90]);
    $waiter = User::factory()->create();
    attachPrompt52Waiter($waiter, $organization);

    foreach ([$branch, $otherBranch] as $sessionBranch) {
        ServicePoint::factory()->count($sessionsPerBranch)->for($sessionBranch, 'branch')
            ->has(TableSession::factory()->active()->state([
                'branch_id' => $sessionBranch->id,
                'started_at' => now()->subMinutes(61),
                'created_at' => now()->subMinutes(61),
                'updated_at' => now()->subMinutes(61),
            ]), 'tableSessions')
            ->create();
    }

    $queries = countDatabaseQueries(function () use ($waiter, $branch, $sessionsPerBranch): void {
        $payload = app(BuildWaiterDashboardAction::class)->handle($waiter, selectedBranchId: $branch->id);

        expect($payload['branches'])->toHaveCount(1)
            ->and($payload['active_session_count'])->toBe($sessionsPerBranch);

        foreach ($payload['branches'] as $branchPayload) {
            $warningMinutes = $branchPayload['id'] === $branch->id ? 90 : 45;

            foreach ($branchPayload['service_points'] as $servicePoint) {
                expect($servicePoint['sessions'])->toHaveCount(1);
                $inactivity = $servicePoint['sessions'][0]['inactivity'];

                expect($inactivity['minutes_inactive'])->toBe(61)
                    ->and($inactivity['warning_minutes'])->toBe($warningMinutes)
                    ->and($inactivity['should_warn'])->toBe($warningMinutes === 45);
            }
        }
    });

    expect($queries)->toBeLessThanOrEqual(35);
})->with([2, 20]);

test('waiter dashboard summarizes drafts without hydrating their items', function (array $prices, string $expectedTotal): void {
    [$organization, , $branch] = createPrompt52Branch();
    $waiter = User::factory()->create();
    attachPrompt52Waiter($waiter, $organization);
    $servicePoint = ServicePoint::factory()->for($branch)->create();
    $tableSession = TableSession::factory()->forServicePoint($servicePoint)->active()->create();
    $guest = TableSessionGuest::factory()->for($tableSession)->create(['guest_name' => 'Anna']);
    $draftOrder = DraftOrder::factory()->for($tableSession)->create([
        'status' => DraftOrderStatus::SentToWaiter,
        'sent_to_waiter_at' => now(),
        'sent_by_guest_id' => $guest->id,
    ]);
    DraftOrderItem::factory()->for($draftOrder)->for($guest, 'guest')->createMany(
        array_map(fn (int $price): array => [
            'menu_item_id' => null,
            'quantity' => 1,
            'unit_price_cents' => $price,
            'total_price_cents' => $price,
        ], $prices),
    );

    $hydratedItems = 0;
    DraftOrderItem::retrieved(function () use (&$hydratedItems): void {
        $hydratedItems++;
    });

    $queryCount = countDatabaseQueries(function () use ($waiter, $draftOrder, $prices, $expectedTotal): void {
        $payload = app(BuildWaiterDashboardAction::class)->handle($waiter);
        $draft = $payload['branches'][0]['drafts'][0];

        expect($draft['id'])->toBe($draftOrder->id)
            ->and($draft['items_count'])->toBe(count($prices))
            ->and($draft['total'])->toBe($expectedTotal)
            ->and($draft['sent_by_guest_name'])->toBe('Anna')
            ->and($payload['branches'][0]['service_points'][0]['sessions'][0]['draft'])->toBe($draft);
    });

    expect($queryCount)->toBeLessThanOrEqual(38)
        ->and($hydratedItems)->toBe(0);
})->with([
    'empty draft' => [[], '€0.00'],
    'mixed cent amounts' => [[29, 70, 1201], '€13.00'],
    'larger draft' => [array_fill(0, 40, 29), '€11.60'],
]);

test('waiter dashboard exposes a freshly authorized bounded desktop table preview with a mobile detail fallback', function () {
    [$organization, , $branch] = createPrompt52Branch();
    $waiter = User::factory()->create();
    attachPrompt52Waiter($waiter, $organization);

    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Preview table',
            'display_number' => 'P7',
            'status' => ServicePointStatus::Occupied,
        ]);

    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->create();

    $component = Livewire::actingAs($waiter)
        ->withQueryParams(['table' => $tableSession->id])
        ->test(WaiterDashboard::class)
        ->assertSet('selectedTableSessionId', $tableSession->id)
        ->assertSee('data-workspace-split', false)
        ->assertSee('data-priority-row', false)
        ->assertSee('data-waiter-mobile-detail', false)
        ->assertSee('data-waiter-desktop-select', false)
        ->assertSee('data-waiter-mobile-detail-wrapper', false)
        ->assertSee('data-waiter-desktop-select-wrapper', false)
        ->assertSee('data-waiter-table-preview', false)
        ->assertSee('aria-current="true"', false)
        ->assertSee(route('restaurant.waiter.tables.show', $tableSession), false);

    $queryCount = countDatabaseQueries(function () use ($component, $tableSession): void {
        $component->call('selectTable', $tableSession->id);
    });

    expect($queryCount)->toBeLessThanOrEqual(14);

    $component
        ->call('selectTable', PHP_INT_MAX)
        ->assertSet('selectedTableSessionId', null)
        ->assertDontSee('data-waiter-table-preview', false);
});

test('waiter dashboard limits branches to active branch assignments when present', function () {
    [$organization, , $firstBranch] = createPrompt52Branch(branchName: 'Assigned Branch');
    $secondBrand = Brand::factory()->for($organization)->create(['name' => 'Second Brand']);
    $secondBranch = Branch::factory()
        ->for($organization)
        ->for($secondBrand)
        ->create(['name' => 'Unassigned Branch']);
    $waiter = User::factory()->create();
    $waiterRole = attachPrompt52Waiter($waiter, $organization);

    $branchUser = new BranchUser;
    $branchUser->forceFill([
        'organization_id' => $organization->id,
        'branch_id' => $firstBranch->id,
        'user_id' => $waiter->id,
        'role_id' => $waiterRole->id,
        'status' => OrganizationUserStatus::Active,
        'assigned_at' => now(),
        'assigned_by_user_id' => null,
    ])->save();

    ServicePoint::factory()->for($firstBranch)->create(['name' => 'Assigned table']);
    ServicePoint::factory()->for($secondBranch)->create(['name' => 'Hidden table']);

    Livewire::actingAs($waiter)
        ->test(WaiterDashboard::class)
        ->assertSet('branches.0.has_activity', false)
        ->assertSee('data-branch-activity="idle"', false)
        ->assertSee('Assigned Branch')
        ->assertSee('Assigned table')
        ->assertDontSee('Unassigned Branch')
        ->assertDontSee('Hidden table');
});

test('waiter dashboard refresh shows newly sent draft without websockets', function () {
    [$organization, , $branch] = createPrompt52Branch();
    $waiter = User::factory()->create();
    attachPrompt52Waiter($waiter, $organization);
    $servicePoint = ServicePoint::factory()->for($branch)->create(['name' => 'Polling table']);
    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->create(['status' => TableSessionStatus::Active]);
    $guest = TableSessionGuest::factory()->for($tableSession)->create(['guest_name' => 'Marta']);

    $component = Livewire::actingAs($waiter)
        ->test(WaiterDashboard::class)
        ->assertSet('newDraftCount', 0)
        ->assertNotDispatched('waiter-new-draft')
        ->assertSee('Polling table');

    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create([
            'status' => DraftOrderStatus::SentToWaiter,
            'sent_to_waiter_at' => now(),
            'sent_by_guest_id' => $guest->id,
        ]);

    DraftOrderItem::factory()
        ->for($draftOrder, 'draftOrder')
        ->for($guest, 'guest')
        ->create([
            'menu_item_id' => null,
            'item_name' => 'Soup',
            'total_price_cents' => 700,
        ]);

    $component
        ->call('refreshDashboard')
        ->assertSet('newDraftCount', 1)
        ->assertDispatched('waiter-new-draft')
        ->assertSee('Marta')
        ->assertSee('€7.00');

    $component
        ->call('refreshDashboard')
        ->assertSet('newDraftCount', 1)
        ->assertNotDispatched('waiter-new-draft');

    $draftOrder->forceFill(['status' => DraftOrderStatus::Rejected])->save();
    $replacementDraft = DraftOrder::factory()
        ->for($tableSession)
        ->create([
            'status' => DraftOrderStatus::SentToWaiter,
            'sent_to_waiter_at' => now()->addSecond(),
            'sent_by_guest_id' => $guest->id,
        ]);

    DraftOrderItem::factory()
        ->for($replacementDraft, 'draftOrder')
        ->for($guest, 'guest')
        ->create([
            'menu_item_id' => null,
            'item_name' => 'Replacement soup',
            'total_price_cents' => 800,
        ]);

    $component
        ->call('refreshDashboard')
        ->assertSet('newDraftCount', 1)
        ->assertDispatched('waiter-new-draft');
});

test('waiter dashboard groups tables by zones and surfaces urgent work', function () {
    [$organization, , $branch] = createPrompt52Branch(branchName: 'Prompt 91 Branch');
    $waiter = User::factory()->create(['name' => 'Prompt 91 Waiter']);
    $waiterRole = attachPrompt52Waiter($waiter, $organization);
    enablePrompt52Permission($waiterRole, SystemPermission::CloseTableSessions);

    $mainHall = AreaNode::factory()
        ->for($branch)
        ->create(['name' => 'Main Hall']);
    $terrace = AreaNode::factory()
        ->for($branch)
        ->create(['name' => 'Terrace']);

    $freeTable = ServicePoint::factory()
        ->for($branch)
        ->for($mainHall, 'areaNode')
        ->create([
            'name' => 'Free Window',
            'display_number' => 'F1',
            'status' => ServicePointStatus::Free,
        ]);
    $newOrderTable = ServicePoint::factory()
        ->for($branch)
        ->for($mainHall, 'areaNode')
        ->create([
            'name' => 'New Order Table',
            'display_number' => 'N1',
            'status' => ServicePointStatus::HasNewOrder,
        ]);
    $callTable = ServicePoint::factory()
        ->for($branch)
        ->for($terrace, 'areaNode')
        ->create([
            'name' => 'Call Terrace',
            'display_number' => 'T2',
            'status' => ServicePointStatus::WaitingWaiter,
        ]);
    $billTable = ServicePoint::factory()
        ->for($branch)
        ->for($terrace, 'areaNode')
        ->create([
            'name' => 'Bill Terrace',
            'display_number' => 'T3',
            'status' => ServicePointStatus::PaymentRequested,
        ]);
    $readyTable = ServicePoint::factory()
        ->for($branch)
        ->for($mainHall, 'areaNode')
        ->create([
            'name' => 'Ready Table',
            'display_number' => 'R1',
            'status' => ServicePointStatus::ReadyToServe,
        ]);

    $newOrderSession = TableSession::factory()
        ->forServicePoint($newOrderTable)
        ->active()
        ->create();
    $newOrderGuest = TableSessionGuest::factory()
        ->for($newOrderSession)
        ->create(['guest_name' => 'Anna']);
    $draftOrder = DraftOrder::factory()
        ->for($newOrderSession)
        ->create([
            'status' => DraftOrderStatus::SentToWaiter,
            'sent_to_waiter_at' => now(),
            'sent_by_guest_id' => $newOrderGuest->id,
        ]);
    DraftOrderItem::factory()
        ->for($draftOrder, 'draftOrder')
        ->for($newOrderGuest, 'guest')
        ->create([
            'menu_item_id' => null,
            'item_name' => 'Prompt 91 Pasta',
            'quantity' => 1,
            'unit_price_cents' => 1200,
            'total_price_cents' => 1200,
        ]);

    $callSession = TableSession::factory()
        ->forServicePoint($callTable)
        ->active()
        ->create();
    $callGuest = TableSessionGuest::factory()
        ->for($callSession)
        ->create(['guest_name' => 'Boris']);
    WaiterCall::factory()
        ->forTableSession($callSession)
        ->create(['requested_by_guest_id' => $callGuest->id]);

    TableSession::factory()
        ->forServicePoint($billTable)
        ->active()
        ->create(['status' => TableSessionStatus::PaymentRequested]);

    $readySession = TableSession::factory()
        ->forServicePoint($readyTable)
        ->active()
        ->create();
    $readyGuest = TableSessionGuest::factory()
        ->for($readySession)
        ->create(['guest_name' => 'Clara']);
    $order = Order::factory()
        ->for($readySession)
        ->create([
            'branch_id' => $branch->id,
            'service_point_id' => $readyTable->id,
            'table_session_id' => $readySession->id,
            'status' => OrderStatus::Ready,
            'currency' => 'EUR',
        ]);
    $orderItem = OrderItem::factory()
        ->for($order)
        ->for($readyGuest, 'guest')
        ->create([
            'guest_name' => 'Clara',
            'guest_name_snapshot' => 'Clara',
            'item_name' => 'Prompt 91 Soup',
            'item_name_snapshot' => 'Prompt 91 Soup',
            'quantity' => 2,
        ]);
    $ticket = KitchenTicket::factory()
        ->for($order)
        ->create([
            'branch_id' => $branch->id,
            'service_point_id' => $readyTable->id,
            'table_session_id' => $readySession->id,
            'department_name' => 'Kitchen',
        ]);
    KitchenTicketItem::factory()
        ->for($ticket, 'kitchenTicket')
        ->for($orderItem, 'orderItem')
        ->create([
            'table_session_guest_id' => $readyGuest->id,
            'guest_name' => 'Clara',
            'item_name' => 'Prompt 91 Soup',
            'quantity' => 2,
            'status' => KitchenTicketItemStatus::Ready,
            'served_at' => null,
        ]);

    Livewire::actingAs($waiter)
        ->test(WaiterDashboard::class)
        ->assertSet('newDraftCount', 1)
        ->assertSet('waiterCallCount', 1)
        ->assertSet('billRequestCount', 1)
        ->assertSet('readyItemCount', 1)
        ->assertSee('Main Hall')
        ->assertSee('Terrace')
        ->assertSee('New orders')
        ->assertSee('Guest calls')
        ->assertSee('Bill requests')
        ->assertSee('Ready items')
        ->assertSee('Prompt 91 Soup')
        ->assertSee('Close table')
        ->assertSee('Free Window')
        ->assertSee('Open table')
        ->call('openTable', $freeTable->id)
        ->assertHasNoErrors()
        ->assertSee(__('ui.livewire.waiter.dashboard.stol_otkryt'));

    expect(TableSession::query()
        ->where('service_point_id', $freeTable->id)
        ->where('status', TableSessionStatus::Active->value)
        ->count())->toBe(1)
        ->and($freeTable->fresh()->status)->toBe(ServicePointStatus::Occupied);
});

function createPrompt52Branch(
    string $organizationName = 'Waiter Group',
    string $brandName = 'Waiter Brand',
    string $branchName = 'Waiter Branch',
): array {
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => $organizationName]);
    $brand = Brand::factory()->for($organization)->create(['name' => $brandName]);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => $branchName,
            'city' => 'Vilnius',
            'currency' => 'EUR',
        ]);

    return [$organization, $brand, $branch, $owner->fresh()];
}

function attachPrompt52Waiter(User $user, Organization $organization): Role
{
    $waiterRole = Role::query()
        ->where('code', SystemRole::Waiter->value)
        ->firstOrFail();
    $viewOrders = Permission::query()
        ->where('code', SystemPermission::ViewOrders->value)
        ->firstOrFail();

    $waiterRole->permissions()->updateExistingPivot($viewOrders->id, ['enabled' => true]);

    $organization->users()->syncWithoutDetachingOrFail([
        $user->id => [
            'role_id' => $waiterRole->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ],
    ]);

    return $waiterRole;
}

function enablePrompt52Permission(Role $role, SystemPermission $permissionCode): void
{
    $permission = Permission::query()
        ->where('code', $permissionCode->value)
        ->firstOrFail();

    $role->permissions()->updateExistingPivot($permission->id, ['enabled' => true]);
}

test('temporary closure requires fresh settings permission and branch scope', function (string $scenario): void {
    [$organization, $brand, $branch] = createPrompt52Branch();
    $branch->update(['is_temporarily_closed' => true]);
    $waiter = User::factory()->create();
    $role = attachPrompt52Waiter($waiter, $organization);
    if ($scenario !== 'view only') {
        enablePrompt52Permission($role, SystemPermission::ManageSettings);
    }
    $component = Livewire::actingAs($waiter)->test(WaiterDashboard::class);
    if ($scenario === 'view only') {
        $component->assertDontSee('wire:click="disableTemporaryClosure(', false);
    } else {
        $component->assertSee('wire:click="disableTemporaryClosure(', false);
    }
    if ($scenario === 'revoked') {
        $permission = Permission::query()->where('code', SystemPermission::ManageSettings->value)->firstOrFail();
        $role->permissions()->updateExistingPivot($permission->id, ['enabled' => false]);
    }
    if ($scenario === 'foreign tenant') {
        [, , $branch] = createPrompt52Branch('Foreign');
        $branch->update(['is_temporarily_closed' => true]);
    }
    if ($scenario === 'unassigned branch') {
        BranchUser::factory()->for($organization)->for($branch)->for($waiter)->create(['status' => OrganizationUserStatus::Active]);
        $branch = Branch::factory()->for($organization)->for($brand)->create(['is_temporarily_closed' => true]);
    }
    $component->call('disableTemporaryClosure', $branch->id);
    if ($scenario === 'authorized') {
        $component->assertHasNoErrors();
        expect($branch->fresh()->is_temporarily_closed)->toBeFalse();
    } else {
        $component->assertForbidden();
        expect($branch->fresh()->is_temporarily_closed)->toBeTrue();
    }
})->with(['view only', 'authorized', 'revoked', 'foreign tenant', 'unassigned branch']);

test('batched waiter access preserves overrides assignments and membership scoping', function (string $mode): void {
    [$organization, , $branch] = createPrompt52Branch();
    $user = User::factory()->create();
    $role = attachPrompt52Waiter($user, $organization);
    enablePrompt52Permission($role, SystemPermission::ManageSettings);
    [$otherOrganization, , $otherBranch] = createPrompt52Branch('Other');
    attachPrompt52Waiter($user, $otherOrganization);
    $permission = Permission::query()->where('code', SystemPermission::ManageSettings->value)->firstOrFail();
    if ($mode === 'deny' || $mode === 'grant') {
        $user->permissionOverrides()->attach($permission, ['enabled' => $mode === 'grant']);
    }
    if ($mode === 'assigned') {
        BranchUser::factory()->for($organization)->for($branch)->for($user)->create(['status' => OrganizationUserStatus::Active]);
    }
    if ($mode === 'revoked') {
        $organization->users()->updateExistingPivot($user->id, ['status' => OrganizationUserStatus::Suspended]);
    }
    $resolver = app(ResolveWaiterAccessibleBranchIdsAction::class);
    $permissions = [SystemPermission::ManageSettings, SystemPermission::ViewOrders, SystemPermission::ManagePayments];
    $batched = $resolver->handleMany($user, $permissions);
    foreach ($permissions as $permission) {
        expect($batched[$permission->value]->all())->toBe($resolver->handle($user, $permission)->all());
    }
})->with(['normal', 'deny', 'grant', 'assigned', 'revoked']);

test('waiter dashboard bounds the selected branch and service point page while honoring polling settings', function (): void {
    [$organization, $brand, $branch] = createPrompt52Branch(branchName: 'A branch');
    $waiter = User::factory()->create();
    attachPrompt52Waiter($waiter, $organization);
    BranchSetting::factory()->for($branch)->create(['polling_interval_seconds' => 12]);
    ServicePoint::factory()->count(60)->for($branch)->create();
    $other = Branch::factory()->for($organization)->for($brand)->create(['name' => 'B branch']);
    ServicePoint::factory()->count(60)->for($other)->create();
    $hydrated = 0;
    Event::listen('eloquent.retrieved: *', function () use (&$hydrated): void {
        $hydrated++;
    });
    $payload = [];
    $queries = countDatabaseQueries(function () use ($waiter, &$payload): void {
        $payload = app(BuildWaiterDashboardAction::class)->handle($waiter);
    });
    expect($queries)->toBeLessThanOrEqual(35)->and($hydrated)->toBeLessThanOrEqual(70)
        ->and(strlen(json_encode($payload)))->toBeLessThan(50_000);
    expect($payload['branches'])->toHaveCount(1)
        ->and($payload['branches'][0]['service_points'])->toHaveCount(50)
        ->and($payload['service_point_count'])->toBe(60);
    Livewire::actingAs($waiter)->test(WaiterDashboard::class)->assertSee('wire:poll.visible.12s="refreshDashboard"', false);
});

test('off-page work changes branch counters and notifications and is reachable through attention filtering', function (): void {
    [$organization, , $branch] = createPrompt52Branch();
    $waiter = User::factory()->create();
    attachPrompt52Waiter($waiter, $organization);
    ServicePoint::factory()->count(50)->for($branch)->sequence(fn ($sequence) => ['name' => sprintf('A table %03d', $sequence->index)])->create();
    $point = ServicePoint::factory()->for($branch)->create(['name' => 'Z urgent table']);
    $session = TableSession::factory()->forServicePoint($point)->active()->create();
    $component = Livewire::actingAs($waiter)->test(WaiterDashboard::class)->assertDontSee('Z urgent table');
    $guest = TableSessionGuest::factory()->for($session)->create();
    WaiterCall::factory()->forTableSession($session)->create(['requested_by_guest_id' => $guest->id]);
    $component->call('refreshDashboard')->assertSet('waiterCallCount', 1)->assertSet('attentionCount', 1)
        ->assertDispatched('waiter-called')->assertDontSee('Z urgent table');
    $component->set('attentionOnly', true)->assertSee('Z urgent table')->assertSet('servicePointCount', 51);
});

test('dashboard branch selection search paging and polling remain scoped and fresh', function (): void {
    [$organization, $brand, $first] = createPrompt52Branch(branchName: 'A first');
    $waiter = User::factory()->create();
    attachPrompt52Waiter($waiter, $organization);
    $second = Branch::factory()->for($organization)->for($brand)->create(['name' => 'B second']);
    BranchSetting::factory()->for($second)->create(['polling_interval_seconds' => 17]);
    ServicePoint::factory()->count(51)->for($second)->sequence(fn ($sequence) => ['name' => sprintf('Second %03d', $sequence->index)])->create();
    $component = Livewire::actingAs($waiter)->test(WaiterDashboard::class);
    $component->set('selectedBranchId', $second->id)->assertSee('wire:poll.visible.17s="refreshDashboard"', false)
        ->assertSet('servicePointCount', 51)->assertSee('Second 000')->assertDontSee('Second 050');
    $component->call('changeTablePage', 2)->assertSee('Second 050')->assertDontSee('Second 000');
    $component->set('branchSearch', 'A first')->assertSet('selectedBranchId', $second->id);
    $foreign = Branch::factory()->create();
    $component->set('selectedBranchId', $foreign->id)->assertForbidden();
});
