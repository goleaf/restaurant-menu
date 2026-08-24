<section data-page="brand-branches" class="flex h-full w-full flex-1 flex-col gap-6">
    <header class="flex flex-col gap-3">
        <flux:button icon="arrow-left" :href="$brandsUrl" wire:navigate>
            {{ __('ui.organizations.brands.branches.index.brendy') }}
            <span class="sr-only">{{ __('navigation.brands') }}</span>
        </flux:button>

        <div class="flex flex-col gap-1">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $contextLabel }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">
                {{ __('ui.organizations.brands.branches.areas.filialy') }}
                <span class="sr-only">{{ __('navigation.branches') }}</span>
            </h1>
        </div>
    </header>

    @if ($canManageBranches && $lifecycle === 'active')
        <form wire:submit="create" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mb-4 flex flex-col gap-1">
                <flux:heading size="lg">{{ __('ui.livewire.organizations.brands.branches.index.sozdat_filial') }}</flux:heading>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.organizations.brands.branches.index.vvedite_osnovnye_dannye_tocki_posle') }}</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="name" :label="__('ui.organizations.brands.branches.index.nazvanie_filiala')" type="text" required maxlength="160" />
                <flux:input wire:model="address" :label="__('ui.livewire.onboarding.restaurantsetup.adres')" type="text" required maxlength="255" />
                <flux:input wire:model="city" :label="__('ui.onboarding.restaurant_setup.gorod')" type="text" required maxlength="120" />
                <flux:input wire:model="country" :label="__('ui.onboarding.restaurant_setup.strana')" type="text" required maxlength="120" />
                <flux:input wire:model="timezone" :label="__('ui.onboarding.restaurant_setup.casovoi_poias')" type="text" required maxlength="64" />
                <flux:field>
                    <flux:label>{{ __('ui.onboarding.restaurant_setup.valiuta') }}</flux:label>
                    <flux:select wire:model="currency">
                        @foreach ($currencyOptions as $currencyCode => $currencyLabel)
                            <flux:select.option wire:key="branch-currency-{{ $currencyCode }}" value="{{ $currencyCode }}">
                                {{ $currencyLabel }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="currency" />
                </flux:field>

                <div class="flex items-end justify-between gap-4 md:col-span-2">
                    <flux:switch wire:model="isActive" :label="__('ui.organizations.brands.branches.index.filial_rabotaet')" />

                    <flux:button icon="plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="create">
                        {{ __('ui.livewire.organizations.brands.branches.index.sozdat_filial') }}
                    </flux:button>
                </div>
            </div>
        </form>
    @endif

    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex flex-col gap-3 border-b border-zinc-200 px-4 py-3 sm:flex-row sm:items-end sm:justify-between dark:border-zinc-800">
            <flux:heading size="lg">
                {{ __('ui.organizations.brands.branches.index.filialy_brenda') }}
                <span class="sr-only">{{ __('ui.organizations.brands.branches.index.branches_in_this_brand') }}</span>
            </flux:heading>
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

            @forelse ($branchSetupGuides as ['branch' => $branch, 'counts' => $counts, 'steps' => $steps])
                <div wire:key="branch-{{ $branch['id'] }}" class="grid gap-4 px-4 py-4 md:grid-cols-[1fr_auto] md:items-center">
                    @if ($editingBranchId === $branch['id'])
                        <div class="grid gap-3 md:col-span-2 md:grid-cols-2">
                            <flux:input wire:model="editingName" :label="__('ui.organizations.brands.branches.index.nazvanie_filiala')" type="text" required maxlength="160" />
                            <flux:input wire:model="editingAddress" :label="__('ui.livewire.onboarding.restaurantsetup.adres')" type="text" required maxlength="255" />
                            <flux:input wire:model="editingCity" :label="__('ui.onboarding.restaurant_setup.gorod')" type="text" required maxlength="120" />
                            <flux:input wire:model="editingCountry" :label="__('ui.onboarding.restaurant_setup.strana')" type="text" required maxlength="120" />
                            <flux:input wire:model="editingTimezone" :label="__('ui.onboarding.restaurant_setup.casovoi_poias')" type="text" required maxlength="64" />
                            <flux:field>
                                <flux:label>{{ __('ui.onboarding.restaurant_setup.valiuta') }}</flux:label>
                                <flux:select wire:model="editingCurrency">
                                    @foreach ($currencyOptions as $currencyCode => $currencyLabel)
                                        <flux:select.option wire:key="branch-editing-currency-{{ $currencyCode }}" value="{{ $currencyCode }}">
                                            {{ $currencyLabel }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="editingCurrency" />
                            </flux:field>

                            <div class="flex items-end justify-between gap-4 md:col-span-2">
                                <flux:switch wire:model="editingIsActive" :label="__('ui.organizations.brands.branches.index.filial_rabotaet')" />

                                <div class="flex flex-wrap gap-2">
                                    @if ($branch['is_active'] && ! $editingIsActive)
                                        <x-dangerous-action-confirmation
                                            name="suspend-branch-{{ $branch['id'] }}"
                                            action="suspend_branch"
                                            confirm-action="update"
                                            submit-target="update"
                                            confirm-label="ui.actions.confirm"
                                            loading-label="ui.actions.saving"
                                            reason-model="branchSuspendReason"
                                            reason-label="ui.confirmations.reason.label"
                                            reason-placeholder="ui.confirmations.reason.placeholder"
                                        >
                                            <x-slot:trigger>
                                                <flux:button icon="check" variant="primary" type="button">
                                                    {{ __('ui.organizations.brands.branches.area_node_row.soxranit') }}
                                                </flux:button>
                                            </x-slot:trigger>
                                        </x-dangerous-action-confirmation>
                                    @else
                                        <flux:button icon="check" variant="primary" type="button" wire:click="update" wire:loading.attr="disabled" wire:target="update">
                                            {{ __('ui.organizations.brands.branches.area_node_row.soxranit') }}
                                        </flux:button>
                                    @endif

                                    <flux:button icon="x-mark" type="button" wire:click="cancelEditing">
                                        {{ __('ui.organizations.brands.branches.area_node_row.otmena') }}
                                    </flux:button>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="min-w-0">
                            <div class="flex gap-3">
                                <div class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-md border border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950">
                                    @if ($branch['logo_url'])
                                        <img src="{{ $branch['logo_url'] }}" alt="{{ $branch['name'] }}" width="48" height="48" loading="lazy" decoding="async" class="size-full object-contain">
                                    @else
                                        <span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">{{ __('uploads.labels.logo') }}</span>
                                    @endif
                                </div>

                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="truncate text-base font-semibold text-zinc-950 dark:text-white">{{ $branch['name'] }}</h2>

                                        @if ($branch['is_archived'])
                                            <flux:badge color="zinc">{{ __('structure.badges.archived') }}</flux:badge>
                                        @endif

                                        @if (! $branch['is_archived'] && $branch['is_active'])
                                            <flux:badge color="green">{{ __('ui.organizations.brands.branches.area_node_row.rabotaet') }}</flux:badge>
                                        @else
                                            <flux:badge color="zinc">{{ __('ui.organizations.brands.branches.index.vykliucen') }}</flux:badge>
                                        @endif
                                    </div>

                                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ $branch['address'] }}, {{ $branch['city'] }}, {{ $branch['country'] }}
                                    </p>

                                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ __('ui.organizations.brands.branches.index.vremia') }}: {{ $branch['timezone'] }} / {{ __('ui.onboarding.restaurant_setup.valiuta') }}: {{ $branch['currency_label'] }}
                                    </p>
                                </div>
                            </div>

                            @if ($canManageBranches && ! $branch['is_archived'])
                                <form wire:submit="saveLogo({{ $branch['id'] }})" class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-start">
                                    <label for="branch-logo-{{ $branch['id'] }}" class="sr-only">{{ __('uploads.labels.logo') }}</label>
                                    <x-ui.image-upload-input id="branch-logo-{{ $branch['id'] }}" wire:model="branchLogos.{{ $branch['id'] }}" :aria-label="__('uploads.actions.choose_file').' '.__('uploads.labels.logo')" class="max-w-xs" />

                                    <div class="flex flex-wrap gap-2">
                                        <flux:button icon="arrow-up-tray" type="submit" wire:loading.attr="disabled" wire:target="branchLogos.{{ $branch['id'] }}, saveLogo({{ $branch['id'] }})">
                                            {{ $branch['logo_url'] ? __('uploads.actions.replace') : __('uploads.actions.upload') }}
                                        </flux:button>

                                        @if ($branch['logo_url'])
                                            <x-dangerous-action-confirmation
                                                name="remove-branch-logo-{{ $branch['id'] }}"
                                                action="delete_media_file"
                                                confirm-action="removeLogo({{ $branch['id'] }})"
                                                submit-target="removeLogo({{ $branch['id'] }})"
                                                confirm-label="ui.actions.confirm"
                                                loading-label="ui.actions.removing"
                                            >
                                                <x-slot:trigger>
                                                    <flux:button icon="trash" type="button" variant="danger">
                                                        {{ __('uploads.actions.remove') }}
                                                    </flux:button>
                                                </x-slot:trigger>
                                            </x-dangerous-action-confirmation>
                                        @endif
                                    </div>

                                    @error('branchLogos.'.$branch['id'])
                                        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </form>
                            @endif
                        </div>

                        @if (! $branch['is_archived'])
                        <div class="border-t border-zinc-100 pt-4 md:col-span-2 dark:border-zinc-800">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                                <div>
                                    <flux:heading size="lg">{{ __('ui.onboarding.restaurant_setup.nastroit_restoran') }}</flux:heading>
                                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ __('ui.organizations.brands.branches.index.idite_po_sagam_sverxu_vniz_filial_zo') }}
                                    </p>
                                </div>

                                <div class="flex flex-wrap gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                                    <span>{{ __('ui.livewire.organizations.brands.branches.index.zony') }}: {{ $counts['areas'] }}</span>
                                    <span>{{ __('ui.livewire.onboarding.restaurantsetup.stoly') }}: {{ $counts['service_points'] }}</span>
                                    <span>{{ __('permissions.groups.qr') }}: {{ $counts['qr_codes'] }}</span>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach ($steps as $step)
                                    @if ($step['href'] !== null)
                                        <a
                                            wire:key="branch-{{ $branch['id'] }}-setup-step-{{ $step['number'] }}"
                                            href="{{ $step['href'] }}"
                                            wire:navigate
                                            class="group flex min-h-28 flex-col justify-between gap-3 rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-left transition hover:border-zinc-300 hover:bg-white dark:border-zinc-800 dark:bg-zinc-950 dark:hover:border-zinc-700 dark:hover:bg-zinc-900"
                                        >
                                            <div class="flex items-start justify-between gap-3">
                                                <flux:badge :icon="$step['icon']" :color="$step['is_done'] ? 'green' : 'zinc'">
                                                    {{ $step['number'] }}
                                                </flux:badge>

                                                @if ($step['is_done'])
                                                    <flux:badge color="green">{{ __('ui.departments.dashboard.gotovo') }}</flux:badge>
                                                @elseif ($step['is_available'])
                                                    <flux:badge color="amber">{{ __('ui.organizations.brands.branches.index.sleduiushhii_sag') }}</flux:badge>
                                                @else
                                                    <flux:badge color="zinc">{{ __('ui.onboarding.restaurant_setup.pozze') }}</flux:badge>
                                                @endif
                                            </div>

                                            <div>
                                                <h3 class="text-base font-semibold text-zinc-950 dark:text-white">{{ $step['label'] }}</h3>
                                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $step['description'] }}</p>
                                            </div>

                                            <span class="inline-flex items-center gap-2 text-sm font-medium text-zinc-900 group-hover:text-zinc-950 dark:text-zinc-100 dark:group-hover:text-white">
                                                {{ $step['button_label'] }}
                                                <span aria-hidden="true">→</span>
                                            </span>
                                        </a>
                                    @else
                                        <div
                                            wire:key="branch-{{ $branch['id'] }}-setup-step-{{ $step['number'] }}"
                                            class="flex min-h-28 flex-col justify-between gap-3 rounded-lg border border-dashed border-zinc-200 bg-zinc-50 p-4 text-left opacity-80 dark:border-zinc-800 dark:bg-zinc-950"
                                        >
                                            <div class="flex items-start justify-between gap-3">
                                                <flux:badge :icon="$step['icon']" :color="$step['is_done'] ? 'green' : 'zinc'">
                                                    {{ $step['number'] }}
                                                </flux:badge>

                                                @if ($step['is_done'])
                                                    <flux:badge color="green">{{ __('ui.departments.dashboard.gotovo') }}</flux:badge>
                                                @else
                                                    <flux:badge color="zinc">{{ __('ui.organizations.brands.branches.index.zdet') }}</flux:badge>
                                                @endif
                                            </div>

                                            <div>
                                                <h3 class="text-base font-semibold text-zinc-950 dark:text-white">{{ $step['label'] }}</h3>
                                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $step['description'] }}</p>
                                            </div>

                                            <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">
                                                {{ $step['is_done'] ? __('ui.departments.dashboard.gotovo') : __('ui.organizations.brands.branches.index.snacala_zaversite_predydushhii_sag') }}
                                            </span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                        @endif

                        @if ($branch['is_archived'])
                            @if ($canManageBranches)
                                <div class="flex flex-wrap gap-2 md:justify-end">
                                    <flux:button icon="arrow-path" variant="primary" type="button" wire:click="restore({{ $branch['id'] }})" wire:loading.attr="disabled" wire:target="restore({{ $branch['id'] }})">
                                        {{ __('structure.actions.restore') }}
                                    </flux:button>
                                </div>
                            @endif
                        @elseif ($canManageBranches || $canManageZones || $canManageMenu || $canChangeAvailability || $canChangeServicePointStatus || $canOpenTable || $canGenerateQr || $canManageStaff)
                            <div class="flex flex-wrap gap-2 md:justify-end">
                                @if ($canManageZones)
                                    <flux:button icon="rectangle-group" type="button" :href="$branch['areas_url']" wire:navigate>
                                        {{ __('ui.livewire.organizations.brands.branches.index.zony') }}
                                        <span class="sr-only">{{ __('ui.organizations.brands.branches.areas.areas') }}</span>
                                    </flux:button>
                                @endif

                                @if ($canManageMenu || $canChangeAvailability)
                                    <flux:button icon="book-open" type="button" :href="$branch['menu_url']" wire:navigate>
                                        {{ $canManageMenu ? __('menu.guest.title') : __('ui.organizations.brands.branches.index.stop_list') }}
                                    </flux:button>
                                @endif

                                @if ($canChangeServicePointStatus || $canOpenTable || $canGenerateQr)
                                    <flux:button icon="squares-2x2" type="button" :href="$branch['service_points_url']" wire:navigate>
                                        {{ __('ui.organizations.brands.branches.index.stoly_i_mesta') }}
                                        <span class="sr-only">{{ __('navigation.service_points') }}</span>
                                    </flux:button>
                                @endif

                                @if ($canGenerateQr)
                                    <flux:button icon="printer" type="button" :href="$branch['print_url']" wire:navigate>
                                        {{ __('ui.organizations.brands.branches.index.pecat_qr') }}
                                        <span class="sr-only">{{ __('qr.print.bulk_title') }}</span>
                                    </flux:button>
                                @endif

                                @if ($canManageStaff)
                                    <flux:button icon="users" type="button" :href="$branch['staff_url']" wire:navigate>
                                        {{ __('navigation.staff') }}
                                    </flux:button>
                                @endif

                                @if ($canManageBranches)
                                    <flux:button icon="cog-6-tooth" type="button" :href="$branch['settings_url']" wire:navigate>
                                        {{ __('ui.livewire.organizations.brands.branches.index.nastroiki') }}
                                    </flux:button>

                                    <flux:button icon="pencil" type="button" wire:click="startEditing({{ $branch['id'] }})">
                                        {{ __('ui.organizations.brands.branches.area_node_row.izmenit') }}
                                    </flux:button>

                                    <flux:button icon="trash" type="button" variant="danger" wire:click="confirmDelete({{ $branch['id'] }})">
                                        {{ __('structure.actions.archive') }}
                                    </flux:button>
                                @endif
                            </div>
                        @endif

                        @if ($deletingBranchId === $branch['id'])
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
                    {{ $search !== '' ? __('ui.empty.no_results') : ($lifecycle === 'archived' ? __('structure.empty.archived') : __('ui.organizations.brands.branches.index.filialov_poka_net_sozdaite_pervyi_fi')) }}
                    <span class="sr-only">{{ __('ui.empty.no_branches') }}</span>
                </div>
            @endforelse
        </div>

        @if ($branchesPaginator->hasPages())
            <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">
                {{ $branchesPaginator->links() }}
            </div>
        @endif
    </div>
</section>
