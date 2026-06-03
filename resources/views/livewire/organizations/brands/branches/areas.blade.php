<section data-page="branch-areas" class="flex h-full w-full flex-1 flex-col gap-6">
    <header class="flex flex-col gap-3">
        <flux:button icon="arrow-left" :href="route('organizations.brands.branches.index', [$organization, $brand])" wire:navigate>
            {{ __('Branches') }}
        </flux:button>

        <div class="flex flex-col gap-1">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $organization->name }} / {{ $brand->name }} / {{ $branch->name }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('Areas') }}</h1>
        </div>
    </header>

    <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex flex-wrap gap-2">
            @foreach ($this->quickCreateOptions as $option)
                <flux:button
                    wire:key="area-preset-{{ $option['type'] }}"
                    :icon="$option['icon']"
                    type="button"
                    wire:click="prepareCreate('{{ $option['type'] }}')"
                >
                    {{ $option['label'] }}
                </flux:button>
            @endforeach
        </div>

        <form wire:submit="create" class="mt-4 grid gap-4 md:grid-cols-2">
            <flux:input wire:model="name" :label="__('Area name')" type="text" required maxlength="160" />

            <flux:select wire:model="type" :label="__('Area type')">
                @foreach ($this->areaTypeOptions as $value => $label)
                    <flux:select.option wire:key="area-type-{{ $value }}" value="{{ $value }}">{{ __($label) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="icon" :label="__('Icon')">
                @foreach ($this->iconOptions as $value => $label)
                    <flux:select.option wire:key="area-icon-{{ $value }}" value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="parentId" :label="__('Place inside')">
                @foreach ($this->parentOptions() as $option)
                    <flux:select.option wire:key="area-parent-create-{{ $option['value'] === '' ? 'top' : $option['value'] }}" value="{{ $option['value'] }}">
                        {{ $option['label'] }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="sortOrder" :label="__('Sort order')" type="number" required min="0" max="9999" />

            <div class="flex items-end justify-between gap-4">
                <flux:switch wire:model="isActive" :label="__('Active')" />

                <flux:button icon="plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="create">
                    {{ __('Create') }}
                </flux:button>
            </div>
        </form>
    </div>

    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <flux:heading size="lg">{{ __('Area tree') }}</flux:heading>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @forelse ($this->treeNodes as $node)
                @include('livewire.organizations.brands.branches.area-node-row', ['node' => $node])
            @empty
                <div class="px-4 py-8 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('No areas yet.') }}
                </div>
            @endforelse
        </div>
    </div>
</section>
