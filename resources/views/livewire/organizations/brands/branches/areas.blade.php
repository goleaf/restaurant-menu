<section data-page="branch-areas" class="flex h-full w-full flex-1 flex-col gap-6">
    <header class="flex flex-col gap-3">
        <flux:button icon="arrow-left" :href="route('organizations.brands.branches.index', [$organization, $brand])" wire:navigate>
            {{ __('ui.organizations.brands.branches.areas.filialy') }}
            <span class="sr-only">{{ __('navigation.branches') }}</span>
        </flux:button>

        <div class="flex flex-col gap-1">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $contextLabel }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">
                {{ __('ui.organizations.brands.branches.areas.zony_restorana') }}
                <span class="sr-only">{{ __('ui.organizations.brands.branches.areas.areas') }}</span>
            </h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.organizations.brands.branches.areas.sozdaite_poniatnuiu_kartu_etazi_zaly') }}</p>
        </div>
    </header>

    <x-ui.card
        :heading="__('ui.organizations.brands.branches.areas.sag_2_dobavte_zony')"
        :description="__('ui.organizations.brands.branches.areas.nazmite_gotovyi_variant_vpisite_nazv')"
    >

        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($quickCreateOptions as $option)
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
            <flux:input wire:model="name" :label="__('ui.onboarding.restaurant_setup.nazvanie_zony')" type="text" required maxlength="160" />

            <flux:select wire:model="type" :label="__('ui.onboarding.restaurant_setup.cto_eto')">
                @foreach ($areaTypeOptions as $value => $label)
                    <flux:select.option wire:key="area-type-{{ $value }}" value="{{ $value }}">{{ __($label) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="icon" :label="__('ui.onboarding.restaurant_setup.ikonka')">
                @foreach ($iconOptions as $value => $label)
                    <flux:select.option wire:key="area-icon-{{ $value }}" value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="parentId" :label="__('ui.organizations.brands.branches.area_node_row.gde_naxoditsia')">
                @foreach ($parentOptions as $option)
                    <flux:select.option wire:key="area-parent-create-{{ $option['value'] === '' ? 'top' : $option['value'] }}" value="{{ $option['value'] }}">
                        {{ $option['label'] }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="sortOrder" :label="__('ui.organizations.brands.branches.area_node_row.poriadok_v_spiske')" type="number" required min="0" max="9999" />

            <div class="flex items-end justify-between gap-4">
                <flux:switch wire:model="isActive" :label="__('ui.organizations.brands.branches.area_node_row.ispolzovat_seicas')" />

                <flux:button icon="plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="create">
                    {{ __('ui.organizations.brands.branches.areas.dobavit_zonu') }}
                </flux:button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card padding="none" class="overflow-hidden">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <flux:heading size="lg">{{ __('ui.organizations.brands.branches.areas.spisok_zon') }}</flux:heading>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @forelse ($treeNodes as $node)
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
