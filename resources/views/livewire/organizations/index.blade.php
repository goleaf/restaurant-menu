<section data-page="organizations" class="flex h-full w-full flex-1 flex-col gap-6">
    <header class="flex flex-col gap-2">
        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('ui.organizations.index.administration') }}</p>
        <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('navigation.organizations') }}</h1>
    </header>

    <form wire:submit="create" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="grid gap-4 md:grid-cols-[1fr_auto] md:items-end">
            <flux:input wire:model="name" :label="__('ui.organizations.index.organization_name')" type="text" required maxlength="120" autocomplete="organization" />

            <flux:button icon="plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="create">
                {{ __('ui.organizations.brands.branches.menu.index.create') }}
            </flux:button>
        </div>
    </form>

    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <flux:heading size="lg">{{ __('ui.organizations.index.my_organizations') }}</flux:heading>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @forelse ($organizationRows as $organization)
                <div wire:key="organization-{{ $organization['id'] }}" class="grid gap-4 px-4 py-4 md:grid-cols-[1fr_auto] md:items-center">
                    @if ($editingOrganizationId === $organization['id'])
                        <form wire:submit="update" class="grid gap-3 md:col-span-2 md:grid-cols-[1fr_auto_auto] md:items-end">
                            <flux:input wire:model="editingName" :label="__('ui.organizations.index.organization_name')" type="text" required maxlength="120" />

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
                                    @if ($organization['logo_url'])
                                        <img src="{{ $organization['logo_url'] }}" alt="{{ $organization['name'] }}" class="size-full object-contain">
                                    @else
                                        <span class="text-xs font-medium text-zinc-400">{{ __('uploads.labels.logo') }}</span>
                                    @endif
                                </div>

                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="truncate text-base font-semibold text-zinc-950 dark:text-white">{{ $organization['name'] }}</h2>

                                        @if ($organization['is_owner'])
                                            <flux:badge color="green">{{ __('staff.roles.owner') }}</flux:badge>
                                        @else
                                            <flux:badge>{{ __('ui.organizations.index.member') }}</flux:badge>
                                        @endif
                                    </div>

                                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ __('qr.labels.created') }} {{ $organization['created_at'] }}
                                    </p>
                                </div>
                            </div>

                            @if ($organization['is_owner'])
                                <form wire:submit="saveLogo({{ $organization['id'] }})" class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-start">
                                    <label for="organization-logo-{{ $organization['id'] }}" class="sr-only">{{ __('uploads.labels.logo') }}</label>
                                    <x-ui.image-upload-input id="organization-logo-{{ $organization['id'] }}" wire:model="organizationLogos.{{ $organization['id'] }}" :aria-label="__('uploads.actions.choose_file').' '.__('uploads.labels.logo')" class="max-w-xs" />

                                    <div class="flex flex-wrap gap-2">
                                        <flux:button icon="arrow-up-tray" type="submit" wire:loading.attr="disabled" wire:target="organizationLogos.{{ $organization['id'] }}, saveLogo({{ $organization['id'] }})">
                                            {{ $organization['logo_url'] ? __('uploads.actions.replace') : __('uploads.actions.upload') }}
                                        </flux:button>

                                        @if ($organization['logo_url'])
                                            <flux:button icon="trash" type="button" variant="danger" wire:click="removeLogo({{ $organization['id'] }})" wire:loading.attr="disabled" wire:target="removeLogo({{ $organization['id'] }})">
                                                {{ __('uploads.actions.remove') }}
                                            </flux:button>
                                        @endif
                                    </div>

                                    @error('organizationLogos.'.$organization['id'])
                                        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </form>
                            @endif
                        </div>

                        @if ($organization['is_owner'])
                            <div class="flex flex-wrap gap-2 md:justify-end">
                                <flux:button icon="building-storefront" type="button" :href="$organization['brands_url']" wire:navigate>
                                    {{ __('navigation.brands') }}
                                </flux:button>

                                @if ($organization['can_manage_staff'])
                                    <flux:button icon="users" type="button" :href="$organization['staff_url']" wire:navigate>
                                        {{ __('navigation.staff') }}
                                    </flux:button>
                                @endif

                                <flux:button icon="pencil" type="button" wire:click="startEditing({{ $organization['id'] }})">
                                    {{ __('guest.cart.edit_item') }}
                                </flux:button>

                                <flux:button icon="trash" type="button" variant="danger" wire:click="confirmDelete({{ $organization['id'] }})">
                                    {{ __('ui.actions.delete') }}
                                </flux:button>
                            </div>
                        @else
                            <div class="flex flex-wrap gap-2 md:justify-end">
                                <flux:button icon="building-storefront" type="button" :href="$organization['brands_url']" wire:navigate>
                                    {{ __('navigation.brands') }}
                                </flux:button>

                                @if ($organization['can_manage_staff'])
                                    <flux:button icon="users" type="button" :href="$organization['staff_url']" wire:navigate>
                                        {{ __('navigation.staff') }}
                                    </flux:button>
                                @endif
                            </div>
                        @endif

                        @if ($deletingOrganizationId === $organization['id'])
                            <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200 md:col-span-2">
                                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                    <span>{{ __('ui.confirmations.delete.title') }}</span>

                                    <div class="flex flex-wrap gap-2">
                                        <flux:button icon="trash" variant="danger" type="button" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">
                                            {{ __('ui.actions.delete') }}
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
                    {{ __('ui.empty.no_organizations') }}
                </div>
            @endforelse
        </div>
    </div>
</section>
