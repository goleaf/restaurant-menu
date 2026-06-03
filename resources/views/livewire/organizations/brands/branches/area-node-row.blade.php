<div wire:key="area-node-{{ $node['id'] }}" class="grid gap-4 px-4 py-4 md:grid-cols-[1fr_auto] md:items-center">
    @if ($editingAreaNodeId === $node['id'])
        <form wire:submit="update" class="grid gap-3 md:col-span-2 md:grid-cols-2">
            <flux:input wire:model="editingName" :label="__('Area name')" type="text" required maxlength="160" />

            <flux:select wire:model="editingType" :label="__('Area type')">
                @foreach ($this->areaTypeOptions as $value => $label)
                    <flux:select.option wire:key="editing-area-type-{{ $node['id'] }}-{{ $value }}" value="{{ $value }}">{{ __($label) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="editingIcon" :label="__('Icon')">
                @foreach ($this->iconOptions as $value => $label)
                    <flux:select.option wire:key="editing-area-icon-{{ $node['id'] }}-{{ $value }}" value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="editingParentId" :label="__('Place inside')">
                @foreach ($this->parentOptions($editingAreaNodeId) as $option)
                    <flux:select.option wire:key="editing-area-parent-{{ $node['id'] }}-{{ $option['value'] === '' ? 'top' : $option['value'] }}" value="{{ $option['value'] }}">
                        {{ $option['label'] }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="editingSortOrder" :label="__('Sort order')" type="number" required min="0" max="9999" />

            <div class="flex items-end justify-between gap-4">
                <flux:switch wire:model="editingIsActive" :label="__('Active')" />

                <div class="flex flex-wrap gap-2">
                    <flux:button icon="check" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="update">
                        {{ __('Save') }}
                    </flux:button>

                    <flux:button icon="x-mark" type="button" wire:click="cancelEditing">
                        {{ __('Cancel') }}
                    </flux:button>
                </div>
            </div>
        </form>
    @else
        <div class="min-w-0" style="padding-left: {{ min($node['depth'], 8) * 1.25 }}rem">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="truncate text-base font-semibold text-zinc-950 dark:text-white">{{ $node['name'] }}</h2>

                <flux:badge :icon="$node['icon']">{{ __($node['type_label']) }}</flux:badge>

                @if ($node['is_active'])
                    <flux:badge color="green">{{ __('Active') }}</flux:badge>
                @else
                    <flux:badge color="zinc">{{ __('Inactive') }}</flux:badge>
                @endif
            </div>

            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Sort order') }}: {{ $node['sort_order'] }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2 md:justify-end">
            @if ($node['is_active'])
                <flux:button icon="eye-slash" type="button" wire:click="disable({{ $node['id'] }})">
                    {{ __('Disable') }}
                </flux:button>
            @else
                <flux:button icon="eye" type="button" wire:click="enable({{ $node['id'] }})">
                    {{ __('Enable') }}
                </flux:button>
            @endif

            <flux:button icon="pencil" type="button" wire:click="startEditing({{ $node['id'] }})">
                {{ __('Edit') }}
            </flux:button>

            <flux:button icon="trash" type="button" variant="danger" wire:click="confirmDelete({{ $node['id'] }})">
                {{ __('Delete') }}
            </flux:button>
        </div>

        @if ($deletingAreaNodeId === $node['id'])
            <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200 md:col-span-2">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <span>{{ __('Delete this area?') }}</span>

                    <div class="flex flex-wrap gap-2">
                        <flux:button icon="trash" variant="danger" type="button" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">
                            {{ __('Delete') }}
                        </flux:button>

                        <flux:button icon="x-mark" type="button" wire:click="cancelDelete">
                            {{ __('Cancel') }}
                        </flux:button>
                    </div>
                </div>
            </div>
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
