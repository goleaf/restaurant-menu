@pushOnce('page-module-status', 'menu-status')
    <x-page-module-status module="menu" />
@endPushOnce

<section data-page-module="menu" wire:ignore.self x-ignore inert data-page="branch-menu" class="mx-auto flex w-full min-w-0 max-w-content flex-1 flex-col gap-6" x-data="menuWorkspace">
    <x-ui.page-header :title="__('navigation.menu')" :description="__('menu.workspace.description')" />

    <div class="grid min-w-0 content-start gap-5 lg:grid-cols-[11.5rem_minmax(0,1fr)] lg:gap-6">
        <nav class="menu-workspace-sections grid min-w-0 grid-cols-[repeat(auto-fit,minmax(min(100%,8rem),1fr))] content-start gap-1.5 rounded-card bg-surface-muted p-1.5 lg:sticky lg:top-6 lg:grid-cols-1 lg:self-start" aria-label="{{ __('menu.workspace.sections') }}">
            @forelse ($sections as $key => $entry)
                <a
                    href="{{ $entry['href'] }}"
                    wire:key="menu-navigation-{{ $key }}"
                    data-menu-section="{{ $key }}"
                    @if ($section === $key) aria-current="page" @endif
                    x-on:click="navigateSection($event, '{{ $key }}')"
                    class="flex min-h-touch min-w-0 items-center gap-2.5 rounded-control border border-transparent px-3 py-2.5 text-sm leading-snug font-medium text-text-muted wrap-anywhere hover:bg-control-hover hover:text-text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus aria-[current=page]:border-border-subtle aria-[current=page]:bg-surface aria-[current=page]:font-semibold aria-[current=page]:text-accent aria-disabled:cursor-wait aria-disabled:opacity-55 forced-colors:aria-[current=page]:border-[Highlight] forced-colors:aria-[current=page]:text-[Highlight] forced-colors:focus-visible:outline-[Highlight]"
                >
                    <flux:icon :name="$entry['icon']" variant="mini" class="size-5 shrink-0" />
                    <span>{{ __($entry['label']) }}</span>
                </a>
            @empty
            @endforelse
        </nav>

        <div class="grid min-w-0 content-start gap-5" data-menu-workspace-content x-bind:aria-busy="navigating">
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
            @elseif ($section === 'departments')
                <livewire:organizations.brands.branches.menu.kitchen-departments :organization-id="$organizationId" :brand-id="$brandId" :branch-id="$branchId" :key="'menu-section-departments'" />
            @elseif ($section === 'modifiers')
                <livewire:organizations.brands.branches.menu.modifiers :organization-id="$organizationId" :brand-id="$brandId" :branch-id="$branchId" :key="'menu-section-modifiers'" />
            @elseif ($section === 'transfer')
                <livewire:organizations.brands.branches.menu.catalog-transfer :organization-id="$organizationId" :brand-id="$brandId" :branch-id="$branchId" :key="'menu-section-transfer'" />
            @endif
        </div>
    </div>

    <flux:modal name="menu-workspace-unsaved" :closable="false" class="w-full max-w-md" x-on:close="pendingNavigation = null">
        <x-modal-close-button :autofocus="true" />
        <div class="grid gap-4">
            <div class="pe-10">
                <flux:heading size="lg">{{ __('menu.workspace.unsaved_title') }}</flux:heading>
                <flux:text class="mt-2">{{ __('menu.workspace.unsaved_description') }}</flux:text>
            </div>
            <div class="flex flex-wrap justify-end gap-2">
                <flux:button type="button" x-on:click="cancelNavigation">{{ __('menu.workspace.keep_editing') }}</flux:button>
                <flux:button type="button" variant="primary" color="red" x-on:click="discardAndNavigate" class="rm-action-danger">{{ __('menu.workspace.discard') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
