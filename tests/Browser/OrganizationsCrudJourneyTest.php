<?php

declare(strict_types=1);

use App\Enums\QrCodeStatus;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use App\Support\DemoLogin\DemoAccountCatalog;
use Database\Seeders\DemoRestaurantSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Pest\Browser\Api\PendingAwaitablePage;

test('demo owner can complete the organization administration browser journey', function (): void {
    $this->withVite();
    $fixtureDirectoryName = 'browser-fixtures/'.bin2hex(random_bytes(8));
    $fixtureDirectory = public_path($fixtureDirectoryName);
    File::ensureDirectoryExists($fixtureDirectory);
    $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($fixtureDirectory));
    config()->set('filesystems.disks.public.root', $fixtureDirectory);
    config()->set('filesystems.disks.public.url', '/'.$fixtureDirectoryName);
    Storage::forgetDisk('public');
    config()->set('demo-login.enabled', true);
    config()->set('demo-login.allowed_hosts', ['ruflo.test', 'restaurant-menu.test', '127.0.0.1', 'localhost']);
    $this->seed(DemoRestaurantSeeder::class);

    $ownerIdentity = DemoAccountCatalog::forRole(SystemRole::Owner);
    $organization = Organization::query()
        ->select(['id', 'owner_user_id', 'name'])
        ->where('name', DemoRestaurantSeeder::ORGANIZATION_NAME)
        ->firstOrFail();
    $branch = Branch::query()
        ->select(['id', 'organization_id', 'brand_id', 'name'])
        ->with(['brand:id,organization_id,name'])
        ->where('organization_id', $organization->id)
        ->where('name', 'Bella Pizza Old Town')
        ->firstOrFail();
    $staffMember = User::query()
        ->select(['users.id', 'users.name', 'users.email'])
        ->where('email', 'director@demo.test')
        ->whereHas('organizations', fn ($query) => $query->whereKey($organization->id))
        ->firstOrFail();
    $qrCode = QrCode::query()
        ->select(['id', 'service_point_id', 'status'])
        ->where('status', QrCodeStatus::Active->value)
        ->whereHas('servicePoint', fn ($query) => $query->where('branch_id', $branch->id))
        ->orderBy('id')
        ->firstOrFail();
    $servicePoint = ServicePoint::query()
        ->select(['id', 'branch_id', 'name'])
        ->whereKey($qrCode->service_point_id)
        ->firstOrFail();
    $menuItem = MenuItem::query()
        ->select(['id', 'menu_category_id', 'name'])
        ->whereHas('category.menu', fn ($query) => $query->where('branch_id', $branch->id))
        ->orderBy('id')
        ->firstOrFail();

    $page = visit(route('demo-login.index', absolute: false));

    clickOrganizationsBrowserElement(
        $page,
        sprintf('li[wire\\:key="demo-%s"] form[wire\\:submit] button[type="submit"]', $ownerIdentity['role']->value),
    );
    $page
        ->assertPathIs(route('dashboard', absolute: false))
        ->assertNoJavaScriptErrors();

    $page->navigate(route('restaurants.index', ['view' => 'structure'], false));
    assertOrganizationsBrowserPage($page, '[data-page="restaurant-center"]');
    clickOrganizationsBrowserElement($page, 'a[href*="create=organization"]');
    $page->assertQueryStringHas('create', 'organization')->fill('input[name="structure_name"]', 'Browser CRUD Temporary Group');
    clickOrganizationsBrowserElement($page, 'form[wire\\:submit="save"] button[type="submit"]');
    $page->assertSee('Browser CRUD Temporary Group')->assertMissing('input[name="structure_name"]');

    $temporaryOrganization = Organization::query()
        ->select(['id', 'owner_user_id', 'name'])
        ->where('owner_user_id', $organization->owner_user_id)
        ->where('name', 'Browser CRUD Temporary Group')
        ->sole();
    $page->assertQueryStringHas('object', (string) $temporaryOrganization->id)
        ->fill('input[wire\\:model="form.name"]', 'Browser CRUD Updated Group');
    clickOrganizationsBrowserElement($page, 'form[wire\\:submit="save"] button[type="submit"]');
    $page->assertSee('Browser CRUD Updated Group');
    expect($temporaryOrganization->fresh()->name)->toBe('Browser CRUD Updated Group')
        ->and($temporaryOrganization->brands()->exists())->toBeFalse();

    clickOrganizationsBrowserElement($page, 'button[wire\\:click="$set(\'confirming\', true)"]');
    $page->assertVisible('dialog[data-modal="structure-lifecycle"]')
        ->assertScript('document.getElementById(document.querySelector("dialog[open]").getAttribute("aria-labelledby"))?.textContent.trim()', __('center.organization').': Browser CRUD Updated Group')
        ->assertScript('document.activeElement.getAttribute("aria-label")', __('ui.accessibility.close_dialog'))
        ->assertScript('document.querySelector("dialog[open]").contains(document.activeElement)');
    $page->resize(390, 844)->script('document.documentElement.style.fontSize = "200%"');
    $page->assertScript('document.querySelector("dialog[open]").getBoundingClientRect().right <= innerWidth + 1')
        ->assertScript('document.querySelector("dialog[open]").getBoundingClientRect().left >= -1');
    $page->script('document.documentElement.style.removeProperty("font-size")');
    clickOrganizationsBrowserElement($page, 'dialog[open] button[autofocus]');
    $page->assertMissing('dialog[open]');
    expect($temporaryOrganization->fresh()->trashed())->toBeFalse();
    clickOrganizationsBrowserElement($page, '[data-center-lifecycle-trigger]');
    $page->assertSee(__('center.archive_notice'))->fill('input[wire\\:model="confirmation"]', 'Browser CRUD Updated Group');
    clickOrganizationsBrowserElement($page, 'form[wire\\:submit="changeLifecycle"] button[type="submit"]');
    $page->assertDontSee('Browser CRUD Updated Group');
    expect($temporaryOrganization->fresh()->trashed())->toBeTrue();

    $page->click('[data-center-filter-toggle]')->select('select[wire\\:model\\.live="filters.lifecycle"]', 'archived')->assertSee('Browser CRUD Updated Group');
    clickOrganizationsBrowserElement($page, sprintf('article[wire\\:key="organization-%d"] a[href*="object=%d"]', $temporaryOrganization->id, $temporaryOrganization->id));
    clickOrganizationsBrowserElement($page, 'button[wire\\:click="$set(\'confirming\', true)"]');
    $page->assertSee(__('center.restore_organization_notice'))->fill('input[wire\\:model="confirmation"]', 'Browser CRUD Updated Group');
    clickOrganizationsBrowserElement($page, 'form[wire\\:submit="changeLifecycle"] button[type="submit"]');
    $page->assertDontSee('Browser CRUD Updated Group');
    expect($temporaryOrganization->fresh()->trashed())->toBeFalse();

    $brand = $branch->brand;
    $qrShowUrl = route('organizations.brands.branches.service-points.qr.show', [$organization, $brand, $branch, $servicePoint, $qrCode], false);
    $qrPrintUrl = route('organizations.brands.branches.service-points.qr.print', [$organization, $brand, $branch, $servicePoint, $qrCode], false);
    $routeChain = [
        [route('organizations.index', absolute: false), '[data-page="restaurant-center"]'],
        [route('organizations.staff.index', [$organization], false), '[data-page="organization-staff"]'],
        [route('organizations.staff.permissions', [$organization, $staffMember], false), '[data-page="employee-card"]'],
        [route('organizations.brands.index', [$organization], false), '[data-page="restaurant-center"]'],
        [route('organizations.brands.branches.index', [$organization, $brand], false), '[data-page="restaurant-center"]'],
        [route('organizations.brands.branches.settings.index', [$organization, $brand, $branch], false), '[data-page="restaurant-settings"]'],
        [route('organizations.brands.branches.areas.index', [$organization, $brand, $branch], false), '[data-page="branch-service-points"]'],
        [route('organizations.brands.branches.service-points.index', [$organization, $brand, $branch], false), '[data-page="branch-service-points"]'],
        [$qrShowUrl, '[data-page="branch-service-points"]'],
        [$qrPrintUrl, '[data-page="branch-service-points"]'],
        [route('organizations.brands.branches.staff.index', [$organization, $brand, $branch], false), '[data-page="branch-staff"]'],
        [route('organizations.brands.branches.menu.index', [$organization, $brand, $branch], false), '[data-page="branch-menu"]'],
    ];

    foreach ($routeChain as [$path, $pageSelector]) {
        $page->resize(1440, 1000)->navigate($path);
        assertOrganizationsBrowserPage($page, $pageSelector);
        if (in_array($path, [$qrShowUrl, $qrPrintUrl], true)) {
            $page->assertVisible($path === $qrShowUrl ? '[data-floor-qr-panel]' : '[data-floor-print-panel]')
                ->assertQueryStringHas('point', (string) $servicePoint->id)
                ->assertQueryStringHas('qr_record', (string) $qrCode->id)
                ->assertQueryStringHas('panel', $path === $qrShowUrl ? 'qr' : 'print');
        }
        if ($pageSelector === '[data-page="employee-card"]') {
            $page->assertSee($staffMember->name)->assertSee($organization->name)->assertSee(__('team.card.organization_scope'))
                ->assertAttribute('[data-team-section="access"]', 'aria-current', 'page');
        }

        $page->resize(375, 812)->navigate($path);
        assertOrganizationsBrowserPage($page, $pageSelector);
        if (in_array($path, [$qrShowUrl, $qrPrintUrl], true)) {
            $page->assertVisible($path === $qrShowUrl ? '[data-floor-qr-panel]' : '[data-floor-print-panel]')
                ->assertQueryStringHas('point', (string) $servicePoint->id)
                ->assertQueryStringHas('qr_record', (string) $qrCode->id)
                ->assertQueryStringHas('panel', $path === $qrShowUrl ? 'qr' : 'print');
        }
        if ($pageSelector === '[data-page="employee-card"]') {
            $page->assertSee($staffMember->name)->assertSee($organization->name)->assertSee(__('team.card.organization_scope'))
                ->assertAttribute('[data-team-section="access"]', 'aria-current', 'page');
        }
    }

    $openingHoursBeforeProfile = $branch->openingHours()->select(['day_of_week', 'is_closed', 'opens_at', 'closes_at', 'sort_order'])
        ->orderBy('day_of_week')->orderBy('sort_order')->get()->toArray();
    $openingHoursVersion = $branch->fresh()->opening_hours_version;
    $page->navigate(route('organizations.brands.branches.settings.index', [$organization, $brand, $branch], false));
    clickOrganizationsBrowserElement($page, 'form[data-settings-group="profile"] button[data-flux-accordion-heading]');
    $page->fill('input[name="profileForm.publicName"]', 'Browser verified restaurant');
    clickOrganizationsBrowserElement($page, 'form[data-settings-group="profile"] button[type="submit"]');
    $page->assertSee(__('settings.saved', ['section' => __('settings.section.profile')]))
        ->assertNoJavaScriptErrors();
    clickOrganizationsBrowserElement($page, '[data-menu-section="settlement"]');
    $page->assertVisible('form[data-settings-group="settlement"]')->assertQueryStringHas('section', 'settlement');
    clickOrganizationsBrowserElement($page, '[id="settlement.serviceChargeEnabled"]');
    $page->assertEnabled('input[name="settlement.serviceChargePercent"]')
        ->fill('input[name="settlement.serviceChargePercent"]', '12.50');
    clickOrganizationsBrowserElement($page, 'form[data-settings-group="settlement"] button[type="submit"]');
    $page->assertSee(__('settings.saved', ['section' => __('settings.section.settlement')]))
        ->assertNoJavaScriptErrors();

    expect($branch->fresh()->public_name)->toBe('Browser verified restaurant')
        ->and($branch->settings()->sole()->service_charge_basis_points)->toBe(1250)
        ->and($branch->fresh()->opening_hours_version)->toBe($openingHoursVersion)
        ->and($branch->openingHours()->select(['day_of_week', 'is_closed', 'opens_at', 'closes_at', 'sort_order'])
            ->orderBy('day_of_week')->orderBy('sort_order')->get()->toArray())->toBe($openingHoursBeforeProfile);

    $page->navigate(route('organizations.brands.branches.settings.index', [$organization, $brand, $branch], false))
        ->assertValue('input[wire\\:model="profileForm.publicName"]', 'Browser verified restaurant')
        ->assertValue('input[wire\\:model="settlement.serviceChargePercent"]', '12.50');

    $mondayIntervalCount = $branch->openingHours()->where('day_of_week', 1)->where('is_closed', false)->count();
    $page->navigate(route('organizations.brands.branches.availability.index', [$organization, $brand, $branch, 'section' => 'schedules'], false))
        ->assertPresent('[data-page="availability-workspace"]');
    clickOrganizationsBrowserElement($page, 'button[wire\\:click="openHours"]');
    $page->assertVisible('form[wire\\:submit="previewSchedule"]');
    clickOrganizationsBrowserElement($page, 'button[wire\\:click="addInterval(0)"]');
    $page->assertPresent(sprintf('button[wire\\:click="removeInterval(0, %d)"]', $mondayIntervalCount));
    clickOrganizationsBrowserElement($page, 'form[wire\\:submit="previewSchedule"] button[type="submit"]');
    $page->assertSee(__('branches.opening_hours.errors.overlap'))->assertMissing('[data-availability-preview]')
        ->assertMissing('button[wire\\:click="applySchedule"]')->assertNoJavaScriptErrors();
    expect($branch->fresh()->public_name)->toBe('Browser verified restaurant')
        ->and($branch->settings()->sole()->service_charge_basis_points)->toBe(1250)
        ->and($branch->fresh()->opening_hours_version)->toBe($openingHoursVersion)
        ->and($branch->openingHours()->select(['day_of_week', 'is_closed', 'opens_at', 'closes_at', 'sort_order'])
            ->orderBy('day_of_week')->orderBy('sort_order')->get()->toArray())->toBe($openingHoursBeforeProfile);

    clickOrganizationsBrowserElement($page, sprintf('button[wire\\:click="removeInterval(0, %d)"]', $mondayIntervalCount));
    $page->assertMissing(sprintf('button[wire\\:click="removeInterval(0, %d)"]', $mondayIntervalCount));
    clickOrganizationsBrowserElement($page, 'form[wire\\:submit="previewSchedule"] button[type="submit"]');
    $page->assertDontSee(__('branches.opening_hours.errors.overlap'))->assertVisible('[data-availability-preview]');
    clickOrganizationsBrowserElement($page, 'button[wire\\:click="applySchedule"]');
    $page->assertMissing('[data-availability-preview]')->assertNoJavaScriptErrors();
    expect($branch->fresh()->public_name)->toBe('Browser verified restaurant')
        ->and($branch->fresh()->opening_hours_version)->toBe($openingHoursVersion + 1)
        ->and($branch->openingHours()->select(['day_of_week', 'is_closed', 'opens_at', 'closes_at', 'sort_order'])
            ->orderBy('day_of_week')->orderBy('sort_order')->get()->toArray())->toBe($openingHoursBeforeProfile);

    $page
        ->resize(1440, 1000)
        ->navigate(route('organizations.brands.branches.menu.index', [$organization, $brand, $branch], false))
        ->assertPresent('[data-menu-section="availability"]')
        ->assertSee(__('menu.workspace.modifiers'))
        ->assertSee(__('menu.workspace.departments'));
    clickOrganizationsBrowserElement($page, '[data-menu-section=modifiers]');
    $page->assertSee(__('ui.organizations.brands.branches.menu.index.modifier_groups'));
    clickOrganizationsBrowserElement($page, '[data-menu-section=departments]');
    $page->assertSee(__('ui.organizations.brands.branches.menu.index.kitchen_departments'));
    clickOrganizationsBrowserElement($page, '[data-menu-section=catalog]');
    $page->assertPresent('[data-section="menu-catalog"]');

    clickOrganizationsBrowserElement(
        $page,
        sprintf('article[wire\\:key="menu-item-%d"] > div:first-child a[wire\\:navigate]', $menuItem->id),
    );
    $page->assertPresent('[data-page="dish-card"]')->assertSee(__('menu.translations.heading'))
        ->click('[data-menu-section="variants"]')->assertVisible('[data-dish-section="variants"]')
        ->assertSee(__('menu.variants.admin.title'));
    $page->click('[data-menu-section="photos"]')->assertSee(__('uploads.labels.gallery'));
    $page->click('a[href*="section=catalog"]')->assertPresent('[data-section="menu-catalog"]');

    $qrStateBeforePreview = QrCode::query()->where('service_point_id', $servicePoint->id)->orderBy('id')
        ->get(['id', 'public_token', 'short_code', 'status'])->toArray();
    $page->navigate($qrShowUrl)->assertVisible('[data-floor-qr-panel]');
    clickOrganizationsBrowserElement($page, 'button[wire\\:click="prepareOperation(\'reissue\')"]');
    $page->assertVisible('[data-floor-qr-operation]')
        ->assertSee(__('floor.qr.operation.reissue'))
        ->assertSee(__('floor.qr.reissue_warning'))
        ->assertSee(__('qr.labels.current_short_code'))
        ->assertSee(__('floor.fields.reason'))
        ->assertVisible('[data-floor-qr-operation] button[data-floor-cancel]');
    $page->resize(390, 844)->script('document.documentElement.style.fontSize = "200%"');
    assertOrganizationsBrowserPage($page, '[data-page="branch-service-points"]');
    $page->screenshot(true, 'organization-qr-reissue-preview-390-text200');
    $page->script('document.documentElement.style.removeProperty("font-size")');
    clickOrganizationsBrowserElement($page, 'button[wire\\:click="cancelOperation"]');
    $page->assertMissing('[data-floor-qr-operation]')->assertVisible('[data-floor-qr-panel]');
    expect(QrCode::query()->where('service_point_id', $servicePoint->id)->orderBy('id')
        ->get(['id', 'public_token', 'short_code', 'status'])->toArray())->toBe($qrStateBeforePreview);
    $page->resize(1440, 1000)->script('document.documentElement.style.fontSize = "200%"');
    assertOrganizationsBrowserPage($page, '[data-page="branch-service-points"]');
    $page->script('document.documentElement.style.removeProperty("font-size")');

    $page
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

function clickOrganizationsBrowserElement(PendingAwaitablePage $page, string $selector): void
{
    $page->assertVisible($selector)->click($selector);
}

function assertOrganizationsBrowserPage(PendingAwaitablePage $page, string $pageSelector): void
{
    $page
        ->assertPresent($pageSelector)
        ->assertNoJavaScriptErrors();

    $quality = $page->script(<<<'JAVASCRIPT'
        (() => {
            const root = document.documentElement;
            const isVisible = (element) => {
                const style = getComputedStyle(element);
                const rectangle = element.getBoundingClientRect();

                return style.display !== 'none'
                    && style.visibility !== 'hidden'
                    && rectangle.width > 0
                    && rectangle.height > 0;
            };
            const accessibleName = (element) => {
                const labelledBy = element.getAttribute('aria-labelledby');
                const labelledText = labelledBy
                    ? labelledBy.split(/\s+/).map((id) => document.getElementById(id)?.textContent ?? '').join(' ')
                    : '';
                const wrappingLabel = element.closest('label')?.textContent ?? '';
                const explicitLabel = element.id
                    ? document.querySelector(`label[for="${CSS.escape(element.id)}"]`)?.textContent ?? ''
                    : '';

                return [
                    element.getAttribute('aria-label') ?? '',
                    labelledText,
                    wrappingLabel,
                    explicitLabel,
                    element.textContent ?? '',
                    element.getAttribute('title') ?? '',
                    element.getAttribute('alt') ?? '',
                ].join(' ').replace(/\s+/g, ' ').trim();
            };
            const unnamedControls = [...document.querySelectorAll('button, a[href], input:not([type="hidden"]), select, textarea')]
                .filter((element) => isVisible(element) && !element.disabled && accessibleName(element) === '')
                .slice(0, 10)
                .map((element) => element.outerHTML.slice(0, 240));
            const failedRequests = performance.getEntriesByType('resource')
                .filter((entry) => entry.name.startsWith(window.location.origin) && Number(entry.responseStatus) >= 400)
                .slice(0, 10)
                .map((entry) => ({ name: entry.name, status: entry.responseStatus }));
            const overflowElements = [...document.querySelectorAll('body *')]
                .map((element) => ({
                    element,
                    rectangle: element.getBoundingClientRect(),
                }))
                .filter(({ element, rectangle }) => isVisible(element)
                    && (rectangle.right > root.clientWidth + 0.5 || rectangle.left < -0.5))
                .slice(0, 10)
                .map(({ element, rectangle }) => ({
                    tag: element.tagName.toLowerCase(),
                    className: typeof element.className === 'string' ? element.className.slice(0, 160) : '',
                    left: rectangle.left,
                    right: rectangle.right,
                    width: rectangle.width,
                }));

            return {
                path: window.location.pathname,
                clientWidth: root.clientWidth,
                scrollWidth: root.scrollWidth,
                overflowElements,
                unnamedControls,
                failedRequests,
            };
        })()
    JAVASCRIPT);

    expect($quality['scrollWidth'])->toBeLessThanOrEqual(
        $quality['clientWidth'],
        'Horizontal overflow: '.json_encode($quality, JSON_THROW_ON_ERROR),
    );
    expect($quality['unnamedControls'])->toBe([], 'Unnamed controls: '.json_encode($quality['unnamedControls'], JSON_THROW_ON_ERROR));
    expect($quality['failedRequests'])->toBe([], 'Failed browser requests: '.json_encode($quality['failedRequests'], JSON_THROW_ON_ERROR));
}
