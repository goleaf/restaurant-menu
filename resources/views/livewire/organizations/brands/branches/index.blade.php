<section data-page="brand-branches" class="flex h-full w-full flex-1 flex-col gap-6">
    <header class="flex flex-col gap-3">
        <flux:button icon="arrow-left" :href="route('organizations.brands.index', $organization)" wire:navigate>
            {{ __('Brands') }}
        </flux:button>

        <div class="flex flex-col gap-1">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $organization->name }} / {{ $brand->name }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('Branches') }}</h1>
        </div>
    </header>

    @if ($canManageBranches)
        <form wire:submit="create" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="name" :label="__('Branch name')" type="text" required maxlength="160" />
                <flux:input wire:model="address" :label="__('Address')" type="text" required maxlength="255" />
                <flux:input wire:model="city" :label="__('City')" type="text" required maxlength="120" />
                <flux:input wire:model="country" :label="__('Country')" type="text" required maxlength="120" />
                <flux:input wire:model="timezone" :label="__('Timezone')" type="text" required maxlength="64" />
                <flux:input wire:model="currency" :label="__('Currency')" type="text" required maxlength="3" />

                <div class="flex items-end justify-between gap-4 md:col-span-2">
                    <flux:switch wire:model="isActive" :label="__('Active')" />

                    <flux:button icon="plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="create">
                        {{ __('Create') }}
                    </flux:button>
                </div>
            </div>
        </form>
    @endif

    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <flux:heading size="lg">{{ __('Branches in this brand') }}</flux:heading>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @forelse ($this->branches as $branch)
                <div wire:key="branch-{{ $branch->id }}" class="grid gap-4 px-4 py-4 md:grid-cols-[1fr_auto] md:items-center">
                    @if ($editingBranchId === $branch->id)
                        <form wire:submit="update" class="grid gap-3 md:col-span-2 md:grid-cols-2">
                            <flux:input wire:model="editingName" :label="__('Branch name')" type="text" required maxlength="160" />
                            <flux:input wire:model="editingAddress" :label="__('Address')" type="text" required maxlength="255" />
                            <flux:input wire:model="editingCity" :label="__('City')" type="text" required maxlength="120" />
                            <flux:input wire:model="editingCountry" :label="__('Country')" type="text" required maxlength="120" />
                            <flux:input wire:model="editingTimezone" :label="__('Timezone')" type="text" required maxlength="64" />
                            <flux:input wire:model="editingCurrency" :label="__('Currency')" type="text" required maxlength="3" />

                            <div class="flex items-end justify-between gap-4 md:col-span-2">
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
                        <div class="min-w-0">
                            @php($branchLogoUrl = $branch->logoUrl())

                            <div class="flex gap-3">
                                <div class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-md border border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950">
                                    @if ($branchLogoUrl)
                                        <img src="{{ $branchLogoUrl }}" alt="{{ $branch->name }}" class="size-full object-contain">
                                    @else
                                        <span class="text-xs font-medium text-zinc-400">{{ __('Logo') }}</span>
                                    @endif
                                </div>

                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="truncate text-base font-semibold text-zinc-950 dark:text-white">{{ $branch->name }}</h2>

                                        @if ($branch->is_active)
                                            <flux:badge color="green">{{ __('Active') }}</flux:badge>
                                        @else
                                            <flux:badge color="zinc">{{ __('Inactive') }}</flux:badge>
                                        @endif
                                    </div>

                                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ $branch->address }}, {{ $branch->city }}, {{ $branch->country }}
                                    </p>

                                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ $branch->timezone }} / {{ $branch->currency }}
                                    </p>
                                </div>
                            </div>

                            @if ($canManageBranches)
                                <form wire:submit="saveLogo({{ $branch->id }})" class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-start">
                                    <label for="branch-logo-{{ $branch->id }}" class="sr-only">{{ __('Branch logo') }}</label>
                                    <input id="branch-logo-{{ $branch->id }}" wire:model="branchLogos.{{ $branch->id }}" type="file" accept="image/png,image/jpeg,image/webp" class="block w-full max-w-xs rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-700 file:mr-3 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-1.5 file:text-sm file:font-medium dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-200 dark:file:bg-zinc-800">

                                    <div class="flex flex-wrap gap-2">
                                        <flux:button icon="arrow-up-tray" type="submit" wire:loading.attr="disabled" wire:target="branchLogos.{{ $branch->id }}, saveLogo({{ $branch->id }})">
                                            {{ __('Upload logo') }}
                                        </flux:button>

                                        @if ($branchLogoUrl)
                                            <flux:button icon="trash" type="button" variant="danger" wire:click="removeLogo({{ $branch->id }})" wire:loading.attr="disabled" wire:target="removeLogo({{ $branch->id }})">
                                                {{ __('Remove logo') }}
                                            </flux:button>
                                        @endif
                                    </div>

                                    @error('branchLogos.'.$branch->id)
                                        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </form>
                            @endif
                        </div>

                        @if ($canManageBranches || $canManageZones || $canManageMenu || $canChangeServicePointStatus || $canOpenTable || $canGenerateQr || $canManageStaff)
                            <div class="flex flex-wrap gap-2 md:justify-end">
                                @if ($canManageZones)
                                    <flux:button icon="rectangle-group" type="button" :href="route('organizations.brands.branches.areas.index', [$organization, $brand, $branch])" wire:navigate>
                                        {{ __('Areas') }}
                                    </flux:button>
                                @endif

                                @if ($canManageMenu)
                                    <flux:button icon="book-open" type="button" :href="route('organizations.brands.branches.menu.index', [$organization, $brand, $branch])" wire:navigate>
                                        {{ __('Menu') }}
                                    </flux:button>
                                @endif

                                @if ($canChangeServicePointStatus || $canOpenTable || $canGenerateQr)
                                    <flux:button icon="squares-2x2" type="button" :href="route('organizations.brands.branches.service-points.index', [$organization, $brand, $branch])" wire:navigate>
                                        {{ __('Service points') }}
                                    </flux:button>
                                @endif

                                @if ($canGenerateQr)
                                    <flux:button icon="printer" type="button" :href="route('organizations.brands.branches.qr.print', [$organization, $brand, $branch])" wire:navigate>
                                        {{ __('Bulk QR print') }}
                                    </flux:button>
                                @endif

                                @if ($canManageStaff)
                                    <flux:button icon="users" type="button" :href="route('organizations.brands.branches.staff.index', [$organization, $brand, $branch])" wire:navigate>
                                        {{ __('Staff') }}
                                    </flux:button>
                                @endif

                                @if ($canManageBranches)
                                    <flux:button icon="cog-6-tooth" type="button" :href="route('organizations.brands.branches.settings.index', [$organization, $brand, $branch])" wire:navigate>
                                        {{ __('Settings') }}
                                    </flux:button>

                                    <flux:button icon="pencil" type="button" wire:click="startEditing({{ $branch->id }})">
                                        {{ __('Edit') }}
                                    </flux:button>

                                    <flux:button icon="trash" type="button" variant="danger" wire:click="confirmDelete({{ $branch->id }})">
                                        {{ __('Delete') }}
                                    </flux:button>
                                @endif
                            </div>
                        @endif

                        @if ($deletingBranchId === $branch->id)
                            <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200 md:col-span-2">
                                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                    <span>{{ __('Delete this branch?') }}</span>

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
                    {{ __('No branches yet.') }}
                </div>
            @endforelse
        </div>
    </div>
</section>
