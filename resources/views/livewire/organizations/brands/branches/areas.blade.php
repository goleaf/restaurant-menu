<section data-page="branch-areas" class="flex h-full w-full flex-1 flex-col gap-6">
    <header class="flex flex-col gap-3">
        <flux:button icon="arrow-left" :href="route('organizations.brands.branches.index', [$organization, $brand])" wire:navigate>
            {{ __('Филиалы') }}
            <span class="sr-only">{{ __('Branches') }}</span>
        </flux:button>

        <div class="flex flex-col gap-1">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $organization->name }} / {{ $brand->name }} / {{ $branch->name }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">
                {{ __('Зоны ресторана') }}
                <span class="sr-only">{{ __('Areas') }}</span>
            </h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Создайте понятную карту: этажи, залы, терраса, VIP-зал или своя зона.') }}</p>
        </div>
    </header>

    <x-ui.card
        :heading="__('Шаг 2: добавьте зоны')"
        :description="__('Нажмите готовый вариант, впишите название и сохраните.')"
    >

        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->quickCreateOptions as $option)
                <flux:button
                    wire:key="area-preset-{{ $option['type'] }}"
                    :icon="$option['icon']"
                    type="button"
                    wire:click="prepareCreate('{{ $option['type'] }}')"
                    class="min-h-14 justify-start"
                >
                    {{ $option['label'] }}
                </flux:button>
            @endforeach
        </div>

        <form wire:submit="create" class="mt-4 grid gap-4 md:grid-cols-2">
            <flux:input wire:model="name" :label="__('Название зоны')" type="text" required maxlength="160" />

            <flux:select wire:model="type" :label="__('Что это?')">
                @foreach ($this->areaTypeOptions as $value => $label)
                    <flux:select.option wire:key="area-type-{{ $value }}" value="{{ $value }}">{{ __($label) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="icon" :label="__('Иконка')">
                @foreach ($this->iconOptions as $value => $label)
                    <flux:select.option wire:key="area-icon-{{ $value }}" value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="parentId" :label="__('Где находится?')">
                @foreach ($this->parentOptions() as $option)
                    <flux:select.option wire:key="area-parent-create-{{ $option['value'] === '' ? 'top' : $option['value'] }}" value="{{ $option['value'] }}">
                        {{ $option['label'] }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="sortOrder" :label="__('Порядок в списке')" type="number" required min="0" max="9999" />

            <div class="flex items-end justify-between gap-4">
                <flux:switch wire:model="isActive" :label="__('Использовать сейчас')" />

                <flux:button icon="plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="create">
                    {{ __('Добавить зону') }}
                </flux:button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card padding="none" class="overflow-hidden">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <flux:heading size="lg">{{ __('Список зон') }}</flux:heading>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @forelse ($this->treeNodes as $node)
                @include('livewire.organizations.brands.branches.area-node-row', ['node' => $node])
            @empty
                <div class="p-4">
                    <x-ui.empty-state
                        icon="squares-2x2"
                        :heading="__('ui.empty.no_areas')"
                        :description="__('areas.empty.no_areas_description')"
                    />
                    <span class="sr-only">{{ __('ui.empty.no_areas') }}</span>
                </div>
            @endforelse
        </div>
    </x-ui.card>
</section>
