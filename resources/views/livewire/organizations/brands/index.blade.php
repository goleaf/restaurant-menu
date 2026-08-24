<section data-page="organization-brands" class="flex h-full w-full flex-1 flex-col gap-6">
    <header class="flex flex-col gap-3">
        <flux:button icon="arrow-left" :href="route('organizations.index')" wire:navigate>
            {{ __('navigation.organizations') }}
        </flux:button>

        <div class="flex flex-col gap-1">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $organizationName }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('navigation.brands') }}</h1>
        </div>
    </header>

    @if ($canManageBrands && $lifecycle === 'active')
        <form wire:submit="create" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="grid gap-4 md:grid-cols-[1fr_auto] md:items-end">
                <flux:input wire:model="name" :label="__('ui.organizations.brands.index.brand_name')" type="text" required maxlength="120" autocomplete="organization-title" />

                <flux:button icon="plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="create">
                    {{ __('ui.organizations.brands.branches.menu.index.create') }}
                </flux:button>
            </div>
        </form>
    @endif

    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex flex-col gap-3 border-b border-zinc-200 px-4 py-3 sm:flex-row sm:items-end sm:justify-between dark:border-zinc-800">
            <flux:heading size="lg">{{ __('ui.organizations.brands.index.brands_in_this_organization') }}</flux:heading>
            <div class="grid gap-3 sm:grid-cols-3">
                <flux:input wire:model.live.debounce.300ms="search" :label="__('layout.search')" type="search" autocomplete="off" />
                <flux:select wire:model.live="lifecycle" :label="__('structure.filters.lifecycle')">
                    <flux:select.option value="active">{{ __('structure.filters.active') }}</flux:select.option>
                    <flux:select.option value="archived">{{ __('structure.filters.archived') }}</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="sort" :label="__('structure.filters.sort')">
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

            @forelse ($brandRows as $brand)
                <div wire:key="brand-{{ $brand['id'] }}" class="grid gap-4 px-4 py-4 md:grid-cols-[1fr_auto] md:items-center">
                    @if ($editingBrandId === $brand['id'])
                        <form wire:submit="update" class="grid gap-3 md:col-span-2 md:grid-cols-[1fr_auto_auto] md:items-end">
                            <flux:input wire:model="editingName" :label="__('ui.organizations.brands.index.brand_name')" type="text" required maxlength="120" />

                            <flux:button icon="check" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="update">
                                {{ __('ui.actions.save') }}
                            </flux:button>

                            <flux:button icon="x-mark" type="button" wire:click="cancelEditing">
                                {{ __('ui.actions.cancel') }}
                            </flux:button>
                        </form>
                    @else
                        <div class="min-w-0">
                            <div class="flex gap-3">
                                <div class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-md border border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950">
                                    @if ($brand['logo_url'])
                                        <img src="{{ $brand['logo_url'] }}" alt="{{ $brand['name'] }}" width="48" height="48" loading="lazy" decoding="async" class="size-full object-contain">
                                    @else
                                        <span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">{{ __('uploads.labels.logo') }}</span>
                                    @endif
                                </div>

                                <div class="min-w-0">
                                    <h2 class="truncate text-base font-semibold text-zinc-950 dark:text-white">{{ $brand['name'] }}</h2>
                                    @if ($brand['is_archived'])
                                        <flux:badge color="zinc">{{ __('structure.badges.archived') }}</flux:badge>
                                    @endif
                                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ __('qr.labels.created') }} {{ $brand['created_at'] }}
                                    </p>
                                </div>
                            </div>

                            @if ($canManageBrands && ! $brand['is_archived'])
                                <form wire:submit="saveLogo({{ $brand['id'] }})" class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-start">
                                    <label for="brand-logo-{{ $brand['id'] }}" class="sr-only">{{ __('uploads.labels.logo') }}</label>
                                    <x-ui.image-upload-input id="brand-logo-{{ $brand['id'] }}" wire:model="brandLogos.{{ $brand['id'] }}" :aria-label="__('uploads.actions.choose_file').' '.__('uploads.labels.logo')" class="max-w-xs" />

                                    <div class="flex flex-wrap gap-2">
                                        <flux:button icon="arrow-up-tray" type="submit" wire:loading.attr="disabled" wire:target="brandLogos.{{ $brand['id'] }}, saveLogo({{ $brand['id'] }})">
                                            {{ $brand['logo_url'] ? __('uploads.actions.replace') : __('uploads.actions.upload') }}
                                        </flux:button>

                                        @if ($brand['logo_url'])
                                            <flux:button icon="trash" type="button" variant="danger" wire:click="removeLogo({{ $brand['id'] }})" wire:loading.attr="disabled" wire:target="removeLogo({{ $brand['id'] }})">
                                                {{ __('uploads.actions.remove') }}
                                            </flux:button>
                                        @endif
                                    </div>

                                    @error('brandLogos.'.$brand['id'])
                                        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </form>
                            @endif
                        </div>

                        @if ($brand['is_archived'])
                            <div class="flex flex-wrap gap-2 md:justify-end">
                                @if ($canManageBrands)
                                    <flux:button icon="arrow-path" variant="primary" type="button" wire:click="restore({{ $brand['id'] }})" wire:loading.attr="disabled" wire:target="restore({{ $brand['id'] }})">
                                        {{ __('structure.actions.restore') }}
                                    </flux:button>
                                @endif
                            </div>
                        @elseif ($canManageBrands)
                            <div class="flex flex-wrap gap-2 md:justify-end">
                                <flux:button icon="map-pin" type="button" :href="$brand['branches_url']" wire:navigate>
                                    {{ __('navigation.branches') }}
                                </flux:button>

                                <flux:button icon="pencil" type="button" wire:click="startEditing({{ $brand['id'] }})">
                                    {{ __('guest.cart.edit_item') }}
                                </flux:button>

                                <flux:button icon="trash" type="button" variant="danger" wire:click="confirmDelete({{ $brand['id'] }})">
                                    {{ __('structure.actions.archive') }}
                                </flux:button>
                            </div>
                        @else
                            <div class="flex flex-wrap gap-2 md:justify-end">
                                <flux:button icon="map-pin" type="button" :href="$brand['branches_url']" wire:navigate>
                                    {{ __('navigation.branches') }}
                                </flux:button>
                            </div>
                        @endif

                        @if ($deletingBrandId === $brand['id'])
                            <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200 md:col-span-2">
                                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                    <span>{{ __('structure.confirmations.archive.title') }}</span>

                                    <div class="flex flex-wrap gap-2">
                                        <flux:button icon="trash" variant="danger" type="button" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">
                                            {{ __('structure.actions.archive') }}
                                        </flux:button>

                                        <flux:button icon="x-mark" type="button" wire:click="cancelDelete">
                                            {{ __('ui.actions.cancel') }}
                                        </flux:button>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            @empty
                <div class="px-4 py-8 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $search !== '' ? __('ui.empty.no_results') : ($lifecycle === 'archived' ? __('structure.empty.archived') : __('ui.empty.no_brands')) }}
                </div>
            @endforelse
        </div>

        @if ($brandsPaginator->hasPages())
            <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">
                {{ $brandsPaginator->links() }}
            </div>
        @endif
    </div>
</section>
