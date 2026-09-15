<section data-page="branch-menu" class="menu-workspace" x-data="menuWorkspace">
    <x-ui.page-header :title="__('navigation.menu')" :breadcrumbs="$breadcrumbs" :description="__('menu.workspace.description')">
        <x-slot:actions>
            <flux:button icon="arrow-left" :href="$branchesUrl" wire:navigate>
                {{ __('navigation.branches') }}
            </flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="menu-workspace-layout">
        <nav class="menu-workspace-navigation" aria-label="{{ __('menu.workspace.sections') }}">
            @forelse ($sections as $key => $entry)
                <a
                    href="{{ $entry['href'] }}"
                    wire:key="menu-navigation-{{ $key }}"
                    data-menu-section="{{ $key }}"
                    @if ($section === $key) aria-current="page" @endif
                    x-on:click="navigateSection($event, '{{ $key }}')"
                    class="menu-workspace-link"
                >
                    <flux:icon :name="$entry['icon']" variant="mini" class="size-5 shrink-0" />
                    <span>{{ __($entry['label']) }}</span>
                </a>
            @empty
            @endforelse
        </nav>

        <div class="menu-workspace-content" data-menu-workspace-content x-bind:aria-busy="navigating">
            <p class="text-sm text-text-muted" role="status" wire:loading wire:target="selectSection,section">{{ __('menu.workspace.loading') }}</p>
            <div wire:offline class="rounded-control border border-warning-border bg-warning-surface p-3 text-sm text-warning" role="status">
                {{ __('menu.workspace.offline') }}
            </div>
            @error('section')
                <p class="rounded-control bg-danger-surface p-3 text-sm text-danger" role="alert" tabindex="-1">{{ $message }}</p>
            @enderror

            @if ($section === 'catalog')
                <livewire:organizations.brands.branches.menu.catalog :organization-id="$organizationId" :brand-id="$brandId" :branch-id="$branchId" :key="'menu-section-catalog'" />
            @elseif ($section === 'availability')
                <livewire:organizations.brands.branches.menu.availability :organization-id="$organizationId" :brand-id="$brandId" :branch-id="$branchId" :key="'menu-section-availability'" />
            @elseif ($section === 'variants')
                <livewire:organizations.brands.branches.menu.variants :organization-id="$organizationId" :brand-id="$brandId" :branch-id="$branchId" :key="'menu-section-variants'" />
            @elseif ($section === 'departments')
                <livewire:organizations.brands.branches.menu.kitchen-departments :organization-id="$organizationId" :brand-id="$brandId" :branch-id="$branchId" :key="'menu-section-departments'" />
            @elseif ($section === 'modifiers')
                <livewire:organizations.brands.branches.menu.modifiers :organization-id="$organizationId" :brand-id="$brandId" :branch-id="$branchId" :key="'menu-section-modifiers'" />
            @elseif ($section === 'transfer')
                <livewire:organizations.brands.branches.menu.catalog-transfer :organization-id="$organizationId" :brand-id="$brandId" :branch-id="$branchId" :key="'menu-section-transfer'" />
            @endif
        </div>
    </div>

    <flux:modal name="menu-workspace-unsaved" class="w-full max-w-md" x-on:close="pendingNavigation = null">
        <div class="grid gap-4">
            <div>
                <flux:heading size="lg">{{ __('menu.workspace.unsaved_title') }}</flux:heading>
                <flux:text class="mt-2">{{ __('menu.workspace.unsaved_description') }}</flux:text>
            </div>
            <div class="flex flex-wrap justify-end gap-2">
                <flux:button type="button" x-on:click="cancelNavigation">{{ __('menu.workspace.keep_editing') }}</flux:button>
                <flux:button type="button" variant="danger" x-on:click="discardAndNavigate">{{ __('menu.workspace.discard') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
