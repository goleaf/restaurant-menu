<?php

declare(strict_types=1);

use App\Enums\KitchenTicketItemStatus;
use App\Enums\SystemRole;
use App\Livewire\Departments\Dashboard;
use App\Models\OrganizationUser;
use App\Models\Role;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Livewire;
use Tests\Support\PreparationWorkflowFixture;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

test('a presentation update rejects stale preparation choices after a partial family revocation', function (bool $withTickets, string $selectedDepartment): void {
    $fixture = PreparationWorkflowFixture::create($withTickets);
    $page = Livewire::actingAs($fixture['actors']['chef'])
        ->withQueryParams(['branch' => $fixture['branch']->id, 'department' => $fixture[$selectedDepartment]->id])
        ->test(Dashboard::class)
        ->assertSee('Preparation bar')
        ->assertSee('Preparation kitchen');

    OrganizationUser::query()
        ->where('organization_id', $fixture['organization']->id)
        ->where('user_id', $fixture['actors']['chef']->id)
        ->firstOrFail()
        ->forceFill(['role_id' => Role::query()->where('code', SystemRole::Cook)->firstOrFail()->id])->save();

    $page->set('compact', true)
        ->assertForbidden()
        ->assertDontSee('Preparation bar');
})->with([
    'other family removed while the selected kitchen remains granted' => [true, 'kitchen'],
    'selected family removed while its queue is empty' => [false, 'bar'],
]);

test('a forged batch selection of a served history row fails with validation and preserves serving', function (): void {
    $fixture = PreparationWorkflowFixture::create();
    $item = $fixture['items']->first();
    $item->forceFill(['status' => KitchenTicketItemStatus::Ready, 'served_at' => now(), 'served_by_user_id' => $fixture['actors']['waiter']->id])->save();

    Livewire::actingAs($fixture['actors']['cook'])
        ->withQueryParams(['branch' => $fixture['branch']->id, 'department' => $fixture['kitchen']->id, 'filter' => 'history'])
        ->test(Dashboard::class)
        ->assertSet('tickets.0.items.0.status_value', 'completed')
        ->set('selection.itemIds', [$item->id])
        ->set('selection.status', 'ready')
        ->call('reviewSelection')
        ->assertHasErrors('selection.itemIds')
        ->assertSet('reviewItems', []);

    expect($item->fresh()->status)->toBe(KitchenTicketItemStatus::Ready)
        ->and($item->fresh()->served_at)->not->toBeNull();
});

test('reviewed preparation work retains the ordered variant additions guest instructions and allergen snapshot', function (): void {
    $fixture = PreparationWorkflowFixture::create();
    $item = $fixture['items']->first();

    Livewire::actingAs($fixture['actors']['cook'])
        ->withQueryParams(['branch' => $fixture['branch']->id, 'department' => $fixture['kitchen']->id])
        ->test(Dashboard::class)
        ->set('selection.itemIds', [$item->id])
        ->call('reviewSelection')
        ->assertHasNoErrors()
        ->assertSet('reviewItems.0.id', $item->id)
        ->assertSet('reviewItems.0.quantity', 2)
        ->assertSet('reviewItems.0.variant_name', 'Large portion')
        ->assertSet('reviewItems.0.comment', 'Keep the guest instruction exactly')
        ->assertSet('reviewItems.0.allergens.0.label', __('menu.allergens.options.milk'))
        ->assertSee('Extra herbs')
        ->assertSee(__('preparation.selection.summary', ['ticket' => $item->kitchen_ticket_id, 'rows' => 1, 'portions' => 2]));
});

test('a full preparation ticket labels all of its portions separately from the ready filter', function (): void {
    $fixture = PreparationWorkflowFixture::create();
    $item = $fixture['items']->first();
    $item->forceFill(['status' => KitchenTicketItemStatus::Ready])->save();

    $page = Livewire::actingAs($fixture['actors']['cook'])
        ->withQueryParams(['branch' => $fixture['branch']->id, 'department' => $fixture['kitchen']->id, 'filter' => 'ready', 'ticket' => $item->kitchen_ticket_id])
        ->test(Dashboard::class)
        ->assertSet('portionCount', 4)
        ->assertSee(__('preparation.selected_ticket', ['number' => $item->kitchen_ticket_id]))
        ->assertSee(__('preparation.ticket_portions', ['count' => 4]))
        ->assertDontSee(__('preparation.portions_count', ['count' => 4]));

    expect($page->get('tickets.0.items'))->toHaveCount(2);
});
