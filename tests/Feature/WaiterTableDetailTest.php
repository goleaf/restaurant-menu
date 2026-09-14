<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Waiter\BuildWaiterTableDetailAction;
use App\Enums\DraftOrderStatus;
use App\Enums\OrderStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Livewire\Waiter\TableDetail;
use App\Livewire\Waiter\TableDetail\DraftReview;
use App\Livewire\Waiter\TableDetail\OrderFulfilment;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('waiter table detail requires authentication', function () {
    [, , , $tableSession] = createPrompt53TableDetailScenario();

    $this->get(route('restaurant.waiter.tables.show', $tableSession))
        ->assertRedirect(route('login'));
});

test('waiter table detail requires view orders permission', function () {
    [, , , $tableSession] = createPrompt53TableDetailScenario();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('restaurant.waiter.tables.show', $tableSession))
        ->assertForbidden();
});

test('waiter sees table detail with guests positions modifiers comments and totals', function () {
    [$organization, $branch, $servicePoint, $tableSession] = createPrompt53TableDetailScenario();
    $waiter = User::factory()->create();
    attachPrompt53Waiter($waiter, $organization);
    [$ana, $zara, $draftOrder] = createPrompt53Draft($tableSession);

    Livewire::actingAs($waiter)
        ->test(TableDetail::class, ['tableSession' => $tableSession])
        ->assertSet('tableSessionId', $tableSession->id)
        ->assertSee('data-table-context-header', false)
        ->assertSee('data-table-summary', false)
        ->assertSee($organization->name)
        ->assertSee($branch->name)
        ->assertSee('Main Hall')
        ->assertSee($servicePoint->name)
        ->assertSee('Active')
        ->assertSee('Sent to waiter')
        ->assertSee('No garlic')
        ->assertSee('Pizza size: Large')
        ->assertSee('Water')
        ->assertSee('€22.50');

    $draftReviewComponent = Livewire::actingAs($waiter)
        ->test(DraftReview::class, ['tableSessionId' => $tableSession->id])
        ->assertSet('draftReview.guest_sections.0.guest_name', 'Ana')
        ->assertSet('draftReview.guest_sections.0.total', '€10.00')
        ->assertSet('draftReview.guest_sections.1.guest_name', 'Zara')
        ->assertSet('draftReview.guest_sections.1.total', '€12.50')
        ->assertSet('draftReview.draft.id', $draftOrder->id)
        ->assertSet('draftReview.draft.sent_by_guest_name', $ana->guest_name)
        ->assertSee('id="waiter-draft-adding-comment"', false)
        ->assertSee('name="addingComment"', false)
        ->assertSee('id="waiter-draft-rejection-reason"', false)
        ->assertSee('name="rejectionReason"', false);

    $draftReviewComponent
        ->call('editDraftItem', $draftOrder->items()->firstOrFail()->id)
        ->assertSee('id="waiter-draft-editing-comment"', false)
        ->assertSee('name="editingComment"', false);

    $this->actingAs($waiter)
        ->get(route('restaurant.waiter.tables.show', $tableSession))
        ->assertOk()
        ->assertSee('wire:poll.visible.1s="refreshDraftReview"', false)
        ->assertSee('wire:poll.visible.1s="refreshOrderFulfilment"', false)
        ->assertSeeTextInOrder(['Ana', 'Water', '€10.00', 'Zara', 'Margherita', '€12.50'])
        ->assertSeeText('€22.50');
});

test('waiter table detail shows participants on the first Livewire render', function () {
    [$organization, , , $tableSession] = createPrompt53TableDetailScenario();
    $waiter = User::factory()->create();
    attachPrompt53Waiter($waiter, $organization);
    $guest = TableSessionGuest::factory()
        ->for($tableSession)
        ->active()
        ->create(['guest_name' => 'Observer Guest']);

    Livewire::actingAs($waiter)
        ->test(TableDetail::class, ['tableSession' => $tableSession])
        ->assertSee('Observer Guest')
        ->assertSee('data-modal="remove-table-guest-'.$guest->id.'"', false);
});

test('historical closed session is viewable without exposing impossible mutations', function () {
    [$organization, , , $tableSession] = createPrompt53TableDetailScenario();
    $waiter = User::factory()->create();
    attachPrompt53Waiter($waiter, $organization);
    $guest = TableSessionGuest::factory()->for($tableSession)->active()->create();
    $draftOrder = DraftOrder::factory()->for($tableSession)->create([
        'status' => DraftOrderStatus::ConvertedToOrder,
    ]);
    DraftOrderItem::factory()->for($draftOrder)->for($guest, 'guest')->create();
    Order::factory()
        ->for($tableSession)
        ->for($draftOrder, 'draftOrder')
        ->create(['status' => OrderStatus::Closed]);
    $tableSession->forceFill([
        'status' => TableSessionStatus::Closed,
        'ended_at' => now(),
    ])->save();

    $payload = app(BuildWaiterTableDetailAction::class)->handle($waiter, $tableSession->fresh());

    expect($payload['has_access'])->toBeTrue()
        ->and(data_get($payload, 'table.session.can_close'))->toBeFalse()
        ->and(data_get($payload, 'table.participants.can_manage'))->toBeFalse()
        ->and(data_get($payload, 'table.draft.can_confirm'))->toBeFalse()
        ->and(data_get($payload, 'table.draft.can_reject'))->toBeFalse()
        ->and(data_get($payload, 'table.draft.can_return_to_draft'))->toBeFalse()
        ->and(data_get($payload, 'table.draft.can_edit'))->toBeFalse()
        ->and(data_get($payload, 'table.draft.can_send_to_kitchen'))->toBeFalse()
        ->and(data_get($payload, 'table.draft.can_cancel'))->toBeFalse();

    $this->actingAs($waiter)
        ->get(route('restaurant.waiter.tables.show', $tableSession))
        ->assertOk()
        ->assertSee(__('ui.waiter.table_detail.order_service_complete'))
        ->assertDontSee(__('ui.waiter.table_detail.prepared_for_kitchen_bar_dispatch_but_not_sent_yet'));
});

test('waiter table detail respects active branch assignments', function () {
    [$organization, , $assignedBranch] = createPrompt53Branch(branchName: 'Assigned Detail Branch');
    $otherBrand = Brand::factory()->for($organization)->create(['name' => 'Other Detail Brand']);
    $otherBranch = Branch::factory()
        ->for($organization)
        ->for($otherBrand)
        ->create(['name' => 'Hidden Detail Branch']);
    $waiter = User::factory()->create();
    $waiterRole = attachPrompt53Waiter($waiter, $organization);

    $branchUser = new BranchUser;
    $branchUser->forceFill([
        'organization_id' => $organization->id,
        'branch_id' => $assignedBranch->id,
        'user_id' => $waiter->id,
        'role_id' => $waiterRole->id,
        'status' => OrganizationUserStatus::Active,
        'assigned_at' => now(),
        'assigned_by_user_id' => null,
    ])->save();

    $assignedServicePoint = ServicePoint::factory()->for($assignedBranch)->create(['name' => 'Assigned table detail']);
    $hiddenServicePoint = ServicePoint::factory()->for($otherBranch)->create(['name' => 'Hidden table detail']);
    $assignedSession = TableSession::factory()->forServicePoint($assignedServicePoint)->active()->create();
    $hiddenSession = TableSession::factory()->forServicePoint($hiddenServicePoint)->active()->create();

    Livewire::actingAs($waiter)
        ->test(TableDetail::class, ['tableSession' => $assignedSession])
        ->assertSee('Assigned table detail');

    $this->actingAs($waiter)
        ->get(route('restaurant.waiter.tables.show', $hiddenSession))
        ->assertForbidden();
});

test('waiter table detail refresh shows newly added draft item without websockets', function () {
    [$organization, , , $tableSession] = createPrompt53TableDetailScenario();
    $waiter = User::factory()->create();
    attachPrompt53Waiter($waiter, $organization);
    [$ana, , $draftOrder] = createPrompt53Draft($tableSession);

    $component = Livewire::actingAs($waiter)
        ->test(DraftReview::class, ['tableSessionId' => $tableSession->id])
        ->assertSet('draftReview.total', '€22.50');

    DraftOrderItem::factory()
        ->for($draftOrder, 'draftOrder')
        ->for($ana, 'guest')
        ->create([
            'menu_item_id' => null,
            'item_name' => 'Tea',
            'quantity' => 1,
            'unit_price_cents' => 300,
            'modifier_total_cents' => 0,
            'total_price_cents' => 300,
            'selected_modifiers' => [],
            'comment' => 'Warm',
        ]);

    $component
        ->call('refreshDraftReview')
        ->assertSee('Tea')
        ->assertSee('Warm')
        ->assertSet('draftReview.guest_sections.0.total', '€13.00')
        ->assertSet('draftReview.total', '€25.50');
});

test('unchanged polling sections retain the same snapshot fingerprint', function () {
    [$organization, , , $tableSession] = createPrompt53TableDetailScenario();
    $waiter = User::factory()->create();
    attachPrompt53Waiter($waiter, $organization);
    foreach ([[DraftReview::class, 'refreshDraftReview'], [OrderFulfilment::class, 'refreshOrderFulfilment']] as [$section, $method]) {
        $component = Livewire::actingAs($waiter)->test($section, ['tableSessionId' => $tableSession->id]);
        $fingerprint = $component->get('changeFingerprint');
        $component->call($method)->assertSet('changeFingerprint', $fingerprint)->assertOk();
    }
});

test('payment-only access cannot invoke waiter draft mutations directly', function () {
    [$organization, , , $tableSession] = createPrompt53TableDetailScenario();
    $paymentViewer = User::factory()->create();
    attachPrompt53PaymentViewer($paymentViewer, $organization);

    Livewire::actingAs($paymentViewer)
        ->test(DraftReview::class, [
            'tableSessionId' => $tableSession->id,
            'initialDraftReview' => ['manual_order' => ['can_add' => false]],
        ])
        ->call('confirmDraft')
        ->assertForbidden();
});

function createPrompt53Branch(
    string $organizationName = 'Waiter Detail Group',
    string $brandName = 'Waiter Detail Brand',
    string $branchName = 'Waiter Detail Branch',
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

function createPrompt53TableDetailScenario(): array
{
    [$organization, , $branch] = createPrompt53Branch();
    $areaNode = AreaNode::factory()->for($branch)->create(['name' => 'Main Hall']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($areaNode)
        ->create([
            'name' => 'Window detail table',
            'display_number' => 'D-12',
            'status' => ServicePointStatus::HasNewOrder,
        ]);
    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->create(['status' => TableSessionStatus::Active]);

    return [$organization, $branch, $servicePoint, $tableSession];
}

function createPrompt53Draft(TableSession $tableSession): array
{
    $ana = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Ana',
            'status' => TableSessionGuestStatus::Active,
            'ready_at' => now(),
        ]);
    $zara = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Zara',
            'status' => TableSessionGuestStatus::Active,
        ]);
    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create([
            'status' => DraftOrderStatus::SentToWaiter,
            'sent_to_waiter_at' => now(),
            'sent_by_guest_id' => $ana->id,
        ]);

    DraftOrderItem::factory()
        ->for($draftOrder, 'draftOrder')
        ->for($zara, 'guest')
        ->create([
            'menu_item_id' => null,
            'item_name' => 'Margherita',
            'quantity' => 1,
            'unit_price_cents' => 1050,
            'modifier_total_cents' => 200,
            'total_price_cents' => 1250,
            'selected_modifiers' => [
                [
                    'group_name' => 'Pizza size',
                    'option_name' => 'Large',
                    'price_delta_cents' => 200,
                ],
            ],
            'comment' => 'No garlic',
        ]);

    DraftOrderItem::factory()
        ->for($draftOrder, 'draftOrder')
        ->for($ana, 'guest')
        ->create([
            'menu_item_id' => null,
            'item_name' => 'Water',
            'quantity' => 1,
            'unit_price_cents' => 1000,
            'modifier_total_cents' => 0,
            'total_price_cents' => 1000,
            'selected_modifiers' => [],
        ]);

    return [$ana, $zara, $draftOrder];
}

function attachPrompt53Waiter(User $user, Organization $organization): Role
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

function attachPrompt53PaymentViewer(User $user, Organization $organization): Role
{
    $role = Role::query()
        ->where('code', SystemRole::Accountant->value)
        ->firstOrFail();
    $viewPayments = Permission::query()
        ->where('code', SystemPermission::ViewPayments->value)
        ->firstOrFail();
    $viewOrders = Permission::query()
        ->where('code', SystemPermission::ViewOrders->value)
        ->firstOrFail();

    $role->permissions()->updateExistingPivot($viewPayments->id, ['enabled' => true]);
    $organization->users()->syncWithoutDetachingOrFail([
        $user->id => [
            'role_id' => $role->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ],
    ]);
    $user->permissionOverrides()->syncWithoutDetaching([
        $viewOrders->id => ['enabled' => false],
    ]);

    return $role;
}

test('draft polling sees same-second edits and replacement rows', function (string $change): void {
    $this->freezeTime();
    [$organization, , , $session] = createPrompt53TableDetailScenario();
    $user = User::factory()->create();
    attachPrompt53Waiter($user, $organization);
    [$guest, , $draft] = createPrompt53Draft($session);
    $component = Livewire::actingAs($user)->test(DraftReview::class, ['tableSessionId' => $session->id]);
    if ($change === 'guest') {
        $guest->update(['guest_name' => 'Changed guest']);
        $expected = 'Changed guest';
    } else {
        $item = $draft->items()->firstOrFail();
        if ($change === 'replace') {
            $item->delete();
            DraftOrderItem::factory()->for($draft, 'draftOrder')->for($guest, 'guest')->create(['item_name' => 'Replacement item']);
            $expected = 'Replacement item';
        } else {
            $item->update(['comment' => 'Same second comment']);
            $expected = 'Same second comment';
        }
    }
    $component->call('refreshDraftReview')->assertSee($expected);
})->with(['guest', 'item', 'replace']);

test('fulfilment polling sees edits to an older order even below the latest timestamp', function (): void {
    [$organization, , , $session] = createPrompt53TableDetailScenario();
    $user = User::factory()->create();
    attachPrompt53Waiter($user, $organization);
    $older = Order::factory()->for($session)->create(['updated_at' => now()->subDays(2)]);
    Order::factory()->for($session)->create(['updated_at' => now()]);
    $component = Livewire::actingAs($user)->test(OrderFulfilment::class, ['tableSessionId' => $session->id]);
    $older->forceFill(['status' => OrderStatus::Cancelled, 'updated_at' => now()->subDay()])->save();
    $component->call('refreshOrderFulfilment');
    expect(collect($component->get('orderFulfilment.orders'))->firstWhere('id', $older->id)['status_value'])->toBe(OrderStatus::Cancelled->value);
});

test('initial section snapshots cannot mask a newer database state on the next poll', function (string $section, string $initial, string $method): void {
    [$organization, , , $session] = createPrompt53TableDetailScenario();
    $user = User::factory()->create();
    attachPrompt53Waiter($user, $organization);
    [, , $draft] = createPrompt53Draft($session);
    $component = Livewire::actingAs($user)->test($section, ['tableSessionId' => $session->id, $initial => ['draft' => [], 'manual_order' => ['can_add' => false]]]);
    $component->call($method)->assertSet(lcfirst(substr($initial, 7)).'.draft.id', $draft->id);
})->with([
    [DraftReview::class, 'initialDraftReview', 'refreshDraftReview'],
    [OrderFulfilment::class, 'initialOrderFulfilment', 'refreshOrderFulfilment'],
]);

test('polling observes rollback without retaining an uncommitted fingerprint', function (): void {
    $this->freezeTime();
    [$organization, , , $session] = createPrompt53TableDetailScenario();
    $user = User::factory()->create();
    attachPrompt53Waiter($user, $organization);
    [$guest] = createPrompt53Draft($session);
    $originalName = $guest->guest_name;
    $component = Livewire::actingAs($user)->test(DraftReview::class, ['tableSessionId' => $session->id]);
    DB::beginTransaction();
    try {
        $guest->update(['guest_name' => 'Uncommitted guest']);
        $component->call('refreshDraftReview')->assertSee('Uncommitted guest');
    } finally {
        DB::rollBack();
    }
    $component->call('refreshDraftReview')->assertSee($originalName)->assertDontSee('Uncommitted guest');
});

test('polling fixed fixture measures reads and model hydration', function (string $section, string $method): void {
    [$organization, , , $session] = createPrompt53TableDetailScenario();
    $user = User::factory()->create();
    attachPrompt53Waiter($user, $organization);
    createPrompt53Draft($session);
    $component = Livewire::actingAs($user)->test($section, ['tableSessionId' => $session->id]);
    $hydrated = 0;
    $measuring = true;
    Event::listen('eloquent.retrieved: *', function () use (&$hydrated, &$measuring): void {
        if ($measuring) {
            $hydrated++;
        }
    });
    $queries = countDatabaseQueries(fn () => $component->call($method)->assertOk());
    $measuring = false;
    expect($queries)->toBe(32)->and($hydrated)->toBe(28);
})->with([[DraftReview::class, 'refreshDraftReview'], [OrderFulfilment::class, 'refreshOrderFulfilment']]);

test('a mutation after payload preparation remains visible to the following poll', function (): void {
    $this->freezeTime();
    [$organization, , , $session] = createPrompt53TableDetailScenario();
    $user = User::factory()->create();
    attachPrompt53Waiter($user, $organization);
    [$guest] = createPrompt53Draft($session);
    $reader = app(BuildWaiterTableDetailAction::class);
    $reads = 0;
    $mock = Mockery::mock(BuildWaiterTableDetailAction::class);
    $mock->shouldReceive('handle')->andReturnUsing(function (User $actor, TableSession $table) use ($reader, $guest, &$reads): array {
        $payload = $reader->handle($actor, $table);
        if (++$reads === 1) {
            $guest->update(['guest_name' => 'Arrived after snapshot']);
        }

        return $payload;
    });
    app()->instance(BuildWaiterTableDetailAction::class, $mock);
    $component = Livewire::actingAs($user)->test(DraftReview::class, ['tableSessionId' => $session->id]);
    $component->assertDontSee('Arrived after snapshot')->call('refreshDraftReview')->assertSee('Arrived after snapshot');
});

test('polling rechecks revoked access even when its presentation was unchanged', function (string $section, string $method): void {
    [$organization, , , $session] = createPrompt53TableDetailScenario();
    $user = User::factory()->create();
    attachPrompt53Waiter($user, $organization);
    $component = Livewire::actingAs($user)->test($section, ['tableSessionId' => $session->id]);
    $organization->users()->updateExistingPivot($user->id, ['status' => OrganizationUserStatus::Suspended->value]);
    $component->call($method)->assertForbidden();
})->with([[DraftReview::class, 'refreshDraftReview'], [OrderFulfilment::class, 'refreshOrderFulfilment']]);

test('section reads preserve the canonical prepared payload contract', function (string $section, array $keys): void {
    [$organization, , , $session] = createPrompt53TableDetailScenario();
    $user = User::factory()->create();
    attachPrompt53Waiter($user, $organization);
    createPrompt53Draft($session);
    $reader = app(BuildWaiterTableDetailAction::class);
    $full = $reader->handle($user, $session)['table'];
    $partial = $reader->handle($user, $session, $section)['table'];
    expect($partial)->toEqual(collect($full)->only($keys)->all());
})->with([
    ['draft', ['branch', 'guest_sections', 'draft', 'manual_order', 'current_draft_total', 'total']],
    ['fulfilment', ['draft', 'orders']],
]);
