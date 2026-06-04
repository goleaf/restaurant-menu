<?php

use Illuminate\Support\Facades\Blade;

test('simple design system components render shared ui primitives', function () {
    $html = Blade::render(<<<'BLADE'
        <x-ui.card heading="Tables" description="Safe QR rules">
            <x-ui.button variant="primary" icon="plus" wire:click="save" full-width>Save</x-ui.button>
            <x-ui.button href="/guest" icon-trailing="arrow-right">Open guest</x-ui.button>
            <x-ui.status-badge tone="warning" dot>Waiting</x-ui.status-badge>
            <x-ui.alert tone="danger" heading="Careful">Danger copy</x-ui.alert>
            <x-ui.empty-state heading="Nothing here" description="Start with a table" icon="inbox" />
            <x-ui.mobile-bottom-actions summary="Total 0.00 EUR">
                <x-ui.button variant="primary" full-width>Send</x-ui.button>
            </x-ui.mobile-bottom-actions>
            <x-ui.area-icon type="terrace" label="Terrace" />
            <x-ui.service-point-icon type="bar_seat" label="Bar seat" />
        </x-ui.card>
    BLADE);

    expect($html)
        ->toContain('Tables')
        ->toContain('Safe QR rules')
        ->toContain('wire:click="save"')
        ->toContain('href="/guest"')
        ->toContain('Waiting')
        ->toContain('Careful')
        ->toContain('Danger copy')
        ->toContain('Nothing here')
        ->toContain('Total 0.00 EUR')
        ->toContain('sticky bottom-0')
        ->toContain('title="Terrace"')
        ->toContain('title="Bar seat"')
        ->toContain('data-flux-icon');
});
