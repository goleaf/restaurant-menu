<?php

declare(strict_types=1);

use App\Actions\Departments\UpdateDepartmentTicketItemStatusAction;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\SystemRole;
use App\Livewire\Departments\Dashboard;
use App\Models\OrderStatusLog;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\TableSessionGuest;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Livewire\Livewire;
use Tests\Support\PreparationWorkflowFixture;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->fixture = PreparationWorkflowFixture::create();
});

test('preparation recovers pending notification delivery after a complete component reload', function (): void {
    $fixture = $this->fixture;
    $item = $fixture['items']->first();
    Exceptions::fake();
    $fail = true;
    Event::listen(NotificationSent::class, function (NotificationSent $event) use (&$fail): void {
        if ($fail && $event->notifiable instanceof TableSessionGuest) {
            throw new RuntimeException('Preparation retry fixture');
        }
    });
    $result = app(UpdateDepartmentTicketItemStatusAction::class)->handlePreparation(
        $item->id, KitchenTicketItemStatus::Ready, $fixture['actors']['cook'], $fixture['branch']->id,
        $item->status, (string) $item->getRawOriginal('updated_at'), $item->kitchen_ticket_id,
    );
    expect($result->notification_delivery_pending)->toBeTrue();
    $fail = false;
    Livewire::actingAs($fixture['actors']['cook'])
        ->withQueryParams(['branch' => $fixture['branch']->id, 'department' => $fixture['kitchen']->id, 'filter' => 'ready'])
        ->test(Dashboard::class)
        ->assertSee(__('preparation.notifications.pending'))
        ->call('retryNotification', $item->id)->assertHasNoErrors();
    expect($fixture['guest']->notifications()->where('type', 'kitchen_item_ready')->count())->toBe(1)
        ->and(OrderStatusLog::query()->where('metadata->kitchen_ticket_item_id', $item->id)->where('event', 'ticket_item_status_changed')->count())->toBe(1);
});

test('preparation reviewed batch rejects a revoked family while another family remains accessible', function (): void {
    $fixture = $this->fixture;
    $item = $fixture['items']->first();
    $page = Livewire::actingAs($fixture['actors']['chef'])->withQueryParams(['branch' => $fixture['branch']->id, 'department' => 'all'])
        ->test(Dashboard::class)->set('selection.itemIds', [$item->id])->call('reviewSelection')->assertHasNoErrors();
    $membership = OrganizationUser::query()->where('organization_id', $fixture['organization']->id)->where('user_id', $fixture['actors']['chef']->id)->firstOrFail();
    $membership->forceFill(['role_id' => Role::query()->where('code', SystemRole::Bartender)->firstOrFail()->id])->save();
    $page->call('refreshDepartment')->assertForbidden();
    expect($item->fresh()->status)->toBe(KitchenTicketItemStatus::New);
});

test('preparation reviews only explicitly visible selected rows from one ticket', function (): void {
    $fixture = $this->fixture;
    $kitchenItem = $fixture['items']->first();
    $barItem = $fixture['items']->last();
    $page = Livewire::actingAs($fixture['actors']['chef'])->withQueryParams(['branch' => $fixture['branch']->id, 'department' => 'all'])
        ->test(Dashboard::class)->set('selection.itemIds', [$kitchenItem->id, $barItem->id])->call('reviewSelection')
        ->assertHasErrors('selection.itemIds');
    expect($page->get('reviewItems'))->toBe([])
        ->and($kitchenItem->fresh()->status)->toBe(KitchenTicketItemStatus::New)
        ->and($barItem->fresh()->status)->toBe(KitchenTicketItemStatus::New);
});

test('preparation single request cannot change scope and mutate a formerly visible row', function (): void {
    $fixture = $this->fixture;
    $item = $fixture['items']->first();
    Livewire::actingAs($fixture['actors']['chef'])->withQueryParams(['branch' => $fixture['branch']->id, 'department' => $fixture['kitchen']->id])
        ->test(Dashboard::class)->update(
            calls: [['method' => 'setItemStatus', 'params' => [$item->id, 'ready', 'new', (string) $item->getRawOriginal('updated_at'), $item->kitchen_ticket_id], 'path' => '']],
            updates: ['selectedDepartmentId' => (string) $fixture['bar']->id],
        )->assertHasErrors('ticket_item_status');
    expect($item->fresh()->status)->toBe(KitchenTicketItemStatus::New);
});

test('preparation drops pending notification identity when scope changes and family access is revoked', function (): void {
    $fixture = $this->fixture;
    $item = $fixture['items']->first();
    Exceptions::fake();
    Event::listen(NotificationSent::class, function (NotificationSent $event): void {
        if ($event->notifiable instanceof TableSessionGuest) {
            throw new RuntimeException('Preparation scope pending fixture');
        }
    });
    $page = Livewire::actingAs($fixture['actors']['chef'])->withQueryParams(['branch' => $fixture['branch']->id, 'department' => $fixture['kitchen']->id, 'filter' => 'active'])
        ->test(Dashboard::class)
        ->call('setItemStatus', $item->id, 'in_progress', 'new', (string) $item->getRawOriginal('updated_at'), $item->kitchen_ticket_id)
        ->assertHasNoErrors();
    expect($page->get('pendingNotifications'))->toHaveKey($item->id);
    $page->set('selectedDepartmentId', (string) $fixture['bar']->id)->assertSet('pendingNotifications', []);
    $membership = OrganizationUser::query()->where('organization_id', $fixture['organization']->id)->where('user_id', $fixture['actors']['chef']->id)->firstOrFail();
    $membership->forceFill(['role_id' => Role::query()->where('code', SystemRole::Bartender)->firstOrFail()->id])->save();
    $page->call('refreshDepartment')->assertOk()->assertSet('pendingNotifications', [])->assertDontSee('Soup')->assertDontSee('Pasta');
});
