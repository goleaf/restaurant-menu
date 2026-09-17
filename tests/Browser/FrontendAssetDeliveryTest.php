<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Invitation;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\QrCode;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Routing\Events\ResponsePrepared;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Pest\Browser\Api\PendingAwaitablePage;
use Tests\Support\IsolatedBrowserIdentity;

beforeEach(function (): void {
    $this->withVite();
    IsolatedBrowserIdentity::configure();
    $this->seed(SystemPermissionsSeeder::class);
});

test('one bootstrap retains its runtime and disposes page listeners through ten Livewire visits and cached history', function (): void {
    $fixture = frontendAssetFixture();
    $page = visit(route('login', absolute: false));
    $page->assertPresent('[data-layout="auth"]');
    frontendAssertSharedBootstrap($page);
    frontendAssetLogin($page, $fixture['owner'], $fixture['branch']);
    frontendObserveLifecycle($page);

    $visits = [
        [$fixture['availabilityUrl'], '[data-page="availability-workspace"]', null],
        [$fixture['staffUrl'], '[data-staff-workspace]', 'staff'],
        [$fixture['dashboardUrl'], '[data-workspace-navigation]', null],
        [$fixture['menuUrl'], '[data-page="branch-menu"]', 'menu'],
        [$fixture['staffUrl'], '[data-staff-workspace]', 'staff'],
        [route('organizations.index', absolute: false), '[data-page="organizations"]', null],
        [$fixture['menuUrl'].'?section=variants', '[data-page="branch-menu"]', 'menu'],
        [$fixture['staffUrl'], '[data-staff-workspace]', 'staff'],
        [$fixture['menuUrl'], '[data-page="branch-menu"]', 'menu'],
        [$fixture['staffUrl'], '[data-staff-workspace]', 'staff'],
    ];

    foreach ($visits as [$url, $selector, $module]) {
        frontendCaptureWorkspace($page);
        frontendAssetNavigate($page, $url);
        $page->assertPresent($selector)->assertScript('window.frontendAssetDocument', 'same-document');
        if ($module !== null) {
            frontendAssertModuleReady($page, $module);
        } elseif ($url === $fixture['availabilityUrl']) {
            frontendAssertAvailabilityReady($page);
        }
        $page->assertScript('document.querySelectorAll("[data-component=notifications-unread-count]").length', 1)
            ->assertScript('document.querySelectorAll("[data-workspace-navigation]").length', 1);
        frontendAssertLifecycle($page, $url);
    }

    $page->script('history.back();');
    $page->assertPathIs($fixture['menuUrl']);
    frontendAssertModuleReady($page, 'menu');
    $page->script('history.forward();');
    $page->assertPathIs($fixture['staffUrl']);
    frontendAssertModuleReady($page, 'staff');
    $page->assertScript('window.frontendAssetDocument', 'same-document');
    frontendAssertSharedBootstrap($page);
    frontendAssertSingleNotificationPoll($page);

    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();

    $page->navigate(route('public.qr.show', ['token' => $fixture['qr']->public_token], false))
        ->assertPresent('[data-layout="guest"]');
    frontendAssertSharedBootstrap($page);
    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

test('direct menu and staff loads retain validation drafts and navigation guards after morphs', function (): void {
    $fixture = frontendAssetFixture();
    $page = visit(route('login', absolute: false));
    frontendAssetLogin($page, $fixture['owner'], $fixture['branch']);
    $page->navigate($fixture['menuUrl'].'?section=availability')->assertPathIs($fixture['availabilityUrl']);
    frontendAssertAvailabilityReady($page);
    frontendAssertSharedBootstrap($page);
    frontendAssetNavigate($page, $fixture['menuUrl'].'?section=variants');
    frontendAssertModuleReady($page, 'menu');
    frontendAssertSharedBootstrap($page);
    frontendObserveRequests($page);
    $page->click('[data-menu-section="catalog"]')->assertPresent('[data-section="menu-catalog"]');
    frontendAssertModuleReady($page, 'menu');
    $page->click('article[wire\\:key="menu-item-'.$fixture['item']->id.'"] > div:first-child a[wire\\:navigate]');
    $prefix = '#edit-menu-item-'.$fixture['item']->id;
    $page->assertPresent('[data-page="dish-card"]')
        ->click($prefix.'-tab-lt')->fill($prefix.'-panel-lt input[type="text"]', '')
        ->click('form[wire\\:submit="saveItem"] button[type="submit"]')
        ->assertPresent($prefix.'-panel-lt [role="alert"]')
        ->assertAttribute($prefix.'-tab-lt', 'aria-selected', 'true');
    $page->click('[data-menu-section="photos"]')->assertVisible('[data-menu-item-images]');
    frontendAssertModuleReady($page, 'menu');
    $page->assertScript('typeof Alpine.$data(document.querySelector("[data-menu-item-images]")).clear', 'function');
    frontendAssertSuccessfulRequest($page, 'saveItem');
    $page->click('[data-menu-section="main"]')->assertVisible($prefix.'-panel-lt');
    $page->fill($prefix.'-panel-lt input[type="text"]', 'Unfinished translated dish');

    $target = json_encode($fixture['staffUrl'], JSON_THROW_ON_ERROR);
    $page->script("Livewire.navigate({$target});");
    $page->assertVisible('dialog[data-modal="menu-workspace-unsaved"]')
        ->click('dialog[data-modal="menu-workspace-unsaved"] button[x-on\\:click="cancelNavigation"]')
        ->assertValue($prefix.'-panel-lt input[type="text"]', 'Unfinished translated dish');
    expect($fixture['item']->translations()->where('language_code', 'lt')->value('name'))->toBe('Asset fixture dish');
    $page->script("Livewire.navigate({$target});");
    $page->click('dialog[data-modal="menu-workspace-unsaved"] button[x-on\\:click="discardAndNavigate"]')
        ->assertPathIs($fixture['staffUrl']);

    $page->refresh();
    frontendAssertModuleReady($page, 'staff');
    frontendAssertSharedBootstrap($page);
    frontendObserveRequests($page);
    $page->resize(390, 844)->click('button[wire\\:click="openInvitation"]')
        ->assertVisible('[data-staff-editor]')
        ->fill('input[name="invitationForm.email"]', 'invalid-email')
        ->click('form[wire\\:submit="previewInvitation"] button[type="submit"]')
        ->assertVisible('[data-staff-editor] ui-field:has(input[name="invitationForm.email"]) [role="alert"]')
        ->assertValue('input[name="invitationForm.email"]', 'invalid-email');
    frontendAssertModuleReady($page, 'staff');
    $page->assertScript('typeof Alpine.$data(document.querySelector("[data-staff-editor]")).present', 'function')
        ->fill('input[name="invitationForm.email"]', 'unsaved.asset@example.test');
    $dashboard = json_encode($fixture['dashboardUrl'], JSON_THROW_ON_ERROR);
    $page->script("Livewire.navigate({$dashboard});");
    $page->assertVisible('dialog[data-modal="staff-workspace-unsaved"]')
        ->click('dialog[data-modal="staff-workspace-unsaved"] button[\\@click="cancelNavigation"]')
        ->assertValue('input[name="invitationForm.email"]', 'unsaved.asset@example.test');
    frontendAssertSuccessfulRequest($page, 'previewInvitation');
    expect(Invitation::query()->where('email', 'unsaved.asset@example.test')->exists())->toBeFalse();
    $page->script("Livewire.navigate({$dashboard});");
    $page->click('dialog[data-modal="staff-workspace-unsaved"] button[\\@click="discardAndNavigate"]')
        ->assertPathIs(route('restaurant.dashboard', absolute: false))->assertQueryStringHas('branch', (string) $fixture['branch']->id)
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

test('staff names and invitation recipients remain readable beside translated actions in narrow containers', function (): void {
    $fixture = frontendAssetFixture();
    $colleague = User::factory()->create([
        'name' => 'Александра Константиновна Длиннофамильская',
        'email' => 'alexandra.long.recipient@example.test',
    ]);
    $organizationMember = OrganizationUser::factory()->forOrganization($fixture['organization'])->forUser($colleague)
        ->forSystemRole(SystemRole::Waiter)->active()->create();
    BranchUser::factory()->forBranch($fixture['branch'])->forUser($colleague)->forRole($organizationMember->role)->suspended()->create([
        'assigned_by_user_id' => $fixture['owner']->id,
    ]);
    $invitation = Invitation::factory()->forOrganization($fixture['organization'])->pending()->create([
        'brand_id' => $fixture['branch']->brand_id,
        'branch_id' => $fixture['branch']->id,
        'email' => 'long.invitation.recipient@example.test',
        'invited_by_user_id' => $fixture['owner']->id,
    ]);
    $page = visit(route('login', absolute: false));
    frontendAssetLogin($page, $fixture['owner'], $fixture['branch']);

    foreach ([
        ['employees', 'member-'.$organizationMember->id, $colleague->name, 'a[href*="/staff/members/'.$organizationMember->id.'"]'],
        ['invitations', 'invitation-'.$invitation->id, $invitation->email, 'button:first-child'],
    ] as [$section, $key, $label, $primaryAction]) {
        $page->navigate($fixture['staffUrl'].'?lang=ru&section='.$section)->assertSee($label);
        frontendAssertModuleReady($page, 'staff');

        foreach ([[390, 1, false], [780, 2, false], [1100, 1, true]] as [$width, $zoom, $editorOpen]) {
            $page->resize($width, 1000)->script('document.documentElement.style.zoom = '.json_encode((string) $zoom, JSON_THROW_ON_ERROR).';');
            if ($editorOpen) {
                $page->click('button[wire\\:click="openInvitation"]')->assertVisible('[data-staff-editor]');
            }

            $selector = 'article[wire\\:key="'.$key.'"]';
            $page->assertVisible($selector.' h3')->assertVisible($selector.' '.$primaryAction);
            $page->script('document.fonts.ready.then(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(() => resolve(true)))))');
            $geometry = $page->script('(() => {
                const row = document.querySelector('.json_encode($selector, JSON_THROW_ON_ERROR).');
                const summary = row.firstElementChild.getBoundingClientRect();
                const name = row.querySelector("h3").getBoundingClientRect();
                const actions = row.lastElementChild.getBoundingClientRect();
                const bounds = row.getBoundingClientRect();
                const scale = bounds.width / row.offsetWidth;
                return {
                    nameWidth: name.width / scale,
                    availableWidth: bounds.width / scale,
                    actionsOverlap: actions.left < summary.right - 1 && actions.top < summary.bottom - 1,
                    outsideControls: [...row.querySelectorAll("button, a")].map(control => {
                        const rect = control.getBoundingClientRect();
                        return { label: control.textContent.trim(), left: rect.left, right: rect.right, rowLeft: bounds.left, rowRight: bounds.right };
                    }).filter(control => control.left < control.rowLeft - 1 || control.right > control.rowRight + 1),
                    controlsInside: [...row.querySelectorAll("button, a")].every(control => {
                        const rect = control.getBoundingClientRect();
                        return rect.left >= bounds.left - 1 && rect.right <= bounds.right + 1;
                    }),
                };
            })()');

            expect($geometry['nameWidth'])->toBeGreaterThanOrEqual(min(200, $geometry['availableWidth'] * 0.8))
                ->and($geometry['actionsOverlap'])->toBeFalse()
                ->and($geometry['controlsInside'])->toBeTrue(json_encode(['section' => $section, 'width' => $width, 'zoom' => $zoom, 'editorOpen' => $editorOpen, 'geometry' => $geometry], JSON_THROW_ON_ERROR));
        }
    }

    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

test('failed shared bootstrap keeps a translated inert editor until explicit native reload', function (string $module, string $locale): void {
    $fixture = frontendAssetFixture();
    $page = visit(route('login', absolute: false));
    frontendAssetLogin($page, $fixture['owner'], $fixture['branch']);
    frontendFailNextBootstrap($fixture[$module.'Url']);
    $page->navigate($fixture[$module.'Url'].'?lang='.$locale);
    $page->assertScript('window.frontendAssetFault.failed', 1);
    frontendAssertModuleBlocked($page, $module);
    $page->assertSee(__('frontend.editor_loading', [], $locale))
        ->assertSee(__('frontend.editor_loading_help', [], $locale))
        ->assertSee(__('frontend.reload', [], $locale))
        ->assertScript('typeof window.Livewire', 'undefined')
        ->assertScript('typeof window.Alpine', 'undefined');
    $page->script('document.querySelector("[data-page-module] button")?.click();');
    $page->wait(0.1)->assertScript('window.frontendAssetFault.requests.length', 0)
        ->assertScript('window.frontendAssetFault.errors.length', 0)
        ->assertScript('window.frontendAssetFault.rejections.length', 0);
    expect($fixture['item']->translations()->where('language_code', 'lt')->value('name'))->toBe('Asset fixture dish');
    expect(Invitation::query()->count())->toBe(0);
    $selector = '[data-page-module-status="'.$module.'"] [data-page-module-reload]';
    $page->assertAttribute($selector, 'href', '')
        ->assertAttributeMissing($selector, 'wire:navigate')
        ->assertScript('document.querySelector('.json_encode($selector, JSON_THROW_ON_ERROR).').tagName', 'A');
    $page->click($selector)->assertPathIs($fixture[$module.'Url'])->assertQueryStringHas('lang', $locale);
    frontendAssertModuleReady($page, $module);
    frontendAssertSharedBootstrap($page);
    $page->assertScript('typeof window.frontendAssetFault', 'undefined')->assertNoJavaScriptErrors();
})->with([
    'menu Lithuanian' => ['menu', 'lt'],
    'staff Russian' => ['staff', 'ru'],
]);

test('cached back and forward initializes eager providers before unlocking their dirty editors', function (string $module): void {
    $fixture = frontendAssetFixture();
    $page = visit(route('login', absolute: false));
    frontendAssetLogin($page, $fixture['owner'], $fixture['branch']);
    frontendObserveLifecycle($page);
    frontendAssetNavigate($page, $fixture[$module.'Url']);
    frontendAssertModuleReady($page, $module);
    frontendCaptureWorkspace($page);
    $page->script('history.back();');
    $page->assertPathIs(route('restaurant.dashboard', absolute: false))->assertQueryStringHas('branch', (string) $fixture['branch']->id)->assertMissing('[data-page-module]');
    frontendAssertDisposedWorkspace($page);
    $page->script('history.forward();');
    $page->assertPathIs($fixture[$module.'Url']);
    frontendAssertModuleReady($page, $module);
    $page->assertScript('window.frontendLifecycle.cachedVisits', 2)
        ->assertScript('window.frontendLifecycle.alpine === Alpine && window.frontendLifecycle.livewire === Livewire')
        ->assertScript('window.frontendLifecycle.alpineInitializations', 0);

    if ($module === 'menu') {
        $page->click('article[wire\\:key="menu-item-'.$fixture['item']->id.'"] > div:first-child a[wire\\:navigate]');
        $page->assertPresent('[data-page="dish-card"]');
        $input = '#edit-menu-item-'.$fixture['item']->id.'-panel-lt input[type="text"]';
        $page->click('#edit-menu-item-'.$fixture['item']->id.'-tab-lt')
            ->assertQueryStringHas('language', 'lt')->fill($input, 'History retains dish draft');
        $draft = 'History retains dish draft';
    } else {
        $input = 'input[name="invitationForm.email"]';
        $page->click('button[wire\\:click="openInvitation"]')->fill($input, 'history-draft@example.test');
        $draft = 'history-draft@example.test';
    }

    $page->script('Livewire.navigate('.json_encode($fixture['dashboardUrl'], JSON_THROW_ON_ERROR).');');
    $dialog = 'dialog[data-modal="'.$module.'-workspace-unsaved"]';
    $page->assertVisible($dialog);
    $cancel = $module === 'menu' ? 'button[x-on\\:click="cancelNavigation"]' : 'button[\\@click="cancelNavigation"]';
    $page->click($dialog.' '.$cancel)->assertValue($input, $draft);
    expect($fixture['item']->translations()->where('language_code', 'lt')->value('name'))->toBe('Asset fixture dish');
    expect(Invitation::query()->where('email', 'history-draft@example.test')->exists())->toBeFalse();
    frontendAssertSharedBootstrap($page);
    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
})->with(['menu', 'staff']);

test('an in flight dish language change blocks navigation without discarding or replaying its draft', function (): void {
    $fixture = frontendAssetFixture();
    $page = visit(route('login', absolute: false));
    frontendAssetLogin($page, $fixture['owner'], $fixture['branch']);
    frontendAssetNavigate($page, $fixture['menuUrl']);
    $page->click('article[wire\:key="menu-item-'.$fixture['item']->id.'"] > div:first-child a[wire\:navigate]')
        ->assertPresent('[data-page="dish-card"]');
    $input = '#edit-menu-item-'.$fixture['item']->id.'-panel-en input[type=text]';
    $page->fill($input, 'Draft survives an in flight language change');
    $destination = json_encode($fixture['dashboardUrl'], JSON_THROW_ON_ERROR);
    $page->script(<<<JAVASCRIPT
        (() => {
            window.frontendPendingDish = { observed: false };
            const unsubscribe = Livewire.interceptMessage(({ message, onSend }) => {
                if (!message.component.el.matches('[data-page="dish-card"]')) return;
                onSend(() => {
                    unsubscribe();
                    const shell = Alpine.\$data(document.querySelector('[data-workspace-navigation]'));
                    const editor = Alpine.\$data(message.component.el);
                    window.frontendPendingDish = { observed: true, pending: shell.pending, dirty: editor.hasUnsavedChanges() };
                    Livewire.navigate({$destination});
                    window.frontendPendingDish.blocked = shell.blocked;
                });
            });
        })()
        JAVASCRIPT);
    $page->click('#edit-menu-item-'.$fixture['item']->id.'-tab-lt')->assertQueryStringHas('language', 'lt')
        ->assertScript('window.frontendPendingDish.observed && window.frontendPendingDish.pending > 0 && window.frontendPendingDish.dirty && window.frontendPendingDish.blocked', true)
        ->assertPresent('[data-page="dish-card"]')
        ->assertValue($input, 'Draft survives an in flight language change')
        ->assertMissing('dialog[data-modal="menu-workspace-unsaved"]');
    expect($fixture['item']->fresh()->name)->toBe('Asset fixture dish')
        ->and($fixture['item']->translations()->where('language_code', 'en')->value('name'))->toBe('Asset fixture dish');
    $page->script('Livewire.navigate('.$destination.');');
    $page->assertVisible('dialog[data-modal="menu-workspace-unsaved"]')
        ->click('button[x-on\:click="cancelNavigation"]')
        ->assertValue($input, 'Draft survives an in flight language change')
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

test('failed shared bootstrap preserves operational state and native reload restores real server controls', function (string $module, string $locale): void {
    $fixture = frontendAssetFixture();
    $operator = frontendAssetOperator($fixture, $module);
    $page = visit(route('login', absolute: false));
    $route = $module === 'waiter' ? 'restaurant.waiter.dashboard' : 'restaurant.kitchen.dashboard';
    frontendAssetLogin($page, $operator, $fixture['branch'], $route);
    $url = route($route, absolute: false);
    frontendFailNextBootstrap($url);
    $page->navigate($url.'?lang='.$locale);
    $page->assertScript('window.frontendAssetFault.failed', 1)
        ->assertVisible('[data-page-module-status="'.$module.'"]')
        ->assertSee(__($module === 'waiter' ? 'frontend.sounds_loading' : 'frontend.timers_loading', [], $locale))
        ->assertSee(__($module === 'waiter' ? 'frontend.sounds_loading_help' : 'frontend.timers_loading_help', [], $locale))
        ->assertScript('typeof window.Livewire', 'undefined');

    if ($module === 'waiter') {
        $page->assertDisabled('[data-waiter-sound-toggle]')->assertDisabled('[data-waiter-sound-test]')
            ->click('[data-zone-scope="all"]')->assertAttribute('[data-zone-scope="mine"]', 'aria-pressed', 'true');
    } else {
        $page->select('select[wire\\:model\\.live="ticketFilter"]', 'completed');
    }

    $page->wait(0.1)->assertScript('window.frontendAssetFault.requests.length', 0)
        ->assertScript('window.frontendAssetFault.errors.length', 0)
        ->assertScript('window.frontendAssetFault.rejections.length', 0);
    expect(Invitation::query()->count())->toBe(0);
    $page->click('[data-page-module-status="'.$module.'"] [data-page-module-reload]')
        ->assertMissing('[data-page-module-status="'.$module.'"]')
        ->assertScript('typeof window.frontendAssetFault', 'undefined');
    frontendAssertSharedBootstrap($page);
    frontendObserveRequests($page);

    if ($module === 'waiter') {
        $page->click('[data-zone-scope="all"]')->assertAttribute('[data-zone-scope="all"]', 'aria-pressed', 'true');
        frontendAssertSuccessfulRequest($page, 'setZoneScope');
    } else {
        $page->assertValue('select[wire\\:model\\.live="ticketFilter"]', 'active')
            ->select('select[wire\\:model\\.live="ticketFilter"]', 'completed')
            ->assertScript('Livewire.find(document.querySelector("[data-page=kitchen-dashboard]").getAttribute("wire:id")).$get("ticketFilter")', 'completed');
        frontendAssertSuccessfulRequest($page, 'ticketFilter');
    }

    $page->assertNoJavaScriptErrors();
})->with([
    'waiter Lithuanian' => ['waiter', 'lt'],
    'kitchen Russian' => ['departments', 'ru'],
]);

test('migrated styles retain five viewport theme contrast and real 44 and 56 pixel controls', function (): void {
    $fixture = frontendAssetFixture();
    $fixture['owner']->roles()->attach(Role::query()->where('code', SystemRole::Superadmin->value)->firstOrFail());
    $department = KitchenDepartment::factory()->for($fixture['branch'])->create();
    $point = ServicePoint::query()->whereKey($fixture['qr']->service_point_id)->firstOrFail();
    $session = TableSession::factory()->forServicePoint($point)->active()->create();
    $order = Order::factory()->forTableSession($session)->sentToDepartments()->create();
    $orderItem = OrderItem::factory()->for($order)->create(['menu_item_id' => $fixture['item']->id, 'item_name' => 'Migration interface dish']);
    $ticket = KitchenTicket::factory()->forOrder($order)->create(['kitchen_department_id' => $department->id]);
    KitchenTicketItem::factory()->forDispatchedOrderItem($ticket, $orderItem)->pending()->create();
    $page = visit(route('login', absolute: false));
    frontendAssetLogin($page, $fixture['owner'], $fixture['branch']);
    $themes = [];

    foreach ([
        [route('local.components', ['lang' => 'ru'], false), '[data-component-reference] button.min-h-touch', 44],
        [route('restaurant.kitchen.dashboard', ['lang' => 'ru'], false), 'button[wire\\:click^="setItemStatus("]', 56],
    ] as [$url, $controls, $minimum]) {
        $page->navigate($url)->assertPresent($controls);
        frontendAssertSharedBootstrap($page);
        foreach (['light', 'dark'] as $appearance) {
            $page->script('Flux.appearance = '.json_encode($appearance, JSON_THROW_ON_ERROR).';');
            $page->assertScript('document.documentElement.classList.contains("dark")', $appearance === 'dark');
            foreach ([[320, 800], [390, 844], [768, 900], [1024, 900], [1440, 1000]] as [$width, $height]) {
                $page->resize($width, $height);
                $metrics = $page->script(str_replace('__CONTROLS__', json_encode($controls, JSON_THROW_ON_ERROR), <<<'JAVASCRIPT'
                    (() => {
                        const root = getComputedStyle(document.documentElement);
                        const canvas = document.createElement('canvas').getContext('2d');
                        const luminance = color => {
                            canvas.fillStyle = color;
                            canvas.fillRect(0, 0, 1, 1);
                            const channels = [...canvas.getImageData(0, 0, 1, 1).data].slice(0, 3).map(value => {
                                value /= 255;
                                return value <= .04045 ? value / 12.92 : ((value + .055) / 1.055) ** 2.4;
                            });
                            return channels[0] * .2126 + channels[1] * .7152 + channels[2] * .0722;
                        };
                        const foreground = luminance(root.color);
                        const background = luminance(root.backgroundColor);
                        const controls = [...document.querySelectorAll(__CONTROLS__)].filter(control => control.getClientRects().length);
                        const rgba = color => {
                            canvas.fillStyle = color;
                            canvas.fillRect(0, 0, 1, 1);
                            return [...canvas.getImageData(0, 0, 1, 1).data].join(',');
                        };
                        return {
                            overflow: document.documentElement.scrollWidth > innerWidth,
                            background: root.backgroundColor,
                            foreground: root.color,
                            token: root.getPropertyValue('--rm-surface').trim(),
                            accentAliasesMatch: ['accent', 'accent-content', 'accent-foreground'].every(name => rgba(root.getPropertyValue('--color-' + name)) === rgba(root.getPropertyValue('--rm-' + name))),
                            primaryAccentMatch: controls.length > 0 && rgba(getComputedStyle(controls[0]).backgroundColor) === rgba(root.getPropertyValue('--rm-accent')),
                            font: getComputedStyle(document.body).fontFamily,
                            contrast: (Math.max(foreground, background) + .05) / (Math.min(foreground, background) + .05),
                            controls: controls.map(control => ({ height: control.getBoundingClientRect().height, radius: parseFloat(getComputedStyle(control).borderTopLeftRadius) })),
                        };
                    })()
                    JAVASCRIPT));
                expect($metrics['overflow'])->toBeFalse()
                    ->and($metrics['token'])->not->toBeEmpty()
                    ->and($metrics['accentAliasesMatch'])->toBeTrue()
                    ->and($metrics['primaryAccentMatch'])->toBeTrue()
                    ->and($metrics['font'])->toContain('Noto Sans')
                    ->and($metrics['contrast'])->toBeGreaterThanOrEqual(4.5)
                    ->and($metrics['controls'])->not->toBeEmpty();
                foreach ($metrics['controls'] as $control) {
                    expect($control['height'])->toBeGreaterThanOrEqual($minimum)
                        ->and($control['radius'])->toBeGreaterThan(0);
                }
                $themes[$appearance] = $metrics;
            }
        }
        expect($themes['light']['background'])->not->toBe($themes['dark']['background'])
            ->and($themes['light']['foreground'])->not->toBe($themes['dark']['foreground']);
    }
    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

/**
 * @return array{owner: User, organization: Organization, branch: Branch, item: MenuItem, qr: QrCode, menuUrl: string, availabilityUrl: string, staffUrl: string, dashboardUrl: string}
 */
function frontendAssetFixture(): array
{
    $owner = User::factory()->create(['password' => 'password']);
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Asset Delivery Restaurant']);
    $branch = Branch::factory()->for($organization)->withDefaultSettings()->create(['name' => 'Asset Delivery Branch']);
    $branch->load('brand');
    $menu = Menu::factory()->forBranch($branch)->active()->withTranslations()->create(['name' => 'Asset fixture menu']);
    $item = MenuItem::factory()->for($menu)->withTranslations()->create(['name' => 'Asset fixture dish', 'description' => 'Asset fixture description']);
    $point = ServicePoint::factory()->for($branch)->create();
    $qr = QrCode::factory()->forServicePoint($point)->active()->create();

    return [
        'owner' => $owner,
        'organization' => $organization,
        'branch' => $branch,
        'item' => $item,
        'qr' => $qr,
        'dashboardUrl' => route('restaurant.dashboard', ['branch' => $branch->id], false),
        'menuUrl' => route('organizations.brands.branches.menu.index', [$organization, $branch->brand, $branch], false),
        'availabilityUrl' => route('organizations.brands.branches.availability.index', [$organization, $branch->brand, $branch], false),
        'staffUrl' => route('organizations.brands.branches.staff.index', [$organization, $branch->brand, $branch], false),
    ];
}

function frontendAssetLogin(PendingAwaitablePage $page, User $user, Branch $branch, string $destination = 'restaurant.dashboard'): void
{
    $page->fill('email', $user->email)->fill('password', 'password')->click('@login-button')
        ->assertPathIs(route($destination, absolute: false))
        ->assertQueryStringHas('branch', (string) $branch->id)
        ->assertSee($branch->name);
}

function frontendAssetNavigate(PendingAwaitablePage $page, string $url): void
{
    $page->script('Livewire.navigate('.json_encode($url, JSON_THROW_ON_ERROR).');');
    $page->assertPathIs((string) parse_url($url, PHP_URL_PATH));
}

function frontendAssertModuleReady(PendingAwaitablePage $page, string $module): void
{
    $selector = '[data-page-module="'.$module.'"]';
    $encoded = json_encode($selector, JSON_THROW_ON_ERROR);
    $method = $module === 'menu' ? 'hasUnsavedChanges' : 'requestNavigation';
    $page->assertPresent($selector)->assertAttributeMissing($selector, 'inert')
        ->assertAttributeMissing($selector, 'x-ignore')
        ->assertScript("typeof Alpine.\$data(document.querySelector({$encoded})).{$method}", 'function')
        ->assertMissing('[data-page-module-status="'.$module.'"]');
}

function frontendAssertAvailabilityReady(PendingAwaitablePage $page): void
{
    $manifest = frontendAssetManifest();
    $stylesheet = json_encode('/build/'.$manifest['resources/scss/availability.scss']['file'], JSON_THROW_ON_ERROR);
    $page->assertPresent('[data-page="availability-workspace"]')
        ->assertScript('typeof Alpine.$data(document.querySelector("[data-page=availability-workspace]")).hasUnsavedChanges', 'function')
        ->assertScript('typeof Alpine.$data(document.querySelector("[data-page=availability-workspace]")).cancelDraft', 'function')
        ->assertScript("[...document.styleSheets].some(sheet => sheet.href && new URL(sheet.href).pathname === {$stylesheet})")
        ->assertScript('getComputedStyle(document.querySelector("[data-page=availability-workspace]")).display', 'grid');
}

function frontendAssertModuleBlocked(PendingAwaitablePage $page, string $module): void
{
    $selector = '[data-page-module="'.$module.'"]';
    $encoded = json_encode($selector, JSON_THROW_ON_ERROR);
    $page->assertPresent($selector)->assertAttribute($selector, 'inert', '')
        ->assertAttribute($selector, 'x-ignore', '')
        ->assertVisible('[data-page-module-status="'.$module.'"]')
        ->assertScript("(() => { const root = document.querySelector({$encoded}); const control = root.querySelector('input, button, a'); control?.focus(); return root.inert && !root.contains(document.activeElement); })()");
}

/** @return array<string, array{file: string}> */
function frontendAssetManifest(): array
{
    return json_decode(File::get(public_path('build/manifest.json')), true, flags: JSON_THROW_ON_ERROR);
}

function frontendAssertSharedBootstrap(PendingAwaitablePage $page): void
{
    $manifest = frontendAssetManifest();
    $path = json_encode('/build/'.$manifest['resources/js/app.js']['file'], JSON_THROW_ON_ERROR);
    $passkeys = json_encode('/build/'.$manifest['node_modules/@simplewebauthn/browser/esm/index.js']['file'], JSON_THROW_ON_ERROR);
    $styles = json_encode(array_map(fn (string $entry): string => '/build/'.$manifest[$entry]['file'], ['resources/css/app.css', 'resources/scss/app.scss']), JSON_THROW_ON_ERROR);
    $page->assertScript("performance.getEntriesByType('resource').filter(entry => new URL(entry.name).pathname === {$path}).length", 1)
        ->assertScript("[...document.scripts].filter(script => script.src && new URL(script.src).pathname === {$path}).length", 1)
        ->assertScript("{$styles}.every(path => [...document.styleSheets].some(sheet => sheet.href && new URL(sheet.href).pathname === path))")
        ->assertScript("performance.getEntriesByType('resource').filter(entry => new URL(entry.name).pathname === {$passkeys}).length", 0)
        ->assertScript('typeof Alpine.version', 'string')
        ->assertScript('typeof Livewire.navigate', 'function')
        ->assertScript('[...document.scripts].filter(script => /\\/livewire[^/]*\\/livewire(?:\\.min)?\\.js/.test(script.src)).length', 0)
        ->assertScript('performance.getEntriesByType("resource").filter(entry => /\\/(menu|staff|waiter|departments)-[^/]+\\.js/.test(new URL(entry.name).pathname)).length', 0);
    expect(array_intersect(['resources/js/menu.js', 'resources/js/staff.js', 'resources/js/waiter.js', 'resources/js/departments.js'], array_keys($manifest)))->toBe([]);
}

function frontendFailNextBootstrap(string $path): void
{
    $asset = '/build/'.frontendAssetManifest()['resources/js/app.js']['file'];
    $pending = true;
    Event::listen(ResponsePrepared::class, function (ResponsePrepared $event) use ($path, $asset, &$pending): void {
        if (! $pending || $event->request->getPathInfo() !== $path || ! $event->request->isMethod('GET')) {
            return;
        }
        $content = $event->response->getContent();
        if (! is_string($content)) {
            return;
        }
        $content = preg_replace('~(<script\b[^>]*\bsrc=")[^"]*'.preg_quote($asset, '~').'(")~', '$1/build/manifest.json$2', $content, 1, $replaced);
        expect($replaced)->toBe(1);
        $pending = false;
        $instrumentation = <<<'HTML'
            <script>
            window.frontendAssetFault = { failed: 0, requests: [], errors: [], rejections: [] };
            document.addEventListener('error', event => {
                if (event.target instanceof HTMLScriptElement && event.target.src.endsWith('/build/manifest.json')) window.frontendAssetFault.failed++;
            }, true);
            window.addEventListener('error', event => {
                if (event instanceof ErrorEvent) window.frontendAssetFault.errors.push(event.message);
            });
            window.addEventListener('unhandledrejection', event => window.frontendAssetFault.rejections.push(String(event.reason)));
            const frontendOriginalFetch = window.fetch;
            window.fetch = (...args) => {
                window.frontendAssetFault.requests.push(String(args[0]));
                return frontendOriginalFetch(...args);
            };
            </script>
            HTML;
        $event->response->setContent(str_replace('<head>', '<head>'.$instrumentation, (string) $content));
    });
}

function frontendObserveRequests(PendingAwaitablePage $page): void
{
    $page->script(<<<'JAVASCRIPT'
        (() => {
            const original = window.fetch;
            window.frontendRequests = [];
            window.fetch = async (...args) => {
                const request = String(args[0]).includes('/livewire') && typeof args[1]?.body === 'string'
                    ? { body: args[1].body, status: null } : null;
                if (request) window.frontendRequests.push(request);
                const response = await original(...args);
                if (request) request.status = response.status;
                return response;
            };
        })()
        JAVASCRIPT);
}

function frontendAssertSuccessfulRequest(PendingAwaitablePage $page, string $action): void
{
    $action = json_encode($action, JSON_THROW_ON_ERROR);
    $page->assertScript("window.frontendRequests.some(request => request.status === 200 && request.body.includes({$action}))");
}

function frontendAssertSingleNotificationPoll(PendingAwaitablePage $page): void
{
    $page->resize(1440, 1000)->assertVisible('[data-notification-trigger]');
    frontendObserveRequests($page);
    frontendAssertSuccessfulRequest($page, 'refreshUnreadCount');
    $page->script('window.frontendRequests = [];');
    $page->wait(6);
    $page->assertScript('window.frontendRequests.filter(request => request.body.includes("refreshUnreadCount")).length', 1)
        ->assertScript('window.frontendRequests.filter(request => request.body.includes("refreshUnreadCount")).every(request => request.status === 200 && request.body.split(\'"refreshUnreadCount"\').length === 2)');
}

function frontendObserveLifecycle(PendingAwaitablePage $page): void
{
    $page->script(<<<'JAVASCRIPT'
        (() => {
            window.frontendAssetDocument = 'same-document';
            const probe = window.frontendLifecycle = {
                alpine: Alpine, livewire: Livewire, alpineInitializations: 0,
                cachedVisits: 0, signals: [], intervals: new Map(), subscriptions: new Set(), previous: null,
            };
            document.addEventListener('alpine:init', () => probe.alpineInitializations++);
            document.addEventListener('livewire:navigate', event => { if (event.detail.cached) probe.cachedVisits++; });
            const add = EventTarget.prototype.addEventListener;
            EventTarget.prototype.addEventListener = function (type, listener, options) {
                if ((this === window || this === document) && options?.signal) probe.signals.push({ type, signal: options.signal });
                return add.call(this, type, listener, options);
            };
            const intercept = Livewire.interceptMessage;
            Livewire.interceptMessage = (...args) => {
                const unsubscribe = intercept(...args);
                const token = {};
                probe.subscriptions.add(token);
                return () => { probe.subscriptions.delete(token); unsubscribe(); };
            };
            const start = window.setInterval;
            const stop = window.clearInterval;
            window.setInterval = (callback, delay, ...args) => {
                const id = start(callback, delay, ...args);
                probe.intervals.set(id, delay);
                return id;
            };
            window.clearInterval = id => { probe.intervals.delete(id); stop(id); };
        })()
        JAVASCRIPT);
}

function frontendCaptureWorkspace(PendingAwaitablePage $page): void
{
    $page->script('(() => { const previousEditor = document.querySelector("[data-page-module], [data-page=availability-workspace], [data-layout=restaurant-dashboard]"); window.frontendLifecycle.previous = previousEditor ? Alpine.$data(previousEditor) : null; window.frontendLifecycle.previousShell = Alpine.$data(document.querySelector("[data-workspace-navigation]")); })();');
}

function frontendAssertDisposedWorkspace(PendingAwaitablePage $page): void
{
    $page->assertScript('window.frontendLifecycle.previous === null || (window.frontendLifecycle.previous.destroyed && window.frontendLifecycle.previous.abortController.signal.aborted && window.frontendLifecycle.previous.unsubscribe === null)')
        ->assertScript('window.frontendLifecycle.previousShell.abortController.signal.aborted && window.frontendLifecycle.previousShell.unsubscribe === null');
}

function frontendAssertLifecycle(PendingAwaitablePage $page, string $url): void
{
    frontendAssertDisposedWorkspace($page);
    $page->assertScript('window.frontendLifecycle.alpine === Alpine && window.frontendLifecycle.livewire === Livewire')
        ->assertScript('window.frontendLifecycle.alpineInitializations', 0)
        ->assertScript('window.frontendLifecycle.signals.filter(entry => entry.type === "livewire:navigate" && !entry.signal.aborted).length === document.querySelectorAll("[data-page-module], [data-page=availability-workspace], [data-layout=restaurant-dashboard], [data-workspace-navigation]").length');
    $key = json_encode($url, JSON_THROW_ON_ERROR);
    $page->assertScript("(() => {
        const probe = window.frontendLifecycle;
        const sample = JSON.stringify({ subscriptions: probe.subscriptions.size, intervals: [...probe.intervals.values()].sort(), signals: probe.signals.filter(entry => !entry.signal.aborted).map(entry => entry.type).sort() });
        probe.baselines ??= {};
        probe.baselines[{$key}] ??= sample;
        return probe.baselines[{$key}] === sample;
    })()");
}

/** @param array{organization: Organization, branch: Branch} $fixture */
function frontendAssetOperator(array $fixture, string $module): User
{
    $operator = User::factory()->create(['password' => 'password']);
    $roleCode = $module === 'waiter' ? SystemRole::Waiter : SystemRole::HeadChef;
    $permissionCode = $module === 'waiter' ? SystemPermission::ViewOrders : SystemPermission::ViewKitchen;
    $role = Role::query()->where('code', $roleCode->value)->firstOrFail();
    $permission = Permission::query()->where('code', $permissionCode->value)->firstOrFail();
    $role->permissions()->updateExistingPivot($permission->id, ['enabled' => true]);
    $fixture['organization']->users()->syncWithoutDetachingOrFail([
        $operator->id => [
            'role_id' => $role->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ],
    ]);
    KitchenDepartment::factory()->for($fixture['branch'])->create(['name' => 'Asset fixture kitchen']);

    return $operator;
}
