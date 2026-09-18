<section class="rm-floor" data-page="branch-service-points" x-data="floorWorkspace">
    <header class="rm-floor__header">
        <div><h1 class="rm-floor__title">{{ __('floor.title') }}</h1><flux:text>{{ $branchName }} · {{ __('floor.description') }}</flux:text></div>
        <div class="rm-floor__actions">
            @if ($abilities['managePoints'])
                <flux:button data-floor-transition wire:click="createPoint" variant="primary" icon="plus" wire:offline.attr="disabled">{{ __('floor.add_table') }}</flux:button>
                <flux:button data-floor-transition wire:click="openBulk" wire:offline.attr="disabled">{{ __('floor.add_several') }}</flux:button>
            @endif
            @if ($abilities['manageAreas'])<flux:button data-floor-transition wire:click="createArea" icon="squares-2x2" wire:offline.attr="disabled">{{ __('floor.add_area') }}</flux:button>@endif
        </div>
    </header>
    <x-floor.errors />
    <flux:callout wire:offline variant="warning" :heading="__('floor.offline')" :text="__('floor.offline_help')" />
    <div class="rm-floor__workspace" data-editor-open="{{ $panel !== '' ? 'true' : 'false' }}">
        <aside class="rm-floor__areas" aria-label="{{ __('floor.areas') }}">
            <flux:heading size="lg">{{ __('floor.areas') }}</flux:heading>
            <flux:input wire:model.live.debounce.300ms="areaSearch" :label="__('floor.search_areas')" icon="magnifying-glass" maxlength="100" />
            <flux:select wire:model.live="areaLifecycle" :label="__('floor.area_lifecycle')">
                <flux:select.option value="active">{{ __('floor.current') }}</flux:select.option><flux:select.option value="archived">{{ __('floor.archived') }}</flux:select.option>
            </flux:select>
            <flux:accordion><flux:accordion.item><flux:accordion.heading>{{ __('floor.filters') }}</flux:accordion.heading><flux:accordion.content>
                <div class="rm-floor__form">
                    <flux:select wire:model.live="areaType" :label="__('floor.fields.type')"><flux:select.option value="all">{{ __('floor.all_types') }}</flux:select.option>@forelse ($areaTypeOptions as $option)<flux:select.option :value="$option['value']">{{ $option['label'] }}</flux:select.option>@empty @endforelse</flux:select>
                    <flux:select wire:model.live="areaActive" :label="__('floor.usability')"><flux:select.option value="all">{{ __('floor.all_states') }}</flux:select.option><flux:select.option value="active">{{ __('floor.active') }}</flux:select.option><flux:select.option value="inactive">{{ __('floor.inactive') }}</flux:select.option></flux:select>
                    <flux:select wire:model.live="areaSort" :label="__('floor.sort')">@forelse ($sortOptions as $option)<flux:select.option :value="$option['value']">{{ $option['label'] }}</flux:select.option>@empty @endforelse</flux:select>
                </div>
            </flux:accordion.content></flux:accordion.item></flux:accordion>
            <nav class="rm-floor__area-list" aria-label="{{ __('floor.choose_area') }}">
                <flux:button data-floor-transition wire:click="chooseArea('all')" :variant="$filters->area === 'all' ? 'primary' : 'ghost'" :aria-current="$filters->area === 'all' ? 'true' : null" wire:offline.attr="disabled">{{ __('floor.all_areas') }}</flux:button>
                <flux:button data-floor-transition wire:click="chooseArea('none')" :variant="$filters->area === 'none' ? 'primary' : 'ghost'" :aria-current="$filters->area === 'none' ? 'true' : null" wire:offline.attr="disabled">{{ __('floor.no_area') }}</flux:button>
                @forelse ($areaRows as $area)
                    <div class="rm-floor__area" wire:key="floor-area-{{ $area['id'] }}">
                        <flux:button data-floor-transition wire:click="chooseArea('{{ $area['id'] }}')" :variant="$selectedArea === $area['id'] ? 'primary' : 'ghost'" :aria-current="$selectedArea === $area['id'] ? 'true' : null" wire:offline.attr="disabled">{{ $area['label'] }}</flux:button>
                        <div class="rm-floor__area-meta">
                            <span>{{ $area['type_label'] }} · {{ __('floor.area_table_count', ['count' => $area['tables_count']]) }}</span>
                            @if ($area['is_archived'])<span>{{ __('floor.archived') }}</span>@elseif (! $area['is_active'])<span>{{ __('floor.inactive') }}</span>@endif
                            @if ($abilities['manageAreas'])<flux:button data-floor-transition wire:click="openArea({{ $area['id'] }})" variant="ghost" size="sm" :aria-label="__('floor.edit_area_named', ['name' => $area['name']])" wire:offline.attr="disabled">{{ __('floor.edit') }}</flux:button>@endif
                        </div>
                    </div>
                @empty
                    <flux:text>{{ __('floor.areas_empty') }}</flux:text>
                @endforelse
            </nav>
            {{ $areaPages->links() }}
            <flux:text size="sm">{{ __('floor.area_direct_scope') }}</flux:text>
        </aside>
        <section class="rm-floor__results" aria-labelledby="floor-results-heading">
            <div class="rm-floor__results-header"><h2 id="floor-results-heading" data-floor-results-heading tabindex="-1">{{ __('floor.tables') }}</h2><flux:text role="status">{{ __('floor.result_count', ['count' => $resultCount]) }}</flux:text></div>
            <div class="rm-floor__search">
                <flux:input wire:model.live.debounce.300ms="filters.search" :label="__('floor.search_tables')" icon="magnifying-glass" maxlength="100" />
                <flux:select wire:model.live="filters.mode" :label="__('floor.view')"><flux:select.option value="cards">{{ __('floor.cards') }}</flux:select.option><flux:select.option value="list">{{ __('floor.list') }}</flux:select.option></flux:select>
            </div>
            <flux:accordion><flux:accordion.item><flux:accordion.heading>{{ __('floor.filters') }}</flux:accordion.heading><flux:accordion.content>
                <div class="rm-floor__filters">
                    <flux:select wire:model.live="filters.type" :label="__('floor.fields.type')"><flux:select.option value="all">{{ __('floor.all_types') }}</flux:select.option>@forelse ($typeOptions as $option)<flux:select.option :value="$option['value']">{{ $option['label'] }}</flux:select.option>@empty @endforelse</flux:select>
                    <flux:select wire:model.live="filters.status" :label="__('floor.service_status')"><flux:select.option value="all">{{ __('floor.all_statuses') }}</flux:select.option>@forelse ($statusOptions as $option)<flux:select.option :value="$option['value']">{{ $option['label'] }}</flux:select.option>@empty @endforelse</flux:select>
                    <flux:select wire:model.live="filters.active" :label="__('floor.usability')"><flux:select.option value="all">{{ __('floor.all_states') }}</flux:select.option><flux:select.option value="active">{{ __('floor.active') }}</flux:select.option><flux:select.option value="inactive">{{ __('floor.inactive') }}</flux:select.option></flux:select>
                    <flux:select wire:model.live="filters.qr" :label="__('floor.qr.title')"><flux:select.option value="all">{{ __('floor.all_states') }}</flux:select.option><flux:select.option value="with">{{ __('floor.qr.exists') }}</flux:select.option><flux:select.option value="without">{{ __('floor.qr.missing') }}</flux:select.option></flux:select>
                    <flux:select wire:model.live="filters.lifecycle" :label="__('floor.lifecycle')"><flux:select.option value="active">{{ __('floor.current') }}</flux:select.option><flux:select.option value="archived">{{ __('floor.archived') }}</flux:select.option></flux:select>
                    <flux:select wire:model.live="filters.sort" :label="__('floor.sort')">@forelse ($sortOptions as $option)<flux:select.option :value="$option['value']">{{ $option['label'] }}</flux:select.option>@empty @endforelse</flux:select>
                </div>
            </flux:accordion.content></flux:accordion.item></flux:accordion>
            @if ($abilities['managePoints'] || $abilities['qr'])
                <div class="rm-floor__selection" aria-label="{{ __('floor.selection') }}">
                    <div class="rm-floor__actions">
                        <flux:button wire:click="selectPage" variant="ghost" size="sm" wire:offline.attr="disabled">{{ __('floor.select_page') }}</flux:button>
                        @if ($selectedCount > 0)<flux:text>{{ __('floor.selected_count', ['count' => $selectedCount]) }}</flux:text><flux:button wire:click="clearSelection" variant="ghost" size="sm" wire:offline.attr="disabled">{{ __('floor.clear_selection') }}</flux:button>@endif
                    </div>
                    @if ($hiddenSelectedCount > 0)<flux:text>{{ __('floor.hidden_selected', ['count' => $hiddenSelectedCount]) }}</flux:text>@endif
                    @if ($selectedCount > 0)
                        <div class="rm-floor__actions">
                            @if ($abilities['qr'])<flux:button data-floor-transition wire:click="openSelection('print')" icon="printer" wire:offline.attr="disabled">{{ __('floor.print_selected') }}</flux:button><flux:button data-floor-transition wire:click="openSelection('generate')" wire:offline.attr="disabled">{{ __('floor.qr.create_selected') }}</flux:button>@endif
                            @if ($abilities['managePoints'])<flux:button data-floor-transition wire:click="openSelection('move')" wire:offline.attr="disabled">{{ __('floor.move_selected') }}</flux:button>@endif
                        </div>
                    @endif
                </div>
            @endif
            <div class="rm-floor__points" data-mode="{{ $filters->mode }}">
                @forelse ($rows as $row)
                    <article class="rm-floor__point" wire:key="floor-point-{{ $row['id'] }}" data-selected="{{ $detail !== null && $detail['id'] === $row['id'] ? 'true' : 'false' }}">
                        <div class="rm-floor__point-heading">
                            @if (($abilities['managePoints'] || $abilities['qr']) && ! $row['archived'])<flux:checkbox wire:click="selectPoint({{ $row['id'] }})" :checked="$row['selected']" :aria-label="__('floor.select_table_named', ['name' => $row['name']])" wire:offline.attr="disabled" />@endif
                            <flux:button data-floor-transition wire:click="openPoint({{ $row['id'] }})" variant="ghost" class="rm-floor__point-link" wire:offline.attr="disabled">{{ $row['name'] }}</flux:button>
                        </div>
                        <p class="rm-floor__point-meta">{{ $row['number'] }} · {{ $row['type'] }} · {{ __('floor.capacity_count', ['count' => $row['capacity']]) }}</p>
                        <p class="rm-floor__point-area">{{ $row['area'] }}</p>
                        <div class="rm-floor__badges"><flux:badge>{{ $row['status'] }}</flux:badge>@if ($row['archived'])<flux:badge>{{ __('floor.archived') }}</flux:badge>@elseif (! $row['active'])<flux:badge color="amber">{{ __('floor.inactive') }}</flux:badge>@endif @if ($row['linked'])<flux:badge color="sky">{{ __('floor.linked') }}</flux:badge>@endif</div>
                        <p class="rm-floor__point-qr">{{ $row['qr'] !== null ? __('floor.qr.code', ['code' => $row['qr']]) : __('floor.qr.missing') }}</p>
                    </article>
                @empty
                    <div class="rm-floor__empty"><flux:heading>{{ __('floor.tables_empty') }}</flux:heading><flux:text>{{ __('floor.tables_empty_help') }}</flux:text></div>
                @endforelse
            </div>
            {{ $points->links() }}
            <flux:text size="sm">{{ __('floor.snapshot_at', ['time' => $evaluatedAt]) }}</flux:text>
        </section>
        <div data-floor-content class="rm-floor__content">
            @if ($panel !== '')
                <aside class="rm-floor__editor" data-floor-editor-shell tabindex="-1" aria-label="{{ __('floor.editor') }}" wire:key="floor-editor-shell-{{ $editorRevision }}">
                    <div class="rm-floor__editor-header"><flux:heading>{{ $detail['name'] ?? __('floor.editor') }}</flux:heading><flux:button x-on:click="closeEditor" variant="ghost" icon="x-mark" :aria-label="__('floor.close_editor')" /></div>
                    @if ($detail !== null)
                        <div class="rm-floor__summary">
                            <flux:text>{{ $detail['area'] }} · {{ $detail['type'] }} · {{ __('floor.capacity_count', ['count' => $detail['capacity']]) }}</flux:text>
                            <flux:text>{{ __('floor.service_status') }}: {{ $detail['status'] }}</flux:text>
                            @if ($detail['occupied'])<flux:callout :heading="$detail['linked'] ? __('floor.linked') : __('floor.session_active')" :text="__('floor.session_since', ['time' => $detail['sessionTime']])" />@endif
                            @if ($detail['serviceUrl'] !== null)<flux:button :href="$detail['serviceUrl']" wire:navigate>{{ __('floor.continue_service') }}</flux:button>@elseif ($detail['canOpen'])<flux:button data-floor-transition wire:click="openService({{ $detail['id'] }})" wire:offline.attr="disabled">{{ __('floor.open_service') }}</flux:button>@endif
                        </div>
                        <nav class="rm-floor__actions" aria-label="{{ __('floor.table_sections') }}">
                            <flux:button data-floor-transition wire:click="openPoint({{ $detail['id'] }}, 'properties')" :variant="$panel === 'properties' ? 'primary' : 'ghost'" :aria-current="$panel === 'properties' ? 'page' : null" wire:offline.attr="disabled">{{ __('floor.properties') }}</flux:button>
                            @if ($abilities['qr'])<flux:button data-floor-transition wire:click="openPoint({{ $detail['id'] }}, 'qr')" :variant="$panel === 'qr' ? 'primary' : 'ghost'" :aria-current="$panel === 'qr' ? 'page' : null" wire:offline.attr="disabled">{{ __('floor.qr.title') }}</flux:button>@endif
                        </nav>
                    @endif
                    @if ($panel === 'properties' && $detail !== null && $abilities['managePoints'])
                        <livewire:organizations.brands.branches.service-points.point-editor :branch-id="$branchId" :point-id="$detail['id']" :key="'floor-point-editor-'.$detail['id'].'-'.$editorRevision" />
                    @elseif ($panel === 'qr' && $detail !== null && $abilities['qr'])
                        <livewire:organizations.brands.branches.service-points.qr-panel :branch-id="$branchId" :point-id="$detail['id']" :qr-id="$legacyQrId" :key="'floor-qr-'.$detail['id'].'-'.$editorRevision" />
                    @elseif ($panel === 'area' && $abilities['manageAreas'])
                        <livewire:organizations.brands.branches.service-points.area-editor :branch-id="$branchId" :area-id="(int) $areaEditor" :key="'floor-area-editor-'.$areaEditor.'-'.$editorRevision" />
                    @elseif ($panel === 'create-point' && $abilities['managePoints'])
                        <livewire:organizations.brands.branches.service-points.point-editor :branch-id="$branchId" :area-id="$selectedArea" :key="'floor-create-point-'.$editorRevision" />
                    @elseif ($panel === 'create-area' && $abilities['manageAreas'])
                        <livewire:organizations.brands.branches.service-points.area-editor :branch-id="$branchId" :parent-id="$selectedArea" :key="'floor-create-area-'.$editorRevision" />
                    @elseif ($panel === 'bulk' && $abilities['managePoints'])
                        <livewire:organizations.brands.branches.service-points.bulk-create :branch-id="$branchId" :area-id="$selectedArea" :key="'floor-bulk-'.$editorRevision" />
                    @elseif ($panel === 'print' && $abilities['qr'])
                        <livewire:organizations.brands.branches.service-points.print-panel :branch-id="$branchId" :ids="$selectedIds" :expected-qr-ids="$expectedQrIds" :key="'floor-print-'.$editorRevision" />
                    @elseif ($panel === 'generate' || $panel === 'move')
                        <livewire:organizations.brands.branches.service-points.selection-operations :branch-id="$branchId" :ids="$selectedIds" :operation="$panel" :key="'floor-selection-'.$panel.'-'.$editorRevision" />
                    @endif
                </aside>
            @endif
        </div>
    </div>
    <flux:modal name="floor-unsaved" class="rm-floor__confirmation">
        <flux:heading>{{ __('floor.unsaved_title') }}</flux:heading><flux:text>{{ __('floor.unsaved_help') }}</flux:text>
        <div class="rm-floor__actions"><flux:button x-on:click="cancelNavigation">{{ __('floor.stay') }}</flux:button><flux:button variant="danger" x-on:click="discardAndNavigate">{{ __('floor.discard_leave') }}</flux:button></div>
    </flux:modal>
</section>
