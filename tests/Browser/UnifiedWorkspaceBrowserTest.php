<?php

declare(strict_types=1);

use App\Enums\AuditLogAction;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Support\ComputeUrl;
use Tests\Support\IsolatedBrowserIdentity;

test('the restaurant address heading navigation and edited resource stay together across workspace transitions', function (): void {
    $this->withVite();
    IsolatedBrowserIdentity::configure();
    $this->seed(SystemPermissionsSeeder::class);
    $organization = Organization::factory()->create(['name' => 'Сеть ресторанов с подтверждёнными длинными названиями для проверки контекста']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Tarptautinė restoranų grupė ir šeimos virtuvės bendras valdymo padalinys']);
    $first = Branch::factory()->for($organization)->for($brand)->create(['name' => 'Žąsis — Семейный ресторан А']);
    $second = Branch::factory()->for($first->organization)->for($first->brand)->create(['name' => 'Žąsis — Ресторан Б']);
    expect(mb_strlen($second->name.' — '.$brand->name.' · '.$organization->name))->toBeGreaterThan(100);
    $user = User::factory()->create(['password' => 'password']);
    OrganizationUser::factory()->forOrganization($first->organization)->forUser($user)->forSystemRole(SystemRole::Owner)->active()->create();
    $page = visit(route('login', absolute: false));
    $page->fill('email', $user->email)->fill('password', 'password')->click('@login-button')
        ->navigate(route('organizations.brands.branches.menu.index', [$first->organization_id, $first->brand_id, $first->id], false))->resize(1440, 1000);
    $page->assertSee($first->name)->assertAttribute('[data-navigation-key="menu"]', 'aria-current', 'page');
    $page->click('[data-navigation-key="halls"]')
        ->assertPathIs(route('organizations.brands.branches.areas.index', [$first->organization_id, $first->brand_id, $first->id], false));
    $page->click('[data-navigation-key="team"]');
    $page->assertNoJavaScriptErrors();
    $page->click('.workspace-restaurant__trigger');
    $page->assertVisible('dialog[data-modal="workspace-restaurant"]')
        ->click('dialog[data-modal="workspace-restaurant"] [data-flux-select-button]')
        ->click('dialog[data-modal="workspace-restaurant"] input')->fill('dialog[data-modal="workspace-restaurant"] input', 'Ресторан Б')
        ->keys('dialog[data-modal="workspace-restaurant"] input', 'ArrowDown')
        ->assertMissing('dialog[data-modal="workspace-restaurant"] ui-option[value="'.$first->id.'"]');
    $page->assertVisible('dialog[data-modal="workspace-restaurant"] ui-option[value="'.$second->id.'"]');
    $page->click('dialog[data-modal="workspace-restaurant"] ui-option[value="'.$second->id.'"]')
        ->assertScript('document.querySelector(\'dialog[data-modal="workspace-restaurant"] ui-select\').value', (string) $second->id)
        ->assertScript('document.querySelector(\'dialog[data-modal="workspace-restaurant"] input\').value.length <= 100')
        ->click('dialog[data-modal="workspace-restaurant"] button[type="submit"]')
        ->assertPathIs(route('organizations.brands.branches.staff.index', [$second->organization_id, $second->brand_id, $second->id], false));
    $page->assertSee($second->name)->assertAttribute('[data-navigation-key="team"]', 'aria-current', 'page');
    $page->resize(320, 900)->click('.workspace-restaurant__trigger')
        ->click('dialog[data-modal="workspace-restaurant"] [data-flux-select-button]')
        ->fill('dialog[data-modal="workspace-restaurant"] input', 'Ресторан Б')
        ->keys('dialog[data-modal="workspace-restaurant"] input', 'ArrowDown')
        ->assertMissing('dialog[data-modal="workspace-restaurant"] ui-option[value="'.$first->id.'"]')
        ->assertVisible('dialog[data-modal="workspace-restaurant"] ui-option[value="'.$second->id.'"]')
        ->assertScript(<<<'JS'
            (() => {
                const dialog = document.querySelector('dialog[data-modal="workspace-restaurant"]');
                const popup = dialog.querySelector('[data-flux-options]');
                const bounds = popup.getBoundingClientRect();
                return document.documentElement.scrollWidth <= window.innerWidth
                    && bounds.left >= 0 && bounds.right <= window.innerWidth
                    && Array.from(popup.querySelectorAll('ui-option')).every(option =>
                        option.scrollHeight <= option.clientHeight + 1 && option.scrollWidth <= option.clientWidth + 1);
            })()
            JS)
        ->screenshot(false, 'workspace-long-label-320')
        ->keys('dialog[data-modal="workspace-restaurant"]', 'Escape')
        ->assertMissing('dialog[data-modal="workspace-restaurant"][open]')
        ->assertScript('document.activeElement.matches(".workspace-restaurant__trigger")')
        ->resize(1440, 1000);
    $page->click('[data-navigation-key="overview"]');
    $availabilityPath = route('organizations.brands.branches.availability.index', [$second->organization_id, $second->brand_id, $second->id], false);
    $page->click('[data-dashboard-ordering] a[href*="/availability"]')->assertPathIs($availabilityPath)
        ->assertAttribute('[data-navigation-key="availability"]', 'aria-current', 'page')
        ->click('button[wire\\:click="openPause"]')->fill('textarea[name="pause.reason"]', 'Keep this draft');
    $page->click('[data-navigation-key="menu"]')->assertVisible('dialog[data-modal="availability-unsaved"]');
    $page->click('dialog[data-modal="availability-unsaved"] button[x-on\\:click="cancelNavigation"]')
        ->assertValue('textarea[name="pause.reason"]', 'Keep this draft')
        ->assertPathIs($availabilityPath);
    $page->click('[data-navigation-key="menu"]')->click('dialog[data-modal="availability-unsaved"] button[x-on\\:click="discardAndNavigate"]')
        ->assertPathIs(route('organizations.brands.branches.menu.index', [$second->organization_id, $second->brand_id, $second->id], false));
    expect($second->fresh()->is_temporarily_closed)->toBeFalse()
        ->and($second->fresh()->temporary_closed_reason)->toBeNull()
        ->and($second->fresh()->pause_version)->toBe(0);
    foreach ([320, 390, 768, 1024, 1440] as $width) {
        $page->resize($width, 900);
        $page->script('new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
        expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
        if ($width === 320) {
            expect($page->script('document.querySelector(".menu-workspace-sections").getBoundingClientRect().height'))->toBeLessThan(300);
        }
        $page->screenshot(false, 'unified-workspace-'.$width);
    }
    $page->resize(1440, 1000);
    for ($transition = 0; $transition < 10; $transition++) {
        $page->click('[data-navigation-key="'.($transition % 2 === 0 ? 'team' : 'menu').'"]');
        $page->assertAttribute('[data-navigation-key="'.($transition % 2 === 0 ? 'team' : 'menu').'"]', 'aria-current', 'page');
        $page->assertScript('document.querySelectorAll("[data-workspace-restaurant]").length', 1)
            ->assertScript('document.querySelectorAll("[data-component=notifications-unread-count]").length', 1);
    }
    $page->script('history.back()');
    $page->assertAttribute('[data-navigation-key="team"]', 'aria-current', 'page');
    $page->script('history.forward()');
    $page->assertAttribute('[data-navigation-key="menu"]', 'aria-current', 'page');
    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

test('a shared restaurant preference cannot retarget an ordering draft open in another tab', function (): void {
    $this->withVite();
    IsolatedBrowserIdentity::configure();
    $this->seed(SystemPermissionsSeeder::class);
    $original = Branch::factory()->create([
        'name' => 'Original restaurant B',
        'is_temporarily_closed' => false,
        'temporary_closed_reason' => null,
        'temporary_closed_until' => null,
    ]);
    $different = Branch::factory()->for($original->organization)->for($original->brand)->create([
        'name' => 'Different restaurant A',
        'is_temporarily_closed' => false,
        'temporary_closed_reason' => null,
        'temporary_closed_until' => null,
    ]);
    $user = User::factory()->create(['password' => 'password']);
    OrganizationUser::factory()->forOrganization($original->organization)->forUser($user)->forSystemRole(SystemRole::Owner)->active()->create();

    $tabA = visit(route('login', absolute: false));
    $tabA->fill('email', $user->email)->fill('password', 'password')->click('@login-button');
    $context = $tabA->page()->context();
    $context->addInitScript(<<<'JS'
        window.workspaceRemembered = 0;
        document.addEventListener('livewire:init', () => {
            window.Livewire.interceptMessage(({ message, onSuccess }) => {
                if (!Array.from(message.actions).some(action => action.name === 'remember')) return;
                onSuccess(({ onRender }) => onRender(() => { window.workspaceRemembered++; }));
            });
        });
        JS);
    $originalPath = route('restaurant.dashboard', ['branch' => $original->id], false);
    $tabA->navigate($originalPath)->assertQueryStringHas('branch', (string) $original->id)
        ->assertScript('window.workspaceRemembered', 1);

    $tabBPage = $context->newPage();
    $availabilityPath = route('organizations.brands.branches.availability.index', [$original->organization_id, $original->brand_id, $original->id], false);
    $tabBUrl = ComputeUrl::from($availabilityPath);
    $tabB = new AwaitableWebpage($tabBPage->goto($tabBUrl), $tabBUrl);

    try {
        expect($tabBPage->context())->toBe($context)
            ->and($tabBPage)->not->toBe($tabA->page());
        $tabB->assertPathIs($availabilityPath)
            ->assertSee($original->name)
            ->assertScript('window.workspaceRemembered', 1)
            ->click('button[wire\\:click="openPause"]')
            ->select('select[name="pause.mode"]', 'indefinite')
            ->assertValue('select[name="pause.mode"]', 'indefinite')
            ->fill('textarea[name="pause.reason"]', 'Only restaurant B should pause');

        $tabA->click('.workspace-restaurant__trigger')
            ->assertVisible('dialog[data-modal="workspace-restaurant"]')
            ->click('dialog[data-modal="workspace-restaurant"] [data-flux-select-button]')
            ->click('dialog[data-modal="workspace-restaurant"] input')
            ->fill('dialog[data-modal="workspace-restaurant"] input', $different->name)
            ->keys('dialog[data-modal="workspace-restaurant"] input', 'ArrowDown')
            ->assertVisible('dialog[data-modal="workspace-restaurant"] ui-option[value="'.$different->id.'"]')
            ->click('dialog[data-modal="workspace-restaurant"] ui-option[value="'.$different->id.'"]')
            ->click('dialog[data-modal="workspace-restaurant"] button[type="submit"]')
            ->assertQueryStringHas('branch', (string) $different->id)
            ->assertSee($different->name)
            ->assertScript('window.workspaceRemembered >= 2');
        $tabA->navigate(route('dashboard', absolute: false))
            ->assertPathIs(route('restaurant.dashboard', absolute: false))
            ->assertQueryStringHas('branch', (string) $different->id);

        $tabB->assertPathIs($availabilityPath)
            ->assertSee($original->name)
            ->assertValue('textarea[name="pause.reason"]', 'Only restaurant B should pause')
            ->click('form[wire\\:submit="previewPause"] button[type="submit"]')
            ->assertVisible('[data-availability-preview]')
            ->assertPathIs($availabilityPath);
        expect($original->fresh()->is_temporarily_closed)->toBeFalse()
            ->and($different->fresh()->is_temporarily_closed)->toBeFalse();
        $tabB->click('button[wire\\:click="applyPause"]')
            ->assertSee(__('availability.applied'))
            ->assertPathIs($availabilityPath)
            ->assertMissing('[data-availability-editor]');

        expect($original->refresh()->is_temporarily_closed)->toBeTrue()
            ->and($original->temporary_closed_reason)->toBe('Only restaurant B should pause')
            ->and($original->temporary_closed_until)->toBeNull()
            ->and($original->pause_version)->toBe(1)
            ->and($different->refresh()->is_temporarily_closed)->toBeFalse()
            ->and($different->temporary_closed_reason)->toBeNull()
            ->and($different->temporary_closed_until)->toBeNull()
            ->and($different->pause_version)->toBe(0);
        $audit = AuditLog::query()->where('action', AuditLogAction::BranchAvailabilityChanged)->sole();
        expect($audit->entity_type)->toBe('branch')
            ->and($audit->entity_id)->toBe($original->id)
            ->and($audit->branch_id)->toBe($original->id)
            ->and($audit->organization_id)->toBe($original->organization_id)
            ->and($audit->user_id)->toBe($user->id)
            ->and($audit->old_values['is_temporarily_closed'])->toBeFalse()
            ->and($audit->new_values['is_temporarily_closed'])->toBeTrue();
        $tabA->assertQueryStringHas('branch', (string) $different->id)->assertNoJavaScriptErrors()->assertNoConsoleLogs();
        $tabB->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    } finally {
        $tabBPage->close();
    }
});
