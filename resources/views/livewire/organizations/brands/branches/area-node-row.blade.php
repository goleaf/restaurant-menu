<div wire:key="area-node-{{ $node['id'] }}" class="grid gap-4 px-4 py-4 md:grid-cols-[1fr_auto] md:items-center">
    @if ($editingAreaNodeId === $node['id'])
        <form wire:submit="update" class="grid gap-3 md:col-span-2 md:grid-cols-2">
            <flux:input wire:model="editingName" :label="__('Название зоны')" type="text" required maxlength="160" />

            <flux:select wire:model="editingType" :label="__('Что это?')">
                @foreach ($this->areaTypeOptions as $value => $label)
                    <flux:select.option wire:key="editing-area-type-{{ $node['id'] }}-{{ $value }}" value="{{ $value }}">{{ __($label) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="editingIcon" :label="__('Иконка')">
                @foreach ($this->iconOptions as $value => $label)
                    <flux:select.option wire:key="editing-area-icon-{{ $node['id'] }}-{{ $value }}" value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="editingParentId" :label="__('Где находится?')">
                @foreach ($this->parentOptions($editingAreaNodeId) as $option)
                    <flux:select.option wire:key="editing-area-parent-{{ $node['id'] }}-{{ $option['value'] === '' ? 'top' : $option['value'] }}" value="{{ $option['value'] }}">
                        {{ $option['label'] }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="editingSortOrder" :label="__('Порядок в списке')" type="number" required min="0" max="9999" />

            <div class="flex items-end justify-between gap-4">
                <flux:switch wire:model="editingIsActive" :label="__('Использовать сейчас')" />

                <div class="flex flex-wrap gap-2">
                    <flux:button icon="check" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="update">
                        {{ __('Сохранить') }}
                    </flux:button>

                    <flux:button icon="x-mark" type="button" wire:click="cancelEditing">
                        {{ __('Отмена') }}
                    </flux:button>
                </div>
            </div>
        </form>
    @else
        <div class="min-w-0" style="padding-left: {{ min($node['depth'], 8) * 1.25 }}rem">
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.area-icon :type="$node['type']" :icon="$node['icon']" :label="__($node['type_label'])" :active="$node['is_active']" />

                <h2 class="truncate text-base font-semibold text-zinc-950 dark:text-white">{{ $node['name'] }}</h2>

                <x-ui.status-badge tone="muted">{{ __($node['type_label']) }}</x-ui.status-badge>

                @if ($node['is_active'])
                    <x-ui.status-badge tone="success" dot>{{ __('Работает') }}</x-ui.status-badge>
                @else
                    <x-ui.status-badge tone="muted" dot>{{ __('Выключена') }}</x-ui.status-badge>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap gap-2 md:justify-end">
            @if ($node['is_active'])
                <flux:button icon="eye-slash" type="button" wire:click="disable({{ $node['id'] }})">
                    {{ __('Выключить') }}
                </flux:button>
            @else
                <flux:button icon="eye" type="button" wire:click="enable({{ $node['id'] }})">
                    {{ __('Включить') }}
                </flux:button>
            @endif

            <flux:button icon="pencil" type="button" wire:click="startEditing({{ $node['id'] }})">
                {{ __('Изменить') }}
            </flux:button>

            <flux:button icon="trash" type="button" variant="danger" wire:click="confirmDelete({{ $node['id'] }})">
                {{ __('ui.actions.delete') }}
            </flux:button>
        </div>

        @if ($deletingAreaNodeId === $node['id'])
            <x-ui.alert tone="danger" class="md:col-span-2">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <span>{{ __('ui.confirmations.delete.title') }}</span>

                    <div class="flex flex-wrap gap-2">
                        <flux:button icon="trash" variant="danger" type="button" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">
                            {{ __('ui.actions.delete') }}
                        </flux:button>

                        <flux:button icon="x-mark" type="button" wire:click="cancelDelete">
                            {{ __('ui.actions.cancel') }}
                        </flux:button>
                    </div>
                </div>
            </x-ui.alert>
        @endif
    @endif

    @if ($node['children'] !== [])
        <div class="border-t border-zinc-100 dark:border-zinc-800 md:col-span-2">
            @foreach ($node['children'] as $child)
                @include('livewire.organizations.brands.branches.area-node-row', ['node' => $child])
            @endforeach
        </div>
    @endif
</div>
