<section data-page="branch-service-points" class="flex h-full w-full flex-1 flex-col gap-6">
    <header class="flex flex-col gap-3">
        <flux:button icon="arrow-left" :href="route('organizations.brands.branches.index', [$organization, $brand])" wire:navigate>
            {{ __('ui.organizations.brands.branches.areas.filialy') }}
            <span class="sr-only">{{ __('navigation.branches') }}</span>
        </flux:button>

        <div class="flex flex-col gap-1">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $contextLabel }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">
                {{ __('ui.organizations.brands.branches.index.stoly_i_mesta') }}
                <span class="sr-only">{{ __('navigation.service_points') }}</span>
            </h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.organizations.brands.branches.service_points.index.zdes_dobavliaiut_fizi') }}</p>
        </div>
    </header>

    @if ($canManageServicePoints && $filterLifecycle === 'active')
        <x-ui.card
            :heading="__('ui.organizations.brands.branches.service_points.index.sag_3_dobavte_stoly')"
            :description="__('ui.organizations.brands.branches.service_points.index.vyberite_tip_mesta_za')"
        >

            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($quickCreateOptions as $option)
                    <flux:button
                        wire:key="service-point-preset-{{ $option['type'] }}"
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
                <flux:input wire:model="name" :label="__('ui.organizations.brands.branches.service_points.index.nazvanie')" type="text" autocomplete="off" required maxlength="160" />
                <flux:input wire:model="displayNumber" :label="__('ui.organizations.brands.branches.service_points.index.nomer_na_nakleike')" type="text" autocomplete="off" maxlength="80" />

                <flux:select wire:model="type" :label="__('ui.organizations.brands.branches.service_points.index.tip_mesta')">
                    @foreach ($servicePointTypeOptions as $value => $label)
                        <flux:select.option wire:key="service-point-type-{{ $value }}" value="{{ $value }}">{{ __($label) }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="areaNodeId" :label="__('ui.livewire.onboarding.restaurantsetup.zona')">
                    @foreach ($areaOptions as $option)
                        <flux:select.option wire:key="service-point-area-create-{{ $option['value'] === '' ? 'none' : $option['value'] }}" value="{{ $option['value'] }}">
                            {{ $option['label'] }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="icon" :label="__('ui.onboarding.restaurant_setup.ikonka')">
                    @foreach ($iconOptions as $value => $label)
                        <flux:select.option wire:key="service-point-icon-{{ $value }}" value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="capacity" :label="__('ui.organizations.brands.branches.service_points.index.skolko_gostei')" type="number" required min="1" max="999" />

                <div class="flex items-end justify-between gap-4 md:col-span-2">
                    <flux:switch wire:model="isActive" :label="__('ui.organizations.brands.branches.service_points.index.mozno_ispolzovat')" />

                    <flux:button icon="plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="create">
                        {{ __('ui.organizations.brands.branches.service_points.index.dobavit_mesto') }}
                    </flux:button>
                </div>
            </form>

            <section class="mt-6 grid gap-4 border-t border-zinc-200 pt-5 dark:border-zinc-800">
                <div class="flex flex-col gap-1">
                    <h2 class="text-base font-semibold text-zinc-950 dark:text-white">{{ __('ui.organizations.brands.branches.service_points.index.dobavit_srazu_neskolk') }}</h2>
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">
                        {{ __('qr.messages.bulk_preview_no_auto_create') }}
                    </p>
                </div>

                <form wire:submit="previewBulkCreate" class="grid gap-4 md:grid-cols-3">
                    <flux:select wire:model.live="bulkAreaNodeId" :label="__('ui.livewire.onboarding.restaurantsetup.zona')">
                        @foreach ($areaOptions as $option)
                            <flux:select.option wire:key="bulk-service-point-area-{{ $option['value'] === '' ? 'none' : $option['value'] }}" value="{{ $option['value'] }}">
                                {{ $option['label'] }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="bulkType" :label="__('ui.organizations.brands.branches.service_points.index.tip_mesta')">
                        @foreach ($servicePointTypeOptions as $value => $label)
                            <flux:select.option wire:key="bulk-service-point-type-{{ $value }}" value="{{ $value }}">{{ __($label) }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model.live="bulkPrefix" :label="__('ui.organizations.brands.branches.service_points.index.prefix')" type="text" autocomplete="off" required maxlength="20" :placeholder="__('fields.placeholders.service_point_prefix_example')" />
                    <flux:input wire:model.live="bulkFrom" :label="__('ui.organizations.brands.branches.service_points.index.from')" type="number" required min="1" max="9999" />
                    <flux:input wire:model.live="bulkTo" :label="__('ui.organizations.brands.branches.service_points.index.to')" type="number" required min="1" max="9999" />
                    <flux:input wire:model.live="bulkCapacity" :label="__('ui.organizations.brands.branches.service_points.index.skolko_gostei')" type="number" required min="1" max="999" />

                    <div class="flex flex-wrap items-end gap-2 md:col-span-3">
                        <flux:button icon="eye" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="previewBulkCreate">
                            {{ __('ui.organizations.brands.branches.service_points.index.pokazat_preview') }}
                        </flux:button>
                    </div>
                </form>

                @if ($bulkPreviewRows !== [])
                    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="border-b border-zinc-200 px-3 py-2 text-sm text-zinc-600 dark:border-zinc-800 dark:text-zinc-300">
                            {{ __('ui.organizations.brands.branches.service_points.index.budet_sozdano') }}: {{ $bulkCreatableCount }} / {{ __('ui.organizations.brands.branches.service_points.index.already_exists') }}: {{ $bulkDuplicateCount }}
                        </div>

                        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @foreach ($bulkPreviewRows as $row)
                                <div wire:key="bulk-service-point-preview-{{ $row['code'] }}" class="grid gap-2 px-3 py-2 text-sm sm:grid-cols-[1fr_auto] sm:items-center">
                                    <div class="font-medium text-zinc-950 dark:text-white">{{ $row['code'] }}</div>

                                    @if ($row['will_create'])
                                        <x-ui.status-badge tone="success">{{ __('ui.organizations.brands.branches.service_points.index.will_be_created') }}</x-ui.status-badge>
                                    @else
                                        <x-ui.status-badge tone="muted">{{ __('ui.organizations.brands.branches.service_points.index.already_exists') }}</x-ui.status-badge>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                    @if ($bulkPreviewReady)
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:button
                                icon="plus"
                                variant="primary"
                                type="button"
                                wire:click="confirmBulkCreate"
                                wire:loading.attr="disabled"
                                wire:target="confirmBulkCreate"
                                :disabled="$bulkCreatableCount === 0"
                            >
                                {{ __('ui.organizations.brands.branches.service_points.index.sozdat_mesta') }}
                            </flux:button>

                            @if ($bulkCreatableCount === 0)
                                <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.organizations.brands.branches.service_points.index.net_novyx_mest_dlia_s') }}</span>
                            @endif
                        </div>
                    @endif
                @endif

                @if ($bulkCreatedCount > 0)
                    <x-ui.alert tone="success" :heading="__('ui.organizations.brands.branches.service_points.index.created_service_point', ['count' => $bulkCreatedCount])">
                        <div class="grid gap-3">
                            <p>
                                {{ __('ui.organizations.brands.branches.service_points.index.skipped_existing_code') }}: {{ $bulkSkippedCount }}.
                                {{ __('qr.messages.generate_later') }}.
                            </p>

                            @if ($canGenerateQr)
                                <div>
                                    <flux:button
                                        icon="qr-code"
                                        :href="route('organizations.brands.branches.qr.print', [$organization, $brand, $branch])"
                                        wire:navigate
                                    >
                                        {{ __('qr.actions.bulk_print') }}
                                    </flux:button>
                                </div>
                            @else
                                <p class="text-sm">
                                    {{ __('qr.messages.generate_permission_hint') }}
                                </p>
                            @endif
                        </div>
                    </x-ui.alert>
                @endif
            </section>
        </x-ui.card>
    @endif

    @if ($filterLifecycle === 'active')
    <x-ui.card padding="none" class="overflow-hidden">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div class="min-w-0">
                    <flux:heading size="lg">{{ __('ui.organizations.brands.branches.service_points.index.vizualnyi_zal') }}</flux:heading>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('ui.organizations.brands.branches.service_points.index.zony_statusy_i_bystry') }}
                    </p>
                </div>

                <x-ui.status-badge tone="muted" size="lg">
                    {{ __('ui.organizations.brands.branches.service_points.index.na_doske') }}: {{ $floorBoardServicePointCount }}
                </x-ui.status-badge>
            </div>
        </div>

        <div class="grid gap-5 bg-zinc-50 p-4 dark:bg-zinc-950/40">
            @forelse ($floorBoardSections as $section)
                <section wire:key="floor-board-zone-{{ $section['area_id'] ?? 'none' }}" class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <x-ui.area-icon :type="$section['type']" :icon="$section['icon']" :label="$section['type_label'] ?: __('ui.livewire.organizations.brands.branches.servicepoints.index.bez_zony')" :active="$section['is_active']" />

                            <div class="min-w-0">
                                <h2 class="truncate text-base font-semibold text-zinc-950 dark:text-white">{{ $section['name'] }}</h2>
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $section['service_point_count'] }} {{ __('ui.organizations.brands.branches.service_points.index.places') }}
                                </p>
                            </div>
                        </div>

                        @unless ($section['is_active'])
                            <x-ui.status-badge tone="muted">{{ __('ui.organizations.brands.branches.service_points.index.zona_vykliucena') }}</x-ui.status-badge>
                        @endunless
                    </div>

                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        @forelse ($section['service_points'] as $servicePoint)
                            <article wire:key="floor-board-service-point-{{ $servicePoint['id'] }}" class="min-h-52 rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex min-w-0 gap-3">
                                        <x-ui.service-point-icon :type="$servicePoint['type']" :icon="$servicePoint['icon']" :label="$servicePoint['type_label']" :active="$servicePoint['is_active']" />

                                        <div class="min-w-0">
                                            <h3 class="truncate text-base font-semibold text-zinc-950 dark:text-white">{{ $servicePoint['name'] }}</h3>
                                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                                {{ __('ui.organizations.brands.branches.service_points.index.nomer') }}: {{ $servicePoint['display_number'] ?: __('ui.organizations.brands.branches.service_points.index.ne_ukazan') }}
                                            </p>
                                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                                {{ $servicePoint['type_label'] }} · {{ __('ui.organizations.brands.branches.service_points.index.gostei') }}: {{ $servicePoint['capacity'] }}
                                            </p>
                                        </div>
                                    </div>

                                    <x-ui.status-badge :tone="$servicePoint['status_tone']" dot>{{ $servicePoint['localized_status'] }}</x-ui.status-badge>
                                </div>

                                <div class="mt-3 flex flex-wrap gap-2">
                                    @if ($servicePoint['is_active'])
                                        <x-ui.status-badge tone="success">{{ __('ui.organizations.brands.branches.area_node_row.rabotaet') }}</x-ui.status-badge>
                                    @else
                                        <x-ui.status-badge tone="muted">{{ __('ui.organizations.brands.branches.service_points.index.vykliuceno') }}</x-ui.status-badge>
                                    @endif

                                    @if ($servicePoint['has_direct_session'])
                                        <x-ui.status-badge tone="info">{{ __('ui.organizations.brands.branches.service_points.index.stol_otkryt') }}</x-ui.status-badge>
                                    @elseif ($servicePoint['has_linked_session'])
                                        <x-ui.status-badge tone="info">{{ __('ui.organizations.brands.branches.service_points.index.stoly_obieedineny') }}</x-ui.status-badge>
                                    @endif

                                    @if ($canGenerateQr)
                                        @if ($servicePoint['has_qr'])
                                            <x-ui.status-badge tone="success" icon="qr-code">{{ $servicePoint['qr_short_code'] }}</x-ui.status-badge>
                                        @else
                                            <x-ui.status-badge tone="muted" icon="qr-code">{{ __('qr.labels.no_qr') }}</x-ui.status-badge>
                                        @endif
                                    @endif
                                </div>

                                <div class="mt-4 flex flex-wrap gap-2">
                                    @if ($canOpenTable)
                                        @if ($servicePoint['has_direct_session'] || $servicePoint['has_linked_session'])
                                            <flux:button size="sm" icon="check" type="button" class="min-h-touch" disabled>
                                                {{ __('ui.organizations.brands.branches.service_points.index.stol_otkryt') }}
                                            </flux:button>
                                        @elseif ($servicePoint['is_active'])
                                            <flux:button size="sm" icon="play" variant="primary" type="button" class="min-h-touch" wire:click="openTable({{ $servicePoint['id'] }})" wire:loading.attr="disabled" wire:target="openTable({{ $servicePoint['id'] }})">
                                                {{ __('ui.organizations.brands.branches.service_points.index.otkryt_stol') }}
                                            </flux:button>
                                        @else
                                            <flux:button size="sm" icon="lock-closed" type="button" class="min-h-touch" disabled>
                                                {{ __('ui.organizations.brands.branches.service_points.index.mesto_vykliuceno') }}
                                            </flux:button>
                                        @endif
                                    @endif

                                    @if ($canGenerateQr)
                                        @if ($servicePoint['has_qr'])
                                            <flux:button
                                                size="sm"
                                                icon="qr-code"
                                                class="min-h-touch"
                                                :href="$servicePoint['qr_show_url']"
                                                wire:navigate
                                            >
                                                {{ __('qr.actions.show') }}
                                            </flux:button>
                                        @else
                                            <flux:button size="sm" icon="qr-code" type="button" class="min-h-touch" wire:click="generateQr({{ $servicePoint['id'] }})" wire:loading.attr="disabled" wire:target="generateQr({{ $servicePoint['id'] }})">
                                                {{ __('qr.actions.generate') }}
                                            </flux:button>
                                        @endif
                                    @endif

                                    @if ($canManageServicePoints)
                                        <flux:button size="sm" icon="pencil" type="button" class="min-h-touch" wire:click="startEditingFromBoard({{ $servicePoint['id'] }})">
                                            {{ __('ui.organizations.brands.branches.area_node_row.izmenit') }}
                                        </flux:button>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="rounded-lg border border-dashed border-zinc-300 bg-white p-5 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">
                                {{ __('ui.organizations.brands.branches.service_points.index.v_etoi_zone_poka_net') }}
                            </div>
                        @endforelse
                    </div>
                </section>
            @empty
                <x-ui.empty-state
                    icon="squares-2x2"
                    :heading="__('service_points.empty.no_floor_board')"
                    :description="__('service_points.empty.no_floor_board_description')"
                />
            @endforelse
        </div>
    </x-ui.card>
    @endif

    <x-ui.card padding="none" class="overflow-hidden">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <div class="grid gap-4">
                <flux:heading size="lg">
                    {{ __('ui.organizations.brands.branches.service_points.index.stoly_i_mesta_filiala') }}
                    <span class="sr-only">{{ __('ui.organizations.brands.branches.service_points.index.service_points_in_thi') }}</span>
                </flux:heading>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <flux:input id="service-point-branch-context" name="servicePointBranchContext" :label="__('ui.organizations.brands.branches.service_points.index.filial')" type="text" autocomplete="off" :value="$branchName" disabled />

                    <flux:input
                        wire:model.live.debounce.300ms="servicePointSearch"
                        :label="__('ui.organizations.brands.branches.service_points.index.poisk')"
                        type="search"
                        autocomplete="off"
                        maxlength="160"
                        :placeholder="__('qr.placeholders.service_point_search')"
                    />

                    <flux:select wire:model.live="filterAreaNodeId" :label="__('ui.livewire.onboarding.restaurantsetup.zona')">
                        @foreach ($filterAreaOptions as $option)
                            <flux:select.option wire:key="service-point-filter-area-{{ $option['value'] }}" value="{{ $option['value'] }}">
                                {{ $option['label'] }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="filterType" :label="__('ui.organizations.brands.branches.service_points.index.tip')">
                        <flux:select.option value="all">{{ __('ui.organizations.brands.branches.service_points.index.all_types') }}</flux:select.option>
                        @foreach ($servicePointTypeOptions as $value => $label)
                            <flux:select.option wire:key="service-point-filter-type-{{ $value }}" value="{{ $value }}">{{ __($label) }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="filterStatus" :label="__('ui.organizations.brands.branches.service_points.index.status')">
                        <flux:select.option value="all">{{ __('ui.organizations.brands.branches.service_points.index.all_statuses') }}</flux:select.option>
                        @foreach ($servicePointStatusOptions as $value => $label)
                            <flux:select.option wire:key="service-point-filter-status-{{ $value }}" value="{{ $value }}">{{ __($label) }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="filterActive" :label="__('ui.organizations.brands.branches.service_points.index.aktivnost')">
                        @foreach ($activeFilterOptions as $value => $label)
                            <flux:select.option wire:key="service-point-filter-active-{{ $value }}" value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="filterQr" :label="__('qr.labels.qr')">
                        @foreach ($qrFilterOptions as $value => $label)
                            <flux:select.option wire:key="service-point-filter-qr-{{ $value }}" value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="filterLifecycle" :label="__('structure.filters.lifecycle')">
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

                    <div class="flex items-end">
                        <flux:button
                            icon="x-mark"
                            type="button"
                            wire:click="resetServicePointFilters"
                            :disabled="! $servicePointFiltersAreActive"
                        >
                            {{ __('ui.organizations.brands.branches.service_points.index.sbrosit') }}
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @error('servicePointDeletion')
                <div role="alert" class="border-b border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300">
                    {{ $message }}
                </div>
            @enderror

            @forelse ($servicePointRows as $servicePoint)
                <div wire:key="service-point-{{ $servicePoint['id'] }}" class="grid gap-4 px-4 py-4 md:grid-cols-[1fr_auto] md:items-center">
                    @if ($editingServicePointId === $servicePoint['id'])
                        <form wire:submit="update" class="grid gap-3 md:col-span-2 md:grid-cols-2">
                            <flux:input wire:model="editingName" :label="__('ui.organizations.brands.branches.service_points.index.nazvanie')" type="text" autocomplete="off" required maxlength="160" />
                            <flux:input wire:model="editingDisplayNumber" :label="__('ui.organizations.brands.branches.service_points.index.nomer_na_nakleike')" type="text" autocomplete="off" maxlength="80" />

                            <flux:select wire:model="editingType" :label="__('ui.organizations.brands.branches.service_points.index.tip_mesta')">
                                @foreach ($servicePointTypeOptions as $value => $label)
                                    <flux:select.option wire:key="editing-service-point-type-{{ $servicePoint['id'] }}-{{ $value }}" value="{{ $value }}">{{ __($label) }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="editingAreaNodeId" :label="__('ui.livewire.onboarding.restaurantsetup.zona')">
                                @foreach ($areaOptions as $option)
                                    <flux:select.option wire:key="editing-service-point-area-{{ $servicePoint['id'] }}-{{ $option['value'] === '' ? 'none' : $option['value'] }}" value="{{ $option['value'] }}">
                                        {{ $option['label'] }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="editingIcon" :label="__('ui.onboarding.restaurant_setup.ikonka')">
                                @foreach ($iconOptions as $value => $label)
                                    <flux:select.option wire:key="editing-service-point-icon-{{ $servicePoint['id'] }}-{{ $value }}" value="{{ $value }}">{{ $label }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:input wire:model="editingCapacity" :label="__('ui.organizations.brands.branches.service_points.index.skolko_gostei')" type="number" required min="1" max="999" />

                            <div class="flex items-end justify-between gap-4 md:col-span-2">
                                <flux:switch wire:model="editingIsActive" :label="__('ui.organizations.brands.branches.service_points.index.mozno_ispolzovat')" />

                                <div class="flex flex-wrap gap-2">
                                    <flux:button icon="check" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="update">
                                        {{ __('ui.organizations.brands.branches.area_node_row.soxranit') }}
                                    </flux:button>

                                    <flux:button icon="x-mark" type="button" wire:click="cancelEditing">
                                        {{ __('ui.organizations.brands.branches.area_node_row.otmena') }}
                                    </flux:button>
                                </div>
                            </div>
                        </form>
                    @else
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-ui.service-point-icon :type="$servicePoint['type']" :icon="$servicePoint['icon']" :label="$servicePoint['type_label']" :active="$servicePoint['is_active']" />

                                <h2 class="truncate text-base font-semibold text-zinc-950 dark:text-white">{{ $servicePoint['name'] }}</h2>

                                @if ($servicePoint['is_archived'])
                                    <x-ui.status-badge tone="muted">{{ __('structure.badges.archived') }}</x-ui.status-badge>
                                @endif

                                <x-ui.status-badge tone="muted">{{ $servicePoint['type_label'] }}</x-ui.status-badge>
                                <x-ui.status-badge :tone="$servicePoint['status_tone']" dot>{{ $servicePoint['localized_status'] }}</x-ui.status-badge>

                                @if ($servicePoint['is_active'])
                                    <x-ui.status-badge tone="success">{{ __('ui.organizations.brands.branches.area_node_row.rabotaet') }}</x-ui.status-badge>
                                @else
                                    <x-ui.status-badge tone="muted">{{ __('ui.organizations.brands.branches.service_points.index.vykliuceno') }}</x-ui.status-badge>
                                @endif

                                @if ($servicePoint['has_direct_session'])
                                    <x-ui.status-badge tone="info">
                                        {{ __('ui.organizations.brands.branches.service_points.index.stol_otkryt') }}
                                        <span class="sr-only">{{ __('ui.organizations.brands.branches.service_points.index.active_session') }}</span>
                                    </x-ui.status-badge>
                                @elseif ($servicePoint['has_linked_session'])
                                    <x-ui.status-badge tone="info">
                                        {{ __('ui.organizations.brands.branches.service_points.index.stoly_obieedineny') }}
                                        <span class="sr-only">{{ __('ui.organizations.brands.branches.service_points.index.merged_table_session') }}</span>
                                    </x-ui.status-badge>
                                @endif

                                @if ($canGenerateQr)
                                    @if ($servicePoint['has_qr'])
                                        <x-ui.status-badge tone="success" icon="qr-code">
                                            {{ __('qr.labels.ready') }}
                                            <span class="sr-only">{{ __('qr.status.active') }}</span>
                                        </x-ui.status-badge>
                                    @else
                                        <x-ui.status-badge tone="muted" icon="qr-code">
                                            {{ __('qr.labels.no_qr') }}
                                            <span class="sr-only">{{ __('qr.labels.no_qr') }}</span>
                                        </x-ui.status-badge>
                                    @endif
                                @endif
                            </div>

                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('ui.organizations.brands.branches.service_points.index.nomer') }}: {{ $servicePoint['display_number'] ?: __('ui.organizations.brands.branches.service_points.index.ne_ukazan') }}
                            </p>

                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('ui.livewire.onboarding.restaurantsetup.zona') }}: {{ $servicePoint['area_name'] }} / {{ __('ui.organizations.brands.branches.service_points.index.gostei') }}: {{ $servicePoint['capacity'] }}
                            </p>

                            @if ($servicePoint['has_direct_session'])
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('ui.organizations.brands.branches.service_points.index.otkryt') }}:
                                    {{ $servicePoint['session_started_at'] ?? __('ui.organizations.brands.branches.service_points.index.seicas') }}
                                </p>
                            @elseif ($servicePoint['has_linked_session'])
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('ui.organizations.brands.branches.service_points.index.obieedinen_s_aktivnym') }}
                                </p>
                            @endif

                            @if ($canGenerateQr && $servicePoint['has_qr'])
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('qr.labels.qr') }}: {{ $servicePoint['qr_short_code'] }} / {{ $servicePoint['qr_localized_status'] }}
                                </p>
                            @endif
                        </div>

                        <div class="flex flex-wrap gap-2 md:justify-end">
                            @if ($servicePoint['is_archived'])
                                @if ($canManageServicePoints)
                                    <flux:button icon="arrow-path" variant="primary" type="button" wire:click="restoreServicePoint({{ $servicePoint['id'] }})" wire:loading.attr="disabled" wire:target="restoreServicePoint({{ $servicePoint['id'] }})">
                                        {{ __('structure.actions.restore') }}
                                    </flux:button>
                                @endif
                            @else
                            @if ($canOpenTable)
                                @if ($servicePoint['has_direct_session'] || $servicePoint['has_linked_session'])
                                    <flux:button icon="check" type="button" disabled>
                                        {{ __('ui.organizations.brands.branches.service_points.index.stol_otkryt') }}
                                        <span class="sr-only">{{ __('ui.organizations.brands.branches.service_points.index.table_opened') }}</span>
                                    </flux:button>
                                @elseif ($servicePoint['is_active'])
                                    <flux:button icon="play" variant="primary" type="button" wire:click="openTable({{ $servicePoint['id'] }})" wire:loading.attr="disabled" wire:target="openTable({{ $servicePoint['id'] }})">
                                        {{ __('ui.organizations.brands.branches.service_points.index.otkryt_stol') }}
                                        <span class="sr-only">{{ __('ui.organizations.brands.branches.service_points.index.open_table') }}</span>
                                    </flux:button>
                                @else
                                    <flux:button icon="lock-closed" type="button" disabled>
                                        {{ __('ui.organizations.brands.branches.service_points.index.mesto_vykliuceno') }}
                                        <span class="sr-only">{{ __('ui.organizations.brands.branches.service_points.index.place_inactive') }}</span>
                                    </flux:button>
                                @endif
                            @endif

                            @if ($canChangeServicePointStatus)
                                <form wire:submit="changeStatus({{ $servicePoint['id'] }})" class="flex flex-wrap items-end gap-2">
                                    <flux:select wire:model="statusSelections.{{ $servicePoint['id'] }}" :label="__('ui.organizations.brands.branches.service_points.index.status')">
                                        @foreach ($servicePointStatusOptions as $value => $label)
                                            <flux:select.option wire:key="service-point-status-{{ $servicePoint['id'] }}-{{ $value }}" value="{{ $value }}">{{ __($label) }}</flux:select.option>
                                        @endforeach
                                    </flux:select>

                                    <flux:button icon="arrow-path" type="submit" wire:loading.attr="disabled" wire:target="changeStatus({{ $servicePoint['id'] }})">
                                        {{ __('ui.organizations.brands.branches.service_points.index.smenit') }}
                                    </flux:button>
                                </form>
                            @endif

                            @if ($canGenerateQr)
                                @if ($servicePoint['has_qr'])
                                    <flux:button
                                        icon="qr-code"
                                        :href="$servicePoint['qr_show_url']"
                                        wire:navigate
                                    >
                                        {{ __('qr.actions.show') }}
                                        <span class="sr-only">{{ __('qr.actions.show') }}</span>
                                    </flux:button>
                                @else
                                    <flux:button icon="qr-code" variant="primary" type="button" wire:click="generateQr({{ $servicePoint['id'] }})" wire:loading.attr="disabled" wire:target="generateQr({{ $servicePoint['id'] }})">
                                        {{ __('qr.actions.generate') }}
                                        <span class="sr-only">{{ __('qr.actions.generate') }}</span>
                                    </flux:button>
                                @endif
                            @endif

                            @if ($canManageServicePoints)
                                @if ($servicePoint['is_active'])
                                    <flux:button icon="eye-slash" type="button" wire:click="disable({{ $servicePoint['id'] }})">
                                        {{ __('ui.organizations.brands.branches.area_node_row.vykliucit') }}
                                    </flux:button>
                                @else
                                    <flux:button icon="eye" type="button" wire:click="enable({{ $servicePoint['id'] }})">
                                        {{ __('ui.organizations.brands.branches.area_node_row.vkliucit') }}
                                    </flux:button>
                                @endif

                                <flux:button icon="pencil" type="button" wire:click="startEditing({{ $servicePoint['id'] }})">
                                    {{ __('ui.organizations.brands.branches.area_node_row.izmenit') }}
                                </flux:button>

                                <x-dangerous-action-confirmation
                                    name="delete-service-point-{{ $servicePoint['id'] }}"
                                    title="service_points.confirmations.delete.title"
                                    consequence="service_points.confirmations.delete.description"
                                    confirm-action="deleteServicePoint({{ $servicePoint['id'] }})"
                                    submit-target="deleteServicePoint({{ $servicePoint['id'] }})"
                                    confirm-label="service_points.actions.delete"
                                >
                                    <x-slot:trigger>
                                        <flux:button icon="trash" variant="danger" type="button">
                                            {{ __('structure.actions.archive') }}
                                        </flux:button>
                                    </x-slot:trigger>
                                </x-dangerous-action-confirmation>
                            @endif
                            @endif
                        </div>

                        @if ($canGenerateQr && $shownQrServicePointId === $servicePoint['id'])
                            <div class="border-t border-zinc-200 pt-4 md:col-span-2 dark:border-zinc-800">
                                @if ($servicePoint['has_qr'])
                                    <div class="grid gap-3 text-sm md:grid-cols-[1fr_auto] md:items-center">
                                        <div class="min-w-0 space-y-1">
                                            <p class="font-medium text-zinc-950 dark:text-white">{{ __('qr.labels.qr') }} {{ $servicePoint['qr_short_code'] }}</p>
                                            <p class="break-all text-zinc-600 dark:text-zinc-300">{{ $servicePoint['qr_public_path'] }}</p>
                                            <p class="text-zinc-500 dark:text-zinc-400">{{ __('ui.organizations.brands.branches.service_points.index.status') }}: {{ $servicePoint['qr_localized_status'] }}</p>
                                        </div>

                                        <flux:button icon="x-mark" type="button" wire:click="hideQr">
                                            {{ __('ui.organizations.brands.branches.service_points.index.skryt') }}
                                        </flux:button>
                                    </div>
                                @else
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('qr.empty.no_active') }}</p>
                                @endif
                            </div>
                        @endif
                    @endif
                </div>
            @empty
                <div class="p-4">
                    <x-ui.empty-state
                        icon="squares-2x2"
                        :heading="__('ui.empty.no_service_points')"
                        :description="$servicePointFiltersAreActive
                            ? __('ui.empty.no_results')
                            : __('service_points.empty.no_service_points_description')"
                    />
                    <span class="sr-only">{{ __('ui.empty.no_service_points') }}</span>
                </div>
            @endforelse
        </div>

        @if ($servicePointPaginator->hasPages())
            <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">
                {{ $servicePointPaginator->links() }}
            </div>
        @endif
    </x-ui.card>
</section>
