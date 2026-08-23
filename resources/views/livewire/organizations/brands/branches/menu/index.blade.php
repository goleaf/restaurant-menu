<section data-page="branch-menu" class="flex h-full w-full flex-1 flex-col gap-6">
    <header class="flex flex-col gap-3">
        <flux:button icon="arrow-left" :href="$branchesUrl" wire:navigate>
            {{ __('navigation.branches') }}
        </flux:button>

        <div class="flex flex-col gap-1">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $contextLabel }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('menu.guest.title') }}</h1>
        </div>
    </header>

    @if ($canChangeAvailability)
        <livewire:organizations.brands.branches.menu.availability
            :organization-id="$organizationId"
            :brand-id="$brandId"
            :branch-id="$branchId"
        />
    @endif

    @if ($canManageMenu)
        <livewire:organizations.brands.branches.menu.catalog
            :organization-id="$organizationId"
            :brand-id="$brandId"
            :branch-id="$branchId"
        />
        <livewire:organizations.brands.branches.menu.variants
            :organization-id="$organizationId"
            :brand-id="$brandId"
            :branch-id="$branchId"
        />
        <livewire:organizations.brands.branches.menu.kitchen-departments
            :organization-id="$organizationId"
            :brand-id="$brandId"
            :branch-id="$branchId"
        />
        <livewire:organizations.brands.branches.menu.modifiers
            :organization-id="$organizationId"
            :brand-id="$brandId"
            :branch-id="$branchId"
        />
    @endif
</section>
