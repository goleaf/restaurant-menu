<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Waiter\BuildWaiterTableDetailAction;
use App\Enums\DraftOrderStatus;
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
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Livewire;
use Mockery\MockInterface;

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
        ->assertSee($organization->name)
        ->assertSee($branch->name)
        ->assertSee('Main Hall')
        ->assertSee($servicePoint->name)
        ->assertSee('Active')
        ->assertSee('Sent to waiter')
        ->assertSee('No garlic')
        ->assertSee('Pizza size: Large')
        ->assertSee('Water')
        ->assertSee('22.50 EUR');

    $draftReviewComponent = Livewire::actingAs($waiter)
        ->test(DraftReview::class, ['tableSessionId' => $tableSession->id])
        ->assertSet('draftReview.guest_sections.0.guest_name', 'Ana')
        ->assertSet('draftReview.guest_sections.0.total', '10.00 EUR')
        ->assertSet('draftReview.guest_sections.1.guest_name', 'Zara')
        ->assertSet('draftReview.guest_sections.1.total', '12.50 EUR')
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
        ->assertSeeTextInOrder(['Ana', 'Water', '10.00 EUR', 'Zara', 'Margherita', '12.50 EUR'])
        ->assertSeeText('22.50 EUR');
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
        ->assertSet('draftReview.total', '22.50 EUR');

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
        ->assertSet('draftReview.guest_sections.0.total', '13.00 EUR')
        ->assertSet('draftReview.total', '25.50 EUR');
});

test('unchanged polling sections do not rebuild the complete waiter table graph', function () {
    [$organization, , , $tableSession] = createPrompt53TableDetailScenario();
    $waiter = User::factory()->create();
    attachPrompt53Waiter($waiter, $organization);

    $this->mock(BuildWaiterTableDetailAction::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('handle');
    });

    Livewire::actingAs($waiter)
        ->test(DraftReview::class, [
            'tableSessionId' => $tableSession->id,
            'initialDraftReview' => ['manual_order' => ['can_add' => false]],
        ])
        ->call('refreshDraftReview')
        ->assertOk();

    Livewire::actingAs($waiter)
        ->test(OrderFulfilment::class, [
            'tableSessionId' => $tableSession->id,
            'initialOrderFulfilment' => ['draft' => []],
        ])
        ->call('refreshOrderFulfilment')
        ->assertOk();
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
