<?php

use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

test('auth header renders the page title as a semantic heading', function () {
    $html = Blade::render(<<<'BLADE'
        <x-auth-header title="Log in" description="Use your restaurant account." />
    BLADE);

    expect($html)
        ->toContain('<h1')
        ->toContain('Log in')
        ->toContain('Use your restaurant account.');
});

test('simple design system components render shared ui primitives', function () {
    $html = Blade::render(<<<'BLADE'
        <x-ui.card heading="Tables" description="Safe QR rules">
            <x-ui.button variant="primary" icon="plus" wire:click="save" full-width>Save</x-ui.button>
            <x-ui.button href="/guest" icon-trailing="arrow-right">Open guest</x-ui.button>
            <x-ui.primary-button label="ui.actions.save" wire:click="savePrimary" />
            <x-ui.secondary-button label="ui.actions.cancel" />
            <x-ui.danger-button label="ui.actions.delete" wire:click="deleteRecord" />
            <x-ui.status-badge tone="warning" dot>Waiting</x-ui.status-badge>
            <x-ui.status-badge status="paid" context="payment" />
            <x-ui.money cents="1450" currency="EUR" />
            <x-ui.alert tone="danger" heading="Careful">Danger copy</x-ui.alert>
            <x-ui.empty-state heading="ui.empty.no_results" description="ui.empty.no_service_points" icon="inbox" />
            <x-ui.page-header title="reports.orders.title" description="reports.exports.description">
                <x-slot:actions>
                    <x-ui.primary-button label="ui.actions.continue" />
                </x-slot:actions>
            </x-ui.page-header>
            <x-ui.form-input name="guest_name" label="guest.table.your_name" placeholder="guest.table.enter_name" wire:model="guestName" />
            <x-ui.select name="payment_method" label="payments.forms.method" :options="['cash' => 'ui.payment_methods.cash', 'card_terminal' => 'ui.payment_methods.card_terminal', 'other' => 'ui.payment_methods.other']" selected="cash" />
            <x-ui.textarea name="note" label="payments.forms.note" placeholder="guest.table.guest_name_placeholder">Kitchen note</x-ui.textarea>
            <x-ui.validation-error error="Translated validation error." />
            <x-ui.table-row title="reports.csv.name" subtitle="reports.csv.area" meta="guest.table.status">
                <x-slot:actions>
                    <x-ui.secondary-button label="ui.actions.continue" />
                </x-slot:actions>
            </x-ui.table-row>
            <x-ui.confirmation-modal
                trigger-label="ui.actions.delete"
                title="ui.confirmations.danger.title"
                description="ui.confirmations.danger.description"
                confirm-label="ui.actions.delete"
                confirm-action="deleteRecord"
            />
            <x-ui.mobile-bottom-actions summary="Total €0.00">
                <x-ui.button variant="primary" full-width>Send</x-ui.button>
            </x-ui.mobile-bottom-actions>
            <x-ui.metric-strip :items="[
                ['label' => 'Tables', 'value' => 4],
                ['label' => 'Needs attention', 'value' => 2, 'tone' => 'danger'],
            ]" />
            <x-ui.area-icon type="terrace" label="Terrace" />
            <x-ui.service-point-icon type="bar_seat" label="Bar seat" />
        </x-ui.card>
    BLADE);

    expect($html)
        ->toContain('Tables')
        ->toContain('Safe QR rules')
        ->toContain('wire:click="save"')
        ->toContain('wire:click="savePrimary"')
        ->toContain('wire:click="deleteRecord"')
        ->toContain('href="/guest"')
        ->toContain('Save')
        ->toContain('Cancel')
        ->toContain('Delete')
        ->toContain('Waiting')
        ->toContain('Paid')
        ->toContain('€14.50')
        ->toContain('Careful')
        ->toContain('Danger copy')
        ->toContain('No results yet.')
        ->toContain('Orders')
        ->toContain(__('reports.exports.description'))
        ->toContain('Continue')
        ->toContain('Your name')
        ->toContain('Enter your name to continue.')
        ->toContain('Payment method')
        ->toContain('Cash')
        ->toContain('Card terminal')
        ->toContain('Other')
        ->toContain('Note')
        ->toContain('Kitchen note')
        ->toContain('Translated validation error.')
        ->toContain('Name')
        ->toContain('Area')
        ->toContain('Status')
        ->toContain('Confirm dangerous action')
        ->toContain('Please confirm before continuing.')
        ->toContain('data-flux-modal-trigger')
        ->toContain('data-flux-modal')
        ->toContain('focusable="focusable"')
        ->toContain('autofocus="autofocus"')
        ->toContain('<button')
        ->not->toContain('<x-ui.danger-button')
        ->toContain('Total €0.00')
        ->toContain('sticky bottom-0')
        ->toContain('Needs attention')
        ->toContain('title="Terrace"')
        ->toContain('title="Bar seat"')
        ->toContain('data-flux-icon');
});

test('service point icon safely falls back when persisted icon is unsupported', function () {
    $html = Blade::render(<<<'BLADE'
        <x-ui.service-point-icon type="table" icon="square" label="Legacy table" />
    BLADE);

    expect($html)
        ->toContain('title="Legacy table"')
        ->toContain('data-flux-icon')
        ->not->toContain('icon.square');
});

test('area icon safely falls back when persisted icon is unsupported', function () {
    $html = Blade::render(<<<'BLADE'
        <x-ui.area-icon type="bar_area" icon="martini" label="Legacy bar" />
    BLADE);

    expect($html)
        ->toContain('title="Legacy bar"')
        ->toContain('data-flux-icon')
        ->not->toContain('icon.martini');
});

test('authentication forms do not force focus before the user chooses a field', function () {
    foreach ([route('login'), route('password.request')] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertDontSee('autofocus', false);
    }
});

test('application head uses favicon assets served successfully by shared hosting', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('href="/favicon.svg"', false)
        ->assertSee('href="/apple-touch-icon.png"', false)
        ->assertDontSee('href="/favicon.ico"', false);
});

test('application layout zones provide a keyboard skip link and main target', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('href="#main-content"', false)
        ->assertSee('id="main-content"', false)
        ->assertSee('data-primary-action="staff-login"', false)
        ->assertDontSee('href="'.route('restaurant.dashboard').'"', false)
        ->assertDontSee('href="'.route('superadmin.dashboard').'"', false);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('href="#main-content"', false)
        ->assertSee('id="main-content"', false);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('href="#main-content"', false)
        ->assertSee('id="main-content"', false);
});

test('design contract and sidecar describe the calm service pass system', function () {
    $design = File::get(base_path('DESIGN.md'));
    $sidecar = json_decode(File::get(base_path('.impeccable/design.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($design)
        ->toContain('The Calm Service Pass')
        ->toContain('surface-selected:')
        ->toContain('dark-surface-selected:')
        ->and(preg_match_all('/^## (?:\d+\. )?(Overview|Colors|Typography|Elevation|Components|Do\'s and Don\'ts)$/m', $design))
        ->toBe(6)
        ->and($sidecar['schemaVersion'])->toBe(2)
        ->and($sidecar['narrative']['northStar'])->toBe('The Calm Service Pass')
        ->and($sidecar['components'])->toHaveCount(8);
});

test('runtime stylesheet exposes semantic workspace roles and avoids decorative card treatments', function () {
    $css = File::get(resource_path('css/app.css'));

    expect($css)
        ->toContain('--color-surface-raised:')
        ->toContain('--color-surface-selected:')
        ->toContain('--color-border-strong:')
        ->toContain('--color-success-border:')
        ->toContain('--color-warning-border:')
        ->toContain('--color-danger-border:')
        ->toContain('--color-information-border:')
        ->toContain('--spacing-operational-touch: 3.5rem;')
        ->toContain('--duration-state: 180ms;')
        ->toContain('--shadow-card: 0 1px 2px oklch(0.21 0.018 45 / 0.08);')
        ->not->toContain('border-left-width: 10px;')
        ->not->toContain('0 8px 24px');
});

test('semantic utility names resolve to declared design tokens', function () {
    $sources = collect(File::allFiles(resource_path('views')))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
        ->map(fn (SplFileInfo $file): string => $file->getContents())
        ->implode("\n");

    expect($sources)
        ->not->toContain('bg-action')
        ->not->toContain('text-text-secondary')
        ->not->toMatch('/(?<![a-z-])border-strong/')
        ->not->toContain('focus:border-strong');
});

test('coarse pointer and livewire request states preserve practical controls', function () {
    $css = File::get(resource_path('css/app.css'));

    expect($css)
        ->toContain('@media (pointer: coarse)')
        ->toContain('[data-flux-control]')
        ->toContain('[data-flux-button]:not(.min-h-operational-touch)')
        ->toContain('[data-flux-sidebar-item]')
        ->toContain('[data-loading]');
});

test('application shell keeps sidebar headings and account menu names accessible', function () {
    $sidebar = File::get(resource_path('views/layouts/app/sidebar.blade.php'));
    $desktopUserMenu = File::get(resource_path('views/components/desktop-user-menu.blade.php'));

    expect($sidebar)
        ->toContain('[&>div:first-child>div]:!text-text-muted')
        ->toContain("'initials' => \$authenticatedUser['initials']")
        ->and($desktopUserMenu)
        ->toContain("'initials' => \$initials");
});

test('product mark is purpose-built and exposes decorative and standalone semantics', function () {
    $decorative = Blade::render('<x-app-logo-icon />');
    $standalone = Blade::render('<x-app-logo-icon :decorative="false" label="Restaurant menu" />');

    expect($decorative)
        ->toContain('data-product-mark="service-pass"')
        ->toContain('viewBox="0 0 24 24"')
        ->toContain('aria-hidden="true"')
        ->not->toContain('M17.2 5.633')
        ->and($standalone)
        ->toContain('role="img"')
        ->toContain('aria-label="Restaurant menu"')
        ->not->toContain('aria-hidden="true"');
});

test('auth layout home links keep a minimum touch target', function () {
    foreach (['simple', 'card', 'split'] as $layout) {
        $source = File::get(resource_path("views/layouts/auth/{$layout}.blade.php"));

        expect($source)->toMatch('/<a[^>]+href="\{\{ route\(\'home\'\) \}\}"[^>]+class="[^"]*min-h-11[^"]*"/');
    }
});

test('context workspace components render prepared presentation data accessibly', function () {
    expect([
        File::exists(resource_path('views/components/ui/priority-row.blade.php')),
        File::exists(resource_path('views/components/ui/workspace-split.blade.php')),
        File::exists(app_path('View/Components/Ui/StatePanel.php')),
        File::exists(resource_path('views/components/ui/state-panel.blade.php')),
    ])->each->toBeTrue();

    $html = Blade::render(<<<'BLADE'
        <x-ui.page-header
            title="Orders"
            description="Review current service."
            context="Old Town · Dinner"
            breadcrumb-label="Workspaces"
            :breadcrumbs="$breadcrumbs"
            :status="$status"
        >
            <x-slot:actions><button type="button">Open table</button></x-slot:actions>
        </x-ui.page-header>

        <x-ui.workspace-split>
            <x-slot:queue>
                <x-ui.priority-row title="Table 12" description="Waiting 7 minutes" tone="warning" selected>
                    <x-slot:meta>Draft ready</x-slot:meta>
                    <x-slot:actions><button type="button">Review</button></x-slot:actions>
                </x-ui.priority-row>
            </x-slot:queue>
            <x-slot:detail><p>Selected table detail</p></x-slot:detail>
            <x-slot:empty-detail><p>Select a table</p></x-slot:empty-detail>
        </x-ui.workspace-split>

        <x-ui.state-panel kind="loading" title="Loading tables" description="Current service data is loading." />
    BLADE, [
        'breadcrumbs' => [
            ['label' => 'Restaurant', 'href' => '/restaurant'],
            ['label' => 'Waiter', 'current' => true],
        ],
        'status' => ['label' => 'Live service', 'tone' => 'success'],
    ]);

    expect($html)
        ->toContain('aria-label="Workspaces"')
        ->toContain('Old Town · Dinner')
        ->toContain('aria-current="page"')
        ->toContain('Live service')
        ->toContain('data-workspace-split')
        ->toContain('data-priority-row')
        ->toContain('data-selected="true"')
        ->toContain('min-h-operational-touch')
        ->toContain('data-state="loading"')
        ->toContain('aria-busy="true"')
        ->toContain('role="status"')
        ->toContain('Loading tables');
});

test('shared ui primitives consume semantic color roles instead of palette utilities', function () {
    $sources = collect([
        app_path('View/Components/Ui/Alert.php'),
        app_path('View/Components/Ui/Card.php'),
        app_path('View/Components/Ui/StatusBadge.php'),
        app_path('View/Components/Ui/TableRow.php'),
        resource_path('views/components/ui/card.blade.php'),
        resource_path('views/components/ui/empty-state.blade.php'),
        resource_path('views/components/ui/form-field.blade.php'),
        resource_path('views/components/ui/form-input.blade.php'),
        resource_path('views/components/ui/mobile-bottom-actions.blade.php'),
        resource_path('views/components/ui/select.blade.php'),
        resource_path('views/components/ui/table-row.blade.php'),
        resource_path('views/components/ui/textarea.blade.php'),
    ])->map(fn (string $path): string => File::get($path))->implode("\n");

    expect($sources)
        ->not->toMatch('/(?:bg|border|text|ring|placeholder:text)-(?:zinc|red|amber|emerald|sky|orange|violet|lime)-/')
        ->not->toContain('shadow-sm');
});
