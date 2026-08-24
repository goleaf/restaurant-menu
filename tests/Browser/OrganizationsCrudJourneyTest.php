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
use Illuminate\Support\Facades\Storage;
use Pest\Browser\Api\PendingAwaitablePage;

test('demo owner can complete the organization administration browser journey', function (): void {
    $this->withVite();
    Storage::fake('public');
    config()->set('demo-login.enabled', true);
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
        sprintf('form[action$="/demo-login/%s"] button[type="submit"]', $ownerIdentity['role']->value),
    );
    $page
        ->assertPathIs(route('dashboard', absolute: false))
        ->assertNoJavaScriptErrors();

    $page->navigate(route('organizations.index', absolute: false));
    assertOrganizationsBrowserPage($page, '[data-page="organizations"]');

    $page->fill('input[wire\\:model="name"]', 'Browser CRUD Temporary Group');
    clickOrganizationsBrowserElement($page, 'form[wire\\:submit="create"] button[type="submit"]');
    $page->assertSee('Browser CRUD Temporary Group');

    $temporaryOrganization = Organization::query()
        ->select(['id', 'owner_user_id', 'name'])
        ->where('owner_user_id', $organization->owner_user_id)
        ->where('name', 'Browser CRUD Temporary Group')
        ->firstOrFail();

    clickOrganizationsBrowserElement(
        $page,
        sprintf('button[wire\\:click="startEditing(%d)"]', $temporaryOrganization->id),
    );
    $page->fill('input[wire\\:model="editingName"]', 'Browser CRUD Updated Group');
    clickOrganizationsBrowserElement($page, 'form[wire\\:submit="update"] button[type="submit"]');
    $page->assertSee('Browser CRUD Updated Group');

    clickOrganizationsBrowserElement(
        $page,
        sprintf('button[wire\\:click="confirmDelete(%d)"]', $temporaryOrganization->id),
    );
    $page->assertSee(__('structure.confirmations.archive.title'));
    clickOrganizationsBrowserElement($page, 'button[wire\\:click="delete"]');
    $page->assertDontSee('Browser CRUD Updated Group');

    expect(Organization::withTrashed()->findOrFail($temporaryOrganization->id)->trashed())->toBeTrue();

    $page
        ->select('select[wire\\:model\\.live="lifecycle"]', 'archived')
        ->assertSee('Browser CRUD Updated Group');
    clickOrganizationsBrowserElement(
        $page,
        sprintf('button[wire\\:click="restore(%d)"]', $temporaryOrganization->id),
    );
    $page->assertDontSee('Browser CRUD Updated Group');

    expect(Organization::query()->findOrFail($temporaryOrganization->id)->trashed())->toBeFalse();

    $brand = $branch->brand;
    $routeChain = [
        [route('organizations.index', absolute: false), '[data-page="organizations"]'],
        [route('organizations.staff.index', [$organization], false), '[data-page="organization-staff"]'],
        [route('organizations.staff.permissions', [$organization, $staffMember], false), '[data-page="staff-permissions"]'],
        [route('organizations.brands.index', [$organization], false), '[data-page="organization-brands"]'],
        [route('organizations.brands.branches.index', [$organization, $brand], false), '[data-page="brand-branches"]'],
        [route('organizations.brands.branches.settings.index', [$organization, $brand, $branch], false), '[data-page="branch-settings"]'],
        [route('organizations.brands.branches.areas.index', [$organization, $brand, $branch], false), '[data-page="branch-areas"]'],
        [route('organizations.brands.branches.service-points.index', [$organization, $brand, $branch], false), '[data-page="branch-service-points"]'],
        [route('organizations.brands.branches.service-points.qr.show', [$organization, $brand, $branch, $servicePoint, $qrCode], false), '[data-page="branch-service-point-qr"]'],
        [route('organizations.brands.branches.service-points.qr.print', [$organization, $brand, $branch, $servicePoint, $qrCode], false), '[data-page="qr-print-template"]'],
        [route('organizations.brands.branches.staff.index', [$organization, $brand, $branch], false), '[data-page="branch-staff"]'],
        [route('organizations.brands.branches.menu.index', [$organization, $brand, $branch], false), '[data-page="branch-menu"]'],
    ];

    foreach ($routeChain as [$path, $pageSelector]) {
        $page->resize(1440, 1000)->navigate($path);
        assertOrganizationsBrowserPage($page, $pageSelector);

        $page->resize(375, 812)->navigate($path);
        assertOrganizationsBrowserPage($page, $pageSelector);
    }

    $page
        ->resize(1440, 1000)
        ->navigate(route('organizations.brands.branches.menu.index', [$organization, $brand, $branch], false))
        ->assertPresent('[data-section="menu-stop-list"]')
        ->assertPresent('[data-section="menu-item-variants"]')
        ->assertSee(__('ui.organizations.brands.branches.menu.index.modifier_groups'))
        ->assertSee(__('ui.organizations.brands.branches.menu.index.kitchen_departments'));

    clickOrganizationsBrowserElement(
        $page,
        sprintf('button[wire\\:click="startEditingItem(%d)"]', $menuItem->id),
    );
    $page
        ->assertSee(__('uploads.labels.gallery'))
        ->assertSee(__('menu.translations.heading'));
    clickOrganizationsBrowserElement($page, 'button[wire\\:click="cancelItemEditing"]');

    $page->navigate(route('organizations.brands.branches.service-points.qr.show', [$organization, $brand, $branch, $servicePoint, $qrCode], false));
    clickOrganizationsBrowserElement($page, 'button[wire\\:click="confirmReissue"]');
    $page->assertPresent('dialog[open]');

    $modalFocusState = $page->script(<<<'JAVASCRIPT'
        (() => {
            const dialog = document.querySelector('dialog[open]');

            return dialog instanceof HTMLDialogElement
                && document.activeElement instanceof HTMLElement
                && dialog.contains(document.activeElement);
        })()
    JAVASCRIPT);

    expect($modalFocusState)->toBeTrue();

    clickOrganizationsBrowserElement($page, 'dialog[open] button[aria-label]');

    $page->resize(1440, 1000);
    $page->script("document.documentElement.style.fontSize = '200%'");
    assertOrganizationsBrowserPage($page, '[data-page="branch-service-point-qr"]');
    $page->script("document.documentElement.style.fontSize = ''");

    $page
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

function clickOrganizationsBrowserElement(PendingAwaitablePage $page, string $selector): void
{
    $encodedSelector = json_encode($selector, JSON_THROW_ON_ERROR);
    $clicked = $page->script(<<<JAVASCRIPT
        (() => {
            const element = document.querySelector({$encodedSelector});

            if (!(element instanceof HTMLElement)) {
                return false;
            }

            element.click();

            return true;
        })()
    JAVASCRIPT);

    expect($clicked)->toBeTrue("Browser element was not found: {$selector}");
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

            return {
                clientWidth: root.clientWidth,
                scrollWidth: root.scrollWidth,
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
