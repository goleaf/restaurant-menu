<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;

test('kitchen delay timers advance locally and expose accessible status changes', function () {
    $this->withVite();
    $this->freezeTime();
    $this->seed(SystemPermissionsSeeder::class);
    $owner = User::factory()->create();
    $chef = User::factory()->create(['email' => 'timer-chef@example.test', 'password' => 'password']);
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Timer Test Group']);
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $department = KitchenDepartment::factory()->for($branch)->create(['name' => 'Timer Test Kitchen']);
    $servicePoint = ServicePoint::factory()->for($branch)->create(['name' => 'Timer Test Table']);
    $session = TableSession::factory()->forServicePoint($servicePoint)->active()->create();
    $timerItems = [];
    foreach (['attention' => 599, 'delayed' => 899, 'completed' => 600] as $name => $elapsed) {
        $order = Order::factory()->forTableSession($session)->sentToDepartments()->create();
        $orderItem = OrderItem::factory()->for($order)->create(['item_name' => 'Timer '.$name]);
        $ticket = KitchenTicket::factory()->forOrder($order)->create([
            'kitchen_department_id' => $department->id,
            'department_name' => $department->name,
            'sent_at' => now()->subSeconds($elapsed),
        ]);
        $item = KitchenTicketItem::factory()->forDispatchedOrderItem($ticket, $orderItem)->create([
            'status' => $name === 'completed' ? KitchenTicketItemStatus::Ready : KitchenTicketItemStatus::New,
            'served_at' => $name === 'completed' ? now()->subMinutes(5) : null,
            'served_by_user_id' => $name === 'completed' ? $chef->id : null,
        ]);
        $timerItems[$name] = $item->id;
    }
    $role = Role::query()->where('code', SystemRole::HeadChef->value)->firstOrFail();
    $permission = Permission::query()->where('code', SystemPermission::ViewKitchen->value)->firstOrFail();
    $role->permissions()->updateExistingPivot($permission->id, ['enabled' => true]);
    $organization->users()->syncWithoutDetachingOrFail([
        $chef->id => [
            'role_id' => $role->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ],
    ]);

    $page = visit(route('login', absolute: false));
    $page->fill('email', $chef->email)->fill('password', 'password')->click('@login-button')->assertPathIs('/dashboard');
    $page->script(<<<'JAVASCRIPT'
        (() => {
            window.__kitchenIntervals = new Set();
            const start = window.setInterval.bind(window);
            const stop = window.clearInterval.bind(window);
            window.setInterval = (callback, delay, ...args) => {
                const id = start(callback, delay, ...args);
                if (delay === 1000) window.__kitchenIntervals.add(id);
                return id;
            };
            window.clearInterval = (id) => {
                window.__kitchenIntervals.delete(id);
                stop(id);
            };
        })()
    JAVASCRIPT);
    $kitchenUrl = json_encode(route('restaurant.kitchen.dashboard', absolute: false), JSON_THROW_ON_ERROR);
    $page->script("new Promise(resolve => { document.addEventListener('livewire:navigated', () => resolve(true), { once: true }); Livewire.navigate({$kitchenUrl}); })");
    $page->assertPresent('[data-page="kitchen-dashboard"]')->assertSee('Timer Test Kitchen');

    $timerIds = json_encode($timerItems, JSON_THROW_ON_ERROR);
    $page->script("window.__kitchenTimerItems = {$timerIds};");
    expect($page->script('window.__kitchenIntervals.size'))->toBe(1);
    $page->assertPresent('#kitchen-dashboard-ticket-item-'.$timerItems['attention'])
        ->assertPresent('#kitchen-dashboard-ticket-item-'.$timerItems['delayed'])
        ->assertMissing('#kitchen-dashboard-ticket-item-'.$timerItems['completed']);

    $page->script('new Promise((resolve) => window.setTimeout(resolve, 2200))');

    $state = $page->script(<<<'JAVASCRIPT'
        (() => {
            const timer = name => document.getElementById(`kitchen-dashboard-ticket-item-${window.__kitchenTimerItems[name]}`)
                ?.closest('article')?.querySelector('[data-kitchen-delay-timer]');
            const attention = timer('attention');
            const delayed = timer('delayed');
            const delayedOverrun = delayed?.querySelector('[data-kitchen-delay-overrun]');
            const attentionValue = attention?.querySelector('[data-kitchen-delay-value]');

            return {
                attentionAdvanced: Number(attentionValue?.dateTime.match(/^PT(\d+)S$/)?.[1]) > 599,
                attentionSource: attention?.dataset.elapsedSeconds,
                attentionReady: attention?.dataset.kitchenDelayTimerReady,
                attentionState: attention?.dataset.delayState,
                attentionStatus: attention?.querySelector('[data-kitchen-delay-status]')?.textContent.trim(),
                statusLive: attention?.querySelector('[data-kitchen-delay-status]')?.getAttribute('aria-live'),
                delayedSource: delayed?.dataset.elapsedSeconds,
                delayedOverrunHidden: delayedOverrun?.hidden,
                delayedOverrunText: delayedOverrun?.textContent,
                delayedReady: delayed?.dataset.kitchenDelayTimerReady,
                delayedState: delayed?.dataset.delayState,
                delayedStatus: delayed?.querySelector('[data-kitchen-delay-status]')?.textContent.trim(),
            };
        })()
    JAVASCRIPT);

    expect($state['attentionAdvanced'])->toBeTrue()
        ->and($state['attentionSource'])->toBe('599')
        ->and($state['attentionReady'])->toBe('true')
        ->and($state['attentionState'])->toBe('attention')
        ->and($state['attentionStatus'])->toBe(__('ui.departments.dashboard.delay_status.attention'))
        ->and($state['statusLive'])->toBe('off')
        ->and($state['delayedSource'])->toBe('899')
        ->and($state['delayedOverrunHidden'])->toBeFalse()
        ->and($state['delayedOverrunText'])->toStartWith(__('ui.departments.dashboard.delay_by', ['time' => '']))
        ->and($state['delayedReady'])->toBe('true')
        ->and($state['delayedState'])->toBe('delayed')
        ->and($state['delayedStatus'])->toBe(__('ui.departments.dashboard.delay_status.delayed'));

    $page->select('select[wire\\:model\\.live="ticketFilter"]', 'completed')
        ->assertPresent('#kitchen-dashboard-ticket-item-'.$timerItems['completed'])
        ->assertMissing('#kitchen-dashboard-ticket-item-'.$timerItems['attention']);
    $completedTimer = '#kitchen-dashboard-ticket-item-'.$timerItems['completed'];
    $readCompleted = 'document.querySelector('.json_encode($completedTimer, JSON_THROW_ON_ERROR).').closest("article").querySelector("[data-kitchen-delay-value]").textContent';
    expect($page->script($readCompleted))->toBe('05:00');
    $page->script('new Promise((resolve) => window.setTimeout(resolve, 2200))');
    expect($page->script($readCompleted))->toBe('05:00')
        ->and($page->script('window.__kitchenIntervals.size'))->toBe(1);

    $page->script("Object.defineProperty(document, 'hidden', { configurable: true, value: true }); document.dispatchEvent(new Event('visibilitychange'));");
    expect($page->script('window.__kitchenIntervals.size'))->toBe(0);
    $page->script("delete document.hidden; document.dispatchEvent(new Event('visibilitychange'));");
    expect($page->script('window.__kitchenIntervals.size'))->toBe(1);

    $guestUrl = json_encode(route('guest.home', absolute: false), JSON_THROW_ON_ERROR);
    $page->script("new Promise(resolve => { document.addEventListener('livewire:navigated', () => resolve(true), { once: true }); Livewire.navigate({$guestUrl}); })");
    $page->assertPathIs('/guest');
    expect($page->script('window.__kitchenIntervals.size'))->toBe(0);
    $page->script("new Promise(resolve => { document.addEventListener('livewire:navigated', () => resolve(true), { once: true }); history.back(); })");
    $page->assertPresent('[data-page="kitchen-dashboard"]');
    expect($page->script('window.__kitchenIntervals.size'))->toBe(1);
    $page->script("new Promise(resolve => { document.addEventListener('livewire:navigated', () => resolve(true), { once: true }); history.forward(); })");
    $page->assertPathIs('/guest');
    expect($page->script('window.__kitchenIntervals.size'))->toBe(0);
    $page->script("new Promise(resolve => { document.addEventListener('livewire:navigated', () => resolve(true), { once: true }); history.back(); })");
    $page->assertPresent('[data-page="kitchen-dashboard"]');
    expect($page->script('window.__kitchenIntervals.size'))->toBe(1);

    $page
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});
