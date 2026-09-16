@props(['locale', 'chart'])

<div class="space-y-6" data-pro-reference-catalog>
    <flux:callout icon="information-circle" :heading="__('ui.reference.pro.heading')" :text="__('ui.reference.pro.description')" class="callout-contrast" />

    <div class="grid min-w-0 gap-6 xl:grid-cols-2">
        <x-ui.card heading="ui.reference.pro.selection">
            <div class="space-y-4">
                <flux:select name="reference-pro-method" variant="listbox" searchable :label="__('payments.forms.method')" data-pro-reference="select">
                    <x-slot:search><flux:select.search :aria-label="__('layout.search')" :placeholder="__('layout.search')" /></x-slot:search>
                    <flux:select.option value="cash">{{ __('ui.payment_methods.cash') }}</flux:select.option>
                    <flux:select.option value="card_terminal">{{ __('ui.payment_methods.card_terminal') }}</flux:select.option>
                    <flux:select.option value="other">{{ __('ui.payment_methods.other') }}</flux:select.option>
                </flux:select>
                <flux:pillbox name="reference-pro-statuses" multiple searchable :label="__('ui.reference.status')" :placeholder="__('ui.organizations.brands.branches.menu.index.select')" data-pro-reference="pillbox">
                    <x-slot:search><flux:pillbox.search :aria-label="__('layout.search')" :placeholder="__('layout.search')" /></x-slot:search>
                    <flux:pillbox.option value="in_progress">{{ __('statuses.kitchen_ticket_item.in_progress') }}</flux:pillbox.option>
                    <flux:pillbox.option value="ready">{{ __('statuses.kitchen_ticket_item.ready') }}</flux:pillbox.option>
                </flux:pillbox>
                <flux:autocomplete name="reference-pro-name" :label="__('ui.reference.example_name')" data-pro-reference="autocomplete">
                    <flux:autocomplete.item>{{ __('ui.reference.example_name') }}</flux:autocomplete.item>
                </flux:autocomplete>
            </div>
        </x-ui.card>

        <x-ui.card heading="ui.reference.pro.details">
            <flux:tab.group data-pro-reference="tabs">
                <flux:tabs :aria-label="__('ui.reference.pro.details')">
                    <flux:tab name="reference-details">{{ __('ui.reference.form') }}</flux:tab>
                    <flux:tab name="reference-history">{{ __('ui.reference.states') }}</flux:tab>
                </flux:tabs>
                <flux:tab.panel name="reference-details">
                    <flux:accordion data-pro-reference="accordion">
                        <flux:accordion.item :heading="__('ui.reference.independent_note')">{{ __('ui.reference.independent_help') }}</flux:accordion.item>
                    </flux:accordion>
                </flux:tab.panel>
                <flux:tab.panel name="reference-history"><x-ui.state-panel kind="empty" title="ui.empty.no_notifications" /></flux:tab.panel>
            </flux:tab.group>
        </x-ui.card>

        <x-ui.card heading="ui.reference.pro.dates">
            <div class="space-y-4">
                <flux:date-picker name="reference-pro-date" :locale="$locale" value="2026-09-16" :label="__('ui.reference.time')" data-pro-reference="date-picker" />
                <flux:time-picker name="reference-pro-time" :locale="$locale" time-format="24-hour" value="12:00" :label="__('ui.reference.time')" data-pro-reference="time-picker" />
                <flux:calendar name="reference-pro-calendar" :locale="$locale" value="2026-09-16" size="sm" :aria-label="__('ui.reference.pro.dates')" data-pro-reference="calendar" />
            </div>
        </x-ui.card>

        <x-ui.card heading="ui.reference.pro.media">
            <div class="space-y-4">
                <flux:file-upload name="reference-pro-file" accept="image/*" :label="__('ui.reference.pro.media')" data-pro-reference="file-upload">
                    <flux:file-upload.dropzone :heading="__('ui.reference.pro.media')" :text="__('ui.reference.pro.description')" />
                </flux:file-upload>
                <flux:file-item heading="example.jpg" :size="1024">
                    <x-slot:actions><flux:file-item.remove :aria-label="__('ui.actions.delete')" /></x-slot:actions>
                </flux:file-item>
                <flux:slider name="reference-pro-position" value="50" min="0" max="100" step="1" :label="__('ui.reference.pro.position')" thumb:class="size-6" data-pro-reference="slider" />
            </div>
        </x-ui.card>

        <x-ui.card heading="ui.reference.pro.actions">
            <div class="space-y-4">
                <flux:command data-pro-reference="command">
                    <flux:command.input :aria-label="__('layout.search')" :placeholder="__('layout.search')" />
                    <flux:command.items>
                        <flux:command.item :href="route('profile.edit')">{{ __('ui.reference.example_name') }}</flux:command.item>
                        <flux:command.empty>{{ __('ui.empty.no_results') }}</flux:command.empty>
                    </flux:command.items>
                </flux:command>
                <flux:context data-pro-reference="context">
                    <flux:button :href="route('profile.edit')">{{ __('ui.reference.example_name') }}</flux:button>
                    <flux:menu><flux:menu.item :href="route('profile.edit')">{{ __('ui.reference.edit') }}</flux:menu.item></flux:menu>
                </flux:context>
                <flux:dropdown>
                    <flux:button icon="adjustments-horizontal">{{ __('ui.reference.controls') }}</flux:button>
                    <flux:popover data-pro-reference="popover">{{ __('ui.reference.independent_help') }}</flux:popover>
                </flux:dropdown>
            </div>
        </x-ui.card>

        <x-ui.card heading="ui.reference.pro.content">
            <div class="space-y-4">
                <flux:composer name="reference-pro-note" :label="__('ui.reference.independent_note')" data-pro-reference="composer">
                    <x-slot:input><textarea class="w-full resize-y bg-transparent p-2" rows="3" aria-label="{{ __('ui.reference.independent_note') }}" maxlength="500"></textarea></x-slot:input>
                </flux:composer>
                <flux:editor name="reference-pro-description" :label="__('ui.reference.pro.content')" data-pro-reference="editor" />
            </div>
        </x-ui.card>

        <x-ui.card heading="ui.reference.pro.activity">
            <div class="space-y-4">
                <flux:chart :value="$chart" :locale="$locale" data-pro-reference="chart" aria-hidden="true">
                    <flux:chart.svg class="h-40"><flux:chart.line field="value" class="text-accent" /><flux:chart.point field="value" /></flux:chart.svg>
                </flux:chart>
                <dl class="grid grid-cols-3 gap-2" aria-label="{{ __('ui.reference.pro.activity') }}">
                    @forelse ($chart as $point)
                        <div><dt class="text-sm text-text-muted">{{ $point['date'] }}</dt><dd>{{ $point['value'] }}</dd></div>
                    @empty
                        <div>{{ __('ui.empty.no_results') }}</div>
                    @endforelse
                </dl>
                <flux:timeline data-pro-reference="timeline">
                    <flux:timeline.item><flux:timeline.indicator /><flux:timeline.content>{{ __('statuses.kitchen_ticket_item.in_progress') }}</flux:timeline.content></flux:timeline.item>
                    <flux:timeline.item><flux:timeline.indicator /><flux:timeline.content>{{ __('statuses.kitchen_ticket_item.ready') }}</flux:timeline.content></flux:timeline.item>
                </flux:timeline>
            </div>
        </x-ui.card>

        <x-ui.card heading="ui.reference.pro.board">
            <flux:kanban class="block!" data-pro-reference="kanban">
                <flux:kanban.column class="w-full!">
                    <flux:kanban.column.header :heading="__('statuses.kitchen_ticket_item.in_progress')" />
                    <flux:kanban.column.cards><flux:kanban.card>{{ __('ui.reference.example_name') }}</flux:kanban.card></flux:kanban.column.cards>
                </flux:kanban.column>
            </flux:kanban>
        </x-ui.card>
    </div>
</div>
