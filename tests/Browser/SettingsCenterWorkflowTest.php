<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\BranchSettingsChange;
use App\Models\ManualPayment;
use App\Models\Menu;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Execution;
use Pest\Browser\Playwright\Client;
use Tests\Support\IsolatedBrowserIdentity;

beforeEach(function (): void {
    $this->withVite();
    IsolatedBrowserIdentity::configure();
    $this->seed(SystemPermissionsSeeder::class);
    $this->owner = User::factory()->create(['email' => 'settings.owner@example.test']);
    $this->organization = app(CreateOrganizationAction::class)->handle($this->owner, ['name' => 'Settings browser fixture']);
    $this->branch = Branch::factory()->for($this->organization)->create([
        'name' => 'Internal restaurant', 'public_name' => 'Original guest name',
        'public_description' => 'Original fallback description', 'timezone' => 'Europe/Vilnius',
    ]);
    $this->settingsUrl = route('organizations.brands.branches.settings.index', [$this->organization, $this->branch->brand, $this->branch], false);
});

test('settings search leads to Lithuanian draft preview and saves the same profile shown by real QR', function (): void {
    $point = ServicePoint::factory()->for($this->branch)->create(['is_active' => true]);
    $qr = QrCode::factory()->forServicePoint($point)->active()->create();
    Menu::factory()->for($this->branch)->active()->create();
    $page = settingsBrowserOwner($this->owner, $this->settingsUrl.'?section=advanced');
    $page->fill('[data-page="restaurant-settings"] input[type="search"]', 'description');
    settingsBrowserClick($page, 'button[wire\:click="selectSection(\'profile\', \'public-text\')"]');
    $page->assertScript('document.activeElement.id', 'public-text');
    settingsBrowserClick($page, 'ui-select[wire\:model\.live="contentLanguage"] button[role="combobox"]');
    settingsBrowserClick($page, 'ui-select[wire\:model\.live="contentLanguage"] ui-option[value="lt"]');
    $page->fill('input[name="profileForm.translations.lt.name"]', 'Šeimos virtuvė')
        ->fill('textarea[name="profileForm.translations.lt.description"]', 'Švieži sezoniniai patiekalai ir jaukūs vakarai.');
    settingsBrowserClick($page, 'button[wire\:click="openPreview(true)"]');
    $page->assertSeeIn('[data-menu-preview]', 'Šeimos virtuvė')->assertSeeIn('[data-menu-preview]', 'Švieži sezoniniai patiekalai ir jaukūs vakarai.')
        ->assertSeeIn('[data-menu-preview]', __('settings.no_contacts', [], 'lt'))->assertAttribute('html[lang]', 'lang', 'en')
        ->screenshotElement('[data-menu-preview]', 'settings-lt-unsaved-preview');
    expect($this->branch->fresh()->public_translations)->toBeNull()
        ->and(BranchSetting::query()->where('branch_id', $this->branch->id)->exists())->toBeFalse()
        ->and(TableSession::query()->count())->toBe(0);
    settingsBrowserClick($page, 'form[data-settings-group="profile"] button[type="submit"]');
    expect($this->branch->fresh()->public_translations['lt']['name'])->toBe('Šeimos virtuvė')
        ->and($this->branch->fresh()->public_name)->toBe('Original guest name')
        ->and($this->branch->fresh()->currency)->toBe('EUR')
        ->and(BranchSetting::query()->where('branch_id', $this->branch->id)->exists())->toBeFalse();
    $page->assertSee(__('settings.saved', ['section' => __('settings.section.profile')]));
    settingsBrowserMetrics($page, 'profile-after-save');
    $guest = visit(route('public.qr.show', ['token' => $qr->public_token, 'lang' => 'lt'], false));
    $guest->assertSee('Šeimos virtuvė')->assertSee('Švieži sezoniniai patiekalai ir jaukūs vakarai.')
        ->screenshot(false, 'settings-lt-real-qr')->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    expect(TableSession::query()->count())->toBe(0);
    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

test('settings keeps separate drafts through section history and never replays an offline save', function (): void {
    $page = settingsBrowserOwner($this->owner, $this->settingsUrl);
    $page->fill('input[name="profileForm.phone"]', '+370 0012345');
    settingsBrowserClick($page, '[data-menu-section="settlement"]');
    $page->assertQueryStringHas('section', 'settlement')->assertVisible('form[data-settings-group="settlement"]')
        ->fill('input[name="settlement.serviceChargePercent"]', '7.25');
    settingsBrowserClick($page, '[data-menu-section="profile"]');
    $page->assertQueryStringMissing('section')->assertVisible('form[data-settings-group="profile"]')
        ->assertValue('input[name="profileForm.phone"]', '+370 0012345');
    $page->back()->assertQueryStringHas('section', 'settlement')->assertValue('input[name="settlement.serviceChargePercent"]', '7.25');
    $page->forward()->assertVisible('form[data-settings-group="profile"]')->assertValue('input[name="profileForm.phone"]', '+370 0012345');
    settingsBrowserClick($page, 'form[data-settings-group="profile"] button[type="submit"]');
    expect($this->branch->fresh()->phone)->toBe('+370 0012345')
        ->and(BranchSetting::query()->where('branch_id', $this->branch->id)->exists())->toBeFalse();
    $savedNoticeScript = 'document.querySelector(\'form[data-settings-group="profile"]\').innerText.includes('.json_encode(__('settings.saved', ['section' => __('settings.section.profile')]), JSON_THROW_ON_ERROR).')';
    $page->assertScript($savedNoticeScript, true)
        ->fill('input[name="profileForm.phone"]', '+370 0098765')
        ->assertScript($savedNoticeScript, false);
    settingsBrowserClick($page, 'button[wire\:click="cancelGroup(\'profile\')"]');
    $page->assertValue('input[name="profileForm.phone"]', '+370 0012345');
    settingsBrowserClick($page, '[data-menu-section="settlement"]');
    $page->assertValue('input[name="settlement.serviceChargePercent"]', '7.25');
    settingsBrowserOffline($page, true);
    $page->assertDisabled('form[data-settings-group="settlement"] button[type="submit"]');
    settingsBrowserOffline($page, false);
    $page->assertEnabled('form[data-settings-group="settlement"] button[type="submit"]');
    expect(BranchSettingsChange::query()->where('branch_id', $this->branch->id)->count())->toBe(1)
        ->and(BranchSetting::query()->where('branch_id', $this->branch->id)->exists())->toBeFalse();
    settingsBrowserClick($page, 'form[data-settings-group="settlement"] button[type="submit"]');
    expect($this->branch->fresh()->settings->service_charge_basis_points)->toBe(725)
        ->and($this->branch->fresh()->phone)->toBe('+370 0012345');
    $page->fill('input[name="settlement.serviceChargePercent"]', '9.50');
    settingsBrowserOffline($page, true);
    settingsBrowserClick($page, 'button[wire\:click="cancelGroup(\'settlement\')"]');
    $page->assertValue('input[name="settlement.serviceChargePercent"]', '7.25');
    settingsBrowserOffline($page, false);
    expect($this->branch->fresh()->settings->service_charge_basis_points)->toBe(725);
    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

test('new guest entry observes disabled guest opening while a saved fee leaves existing payment unchanged', function (): void {
    BranchSetting::factory()->for($this->branch)->create(['allow_guest_created_sessions' => true]);
    $point = ServicePoint::factory()->for($this->branch)->create(['is_active' => true]);
    $qr = QrCode::factory()->forServicePoint($point)->active()->create();
    Menu::factory()->for($this->branch)->active()->create();
    $historicalPoint = ServicePoint::factory()->for($this->branch)->create();
    $session = TableSession::factory()->forServicePoint($historicalPoint)->closed()->create();
    $payment = ManualPayment::factory()->forTableSession($session)->create([
        'covered_subtotal_cents' => 1000, 'service_charge_basis_points' => 500,
        'service_charge_cents' => 50, 'tips_cents' => 100, 'amount_cents' => 1150,
    ]);
    $originalPayment = $payment->fresh()->getAttributes();
    $page = settingsBrowserOwner($this->owner, $this->settingsUrl.'?section=guests');
    settingsBrowserClick($page, '[wire\:model="guests.allowGuestCreatedSessions"][role="switch"]');
    settingsBrowserClick($page, 'form[data-settings-group="guests"] button[type="submit"]');
    expect($this->branch->fresh()->settings->allow_guest_created_sessions)->toBeFalse();
    $guest = visit(route('public.qr.show', ['token' => $qr->public_token], false));
    $guest->fill('guest_name', 'Browser visitor')->click('form[wire\:submit="enterTable"] button[type="submit"]')
        ->assertSee(__('guest.table.guest_created_sessions_disabled'));
    expect(TableSession::query()->count())->toBe(1);
    settingsBrowserClick($page, '[data-menu-section="settlement"]');
    $page->fill('input[name="settlement.serviceChargePercent"]', '12.50');
    settingsBrowserClick($page, '[wire\:model="settlement.serviceChargeEnabled"][role="switch"]');
    settingsBrowserClick($page, 'form[data-settings-group="settlement"] button[type="submit"]');
    expect($this->branch->fresh()->settings->service_charge_basis_points)->toBe(1250)
        ->and($this->branch->fresh()->settings->service_charge_enabled)->toBeTrue()
        ->and($payment->fresh()->getAttributes())->toBe($originalPayment)
        ->and($session->fresh()->status)->toBe($session->status);
    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    $guest->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

test('settings protects a draft when switching restaurants and a logged out tab cannot save', function (): void {
    $secondOrganization = app(CreateOrganizationAction::class)->handle($this->owner, ['name' => 'Other organization']);
    $second = Branch::factory()->for($secondOrganization)->create(['name' => 'Other restaurant']);
    $page = settingsBrowserOwner($this->owner, $this->settingsUrl);
    $page->fill('input[name="profileForm.phone"]', '+370 00999');
    settingsBrowserClick($page, '.workspace-restaurant__trigger');
    settingsBrowserClick($page, 'dialog[data-modal="workspace-restaurant"] [data-flux-select-button]');
    $page->fill('dialog[data-modal="workspace-restaurant"] input', 'Other restaurant');
    settingsBrowserClick($page, 'dialog[data-modal="workspace-restaurant"] ui-option[value="'.$second->id.'"]');
    settingsBrowserClick($page, 'dialog[data-modal="workspace-restaurant"] button[type="submit"]');
    $page->assertVisible('dialog[data-modal="settings-unsaved"][open]')->assertPathIs($this->settingsUrl);
    settingsBrowserClick($page, 'dialog[data-modal="settings-unsaved"] button[x-on\:click="cancelNavigation"]');
    $page->assertValue('input[name="profileForm.phone"]', '+370 00999')->assertPathIs($this->settingsUrl);
    expect($this->branch->fresh()->phone)->toBeNull()->and($second->fresh()->phone)->toBeNull();

    $otherTab = new AwaitableWebpage($page->page()->context()->newPage(), $page->url());
    $otherTab->navigate($this->settingsUrl)->click('@sidebar-menu-button')->click('@logout-button')->assertMissing('[data-page="restaurant-settings"]');
    $responses = [];
    Event::listen(RequestHandled::class, function (RequestHandled $event) use (&$responses): void {
        if ($event->request->route()?->named('*livewire.update')) {
            $responses[] = ['status' => $event->response->getStatusCode(), 'location' => parse_url((string) $event->response->headers->get('Location', ''), PHP_URL_PATH)];
        }
    });
    $page->assertVisible('form[data-settings-group="profile"] button[type="submit"]')->assertEnabled('form[data-settings-group="profile"] button[type="submit"]');
    $page->script(<<<'JS'
        document.querySelector('form[data-settings-group="profile"] button[type="submit"]').click(); void 0;
        JS);
    Execution::instance()->waitForExpectation(function () use (&$responses): void {
        expect(collect($responses)->contains(fn (array $response): bool => in_array($response['status'], [401, 403, 419], true) || $response['status'] === 302 && $response['location'] === '/login'))->toBeTrue();
    });
    expect($this->branch->fresh()->phone)->toBeNull()->and(BranchSettingsChange::query()->count())->toBe(0);
});

test('settings sections fit translated phone tablet and desktop layouts in both themes and two hundred percent zoom', function (string $locale): void {
    $this->owner->update(['locale' => $locale]);
    $page = settingsBrowserOwner($this->owner, $this->settingsUrl.'?lang='.$locale);
    $page->assertAttribute('html[lang]', 'lang', $locale)->assertSee(__('settings.title', [], $locale));
    $page->assertScript('getComputedStyle(document.querySelector("[data-page=restaurant-settings] > .rm-availability")).display', 'grid')
        ->assertScript('parseFloat(getComputedStyle(document.querySelector("[data-settings-active=true]")).rowGap) > 0', true);
    foreach (['light', 'dark'] as $theme) {
        $page->script("window.Flux.appearance = '{$theme}'");
        foreach ([320, 390, 768, 1024, 1440] as $width) {
            $page->resize($width, 900);
            foreach (['profile', 'guests', 'settlement', 'locale', 'advanced'] as $section) {
                settingsBrowserClick($page, '[data-menu-section="'.$section.'"]');
                $page->assertVisible('#'.$section.'-heading')
                    ->assertScript('document.documentElement.scrollWidth <= innerWidth + 1', true);
            }
            if ($width === 1440) {
                $page->assertScript('(() => { const root = document.querySelector("[data-page=restaurant-settings]"); return root.querySelector("[data-menu-workspace-content]").getBoundingClientRect().left >= root.querySelector("aside").getBoundingClientRect().right; })()', true);
            }
            $page->screenshot(false, "settings-{$locale}-{$theme}-{$width}");
        }
        $page->resize(390, 900)->script('document.documentElement.style.zoom = "2"');
        settingsBrowserClick($page, '[data-menu-section="profile"]');
        $page->assertScript('document.documentElement.scrollWidth <= innerWidth + 1', true)
            ->screenshot(false, "settings-{$locale}-{$theme}-zoom200");
        $page->script('document.documentElement.style.zoom = ""');
    }
    $page->keys('input[name="profileForm.phone"]', 'Tab')->assertScript('document.activeElement.matches(":focus-visible")', true)
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    settingsBrowserMetrics($page, 'responsive-'.$locale);
})->with(['en', 'lt', 'ru']);

test('invalid public translation containers open the right language focus the group and retain other drafts', function (string $interfaceLocale): void {
    $this->owner->update(['locale' => $interfaceLocale]);
    $original = $this->branch->fresh()->getAttributes();
    $page = settingsBrowserOwner($this->owner, $this->settingsUrl.'?lang='.$interfaceLocale);
    $page->fill('input[name="profileForm.translations.en.name"]', 'Unpublished English name')
        ->fill('input[name="profileForm.phone"]', '+370 0012345');
    foreach (['broken', ['unknown' => 'x']] as $index => $invalidTranslation) {
        if ($index > 0) {
            settingsBrowserClick($page, 'ui-select[wire\:model\.live="contentLanguage"] button[role="combobox"]');
            settingsBrowserClick($page, 'ui-select[wire\:model\.live="contentLanguage"] ui-option[value="en"]');
        }
        settingsBrowserClick($page, '[data-menu-section="advanced"]');
        $page->assertQueryStringHas('section', 'advanced')->assertVisible('#advanced-heading')
            ->fill('input[name="advanced.inactivityWarningMinutes"]', '75');
        $page->script('(() => { const root = document.querySelector(\'[data-page="restaurant-settings"]\'); const wire = Livewire.find(root.getAttribute(\'wire:id\')); wire.$set(\'profileForm.translations.lt\', '.json_encode($invalidTranslation, JSON_THROW_ON_ERROR).', false); wire.$call(\'saveProfile\'); })(); void 0;');
        $page->assertVisible('form[data-settings-group="profile"]')->assertQueryStringMissing('section')
            ->assertQueryStringHas('language', 'lt')->assertAttribute('html[lang]', 'lang', $interfaceLocale)
            ->assertVisible('[id="profileForm.translations.lt"] > [data-flux-error]')
            ->assertSeeIn('[id="profileForm.translations.lt"] > [data-flux-error]', __('validation.array', ['attribute' => __('ui.languages.lt', [], $interfaceLocale)], $interfaceLocale))
            ->assertScript('document.activeElement.id', 'profileForm.translations.lt')
            ->assertValue('input[name="profileForm.translations.en.name"]', 'Unpublished English name')
            ->assertValue('input[name="profileForm.phone"]', '+370 0012345')
            ->assertValue('input[name="advanced.inactivityWarningMinutes"]', '75');
        expect($this->branch->fresh()->getAttributes())->toBe($original)
            ->and(BranchSetting::query()->where('branch_id', $this->branch->id)->exists())->toBeFalse()
            ->and(BranchSettingsChange::query()->where('branch_id', $this->branch->id)->count())->toBe(0);
    }
    $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
})->with(['en', 'lt', 'ru']);

function settingsBrowserOwner(User $owner, string $url): PendingAwaitablePage
{
    $page = visit(route('login', absolute: false));
    $page->fill('email', $owner->email)->fill('password', 'password')->click('@login-button')->assertMissing('input[type="password"]');
    $page->navigate($url)->assertVisible('[data-page="restaurant-settings"]')->assertScript('document.querySelector("[data-page=restaurant-settings]").inert', false);

    return $page;
}

function settingsBrowserClick(PendingAwaitablePage|AwaitableWebpage $page, string $selector): void
{
    $page->assertVisible($selector)->assertEnabled($selector)->click($selector)->wait(0.15);
}

function settingsBrowserOffline(PendingAwaitablePage $page, bool $offline): void
{
    $guid = (new ReflectionProperty($page->page()->context(), 'guid'))->getValue($page->page()->context());
    foreach (Client::instance()->execute($guid, 'setOffline', ['offline' => $offline]) as $message) {
    }
    $page->assertScript('navigator.onLine', ! $offline);
}

function settingsBrowserMetrics(PendingAwaitablePage $page, string $scenario): void
{
    $metrics = $page->script(<<<'JS'
        (() => {
            const navigation = performance.getEntriesByType('navigation')[0];
            const root = document.querySelector('[data-page="restaurant-settings"]');
            return {
                viewport: innerWidth,
                html_bytes: new TextEncoder().encode(document.documentElement.outerHTML).byteLength,
                livewire_snapshot_bytes: new TextEncoder().encode(root.getAttribute('wire:snapshot') ?? '').byteLength,
                rendered_forms: root.querySelectorAll('form').length,
                navigation_ttfb_ms: navigation ? navigation.responseStart - navigation.requestStart : null,
                navigation_duration_ms: navigation?.duration ?? null,
                js_heap_bytes: performance.memory?.usedJSHeapSize ?? null,
                user_agent: navigator.userAgent,
            };
        })()
        JS);
    file_put_contents(storage_path('framework/settings-'.$scenario.'-metrics.json'), json_encode($metrics, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
}
