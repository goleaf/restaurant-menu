<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;

test('the local Pro component family renders through normal package discovery', function (string $template, string $marker): void {
    view()->share('errors', new ViewErrorBag);
    $html = Blade::render($template);

    expect($html)->toContain($marker)
        ->not->toContain('<flux:', '@blaze', '@props');
})->with([
    'accordion' => ['<flux:accordion><flux:accordion.item heading="Details">Soup</flux:accordion.item></flux:accordion>', 'data-flux-accordion-item'],
    'autocomplete' => ['<flux:autocomplete name="dish"><flux:autocomplete.item>Soup</flux:autocomplete.item></flux:autocomplete>', 'data-flux-autocomplete'],
    'calendar' => ['<flux:calendar name="date" value="2026-09-16" locale="en" />', '<ui-calendar'],
    'chart' => ['<flux:chart :value="[[\'date\' => \'2026-09-16\', \'value\' => 2]]"><flux:chart.svg><flux:chart.bar /></flux:chart.svg></flux:chart>', '<ui-chart'],
    'command' => ['<flux:command><flux:command.input /><flux:command.items><flux:command.item href="/settings/profile">Profile</flux:command.item><flux:command.empty>Empty</flux:command.empty></flux:command.items></flux:command>', 'data-flux-command'],
    'composer' => ['<flux:composer name="note" />', 'data-flux-composer'],
    'context' => ['<flux:context><span>Dish</span><flux:menu><flux:menu.item>View</flux:menu.item></flux:menu></flux:context>', 'data-flux-context'],
    'date picker' => ['<flux:date-picker name="report-date" value="2026-09-16" locale="en" />', '<ui-date-picker'],
    'editor' => ['<flux:editor name="description" />', 'data-flux-editor'],
    'file upload and item' => ['<flux:file-upload name="images"><flux:file-upload.dropzone heading="Images" /></flux:file-upload><flux:file-item heading="Soup.jpg" :size="1024"><x-slot:actions><flux:file-item.remove /></x-slot:actions></flux:file-item>', 'data-flux-file-item'],
    'kanban' => ['<flux:kanban><flux:kanban.column><flux:kanban.column.header heading="New" /><flux:kanban.column.cards><flux:kanban.card>Soup</flux:kanban.card></flux:kanban.column.cards></flux:kanban.column></flux:kanban>', 'data-flux-kanban'],
    'pillbox' => ['<flux:pillbox name="allergens"><flux:pillbox.option value="milk">Milk</flux:pillbox.option></flux:pillbox>', 'data-flux-pillbox'],
    'popover' => ['<flux:dropdown><flux:button>Filters</flux:button><flux:popover>Settings</flux:popover></flux:dropdown>', 'data-flux-popover'],
    'select listbox' => ['<flux:select name="branch" variant="listbox" searchable><flux:select.option value="one">Branch</flux:select.option></flux:select>', '<ui-select'],
    'slider' => ['<flux:slider name="focal" value="50" min="0" max="100" step="1" />', '<ui-slider'],
    'tabs' => ['<flux:tab.group><flux:tabs><flux:tab name="details">Details</flux:tab></flux:tabs><flux:tab.panel name="details">Soup</flux:tab.panel></flux:tab.group>', 'data-flux-tab'],
    'time picker' => ['<flux:time-picker name="opens" value="12:00" locale="en" time-format="24-hour" />', '<ui-time-picker'],
    'timeline' => ['<flux:timeline><flux:timeline.item><flux:timeline.indicator /><flux:timeline.content>Confirmed</flux:timeline.content></flux:timeline.item></flux:timeline>', 'data-flux-timeline'],
]);
