<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\DraftOrderStatus;
use App\Enums\OrderStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Livewire\PublicQr\DraftOrder as GuestDraftOrder;
use App\Livewire\Waiter\TableDetail;
use App\Models\AreaNode;
use App\Models\Branch;
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
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('waiter with view orders but without confirm orders cannot confirm sent draft', function () {
    [$organization, , , $tableSession, $draftOrder] = createPrompt54SentDraftScenario();
    $waiter = User::factory()->create();
    $role = attachPrompt54Staff($waiter, $organization, [SystemPermission::ViewOrders]);
    $role->permissions()->updateExistingPivot(
        Permission::query()->where('code', SystemPermission::ConfirmOrders->value)->firstOrFail()->id,
        ['enabled' => false],
    );

    Livewire::actingAs($waiter)
        ->test(TableDetail::class, ['tableSession' => $tableSession])
        ->assertDontSee('Confirm order')
        ->call('confirmDraft')
        ->assertHasErrors('draft_review');

    expect(Order::query()->count())->toBe(0)
        ->and($draftOrder->fresh()->status)->toBe(DraftOrderStatus::SentToWaiter);
});

test('waiter can confirm sent draft into real order without sending kitchen or bar', function () {
    [$organization, , $servicePoint, $tableSession, $draftOrder] = createPrompt54SentDraftScenario();
    $waiter = User::factory()->create(['name' => 'Prompt 54 Waiter']);
    attachPrompt54Staff($waiter, $organization, [SystemPermission::ViewOrders, SystemPermission::ConfirmOrders]);

    Livewire::actingAs($waiter)
        ->test(TableDetail::class, ['tableSession' => $tableSession])
        ->assertSee('Confirm order')
        ->call('confirmDraft')
        ->assertHasNoErrors()
        ->assertSee('Order #')
        ->assertSee('Confirmed by waiter');

    $order = Order::query()
        ->with(['items'])
        ->where('draft_order_id', $draftOrder->id)
        ->firstOrFail();

    expect($order->status)->toBe(OrderStatus::ConfirmedByWaiter)
        ->and($order->branch_id)->toBe($tableSession->branch_id)
        ->and($order->service_point_id)->toBe($tableSession->service_point_id)
        ->and($order->confirmed_by_user_id)->toBe($waiter->id)
        ->and($order->total_price)->toBe('22.50')
        ->and($order->metadata['sent_to_kitchen'])->toBeFalse()
        ->and($order->metadata['sent_to_bar'])->toBeFalse()
        ->and($order->items)->toHaveCount(2)
        ->and($order->items->pluck('item_name')->all())->toContain('Margherita', 'Water')
        ->and($draftOrder->fresh()->status)->toBe(DraftOrderStatus::ConvertedToOrder)
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::Occupied);
});

test('waiter can reject sent draft with reason and guests see it', function () {
    [$organization, , , $tableSession, $draftOrder, $ana] = createPrompt54SentDraftScenario();
    $waiter = User::factory()->create();
    attachPrompt54Staff($waiter, $organization, [SystemPermission::ViewOrders, SystemPermission::ConfirmOrders]);

    Livewire::actingAs($waiter)
        ->test(TableDetail::class, ['tableSession' => $tableSession])
        ->set('rejectionReason', 'Please remove the unavailable pizza.')
        ->call('rejectDraft')
        ->assertHasNoErrors()
        ->assertSee('Rejected')
        ->assertSee('Please remove the unavailable pizza.');

    $draftOrder = $draftOrder->fresh();

    expect($draftOrder->status)->toBe(DraftOrderStatus::Rejected)
        ->and($draftOrder->rejection_reason)->toBe('Please remove the unavailable pizza.')
        ->and($draftOrder->rejected_by_user_id)->toBe($waiter->id)
        ->and(Order::query()->count())->toBe(0);

    Livewire::test(GuestDraftOrder::class, [
        'tableSessionId' => $tableSession->id,
        'currentGuestId' => $ana->id,
        'currency' => 'EUR',
        'publicToken' => 'prompt54publictoken',
    ])
        ->assertSet('draftStatusValue', DraftOrderStatus::Rejected->value)
        ->assertSet('rejectionReason', 'Please remove the unavailable pizza.')
        ->assertSet('canEditDraft', false)
        ->assertSee('Официант отклонил черновик.')
        ->assertSee('Please remove the unavailable pizza.');
});

test('waiter can return rejected draft to draft for guest edits', function () {
    [$organization, , , $tableSession, $draftOrder, $ana] = createPrompt54SentDraftScenario();
    $waiter = User::factory()->create();
    attachPrompt54Staff($waiter, $organization, [SystemPermission::ViewOrders, SystemPermission::ConfirmOrders]);

    $draftOrder->forceFill([
        'status' => DraftOrderStatus::Rejected,
        'rejected_at' => now(),
        'rejected_by_user_id' => $waiter->id,
        'rejection_reason' => 'Please adjust comments.',
    ])->save();

    Livewire::actingAs($waiter)
        ->test(TableDetail::class, ['tableSession' => $tableSession])
        ->assertSee('Return to draft')
        ->call('returnRejectedDraftToDraft')
        ->assertHasNoErrors()
        ->assertSee('Draft');

    $draftOrder = $draftOrder->fresh();

    expect($draftOrder->status)->toBe(DraftOrderStatus::Draft)
        ->and($draftOrder->rejection_reason)->toBeNull()
        ->and($draftOrder->rejected_by_user_id)->toBeNull();

    Livewire::test(GuestDraftOrder::class, [
        'tableSessionId' => $tableSession->id,
        'currentGuestId' => $ana->id,
        'currency' => 'EUR',
        'publicToken' => 'prompt54publictoken',
    ])
        ->assertSet('draftStatusValue', DraftOrderStatus::Draft->value)
        ->assertSet('canEditDraft', true)
        ->assertDontSee('Официант отклонил черновик.');
});

test('waiter must provide reason before rejecting draft', function () {
    [$organization, , , $tableSession, $draftOrder] = createPrompt54SentDraftScenario();
    $waiter = User::factory()->create();
    attachPrompt54Staff($waiter, $organization, [SystemPermission::ViewOrders, SystemPermission::ConfirmOrders]);

    Livewire::actingAs($waiter)
        ->test(TableDetail::class, ['tableSession' => $tableSession])
        ->set('rejectionReason', '   ')
        ->call('rejectDraft')
        ->assertHasErrors('rejectionReason');

    expect($draftOrder->fresh()->status)->toBe(DraftOrderStatus::SentToWaiter);
});

function createPrompt54SentDraftScenario(): array
{
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Prompt 54 Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Prompt 54 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 54 Branch',
            'currency' => 'EUR',
        ]);
    $areaNode = AreaNode::factory()->for($branch)->create(['name' => 'Prompt 54 Hall']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($areaNode)
        ->create([
            'name' => 'Prompt 54 Table',
            'status' => ServicePointStatus::HasNewOrder,
        ]);
    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->create(['status' => TableSessionStatus::Active]);
    $ana = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Ana',
            'status' => TableSessionGuestStatus::Active,
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
            'unit_price' => '10.50',
            'modifier_total' => '2.00',
            'total_price' => '12.50',
            'selected_modifiers' => [
                [
                    'group_name' => 'Pizza size',
                    'option_name' => 'Large',
                    'price_delta' => '2.00',
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
            'unit_price' => '10.00',
            'modifier_total' => '0.00',
            'total_price' => '10.00',
            'selected_modifiers' => [],
        ]);

    return [$organization, $branch, $servicePoint, $tableSession, $draftOrder, $ana, $zara];
}

/**
 * @param  list<SystemPermission>  $permissions
 */
function attachPrompt54Staff(User $user, Organization $organization, array $permissions): Role
{
    $role = Role::query()
        ->where('code', SystemRole::Waiter->value)
        ->firstOrFail();

    foreach ($permissions as $permission) {
        $permissionModel = Permission::query()
            ->where('code', $permission->value)
            ->firstOrFail();

        $role->permissions()->updateExistingPivot($permissionModel->id, ['enabled' => true]);
    }

    $organization->users()->syncWithoutDetachingOrFail([
        $user->id => [
            'role_id' => $role->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ],
    ]);

    return $role;
}
