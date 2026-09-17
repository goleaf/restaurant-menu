<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\MenuStatus;
use App\Models\Branch;
use App\Models\DraftOrderItem;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemPermissionsSeeder;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Playwright\Client;
use Tests\Support\IsolatedBrowserIdentity;

beforeEach(function (): void {
    $this->withVite();
    IsolatedBrowserIdentity::configure();
    $this->seed(SystemPermissionsSeeder::class);
    $this->owner = User::factory()->create(['email' => 'availability.owner@example.test']);
    $this->organization = app(CreateOrganizationAction::class)->handle($this->owner, ['name' => 'Šeimos virtuvė — Семейная кухня']);
    $this->branch = Branch::factory()->for($this->organization)->withDefaultSettings()->create(['name' => 'Senamiesčio šeimos restoranas — Ресторан старого города', 'timezone' => 'UTC']);
    $this->menu = Menu::factory()->for($this->branch)->create(['name' => 'Seasonal family menu — Сезонное меню', 'status' => MenuStatus::Active]);
    $category = MenuCategory::factory()->for($this->menu)->active()->create(['name' => 'Pagrindiniai patiekalai — Основные блюда']);
    $this->dish = MenuItem::factory()->for($this->menu)->for($category, 'category')->create(['name' => 'Burokėlių sriuba su šviežiomis daržovėmis — Овощной суп', 'is_available' => true, 'price_cents' => 1250]);
    $this->availabilityUrl = route('organizations.brands.branches.availability.index', [$this->organization, $this->branch->brand, $this->branch], false);
});

test('a restaurant pause blocks a separate guest context while preserving its draft and expires on the next request', function (): void {
    $this->travelTo(CarbonImmutable::now()->startOfSecond());
    $admin = availabilityBrowserOwner($this->owner, $this->availabilityUrl);
    $point = ServicePoint::factory()->for($this->branch)->create(['is_active' => true]);
    $qr = QrCode::factory()->forServicePoint($point)->active()->create();
    $session = TableSession::factory()->forServicePoint($point)->waiterOpened()->active()->create();
    $person = TableSessionGuest::factory()->for($session)->active()->create(['locale' => 'en']);
    $this->withCookie('guest_token_'.substr(hash('sha256', $qr->public_token), 0, 24), $person->guest_token);
    $guestUrl = route('public.qr.show', ['token' => $qr->public_token], false);
    $guest = visit($guestUrl);
    availabilityBrowserClick($guest, '#guest-menu-item-details-'.$this->dish->id);
    availabilityBrowserClick($guest, 'button[wire\:click="saveConfiguredItem"]');
    $line = DraftOrderItem::query()->sole();
    expect($line->unit_price_cents)->toBe(1250);
    availabilityBrowserClick($admin, 'button[wire\:click="openPause"]');
    $admin->assertSee(__('availability.public_reason'));
    $admin->select('select[name="pause.mode"]', 'duration')->fill('input[name="pause.durationMinutes"]', '1')
        ->fill('textarea[name="pause.reason"]', 'Short kitchen reset.');
    availabilityBrowserClick($admin, 'form[wire\:submit="previewPause"] button[type="submit"]');
    expect($this->branch->fresh()->is_temporarily_closed)->toBeFalse();
    $admin->assertVisible('[data-availability-preview]')->screenshot(false, 'availability-pause-preview');
    availabilityBrowserClick($admin, 'button[wire\:click="applyPause"]');
    expect($this->branch->fresh()->is_temporarily_closed)->toBeTrue();
    $guest->navigate($guestUrl)->assertSee($this->dish->name)->assertSee(__('availability.guest.paused'))->assertSee('Short kitchen reset.');
    availabilityBrowserClick($guest, '#guest-menu-item-details-'.$this->dish->id);
    $guest->assertMissing('button[wire\:click="saveConfiguredItem"]')->screenshot(false, 'availability-guest-paused');
    expect($line->fresh()->unit_price_cents)->toBe(1250)->and(DraftOrderItem::query()->count())->toBe(1);
    $this->travel(60)->seconds();
    $guest->navigate($guestUrl);
    availabilityBrowserClick($guest, '#guest-menu-item-details-'.$this->dish->id);
    $guest->assertVisible('button[wire\:click="saveConfiguredItem"]');
    expect($this->branch->fresh()->is_temporarily_closed)->toBeTrue()->and($line->fresh()->unit_price_cents)->toBe(1250);
    $admin->navigate($this->availabilityUrl.'?section=stoplist&item='.$this->dish->id);
    $admin->fill('textarea[name="restriction.reason"]', 'Internal stop rationale: supplier dispute.');
    availabilityBrowserClick($admin, 'form[wire\:submit="previewRestriction"] button[type="submit"]');
    availabilityBrowserClick($admin, 'button[wire\:click="applyRestriction"]');
    $guest->navigate($guestUrl)->assertSee($this->dish->name)->assertDontSee('Internal stop rationale: supplier dispute.');
    expect($this->dish->fresh()->is_available)->toBeFalse()->and(DraftOrderItem::query()->count())->toBe(1);
    $admin->assertNoJavaScriptErrors();
    $guest->assertNoJavaScriptErrors();
});

test('restriction and schedule editors preserve separate limits and allow local offline discard', function (): void {
    $this->dish->update(['hidden_until' => now()->addDay(), 'is_available' => false]);
    $admin = availabilityBrowserOwner($this->owner, $this->availabilityUrl.'?section=stoplist&item='.$this->dish->id);
    $admin->assertVisible('[data-availability-editor]')->select('select[name="restriction.operation"]', 'resume');
    availabilityBrowserClick($admin, 'form[wire\:submit="previewRestriction"] button[type="submit"]');
    $admin->assertSee(__('availability.reasons.item_hidden'))->screenshot(false, 'availability-resume-still-hidden');
    availabilityBrowserClick($admin, 'button[wire\:click="applyRestriction"]');
    expect($this->dish->fresh()->is_available)->toBeTrue()->and($this->dish->fresh()->hidden_until->isFuture())->toBeTrue();
    availabilityBrowserClick($admin, 'button[wire\:click="openItem('.$this->dish->id.')"]');
    $admin->select('select[name="restriction.operation"]', 'unhide');
    availabilityBrowserClick($admin, 'form[wire\:submit="previewRestriction"] button[type="submit"]');
    availabilityBrowserClick($admin, 'button[wire\:click="applyRestriction"]');
    expect($this->dish->fresh()->hidden_until)->toBeNull();
    $admin->navigate($this->availabilityUrl.'?section=schedules&menu='.$this->menu->id);
    availabilityBrowserClick($admin, 'button[wire\:click="openMenuSchedule"]');
    $admin->select('select[name="weekly.mode"]', 'closed');
    availabilityBrowserClick($admin, 'form[wire\:submit="previewSchedule"] button[type="submit"]');
    availabilityBrowserClick($admin, 'button[wire\:click="applySchedule"]');
    expect($this->menu->fresh()->schedule_is_closed)->toBeTrue();
    availabilityBrowserClick($admin, '[data-availability-section="now"]');
    availabilityBrowserClick($admin, 'button[wire\:click="openPause"]');
    $admin->fill('textarea[name="pause.reason"]', 'Unsaved local pause');
    availabilityBrowserClick($admin, '[data-availability-section="stoplist"]');
    $admin->assertSee(__('availability.unsaved_title'));
    availabilityBrowserClick($admin, 'button[x-on\:click="cancelNavigation"]');
    $admin->assertValue('textarea[name="pause.reason"]', 'Unsaved local pause');
    availabilityBrowserOffline($admin, true);
    $admin->assertDisabled('form[wire\:submit="previewPause"] button[type="submit"]');
    availabilityBrowserClick($admin, 'button[x-on\:click="cancelDraft"]');
    expect($admin->script("document.querySelector('[data-availability-editor]')?.hidden"))->toBeTrue();
    availabilityBrowserOffline($admin, false);
    $admin->wait(0.5);
    expect($this->branch->fresh()->is_temporarily_closed)->toBeFalse();
    availabilityBrowserClick($admin, '[data-availability-section="stoplist"]');
    $admin->assertQueryStringHas('section', 'stoplist')->assertNoJavaScriptErrors();
});

test('availability editors remain readable across locales themes widths and two hundred percent zoom', function (string $locale): void {
    $admin = availabilityBrowserOwner($this->owner, $this->availabilityUrl.'?section=stoplist&item='.$this->dish->id.'&lang='.$locale);
    $admin->assertAttribute('html[lang]', 'lang', $locale)->assertVisible('[data-availability-editor]');
    $admin->assertScript("getComputedStyle(document.querySelector('.rm-availability')).display", 'grid')
        ->assertScript("parseFloat(getComputedStyle(document.querySelector('.rm-availability')).rowGap) > 0", true);
    $admin->select('select[name="restriction.operation"]', 'hide');
    $admin->assertSee(__('availability.choose_time', [], $locale))->assertSee(__('availability.choose_date', [], $locale));
    if ($locale !== 'en') {
        $admin->assertDontSee('Select a time')->assertDontSee('Select date');
    }
    foreach (['light', 'dark'] as $theme) {
        $admin->script("window.Flux.appearance = '{$theme}'");
        foreach ([[320, 800], [390, 844], [768, 900], [1024, 900], [1440, 1000]] as [$width, $height]) {
            $admin->resize($width, $height);
            foreach ([1, 2] as $zoom) {
                $admin->script("document.documentElement.style.zoom = '{$zoom}'; document.querySelector('[data-availability-editor]').scrollIntoView({ block: 'start' })");
                expect($admin->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
                $admin->assertScript("(() => { const rect = document.querySelector('[data-availability-editor]').getBoundingClientRect(); return rect.left >= 0 && rect.right <= window.innerWidth; })()", true);
                $admin->assertScript("(() => { const link = document.querySelector('.skip-link'); return link.matches(':focus-visible') || link.getBoundingClientRect().bottom <= 0; })()", true)
                    ->assertScript("Array.from(document.querySelectorAll('[data-availability-editor] [data-flux-badge]')).every(badge => badge.scrollWidth <= badge.clientWidth)", true);
                if ($width === 320 && $zoom === 2) {
                    $admin->assertScript("document.querySelector('[data-flux-header]').getBoundingClientRect().bottom <= 0", true)
                        ->assertScript("(() => { const rect = document.querySelector('[data-availability-editor]').getBoundingClientRect(); return rect.top >= 0 && rect.top < window.innerHeight; })()", true);
                }
                $admin->screenshot(false, "availability-{$locale}-{$theme}-{$width}-zoom{$zoom}");
                if ($width === 320 && $zoom === 2) {
                    $admin->script("document.querySelector('textarea[name=\"restriction.reason\"]').scrollIntoView({ block: 'center' })");
                    $admin->assertScript("(() => { const rect = document.querySelector('textarea[name=\"restriction.reason\"]').getBoundingClientRect(); return rect.top >= 0 && rect.bottom <= window.innerHeight; })()", true);
                }
            }
            $admin->script("document.documentElement.style.zoom = ''");
        }
    }
    $admin->keys('body[class]', 'Tab');
    expect($admin->script("document.activeElement.matches(':focus-visible')"))->toBeTrue();
    $admin->script("document.querySelector('.skip-link').focus()");
    $admin->assertScript("document.querySelector('.skip-link').matches(':focus-visible')", true)
        ->assertScript("document.querySelector('.skip-link').getBoundingClientRect().top >= 0", true);
    $admin->assertNoJavaScriptErrors()->assertNoConsoleLogs();
})->with(['en', 'lt', 'ru']);

function availabilityBrowserOwner(User $owner, string $url): PendingAwaitablePage
{
    $page = visit(route('login', absolute: false));
    $page->fill('email', $owner->email)->fill('password', 'password')->click('@login-button')->assertPathIs(route('restaurant.dashboard', absolute: false));
    $page->navigate($url)->assertPresent('[data-page="availability-workspace"]');

    return $page;
}

function availabilityBrowserClick(PendingAwaitablePage $page, string $selector): void
{
    $page->assertVisible($selector)->assertEnabled($selector)->click($selector)->wait(0.3);
}

function availabilityBrowserOffline(PendingAwaitablePage $page, bool $offline): void
{
    $guid = (new ReflectionProperty($page->page()->context(), 'guid'))->getValue($page->page()->context());
    expect($guid)->toBeString();
    foreach (Client::instance()->execute($guid, 'setOffline', ['offline' => $offline]) as $message) {
        // Consume the installed Playwright protocol response before observing the browser.
    }
}
