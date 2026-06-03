<section data-page="organization-brands" class="flex h-full w-full flex-1 flex-col gap-6">
    <header class="flex flex-col gap-3">
        <flux:button icon="arrow-left" :href="route('organizations.index')" wire:navigate>
            {{ __('Organizations') }}
        </flux:button>

        <div class="flex flex-col gap-1">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $organization->name }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('Brands') }}</h1>
        </div>
    </header>

    @if ($canManageBrands)
        <form wire:submit="create" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="grid gap-4 md:grid-cols-[1fr_auto] md:items-end">
                <flux:input wire:model="name" :label="__('Brand name')" type="text" required maxlength="120" autocomplete="organization-title" />

                <flux:button icon="plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="create">
                    {{ __('Create') }}
                </flux:button>
            </div>
        </form>
    @endif

    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <flux:heading size="lg">{{ __('Brands in this organization') }}</flux:heading>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @forelse ($this->brands as $brand)
                <div wire:key="brand-{{ $brand->id }}" class="grid gap-4 px-4 py-4 md:grid-cols-[1fr_auto] md:items-center">
                    @if ($editingBrandId === $brand->id)
                        <form wire:submit="update" class="grid gap-3 md:col-span-2 md:grid-cols-[1fr_auto_auto] md:items-end">
                            <flux:input wire:model="editingName" :label="__('Brand name')" type="text" required maxlength="120" />

                            <flux:button icon="check" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="update">
                                {{ __('Save') }}
                            </flux:button>

                            <flux:button icon="x-mark" type="button" wire:click="cancelEditing">
                                {{ __('Cancel') }}
                            </flux:button>
                        </form>
                    @else
                        <div class="min-w-0">
                            @php($brandLogoUrl = $brand->logoUrl())

                            <div class="flex gap-3">
                                <div class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-md border border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950">
                                    @if ($brandLogoUrl)
                                        <img src="{{ $brandLogoUrl }}" alt="{{ $brand->name }}" class="size-full object-contain">
                                    @else
                                        <span class="text-xs font-medium text-zinc-400">{{ __('Logo') }}</span>
                                    @endif
                                </div>

                                <div class="min-w-0">
                                    <h2 class="truncate text-base font-semibold text-zinc-950 dark:text-white">{{ $brand->name }}</h2>
                                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ __('Created') }} {{ $brand->created_at->format('d.m.Y') }}
                                    </p>
                                </div>
                            </div>

                            @if ($canManageBrands)
                                <form wire:submit="saveLogo({{ $brand->id }})" class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-start">
                                    <label for="brand-logo-{{ $brand->id }}" class="sr-only">{{ __('Brand logo') }}</label>
                                    <input id="brand-logo-{{ $brand->id }}" wire:model="brandLogos.{{ $brand->id }}" type="file" accept="image/png,image/jpeg,image/webp" class="block w-full max-w-xs rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-700 file:mr-3 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-1.5 file:text-sm file:font-medium dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-200 dark:file:bg-zinc-800">

                                    <div class="flex flex-wrap gap-2">
                                        <flux:button icon="arrow-up-tray" type="submit" wire:loading.attr="disabled" wire:target="brandLogos.{{ $brand->id }}, saveLogo({{ $brand->id }})">
                                            {{ __('Upload logo') }}
                                        </flux:button>

                                        @if ($brandLogoUrl)
                                            <flux:button icon="trash" type="button" variant="danger" wire:click="removeLogo({{ $brand->id }})" wire:loading.attr="disabled" wire:target="removeLogo({{ $brand->id }})">
                                                {{ __('Remove logo') }}
                                            </flux:button>
                                        @endif
                                    </div>

                                    @error('brandLogos.'.$brand->id)
                                        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </form>
                            @endif
                        </div>

                        @if ($canManageBrands)
                            <div class="flex flex-wrap gap-2 md:justify-end">
                                <flux:button icon="map-pin" type="button" :href="route('organizations.brands.branches.index', [$organization, $brand])" wire:navigate>
                                    {{ __('Branches') }}
                                </flux:button>

                                <flux:button icon="pencil" type="button" wire:click="startEditing({{ $brand->id }})">
                                    {{ __('Edit') }}
                                </flux:button>

                                <flux:button icon="trash" type="button" variant="danger" wire:click="confirmDelete({{ $brand->id }})">
                                    {{ __('Delete') }}
                                </flux:button>
                            </div>
                        @else
                            <div class="flex flex-wrap gap-2 md:justify-end">
                                <flux:button icon="map-pin" type="button" :href="route('organizations.brands.branches.index', [$organization, $brand])" wire:navigate>
                                    {{ __('Branches') }}
                                </flux:button>
                            </div>
                        @endif

                        @if ($deletingBrandId === $brand->id)
                            <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200 md:col-span-2">
                                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                    <span>{{ __('Delete this brand?') }}</span>

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
                </div>
            @empty
                <div class="px-4 py-8 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('No brands yet.') }}
                </div>
            @endforelse
        </div>
    </div>
</section>
