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

    @if ($lifecycle === 'active')
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
    @endif

    <x-ui.card padding="none" class="overflow-hidden">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <div class="grid gap-3 lg:grid-cols-5">
                <flux:heading size="lg">{{ __('ui.organizations.brands.branches.areas.spisok_zon') }}</flux:heading>
                <flux:input wire:model.live.debounce.300ms="areaSearch" :label="__('layout.search')" type="search" autocomplete="off" />
                <flux:select wire:model.live="filterType" :label="__('ui.organizations.brands.branches.service_points.index.tip')">
                    <flux:select.option value="all">{{ __('ui.organizations.brands.branches.service_points.index.all_types') }}</flux:select.option>
                    @foreach ($areaTypeOptions as $value => $label)
                        <flux:select.option wire:key="area-filter-type-{{ $value }}" value="{{ $value }}">{{ __($label) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="filterActive" :label="__('ui.organizations.brands.branches.service_points.index.aktivnost')">
                    <flux:select.option value="all">{{ __('ui.livewire.organizations.brands.branches.servicepoints.index.all_places') }}</flux:select.option>
                    <flux:select.option value="active">{{ __('ui.livewire.organizations.brands.branches.servicepoints.index.active_only') }}</flux:select.option>
                    <flux:select.option value="inactive">{{ __('ui.livewire.organizations.brands.branches.servicepoints.index.inactive_only') }}</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="lifecycle" :label="__('structure.filters.lifecycle')">
                    <flux:select.option value="active">{{ __('structure.filters.active') }}</flux:select.option>
                    <flux:select.option value="archived">{{ __('structure.filters.archived') }}</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="sort" :label="__('structure.filters.sort')">
                    <flux:select.option value="position">{{ __('structure.sort.position') }}</flux:select.option>
                    <flux:select.option value="name_asc">{{ __('structure.sort.name_asc') }}</flux:select.option>
                    <flux:select.option value="name_desc">{{ __('structure.sort.name_desc') }}</flux:select.option>
                    <flux:select.option value="newest">{{ __('structure.sort.newest') }}</flux:select.option>
                    <flux:select.option value="oldest">{{ __('structure.sort.oldest') }}</flux:select.option>
                </flux:select>
            </div>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @error('structureDeletion')
                <div role="alert" class="border-b border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300">{{ $message }}</div>
            @enderror

            @forelse ($treeNodes as $node)
                @include('livewire.organizations.brands.branches.area-node-row', ['node' => $node])
            @empty
                <div class="p-4">
                    <x-ui.empty-state
                        icon="squares-2x2"
                        :heading="__('ui.empty.no_areas')"
                        :description="$lifecycle === 'archived' ? __('structure.empty.archived') : __('areas.empty.no_areas_description')"
                    />
                    <span class="sr-only">{{ __('ui.empty.no_areas') }}</span>
                </div>
            @endforelse
        </div>

        @if ($areaNodesPaginator->hasPages())
            <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">
                {{ $areaNodesPaginator->links() }}
            </div>
        @endif
    </x-ui.card>
</section>
