<?php

use App\Models\User;
use Illuminate\Support\Facades\Blade;

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
            <x-ui.money amount="14.5" currency="EUR" />
            <x-ui.alert tone="danger" heading="Careful">Danger copy</x-ui.alert>
            <x-ui.empty-state heading="ui.empty.no_results" description="ui.empty.no_service_points" icon="inbox" />
            <x-ui.page-header title="ui.headers.orders.title" description="ui.headers.orders.description">
                <x-slot:actions>
                    <x-ui.primary-button label="ui.actions.new_order" />
                </x-slot:actions>
            </x-ui.page-header>
            <x-ui.form-input name="guest_name" label="ui.forms.guest_name" placeholder="ui.forms.guest_name_placeholder" wire:model="guestName" />
            <x-ui.select name="payment_method" label="ui.forms.payment_method" :options="['cash' => 'ui.payment_methods.cash', 'card_terminal' => 'ui.payment_methods.card_terminal', 'other' => 'ui.payment_methods.other']" selected="cash" />
            <x-ui.textarea name="note" label="ui.forms.note" placeholder="ui.forms.note_placeholder">Kitchen note</x-ui.textarea>
            <x-ui.validation-error error="Translated validation error." />
            <x-ui.table-row title="ui.rows.table_one.title" subtitle="ui.rows.table_one.subtitle" meta="ui.rows.table_one.meta">
                <x-slot:actions>
                    <x-ui.secondary-button label="ui.actions.open" />
                </x-slot:actions>
            </x-ui.table-row>
            <x-ui.confirmation-modal
                trigger-label="ui.actions.delete"
                title="ui.confirmations.delete.title"
                description="ui.confirmations.delete.description"
                confirm-label="ui.actions.delete"
                confirm-action="deleteRecord"
            />
            <x-ui.mobile-bottom-actions summary="Total 0.00 EUR">
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
        ->toContain(__('ui.headers.orders.description'))
        ->toContain('New order')
        ->toContain('Guest name')
        ->toContain('Name shown to staff')
        ->toContain('Payment method')
        ->toContain('Cash')
        ->toContain('Card terminal')
        ->toContain('Other')
        ->toContain('Note')
        ->toContain('Kitchen note')
        ->toContain('Translated validation error.')
        ->toContain('Table 1')
        ->toContain('Window side')
        ->toContain('Open')
        ->toContain('Delete record?')
        ->toContain('This action cannot be undone.')
        ->toContain('data-flux-modal-trigger')
        ->toContain('data-flux-modal')
        ->toContain('focusable="focusable"')
        ->toContain('autofocus="autofocus"')
        ->toContain('<button')
        ->not->toContain('<x-ui.danger-button')
        ->toContain('Total 0.00 EUR')
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
