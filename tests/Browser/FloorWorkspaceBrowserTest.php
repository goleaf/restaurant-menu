<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Playwright\Client;
use Tests\Support\IsolatedBrowserIdentity;

beforeEach(function (): void {
    $this->withVite();
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
        $page->assertScript('matchMedia("print").matches', true)->screenshot(true, 'floor-print-media')
            ->assertScript('Array.from(document.querySelectorAll("body *")).filter(element => element.children.length === 0 && element.textContent.trim() && element.checkVisibility({visibilityProperty: true})).every(element => !!element.closest("[data-floor-print-labels]"))', true);
    }
    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
})->with(['assets', 'print']);

test('room bulk creation recovers missing QR and downloads only the chosen subset without losing context', function (): void {
    $area = AreaNode::factory()->for($this->branch)->create(['name' => 'Garden room']);
    fwrite(STDERR, "P5 trace 1\n");
    $page = floorBrowserLogin($this->actor);
    $url = route('organizations.brands.branches.service-points.index', [$this->organization, $this->brand, $this->branch], false);
    fwrite(STDERR, "P5 trace 2\n");
    $page->navigate($url)->click('button[wire\:click="chooseArea(\''.$area->id.'\')"]')
        ->click('button[wire\:click="openBulk"]')->fill('input[wire\:model="form.bulkPrefix"]', 'Garden ')
        ->fill('input[wire\:model="form.bulkFrom"]', '1')->fill('input[wire\:model="form.bulkTo"]', '3')
        ->click('form[wire\:submit="review"] button[type=submit]')->assertVisible('.rm-floor__preview-list');
    fwrite(STDERR, "P5 trace 3\n");
    expect(ServicePoint::query()->where('area_node_id', $area->id)->count())->toBe(0);
    fwrite(STDERR, "P5 trace 4\n");
    $page->click('button[wire\:click="apply"]')->assertSee(__('floor.bulk_result', ['created' => 3, 'skipped' => 0]))
        ->click('[aria-label="'.__('floor.close_editor').'"]')->assertNotPresent('[data-floor-editor-shell]');
    fwrite(STDERR, "P5 trace 5\n");
    $points = ServicePoint::query()->where('area_node_id', $area->id)->orderBy('id')->get();
    fwrite(STDERR, "P5 trace 6\n");
    expect($points)->toHaveCount(3);
    fwrite(STDERR, "P5 trace 7\n");
    foreach ($points->take(2) as $point) {
        fwrite(STDERR, "P5 trace 8\n");
        $page->click('[aria-label="'.__('floor.select_table_named', ['name' => $point->name]).'"]');
    }
    fwrite(STDERR, "P5 trace 9\n");
    $page->click('button[wire\:click="openSelection(\'print\')"]')->assertVisible('[data-floor-print-panel]');
    fwrite(STDERR, "P5 trace 10\n");
    foreach ($points->take(2) as $point) {
        fwrite(STDERR, "P5 trace 11\n");
        $page->assertAttribute('[data-floor-print-target="'.$point->id.'"]', 'data-qr-state', 'missing')
            ->click('button[wire\:click="reviewQr('.$point->id.')"]')->assertVisible('[data-floor-qr-panel]')
            ->click('button[wire\:click="prepareOperation(\'generate\')"]')->click('form[wire\:submit="applyOperation"] button[type=submit]')
            ->assertNotPresent('[data-floor-qr-operation]')->click('button[wire\:click="requestPrint"]')->assertVisible('[data-floor-print-panel]');
    }
    fwrite(STDERR, "P5 trace 12\n");
    expect(QrCode::query()->whereIn('service_point_id', $points->modelKeys())->count())->toBe(2);
    fwrite(STDERR, "P5 trace 13\n");
    $identities = QrCode::query()->whereIn('service_point_id', $points->modelKeys())->pluck('public_token', 'id')->all();
    fwrite(STDERR, "P5 trace 14\n");
    $page->click('form[wire\:submit="preparePrint"] button[type=submit]')->assertVisible('[data-floor-print-labels]')
        ->assertScript('document.querySelectorAll("[data-floor-print-labels] article").length', 2);
    fwrite(STDERR, "P5 trace 15\n");
    $page->script(<<<'JS'
        window.floorPdf = null;
        window.Livewire.interceptMessage(({onSuccess}) => onSuccess(({payload}) => {
            if (payload.effects?.download) window.floorPdf = {type: payload.effects.download.contentType, bytes: atob(payload.effects.download.content).length, header: atob(payload.effects.download.content).slice(0, 5)};
        }));
        JS);
    fwrite(STDERR, "P5 trace 16\n");
    $page->click('button[wire\:click="downloadPdf"]')->assertScript('window.floorPdf?.header', '%PDF-')
        ->assertScript('window.floorPdf?.bytes > 5000 && window.floorPdf?.bytes < 4000000', true)
        ->click('[aria-label="'.__('floor.close_editor').'"]')->assertNotPresent('[data-floor-editor-shell]')
        ->assertSee(__('floor.selected_count', ['count' => 2]))
        ->assertQueryStringHas('area', (string) $area->id)->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    fwrite(STDERR, "P5 trace 17\n");
    expect(QrCode::query()->whereIn('service_point_id', $points->modelKeys())->pluck('public_token', 'id')->all())->toBe($identities);
});

test('floor QR and print remain usable across localized themes widths and accessibility media', function (string $locale): void {
    $this->actor->update(['locale' => $locale]);
    $this->point->update(['name' => 'Window table — Stalas prie lango — Стол у большого окна']);
    $this->branch->update(['name' => 'Riverside family restaurant — Šeimos restoranas — Семейный ресторан у реки']);
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
                $page->screenshot(true, "floor-print-{$locale}-{$theme}-{$width}");
            }
        }
    }
    $page->resize(390, 844)->script('document.documentElement.style.zoom = "2"');
    $page->assertScript('document.documentElement.scrollWidth <= innerWidth', true);
    $page->script('document.documentElement.style.zoom = "1"');
    floorBrowserMedia($page, ['reducedMotion' => 'reduce', 'forcedColors' => 'active']);
    $page->assertScript('matchMedia("(prefers-reduced-motion: reduce)").matches && matchMedia("(forced-colors: active)").matches', true);
    $page->script('document.querySelector("[data-floor-print-panel] button").focus()');
    $page->assertScript('document.activeElement.checkVisibility() && document.activeElement.closest("[data-floor-print-panel]") !== null', true)
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
})->with(['en', 'lt', 'ru']);

test('closing a bookmarked mobile room editor restores focus to the visible rooms heading', function (): void {
    $area = AreaNode::factory()->for($this->branch)->create();
    $page = floorBrowserLogin($this->actor);
    $page->resize(390, 844)->navigate(route('organizations.brands.branches.service-points.index', [
        $this->organization, $this->brand, $this->branch, 'view' => 'zones', 'area_editor' => $area->id, 'panel' => 'area',
    ], false))->assertVisible('[data-floor-editor-shell]')->click('[aria-label="'.__('floor.close_editor').'"]')
        ->assertNotPresent('[data-floor-editor-shell]');
    fwrite(STDERR, json_encode($page->script('JSON.stringify({active:document.activeElement.outerHTML,areas:document.querySelector("[data-floor-areas-heading]").outerHTML,visible:document.querySelector("[data-floor-areas-heading]").checkVisibility(),state:document.querySelector(".rm-floor__workspace").dataset,wire:window.Livewire.find(document.querySelector(".rm-floor").getAttribute("wire:id")).mobileView})'), JSON_THROW_ON_ERROR));
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
