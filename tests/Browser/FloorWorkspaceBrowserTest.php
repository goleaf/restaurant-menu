<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\QrCodes\StoreQrCodeImageAction;
use App\Enums\QrCodeStatus;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Playwright\Client;
use Tests\Support\IsolatedBrowserIdentity;

beforeEach(function (): void {
    $this->withVite();
    $directoryName = 'browser-fixtures/'.bin2hex(random_bytes(8));
    $directory = public_path($directoryName);
    File::ensureDirectoryExists($directory);
    $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($directory));
    config()->set('filesystems.disks.public.root', $directory);
    config()->set('filesystems.disks.public.url', '/'.$directoryName);
    Storage::forgetDisk('public');
    IsolatedBrowserIdentity::configure();
    $this->seed(SystemPermissionsSeeder::class);
    $this->actor = User::factory()->create();
    $this->organization = app(CreateOrganizationAction::class)->handle($this->actor, ['name' => 'Floor browser organization']);
    $this->brand = Brand::factory()->for($this->organization)->create();
    $this->branch = Branch::factory()->for($this->organization)->for($this->brand)->create(['name' => 'Floor browser restaurant']);
    $this->point = ServicePoint::factory()->for($this->branch)->create(['name' => 'Window table', 'display_number' => '12']);
    $this->qr = QrCode::factory()->for($this->point)->create();
});

test('floor print loads its stylesheet after Livewire insertion and prints only reviewed labels', function (string $check): void {
    $page = visit('/login')->fill('email', $this->actor->email)->fill('password', 'password')->click('@login-button')
        ->assertPathIs(route('restaurant.dashboard', absolute: false));
    $page->navigate(route('organizations.brands.branches.service-points.index', [$this->organization, $this->brand, $this->branch], false))
        ->click('[aria-label="'.__('floor.select_table_named', ['name' => $this->point->name]).'"]')
        ->click('button[wire\:click="openSelection(\'print\')"]')->assertVisible('[data-floor-print-panel]')
        ->click('form[wire\:submit="preparePrint"] button[type=submit]')->assertVisible('[data-floor-print-labels]');
    if ($check === 'assets') {
        $page->assertScript('Array.from(document.styleSheets).some(sheet => sheet.href?.includes("qr-print-"))', true);
    } else {
        $guid = (new ReflectionProperty($page->page(), 'guid'))->getValue($page->page());
        foreach (Client::instance()->execute($guid, 'emulateMedia', ['media' => 'print']) as $message) {
            // Wait for the actual browser media change before inspecting rendered output.
        }
        $page->assertScript('matchMedia("print").matches', true)->assertScript('(window.scrollTo({top: 0, behavior: "instant"}), window.scrollY)', 0)->screenshot(true, 'floor-print-media')
            ->assertScript('Array.from(document.querySelectorAll("body *")).filter(element => element.children.length === 0 && element.textContent.trim() && element.checkVisibility({visibilityProperty: true})).every(element => !!element.closest("[data-floor-print-labels]"))', true);
    }
    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
})->with(['assets', 'print']);

test('room bulk creation recovers missing QR and downloads only the chosen subset without losing context', function (): void {
    $page = floorBrowserLogin($this->actor);
    $url = route('organizations.brands.branches.service-points.index', [$this->organization, $this->brand, $this->branch], false);
    $page->resize(1440, 1000)->navigate($url)->assertSee(__('floor.area_filters'))->assertSee(__('floor.filters'))
        ->click('button[wire\:click="createArea"]')->assertVisible('[data-floor-editor-shell] input[wire\:model="form.name"]')
        ->fill('[data-floor-editor-shell] input[wire\:model="form.name"]', 'Garden room')
        ->click('form[wire\:submit="save"] button[type=submit]')->assertSee(__('floor.area_properties'))
        ->assertSeeIn('.rm-floor__area-list', 'Garden room')->assertScript('(window.scrollTo({top: 0, behavior: "instant"}), window.scrollY)', 0)->screenshot(true, 'floor-created-room-1440')
        ->resize(390, 844)->assertScript('(window.scrollTo({top: 0, behavior: "instant"}), window.scrollY)', 0)->screenshot(true, 'floor-created-room-390')->resize(1440, 1000);
    $area = AreaNode::query()->where('branch_id', $this->branch->id)->where('name', 'Garden room')->sole();
    $page->click('[aria-label="'.__('floor.close_editor').'"]')->assertNotPresent('[data-floor-editor-shell]')
        ->click('button[wire\:click="chooseArea(\''.$area->id.'\')"]')->assertQueryStringHas('zone', (string) $area->id);
    $page->click('button[wire\:click="openBulk"]')->assertVisible('input[wire\:model="form.bulkPrefix"]');
    $page->fill('input[wire\:model="form.bulkPrefix"]', 'Garden ')
        ->fill('input[wire\:model="form.bulkFrom"]', '1')->fill('input[wire\:model="form.bulkTo"]', '3')
        ->click('form[wire\:submit="review"] button[type=submit]')->assertVisible('.rm-floor__preview-list')->assertScript('(window.scrollTo({top: 0, behavior: "instant"}), window.scrollY)', 0)->screenshot(true, 'floor-bulk-review-1440');
    expect(ServicePoint::query()->where('area_node_id', $area->id)->count())->toBe(0);
    $page->click('button[wire\:click="apply"]')->assertSee(__('floor.bulk_result', ['created' => 3, 'skipped' => 0]))
        ->click('[aria-label="'.__('floor.close_editor').'"]')->assertNotPresent('[data-floor-editor-shell]');
    $points = ServicePoint::query()->where('area_node_id', $area->id)->orderBy('id')->get();
    expect($points)->toHaveCount(3);
    $page->script('window.floorSelectionActions = []; window.Livewire.interceptMessage(({message,onSend}) => onSend(() => window.floorSelectionActions.push(...Array.from(message.actions).map(action => action.name)))); void 0;');
    $page->assertSee(__('floor.selected_count', ['count' => 3]))
        ->click('button[wire\:click="clearSelection"]')->assertNotPresent('button[wire\:click="clearSelection"]')
        ->assertScript('Array.from(document.querySelectorAll(".rm-floor__point ui-checkbox")).every(element => element.checked === false && element.getAttribute("aria-checked") === "false")', true)
        ->click('button[wire\:click="selectPage"]')->assertSee(__('floor.selected_count', ['count' => 3]))
        ->assertScript('Array.from(document.querySelectorAll(".rm-floor__point ui-checkbox")).every(element => element.checked === true && element.getAttribute("aria-checked") === "true")', true)
        ->click('button[wire\:click="clearSelection"]')->assertNotPresent('button[wire\:click="clearSelection"]');
    foreach ($points->take(2) as $index => $point) {
        $selector = '[aria-label="'.__('floor.select_table_named', ['name' => $point->name]).'"]';
        if ($index === 0) {
            $page->keys($selector, 'Space');
        } else {
            $page->click($selector);
        }
        $page->assertSee(__('floor.selected_count', ['count' => $index + 1]))
            ->assertAttribute('[aria-label="'.__('floor.select_table_named', ['name' => $point->name]).'"]', 'aria-checked', 'true');
    }
    $page->assertScript('window.floorSelectionActions.filter(action => action === "selectPoint").length', 2)
        ->assertScript('window.floorSelectionActions.filter(action => action === "clearSelection").length', 2)
        ->assertScript('window.floorSelectionActions.filter(action => action === "selectPage").length', 1)
        ->click('button[wire\:click="openSelection(\'print\')"]')->assertVisible('[data-floor-print-panel]');
    foreach ($points->take(2) as $point) {
        $page->assertAttribute('[data-floor-print-target="'.$point->id.'"]', 'data-qr-state', 'missing')
            ->click('button[wire\:click="reviewQr('.$point->id.')"]')->assertVisible('[data-floor-qr-panel]')
            ->click('button[wire\:click="prepareOperation(\'generate\')"]')->click('form[wire\:submit="applyOperation"] button[type=submit]')
            ->assertNotPresent('[data-floor-qr-operation]')->click('button[wire\:click="requestPrint"]')->assertVisible('[data-floor-print-panel]');
    }
    expect(QrCode::query()->whereIn('service_point_id', $points->modelKeys())->count())->toBe(2);
    $identities = QrCode::query()->whereIn('service_point_id', $points->modelKeys())->pluck('public_token', 'id')->all();
    $page->click('form[wire\:submit="preparePrint"] button[type=submit]')->assertVisible('[data-floor-print-labels]')
        ->assertScript('document.querySelectorAll("[data-floor-print-labels] article").length', 2);
    $page->script(<<<'JS'
        window.floorPdf = null;
        window.floorDownloads = [];
        const floorFetch = window.fetch;
        window.fetch = async (...args) => {
            const response = await floorFetch(...args);
            if (response.headers.get('content-type')?.includes('application/json')) {
                response.clone().json().then(data => {
                    for (const component of data.components ?? []) {
                        window.floorDownloads.push({status: response.status, hasDownload: !!component.effects?.download});
                        const download = component.effects?.download;
                        if (download) window.floorPdf = {type: download.contentType, bytes: atob(download.content).length, header: atob(download.content).slice(0, 5)};
                    }
                });
            }
            return response;
        };
        void 0;
        JS);
    $page->click('button[wire\:click="downloadPdf"]');
    $page->assertScript('window.floorPdf?.header', '%PDF-')
        ->assertScript('window.floorDownloads.every(response => response.status < 400)', true)
        ->assertScript('window.floorPdf?.bytes > 5000 && window.floorPdf?.bytes < 4000000', true)
        ->click('[aria-label="'.__('floor.close_editor').'"]')->assertNotPresent('[data-floor-editor-shell]')
        ->assertSee(__('floor.selected_count', ['count' => 2]))
        ->assertQueryStringHas('zone', (string) $area->id)->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    expect(QrCode::query()->whereIn('service_point_id', $points->modelKeys())->pluck('public_token', 'id')->all())->toBe($identities);
});

test('floor QR and print remain usable across localized themes widths and accessibility media', function (string $locale): void {
    $this->actor->update(['locale' => $locale]);
    $this->point->update(['name' => 'Window table — Stalas prie lango — Стол у большого окна']);
    $this->branch->update(['name' => 'Riverside family restaurant — Šeimos restoranas — Семейный ресторан у реки']);
    app(StoreQrCodeImageAction::class)->handle($this->qr);
    $page = floorBrowserLogin($this->actor, $locale);
    $url = route('organizations.brands.branches.service-points.index', [$this->organization, $this->brand, $this->branch], false);
    $page->navigate($url)->click('[aria-label="'.__('floor.select_table_named', ['name' => $this->point->name], $locale).'"]')
        ->click('button[wire\:click="openSelection(\'print\')"]')->assertVisible('[data-floor-print-panel]')
        ->click('form[wire\:submit="preparePrint"] button[type=submit]')->assertVisible('[data-floor-print-labels]');
    foreach (['light', 'dark'] as $theme) {
        $page->script('window.Flux.appearance = '.json_encode($theme, JSON_THROW_ON_ERROR));
        foreach ([320, 390, 768, 1024, 1440] as $width) {
            $page->resize($width, 900)->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
                ->assertScript('Array.from(document.querySelectorAll(".rm-floor [data-flux-button]")).filter(element => element.checkVisibility()).every(element => element.getBoundingClientRect().height >= 44 && element.scrollWidth <= element.clientWidth + 1)', true);
            if ($width === 390 || $width === 1440) {
                $page->assertScript('(window.scrollTo({top: 0, behavior: "instant"}), window.scrollY)', 0)->screenshot(true, "floor-print-{$locale}-{$theme}-{$width}");
            }
        }
    }
    $page->resize(390, 844)->script('document.documentElement.style.zoom = "2"');
    $page->assertScript('document.documentElement.scrollWidth <= innerWidth', true);
    $page->script('document.documentElement.style.zoom = "1"');
    floorBrowserMedia($page, ['reducedMotion' => 'reduce', 'forcedColors' => 'active']);
    $page->assertScript('matchMedia("(prefers-reduced-motion: reduce)").matches && matchMedia("(forced-colors: active)").matches', true);
    $page->script('document.querySelector("[data-floor-print-panel] button").focus()');
    $page->assertScript('document.activeElement.checkVisibility() && document.activeElement.closest("[data-floor-print-panel]") !== null', true);
    floorBrowserMedia($page, ['reducedMotion' => 'no-preference', 'forcedColors' => 'none']);
    $page->click('[aria-label="'.__('floor.close_editor', [], $locale).'"]')->assertNotPresent('[data-floor-editor-shell]')
        ->assertScript('(window.scrollTo({top: 0, behavior: "instant"}), window.scrollY)', 0)->screenshot(true, "floor-tables-{$locale}-390")
        ->select('select[wire\:model\.live="filters.mode"]', 'list')->assertAttribute('.rm-floor__points', 'data-mode', 'list')
        ->assertScript('(window.scrollTo({top: 0, behavior: "instant"}), window.scrollY)', 0)->screenshot(true, "floor-table-list-{$locale}-390")
        ->resize(1440, 1000)->assertScript('(window.scrollTo({top: 0, behavior: "instant"}), window.scrollY)', 0)->screenshot(true, "floor-table-list-{$locale}-1440")
        ->select('select[wire\:model\.live="filters.mode"]', 'cards')->assertAttribute('.rm-floor__points', 'data-mode', 'cards')
        ->assertScript('(window.scrollTo({top: 0, behavior: "instant"}), window.scrollY)', 0)->screenshot(true, "floor-tables-{$locale}-1440")->resize(390, 844)
        ->click('button[wire\:click="openPoint('.$this->point->id.')"]')->assertVisible('[data-floor-point-editor]')
        ->assertScript('(window.scrollTo({top: 0, behavior: "instant"}), window.scrollY)', 0)->screenshot(true, "floor-point-{$locale}-390")
        ->click('button[wire\:click="openPoint('.$this->point->id.', \'qr\')"]')->assertVisible('[data-floor-qr-panel]')
        ->assertScript('document.querySelector("[data-floor-qr-panel] img")?.naturalWidth > 0', true);
    foreach (['light', 'dark'] as $theme) {
        $page->script('window.Flux.appearance = '.json_encode($theme, JSON_THROW_ON_ERROR));
        foreach ([320, 390, 768, 1024, 1440] as $width) {
            $page->resize($width, 900)->assertScript('document.documentElement.scrollWidth <= innerWidth', true)
                ->assertScript('Array.from(document.querySelectorAll("[data-floor-qr-panel] [data-flux-button]")).filter(element => element.checkVisibility()).every(element => element.getBoundingClientRect().height >= 44 && element.scrollWidth <= element.clientWidth + 1)', true);
            if ($width === 390 || $width === 1440) {
                $page->assertScript('(window.scrollTo({top: 0, behavior: "instant"}), window.scrollY)', 0)->screenshot(true, "floor-qr-{$locale}-{$theme}-{$width}");
            }
        }
    }
    $page->resize(390, 844)->script('document.documentElement.style.zoom = "2"');
    $page->assertScript('document.documentElement.scrollWidth <= innerWidth', true);
    $page->script('document.documentElement.style.zoom = "1"');
    floorBrowserMedia($page, ['reducedMotion' => 'reduce', 'forcedColors' => 'active']);
    $page->script('document.querySelector("[data-floor-qr-panel] button").focus()');
    $page->assertScript('document.activeElement.getAttribute("wire:click")', 'downloadQrImage')
        ->keys('[data-floor-qr-panel] button[wire\:click="downloadQrImage"]', 'Alt+Tab')
        ->assertScript('document.activeElement.checkVisibility() && document.activeElement.closest("[data-floor-qr-panel]") !== null', true)
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
})->with(['en', 'lt', 'ru']);

test('QR repair rename disable and explicit reissue preserve identity until the confirmed replacement', function (): void {
    $page = floorBrowserLogin($this->actor);
    $floor = route('organizations.brands.branches.service-points.index', [$this->organization, $this->brand, $this->branch, 'point' => $this->point->id, 'panel' => 'qr'], false);
    $public = route('public.qr.show', ['token' => $this->qr->public_token], false);
    $page->navigate($floor)->assertVisible('[data-floor-qr-panel]')->assertSee(__('floor.qr.image_missing'))
        ->click('button[wire\:click="prepareOperation(\'repair\')"]')->assertVisible('[data-floor-qr-operation]')
        ->click('form[wire\:submit="applyOperation"] button[type=submit]')->assertNotPresent('[data-floor-qr-operation]')
        ->assertScript('document.querySelector("[data-floor-qr-panel] img")?.naturalWidth > 0', true);
    expect($this->qr->fresh()->public_token)->toBe($this->qr->public_token)
        ->and(QrCode::query()->where('service_point_id', $this->point->id)->count())->toBe(1);
    $page->click('button[wire\:click="openPoint('.$this->point->id.', \'properties\')"]')->assertVisible('[data-floor-point-editor]')
        ->fill('[data-floor-point-editor] input[wire\:model="form.name"]', 'Renamed window table')
        ->fill('[data-floor-point-editor] input[wire\:model="form.displayNumber"]', '44')
        ->click('form[wire\:submit="save"] button[type=submit]')->assertSee(__('floor.saved'));
    expect($this->point->fresh()->name)->toBe('Renamed window table')
        ->and($this->qr->fresh()->public_token)->toBe($this->qr->public_token);
    $page->navigate($public)->assertSee($this->qr->short_code)->assertDontSee(__('qr.errors.revoked.title'))
        ->navigate($floor)->assertVisible('[data-floor-qr-panel]')
        ->click('button[wire\:click="prepareOperation(\'reissue\')"]')->assertVisible('[data-floor-qr-operation]')
        ->assertSee(__('floor.qr.reissue_warning'))
        ->fill('input[wire\:model="form.confirmation"]', $this->qr->short_code)
        ->fill('textarea[wire\:model="form.reason"]', 'Replace the damaged printed label')
        ->click('form[wire\:submit="applyOperation"] button[type=submit]')->assertNotPresent('[data-floor-qr-operation]');
    $replacement = QrCode::query()->where('service_point_id', $this->point->id)->where('id', '!=', $this->qr->id)->sole();
    expect($replacement->public_token)->not->toBe($this->qr->public_token)
        ->and($this->qr->fresh()->status)->toBe(QrCodeStatus::Revoked);
    $page->navigate($public)->assertSee(__('qr.errors.revoked.title'))
        ->navigate(route('public.qr.show', ['token' => $replacement->public_token], false))->assertSee($replacement->short_code)
        ->assertDontSee(__('qr.errors.revoked.title'))
        ->navigate($floor)->assertVisible('[data-floor-qr-panel]')
        ->click('button[wire\:click="prepareOperation(\'disable\')"]')->assertVisible('[data-floor-qr-operation]')
        ->fill('textarea[wire\:model="form.reason"]', 'Damaged replacement label')
        ->click('form[wire\:submit="applyOperation"] button[type=submit]')->assertNotPresent('[data-floor-qr-operation]')
        ->navigate(route('public.qr.show', ['token' => $replacement->public_token], false))->assertSee(__('qr.errors.disabled.title'))
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

test('floor drafts survive cancelled history and offline close never replays a mutation after reconnect', function (): void {
    $page = floorBrowserLogin($this->actor);
    $url = route('organizations.brands.branches.service-points.index', [$this->organization, $this->brand, $this->branch], false);
    $editor = '[data-floor-point-editor]';
    $input = $editor.' input[wire\:model="form.name"]';
    $dialog = 'dialog[data-modal="floor-unsaved"]';
    $page->resize(390, 844)->navigate($url)->click('button[wire\:click="openPoint('.$this->point->id.')"]')
        ->assertVisible($editor)->assertQueryStringHas('point', (string) $this->point->id);
    $page->script(<<<'JS'
        window.floorActions = [];
        window.Livewire.interceptMessage(({message,onSend}) => onSend(() => {
            window.floorActions.push(...Array.from(message.actions).map(action => action.name));
        }));
        void 0;
        JS);
    $page->fill($input, 'Unsaved local table')
        ->click('[aria-label="'.__('floor.close_editor').'"]')->assertVisible($dialog)
        ->assertAttribute($dialog, 'aria-labelledby', 'floor-unsaved-heading')
        ->click($dialog.' button[x-on\:click="cancelNavigation"]')->assertMissing($dialog)
        ->assertValue($input, 'Unsaved local table');
    $page->script('history.back()');
    $page->assertVisible($dialog)->click($dialog.' button[x-on\:click="cancelNavigation"]')
        ->assertMissing($dialog)->assertValue($input, 'Unsaved local table')
        ->assertQueryStringHas('point', (string) $this->point->id);
    floorBrowserOffline($page, true);
    try {
        $page->assertScript('navigator.onLine', false)->assertVisible('.rm-floor > [wire\:offline]')
            ->assertDisabled($editor.' button[type=submit]')
            ->click('[aria-label="'.__('floor.close_editor').'"]')->assertVisible($dialog)
            ->click($dialog.' button[x-on\:click="discardAndNavigate"]')->assertMissing('[data-floor-editor-shell]')
            ->assertScript('window.floorActions.length', 0);
        expect($this->point->fresh()->name)->toBe('Window table');
    } finally {
        floorBrowserOffline($page, false);
    }
    $page->assertScript('navigator.onLine', true)->assertMissing('.rm-floor > [wire\:offline]')
        ->assertScript('window.floorActions.length', 0)
        ->click('button[wire\:click="openPoint('.$this->point->id.')"]')->assertVisible($editor)
        ->assertValue($input, 'Window table')
        ->assertScript('window.floorActions.includes("clearEditor") && window.floorActions.includes("openPoint")', true);
    $page->script('history.back()');
    $page->assertNotPresent('[data-floor-editor-shell]')->assertMissing($dialog)
        ->assertScript('window.floorActions.includes("save") || window.floorActions.includes("archive")', false)
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    expect($this->point->fresh()->name)->toBe('Window table');
});

test('closing a bookmarked mobile room editor restores focus to the visible rooms heading', function (): void {
    $area = AreaNode::factory()->for($this->branch)->create(['name' => 'Saved mobile room']);
    $page = floorBrowserLogin($this->actor);
    $page->resize(390, 844)->navigate(route('organizations.brands.branches.areas.index', [
        $this->organization, $this->brand, $this->branch, 'q' => 'Saved mobile', 'sort' => 'name_desc',
    ], false))->assertQueryStringHas('view', 'zones')->assertQueryStringHas('area_search', 'Saved mobile')
        ->assertQueryStringHas('area_sort', 'name_desc')->assertVisible('[data-floor-areas-heading]')
        ->assertSeeIn('.rm-floor__area-list', 'Saved mobile room')
        ->assertScript('(window.scrollTo({top: 0, behavior: "instant"}), window.scrollY)', 0)->screenshot(true, 'floor-legacy-rooms-390');
    $page->navigate(route('organizations.brands.branches.service-points.index', [
        $this->organization, $this->brand, $this->branch, 'view' => 'zones', 'area_editor' => $area->id, 'panel' => 'area',
    ], false))->assertVisible('[data-floor-editor-shell]')->click('[aria-label="'.__('floor.close_editor').'"]')
        ->assertNotPresent('[data-floor-editor-shell]');
    $page->assertScript('document.activeElement.matches("[data-floor-areas-heading]") && document.activeElement.checkVisibility()', true)
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

function floorBrowserLogin(User $actor, string $locale = 'en'): PendingAwaitablePage
{
    $page = visit(route('login', ['lang' => $locale], false));
    $page->fill('email', $actor->email)->fill('password', 'password')->click('@login-button')
        ->assertPathIs(route('restaurant.dashboard', absolute: false));

    return $page;
}

/** @param array<string,string> $media */
function floorBrowserMedia(PendingAwaitablePage $page, array $media): void
{
    $guid = (new ReflectionProperty($page->page(), 'guid'))->getValue($page->page());
    foreach (Client::instance()->execute($guid, 'emulateMedia', $media) as $message) {
        // Consume the browser media response before checking the rendered state.
    }
}

function floorBrowserOffline(PendingAwaitablePage $page, bool $offline): void
{
    $context = $page->page()->context();
    $guid = (new ReflectionProperty($context, 'guid'))->getValue($context);
    foreach (Client::instance()->execute($guid, 'setOffline', ['offline' => $offline]) as $message) {
        // Consume the protocol response before checking actual network connectivity.
    }
}
