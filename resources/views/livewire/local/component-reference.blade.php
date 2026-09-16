<div class="content-safe space-y-6" data-component-reference>
    <x-ui.page-header title="ui.reference.title" description="ui.reference.description" icon="squares-2x2" />

    <x-ui.card heading="ui.reference.controls">
        <div class="flex flex-wrap gap-3">
            <flux:button variant="primary" class="min-h-touch" icon="check">{{ __('ui.actions.save') }}</flux:button>
            <flux:button variant="outline" class="min-h-touch">{{ __('ui.actions.cancel') }}</flux:button>
            <flux:button variant="primary" color="red" class="min-h-touch bg-danger! hover:bg-danger/90! dark:text-text-inverse!" icon="trash">{{ __('ui.actions.delete') }}</flux:button>
            <flux:button variant="ghost" class="h-auto min-h-touch min-w-0 max-w-full py-2" disabled><span class="whitespace-normal wrap-anywhere">{{ __('ui.reference.disabled') }}</span></flux:button>
            <flux:button type="submit" variant="filled" class="min-h-touch" loading disabled aria-busy="true">{{ __('ui.reference.loading') }}</flux:button>
            <flux:modal.trigger name="component-reference-edit">
                <flux:button variant="outline" icon="pencil-square" class="h-auto min-h-touch min-w-0 max-w-full py-2" data-reference-editor-trigger><span class="whitespace-normal wrap-anywhere">{{ __('ui.reference.edit') }}</span></flux:button>
            </flux:modal.trigger>
        </div>
    </x-ui.card>

    <div class="grid min-w-0 gap-6 lg:grid-cols-2">
        <x-ui.card heading="ui.reference.form">
            <div class="space-y-4">
                <flux:textarea wire:model="note" :label="__('ui.reference.independent_note')" :description="__('ui.reference.independent_help')" description:id="reference-note-help" aria-describedby="reference-note-help" maxlength="500" rows="3" />
                <flux:select :label="__('payments.forms.method')" name="reference-payment-method" class="min-h-touch">
                    <flux:select.option value="cash">{{ __('ui.payment_methods.cash') }}</flux:select.option>
                    <flux:select.option value="card_terminal">{{ __('ui.payment_methods.card_terminal') }}</flux:select.option>
                    <flux:select.option value="other">{{ __('ui.payment_methods.other') }}</flux:select.option>
                </flux:select>
                <flux:field>
                    <flux:label for="reference-invalid">{{ __('guest.table.your_name') }}</flux:label>
                    <flux:input id="reference-invalid" :placeholder="__('guest.table.enter_name')" name="reference-invalid" invalid aria-describedby="reference-invalid-error" />
                    <flux:error id="reference-invalid-error" :message="__('ui.reference.name_required')" class="text-danger!" />
                </flux:field>
            </div>
        </x-ui.card>
        <x-ui.card heading="ui.reference.states">
            <div class="space-y-4">
                <x-ui.state-panel kind="loading" title="ui.reference.loading" />
                <x-ui.state-panel kind="empty" title="ui.empty.no_notifications" />
                <x-ui.state-panel kind="offline" title="notifications.panel.offline_title" description="notifications.panel.offline_description" />
                <flux:callout color="green" icon="check-circle" class="callout-contrast" :heading="__('ui.reference.saved')" />
            </div>
        </x-ui.card>
    </div>

    <x-ui.card heading="ui.reference.list">
        <flux:table class="whitespace-normal!" :aria-label="__('ui.reference.list')">
            <flux:table.columns>
                <flux:table.column>{{ __('ui.reference.table') }}</flux:table.column>
                <flux:table.column>{{ __('ui.reference.status') }}</flux:table.column>
                <flux:table.column>{{ __('ui.reference.time') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                <flux:table.row>
                    <flux:table.cell>01</flux:table.cell>
                    <flux:table.cell><x-ui.status-badge status="ready" label="statuses.kitchen_ticket_item.ready" /></flux:table.cell>
                    <flux:table.cell>00:04</flux:table.cell>
                </flux:table.row>
                <flux:table.row>
                    <flux:table.cell>02</flux:table.cell>
                    <flux:table.cell><x-ui.status-badge status="in_progress" label="statuses.kitchen_ticket_item.in_progress" /></flux:table.cell>
                    <flux:table.cell>00:12</flux:table.cell>
                </flux:table.row>
            </flux:table.rows>
        </flux:table>
    </x-ui.card>

    <x-local.pro-reference :locale="$referenceLocale" :chart="$referenceChart" />

    <flux:modal name="component-reference-edit" :closable="false" class="content-safe w-full min-w-0! max-w-lg">
        <div class="space-y-5">
            <flux:heading level="2" size="lg" id="component-reference-edit-title" x-bind="dialogLabel">{{ __('ui.reference.edit') }}</flux:heading>
            <form wire:submit="saveExample" class="space-y-5" novalidate>
                <flux:input wire:model="name" :label="__('ui.reference.example_name')" error:id="reference-name-error" error:class="text-danger!" aria-describedby="reference-name-error" required maxlength="80" autocomplete="off" />
                <div class="flex flex-wrap justify-end gap-3">
                    <flux:modal.close><flux:button autofocus class="min-h-touch">{{ __('ui.actions.cancel') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary" class="min-h-touch" wire:offline.attr="disabled">{{ __('ui.actions.save') }}</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
