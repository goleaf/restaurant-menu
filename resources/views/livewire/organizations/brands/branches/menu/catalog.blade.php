<div data-section="menu-catalog">
    <div class="grid min-w-0 gap-5">
    @if ($completedCatalogCopyId !== null)
        <section class="flex flex-wrap items-center justify-between gap-3 rounded-card border border-border-subtle bg-surface p-4" role="status">
            <p class="text-sm text-text-muted">{{ __('menu.operations.copy_ready') }}</p>
            <flux:button type="button" wire:click="openCompletedCatalogCopy" icon="pencil">{{ __('menu.operations.open_copy') }}</flux:button>
        </section>
    @endif
    @if ($catalogOperation)
        <section class="grid gap-2 rounded-card border border-border-subtle bg-surface-muted p-4" role="status"
            @if (! $catalogOperationPaused) wire:poll.2s.visible="advanceCatalogOperation" @endif>
            <h2 class="text-sm font-semibold text-text-primary">{{ __('menu.operations.in_progress') }}</h2>
            <p class="text-sm text-text-muted">{{ __('menu.operations.progress', ['count' => $catalogOperation['processed_count']]) }}</p>
            <p class="text-xs text-text-muted">{{ __('menu.operations.resume_help') }}</p>
            @if ($catalogOperationPaused)
                <flux:button type="button" wire:click="resumeCatalogOperation" wire:loading.attr="disabled" icon="arrow-path">{{ __('menu.operations.resume') }}</flux:button>
            @endif
        </section>
    @endif
    @error('catalogOperation')
        <p role="alert" class="rounded-control bg-danger-surface p-3 text-sm text-danger">{{ $message }}</p>
    @enderror
    @error('operation')
        <p role="alert" class="rounded-control bg-danger-surface p-3 text-sm text-danger">{{ $message }}</p>
    @enderror
    <section class="min-w-0 space-y-2 rounded-card border border-border-subtle bg-surface p-3" aria-label="{{ __('menu.catalog.filters') }}">
        <div class="grid min-w-0 grid-cols-[minmax(0,1fr)_auto] items-end gap-2 [&>[data-flux-field]]:min-w-0">
            <flux:input wire:model.live.debounce.300ms="filters.search" type="search" icon="magnifying-glass" :label="__('menu.catalog.search')" :placeholder="__('menu.catalog.search_placeholder')" maxlength="200" />
            <flux:button wire:click="resetCatalogFilters" icon="x-mark" :aria-label="__('menu.catalog.reset_filters')" />
        </div>
        <details class="min-w-0">
            <summary class="min-h-11 cursor-pointer py-3 text-sm font-medium text-text-muted">{{ __('menu.catalog.filters') }}@if ($catalogQualityValue !== '') · {{ $catalogQualityOptions[$catalogQualityValue] }}@endif</summary>
            <div class="grid min-w-0 gap-3 border-t border-border-subtle pt-3 sm:grid-cols-2 [&>[data-flux-field]]:min-w-0">
                <flux:select wire:model.live="filters.menuId" :label="__('menu.guest.title')">
                    <flux:select.option value="">{{ __('menu.catalog.all_menus') }}</flux:select.option>
                    @forelse ($menuOptions as $option)
                        <flux:select.option value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                    @empty
                    @endforelse
                </flux:select>
                <flux:select wire:model.live="filters.availability" :label="__('menu.catalog.availability')">
                    <flux:select.option value="">{{ __('menu.catalog.all_items') }}</flux:select.option>
                    <flux:select.option value="available">{{ __('menu.guest.available') }}</flux:select.option>
                    <flux:select.option value="unavailable">{{ __('menu.catalog.unavailable') }}</flux:select.option>
                </flux:select>
                <div class="flex flex-wrap gap-2 sm:col-span-full" aria-label="{{ __('menu.quality.title') }}">
                    @forelse ($catalogQualityOptions as $quality => $qualityLabel)
                        <flux:button type="button" wire:click="$set('filters.quality', '{{ $quality }}')" :variant="$catalogQualityValue === $quality ? 'primary' : 'subtle'" :aria-pressed="$catalogQualityValue === $quality ? 'true' : 'false'">{{ $qualityLabel }}</flux:button>
                    @empty
                    @endforelse
                </div>
                <p class="text-xs text-text-muted sm:col-span-full">{{ __('menu.catalog.page_help', ['count' => 24]) }}</p>
            </div>
        </details>
    </section>

    <section class="space-y-2 rounded-card border border-border-subtle bg-surface p-3" aria-label="{{ __('menu.bulk.title') }}">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-text-primary" aria-live="polite">{{ __('menu.bulk.selected', ['count' => $selectedCatalogCount]) }}</p>
                <p class="sr-only">{{ __('menu.bulk.page_only') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <flux:button type="button" wire:click="selectCatalogPage" :disabled="$catalogVisibleCount === 0" wire:loading.attr="disabled">{{ __('menu.bulk.select_page') }}</flux:button>
                @if ($selectedCatalogCount > 0)<flux:button type="button" wire:click="clearCatalogSelection">{{ __('menu.bulk.clear') }}</flux:button>@endif
            </div>
        </div>
        @if ($selectedCatalogCount > 0)
            <p class="text-xs text-text-muted">{{ __('menu.bulk.page_only') }}</p>
            <form wire:submit="applyCatalogBulk" class="space-y-3" novalidate>
                <fieldset wire:offline.attr="disabled" wire:loading.attr="disabled" wire:target="applyCatalogBulk" class="grid min-w-0 gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end">
                    <flux:select wire:model.live="bulk.operation" :label="__('menu.bulk.action')">
                        <flux:select.option value="">{{ __('menu.bulk.choose_action') }}</flux:select.option>
                        @if ($canChangeAvailability)
                            <flux:select.option value="available">{{ __('menu.bulk.available') }}</flux:select.option>
                            <flux:select.option value="unavailable">{{ __('menu.bulk.unavailable') }}</flux:select.option>
                        @endif
                        <flux:select.option value="move">{{ __('menu.bulk.move') }}</flux:select.option>
                        <flux:select.option value="archive">{{ __('menu.bulk.archive') }}</flux:select.option>
                    </flux:select>
                    @if ($bulk->operation === 'move')
                        <div class="min-w-0">
                            <flux:select wire:model.live="bulk.categoryId" :label="__('menu.bulk.category')">
                                <flux:select.option value="">{{ __('menu.bulk.choose_category') }}</flux:select.option>
                                @forelse ($bulkCategoryOptions as $option)
                                    <flux:select.option value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                                @empty
                                @endforelse
                            </flux:select>
                            <p class="mt-1 text-xs text-text-muted">{{ __('menu.bulk.move_help') }}</p>
                        </div>
                    @endif
                    @if ($bulk->operation === 'archive')
                        <flux:checkbox wire:model.live="bulk.confirmArchive" :label="__('menu.bulk.archive_confirm')" :description="__('menu.bulk.archive_help')" />
                    @endif
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="applyCatalogBulk" :disabled="$activeCatalogOperationId !== ''">{{ __('menu.bulk.apply', ['count' => $selectedCatalogCount]) }}</flux:button>
                </fieldset>
                <p wire:loading wire:target="applyCatalogBulk" role="status" class="text-sm text-text-muted">{{ __('ui.actions.saving') }}</p>
            </form>
        @endif
        @error('bulkSelection')<p role="alert" class="rounded-control bg-danger-surface p-3 text-sm text-danger">{{ $message }}</p>@enderror
        @if ($bulkSuccess !== '')<p role="status" class="text-sm text-success">{{ $bulkSuccess }}</p>@endif
        <p wire:offline role="status" class="text-sm text-text-muted">{{ __('menu.bulk.offline') }}</p>
    </section>
    @if ($catalogVisibleCount === 0 && $menuRows !== [])
        <section class="space-y-2 rounded-card border border-border-subtle bg-surface p-5" role="status">
            <h2 class="font-semibold text-text-primary">{{ __('menu.quality.no_results') }}</h2>
            <p class="text-sm text-text-muted">{{ __('menu.quality.no_results_help') }}</p>
            <flux:button wire:click="resetCatalogFilters">{{ __('menu.catalog.reset_filters') }}</flux:button>
        </section>
    @endif

    <div data-catalog-create-actions class="flex flex-wrap justify-end gap-2 py-3">
<flux:modal.trigger name="catalog-create-menu"><flux:button icon="plus">{{ __('ui.organizations.brands.branches.menu.index.new_menu') }}</flux:button></flux:modal.trigger>
<flux:modal.trigger name="catalog-create-category"><flux:button icon="plus">{{ __('ui.organizations.brands.branches.menu.index.new_category') }}</flux:button></flux:modal.trigger>
<flux:modal.trigger name="catalog-create-item"><flux:button icon="plus" variant="primary">{{ __('ui.organizations.brands.branches.menu.index.new_dish') }}</flux:button></flux:modal.trigger>
    </div>



        <div data-catalog-menu-list class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                <flux:heading size="lg">{{ __('ui.organizations.brands.branches.menu.index.menus_in_this_branch') }}</flux:heading>
            </div>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse ($menuRows as $menu)
                    <div wire:key="menu-{{ $menu['id'] }}" class="grid gap-4 px-4 py-4">
                        @if ($editingMenuId === $menu['id'])
                            <form wire:submit="updateMenu" novalidate class="grid gap-3 md:grid-cols-[1fr_180px_120px_auto] md:items-end">


                                <x-menu.name-translations
                                    class="md:col-span-full"
                                    id-prefix="edit-menu-{{ $menu['id'] }}"
                                    model="editingMenuTranslations"
 base-name-model="editingMenuName"
                                    :language-options="$languageOptions"
                                />

                                <flux:select wire:model="editingMenuStatus" :label="__('guest.table.status')">
                                    @foreach ($menuStatusOptions as $value => $label)
                                        <flux:select.option wire:key="menu-status-edit-{{ $menu['id'] }}-{{ $value }}" value="{{ $value }}">{{ __($label) }}</flux:select.option>
                                    @endforeach
                                </flux:select>

                                <flux:input wire:model="editingMenuSortOrder" :label="__('ui.departments.dashboard.sort')" type="number" required min="0" max="9999" />

                                <div class="flex flex-wrap gap-2">
                                    <flux:button icon="check" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="updateMenu">
                                        {{ __('ui.actions.save') }}
                                    </flux:button>

                                    <flux:button icon="x-mark" type="button" wire:click="cancelMenuEditing">
                                        {{ __('ui.actions.cancel') }}
                                    </flux:button>
                                </div>
                            </form>
                        @else
                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="truncate text-base font-semibold text-zinc-950 dark:text-white">{{ $menu['name'] }}</h2>
                                        <flux:badge :color="$menu['status_color']">{{ $menu['localized_status'] }}</flux:badge>
                                        <flux:badge>{{ __('ui.departments.dashboard.sort') }} {{ $menu['sort_order'] }}</flux:badge>
                                    </div>

                                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ trans_choice('ui.organizations.brands.branches.menu.index.category_categories', $menu['categories_count'], ['count' => $menu['categories_count']]) }}
                                        /
                                        {{ trans_choice('ui.organizations.brands.branches.menu.index.dish_dishes', $menu['items_count'], ['count' => $menu['items_count']]) }}
                                    </p>
                                </div>

                                <div class="flex flex-wrap gap-2 md:justify-end">
                                    <flux:button icon="pencil" type="button" wire:click="startEditingMenu({{ $menu['id'] }})">
                                        {{ __('ui.organizations.brands.branches.menu.index.edit_menu') }}
                                    </flux:button>

                                    <x-dangerous-action-confirmation name="delete-menu-{{ $menu['id'] }}" action="delete_menu" confirm-action="deleteMenu({{ $menu['id'] }})" confirm-label="ui.actions.confirm" loading-label="ui.actions.deleting">
                                        <x-slot:trigger><flux:button icon="trash" type="button" variant="danger" :disabled="$activeCatalogOperationId !== ''">{{ __('ui.organizations.brands.branches.menu.index.delete_menu') }}</flux:button></x-slot:trigger>
                                    </x-dangerous-action-confirmation>
                                </div>
                            </div>
                        @endif

                        <details class="rounded-control border border-border-subtle p-3" @if ($editingScheduleId !== null) open @endif><summary class="min-h-11 cursor-pointer py-2 text-sm font-semibold text-text-primary">{{ __('ui.organizations.brands.branches.menu.index.menu_schedule') }}</summary>
                            <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('ui.organizations.brands.branches.menu.index.menu_schedule') }}</p>
                                        <flux:badge :color="$menu['availability_color']">
                                            {{ $menu['availability_label'] }}
                                        </flux:badge>
                                    </div>

                                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $menu['availability_detail'] }}</p>
                                </div>
                            </div>

                            <div class="mt-3 grid gap-2">
                                @forelse ($menu['schedules'] as $schedule)
                                    <div wire:key="menu-schedule-{{ $schedule['id'] }}" class="flex flex-col gap-2 rounded-md border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900 sm:flex-row sm:items-center sm:justify-between">
                                        @if ($editingScheduleId === $schedule['id'])
                                            <form wire:submit="updateMenuSchedule" class="grid min-w-0 flex-1 gap-3 md:grid-cols-[1fr_140px_140px_auto] md:items-end">
                                                <flux:select wire:model="editingScheduleDayOfWeek" :label="__('ui.organizations.brands.branches.menu.index.day')">
                                                    @foreach ($scheduleDayOptions as $dayValue => $dayLabel)
                                                        <flux:select.option wire:key="editing-menu-schedule-{{ $schedule['id'] }}-day-{{ $dayValue }}" value="{{ $dayValue }}">{{ $dayLabel }}</flux:select.option>
                                                    @endforeach
                                                </flux:select>

                                                <flux:input wire:model="editingScheduleStartsAt" :label="__('ui.organizations.brands.branches.menu.index.start')" type="time" required />
                                                <flux:input wire:model="editingScheduleEndsAt" :label="__('ui.organizations.brands.branches.menu.index.end')" type="time" required />

                                                <div class="flex flex-wrap gap-2">
                                                    <flux:button icon="check" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="updateMenuSchedule">
                                                        {{ __('ui.actions.save') }}
                                                    </flux:button>
                                                    <flux:button icon="x-mark" type="button" wire:click="cancelMenuScheduleEditing">
                                                        {{ __('ui.actions.cancel') }}
                                                    </flux:button>
                                                </div>
                                            </form>
                                        @else
                                            <div class="flex flex-wrap items-center gap-2 text-sm">
                                                <flux:badge>{{ $schedule['day_label'] }}</flux:badge>
                                                <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $schedule['time_range'] }}</span>
                                            </div>

                                            <div class="flex flex-wrap gap-2">
                                                <flux:button icon="pencil" type="button" wire:click="startEditingMenuSchedule({{ $schedule['id'] }})">
                                                    {{ __('menu.schedules.actions.edit') }}
                                                </flux:button>
                                                <flux:button icon="trash" type="button" variant="danger" wire:click="deleteMenuSchedule({{ $schedule['id'] }})" wire:loading.attr="disabled" wire:target="deleteMenuSchedule({{ $schedule['id'] }})">
                                                    {{ __('ui.actions.delete') }}
                                                </flux:button>
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <p class="rounded-md border border-dashed border-zinc-300 bg-white px-3 py-4 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">
                                        {{ __('menu.empty.no_schedule') }}
                                    </p>
                                @endforelse
                            </div>

                            <form wire:submit="createMenuSchedule({{ $menu['id'] }})" class="mt-3 grid gap-3 md:grid-cols-[1fr_140px_140px_auto] md:items-end">
                                <flux:select wire:model="scheduleDayOfWeek" :label="__('ui.organizations.brands.branches.menu.index.day')">
                                    @foreach ($scheduleDayOptions as $dayValue => $dayLabel)
                                        <flux:select.option wire:key="menu-schedule-day-{{ $menu['id'] }}-{{ $dayValue }}" value="{{ $dayValue }}">{{ $dayLabel }}</flux:select.option>
                                    @endforeach
                                </flux:select>

                                <flux:input wire:model="scheduleStartsAt" :label="__('ui.organizations.brands.branches.menu.index.start')" type="time" required />
                                <flux:input wire:model="scheduleEndsAt" :label="__('ui.organizations.brands.branches.menu.index.end')" type="time" required />

                                <flux:button icon="plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createMenuSchedule({{ $menu['id'] }})">
                                    {{ __('ui.organizations.brands.branches.menu.index.add_interval') }}
                                </flux:button>
                            </form>
                        </details>

                        <div class="grid min-w-0 gap-4">
                            <details class="rounded-control border border-border-subtle p-3" @if ($editingCategoryId !== null) open @endif><summary class="min-h-11 cursor-pointer py-2 text-sm font-semibold text-text-primary">{{ __('menu.guest.categories') }}</summary>
                                <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('menu.guest.categories') }}</p>

                                <div class="mt-3 space-y-2">
                                    @forelse ($menu['categories'] as $category)
                                        <div wire:key="menu-category-{{ $category['id'] }}" class="rounded-md border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
                                            @if ($editingCategoryId === $category['id'])
                                                <form wire:submit="updateCategory" novalidate class="grid gap-3">




                                                    <x-menu.translation-fields
                                                        id-prefix="edit-menu-category-{{ $category['id'] }}"
                                                        model="editingCategoryTranslations"
 base-name-model="editingCategoryName"
 base-description-model="editingCategoryDescription"
                                                        :language-options="$languageOptions"
                                                        :name-max="160"
                                                        :description-max="1000"
                                                    />

                                                    <div class="grid gap-3 sm:grid-cols-2">
                                                        <flux:select wire:model="editingCategoryIcon" :label="__('ui.organizations.brands.branches.menu.index.icon')">
                                                            @foreach ($iconOptions as $value => $label)
                                                                <flux:select.option wire:key="category-icon-edit-{{ $category['id'] }}-{{ $value }}" value="{{ $value }}">{{ $label }}</flux:select.option>
                                                            @endforeach
                                                        </flux:select>

                                                        <flux:input wire:model="editingCategorySortOrder" :label="__('ui.departments.dashboard.sort')" type="number" required min="0" max="9999" />
                                                    </div>

                                                    <div class="flex items-center justify-between gap-3">
                                                        <flux:switch wire:model="editingCategoryIsActive" :label="__('qr.status.active')" />

                                                        <div class="flex flex-wrap gap-2">
                                                            <flux:button icon="check" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="updateCategory">
                                                                {{ __('ui.actions.save') }}
                                                            </flux:button>

                                                            <flux:button icon="x-mark" type="button" wire:click="cancelCategoryEditing">
                                                                {{ __('ui.actions.cancel') }}
                                                            </flux:button>
                                                        </div>
                                                    </div>
                                                </form>
                                            @else
                                                <div class="flex items-start justify-between gap-3">
                                                    <div class="min-w-0">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <flux:badge :icon="$category['icon']">{{ $category['name'] }}</flux:badge>

                                                            @if ($category['is_active'])
                                                                <flux:badge color="green">{{ __('qr.status.active') }}</flux:badge>
                                                            @else
                                                                <flux:badge color="zinc">{{ __('ui.status.inactive') }}</flux:badge>
                                                            @endif
                                                        </div>

                                                        @if ($category['description'])
                                                            <x-ui.plain-text :text="$category['description']" class="mt-2 block text-sm leading-5 text-zinc-500 dark:text-zinc-400" />
                                                        @endif

                                                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('ui.departments.dashboard.sort') }} {{ $category['sort_order'] }}</p>

                                                        <dl class="mt-3 grid gap-2 text-sm">
                                                            @foreach ($languageOptions as $languageCode => $languageLabel)
                                                                <div wire:key="menu-category-{{ $category['id'] }}-translation-{{ $languageCode }}" class="min-w-0">
                                                                    <dt class="font-medium text-zinc-500 dark:text-zinc-400">{{ $languageLabel }}</dt>
                                                                    <dd class="break-words text-zinc-900 dark:text-zinc-100">{{ $category['translations'][$languageCode]['name'] ?: __('menu.translations.fallback') }}</dd>
                                                                </div>
                                                            @endforeach
                                                        </dl>
                                                    </div>

                                                    <div class="flex flex-wrap gap-2">
                                                        <flux:button icon="pencil" type="button" wire:click="startEditingCategory({{ $category['id'] }})">
                                                            {{ __('guest.cart.edit_item') }}
                                                        </flux:button>

                                                        <x-dangerous-action-confirmation name="delete-menu-category-{{ $category['id'] }}" action="delete_menu_category" confirm-action="deleteCategory({{ $category['id'] }})" confirm-label="ui.actions.confirm" loading-label="ui.actions.deleting">
                                                            <x-slot:trigger><flux:button icon="trash" type="button" variant="danger" :disabled="$activeCatalogOperationId !== ''">{{ __('ui.actions.delete') }}</flux:button></x-slot:trigger>
                                                        </x-dangerous-action-confirmation>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @empty
                                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('menu.empty.no_categories') }}</p>
                                    @endforelse
                                </div>
                            </details>

                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-text-primary">{{ __('ui.organizations.brands.branches.menu.index.dishes') }}</p>

                                <div class="mt-3 space-y-3">
                                    @forelse ($menu['items'] as $item)
                                        <article wire:key="menu-item-{{ $item['id'] }}" class="min-w-0 border-b border-border-subtle py-4 last:border-0">
                                            <div class="grid min-w-0 grid-cols-[44px_56px_minmax(0,1fr)] items-start gap-3 sm:grid-cols-[44px_64px_minmax(0,1fr)_auto]">
                                                <button type="button" role="checkbox" aria-checked="{{ isset($selectedCatalogVersions[$item['id']]) ? 'true' : 'false' }}" aria-label="{{ __('menu.bulk.select_item', ['name' => $item['name']]) }}" wire:click="toggleCatalogSelection({{ $item['id'] }})" wire:loading.attr="disabled" class="flex size-11 shrink-0 items-center justify-center rounded-control border border-border-subtle text-text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                                                    @if (isset($selectedCatalogVersions[$item['id']]))<flux:icon.check class="size-5" />@endif
                                                </button>
                                                <div class="flex size-14 items-center justify-center overflow-hidden rounded-control bg-surface-muted sm:size-16">
                                                    @if ($item['has_image'])
                                                        <img src="{{ $item['image_url'] }}" alt="{{ $item['name'] }}" width="64" height="64" loading="lazy" decoding="async" class="size-full object-cover">
                                                    @else
                                                        <flux:icon.photo class="size-6 text-text-muted" />
                                                    @endif
                                                </div>
                                                <div class="min-w-0 space-y-1">
                                                    <x-ui.plain-text :text="$item['name']" class="block font-semibold text-text-primary" :preserve-lines="false" />
                                                    <p class="text-sm text-text-muted">{{ $item['category_name'] }}</p>
                                                    <p class="text-sm font-semibold tabular-nums text-text-primary">{{ $item['formatted_price'] }}</p>
                                                    @if ($item['description_excerpt'])<p class="text-sm text-text-muted">{{ $item['description_excerpt'] }}</p>@endif
                                                </div>
                                                <div class="col-span-full flex flex-wrap items-center gap-2 sm:col-auto sm:max-w-56 sm:justify-end">
                                                    @if ($item['is_available'])<flux:badge color="green">{{ __('menu.guest.available') }}</flux:badge>
                                                    @else<flux:badge color="zinc">{{ __('menu.guest.unavailable') }}</flux:badge>@endif
                                                    @if ($item['is_temporarily_hidden'])<flux:badge color="amber">{{ __('menu.admin.hidden_until_value', ['date' => $item['hidden_until']]) }}</flux:badge>@endif
                                                    <flux:button type="button" icon="pencil" variant="primary" wire:click="startEditingItem({{ $item['id'] }})" wire:loading.attr="disabled">{{ __('guest.cart.edit_item') }}</flux:button>
                                                </div>
                                            </div>
                                            @if ($item['quality_issues'] !== [])
                                                <div class="mt-2 flex flex-wrap gap-1">
                                                    @forelse ($item['quality_issues'] as $issue => $label)
                                                        <flux:button type="button" variant="subtle" icon="exclamation-circle" wire:click="$set('filters.quality', '{{ $issue }}')">{{ $label }}</flux:button>
                                                    @empty
                                                    @endforelse
                                                </div>
                                            @endif
                                            <details class="mt-2 min-w-0">
                                                <summary class="flex min-h-11 cursor-pointer items-center text-sm font-medium text-text-muted focus-visible:outline-2 focus-visible:outline-accent">{{ __('menu.catalog.details_actions') }}</summary>
                                                <div class="space-y-3 border-t border-border-subtle pt-3">
                                                    <dl class="grid min-w-0 gap-3 sm:grid-cols-3">
                                                        @forelse ($languageOptions as $languageCode => $languageLabel)
                                                            <div class="min-w-0"><dt class="text-xs text-text-muted">{{ $languageLabel }}</dt><dd class="break-words text-sm text-text-primary">{{ $item['translations'][$languageCode]['name'] ?: __('menu.translations.fallback') }}</dd></div>
                                                        @empty
                                                        @endforelse
                                                    </dl>
                                                    <x-menu.item-labels :allergens="$item['allergens']" :dietary-labels="$item['dietary_labels']" />
                                                    <p class="text-xs text-text-muted">{{ $item['department_name'] ?: __('ui.livewire.organizations.brands.branches.menu.index.default_kitchen') }} · {{ __('reports.csv.weight') }}: {{ $item['weight'] }} · {{ __('reports.csv.volume') }}: {{ $item['volume'] }} · {{ __('reports.csv.calories') }}: {{ $item['calories'] }}</p>
                                                    <div class="flex flex-wrap gap-2">
                                                        @if ($canChangeAvailability)
                                                            @if ($item['is_available'])
                                                                <x-dangerous-action-confirmation name="disable-menu-item-{{ $item['id'] }}" action="delete_or_deactivate_menu_item" confirm-action="setItemAvailability({{ $item['id'] }}, false)" confirm-label="ui.actions.confirm" loading-label="ui.actions.saving">
                                                                    <x-slot:trigger><flux:button icon="eye-slash" type="button">{{ __('ui.actions.disable') }}</flux:button></x-slot:trigger>
                                                                </x-dangerous-action-confirmation>
                                                            @else
                                                                <flux:button icon="eye" type="button" wire:click="setItemAvailability({{ $item['id'] }}, true)">{{ __('ui.organizations.brands.branches.menu.index.enable') }}</flux:button>
                                                            @endif
                                                        @endif
                                                        <flux:button icon="document-duplicate" type="button" wire:click="duplicateItem({{ $item['id'] }})" wire:loading.attr="disabled" :disabled="$activeCatalogOperationId !== ''">{{ __('menu.operations.duplicate') }}</flux:button>
                                                        <x-dangerous-action-confirmation name="delete-menu-item-{{ $item['id'] }}" action="delete_or_deactivate_menu_item" confirm-action="deleteItem({{ $item['id'] }})" confirm-label="ui.actions.confirm" loading-label="ui.actions.deleting">
                                                            <x-slot:trigger><flux:button icon="trash" type="button" variant="danger">{{ __('ui.actions.delete') }}</flux:button></x-slot:trigger>
                                                        </x-dangerous-action-confirmation>
                                                    </div>
                                                </div>
                                            </details>
                                        </article>
                                    @empty
                                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('menu.empty.no_items') }}</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-8 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('menu.empty.no_menus') }}
                    </div>
                @endforelse
            </div>
        </div>

    <nav class="flex flex-wrap items-center justify-between gap-3 py-3" aria-label="{{ __('menu.catalog.pagination') }}">
        <flux:button wire:click="changeCatalogPage({{ $catalogPageNumber - 1 }})" :disabled="! $catalogHasPrevious" icon="chevron-left">{{ __('menu.catalog.previous') }}</flux:button>
        <span class="text-sm text-text-muted" aria-live="polite">{{ __('menu.catalog.page_number', ['page' => $catalogPageNumber]) }}</span>
        <flux:button wire:click="changeCatalogPage({{ $catalogPageNumber + 1 }})" :disabled="! $catalogHasMore" icon-trailing="chevron-right">{{ __('menu.catalog.next') }}</flux:button>
    </nav>

    </div>

    <flux:modal name="catalog-create-menu" :closable="false" class="w-full max-w-2xl">
        <x-modal-close-button :autofocus="true" />
<form wire:submit="createMenu" novalidate class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-wrap items-center justify-between gap-3 pe-6">
                    <flux:heading size="lg">{{ __('ui.organizations.brands.branches.menu.index.new_menu') }}</flux:heading>
                    <flux:button icon="plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createMenu">
                        {{ __('ui.organizations.brands.branches.menu.index.create') }}
                    </flux:button>
                </div>

                <div class="mt-4 grid gap-3">


                    <x-menu.name-translations
                        id-prefix="create-menu"
                        model="menuTranslations"
 base-name-model="menuName"
                        :language-options="$languageOptions"
                    />

                    <flux:select wire:model="menuStatus" :label="__('guest.table.status')">
                        @foreach ($menuStatusOptions as $value => $label)
                            <flux:select.option wire:key="menu-status-create-{{ $value }}" value="{{ $value }}">{{ __($label) }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="menuSortOrder" :label="__('ui.departments.dashboard.sort')" type="number" required min="0" max="9999" />
                </div>
            </form>
</flux:modal>

    <flux:modal name="catalog-create-category" :closable="false" class="w-full max-w-2xl">
        <x-modal-close-button :autofocus="true" />
<form wire:submit="createCategory" novalidate class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-wrap items-center justify-between gap-3 pe-6">
                    <flux:heading size="lg">{{ __('ui.organizations.brands.branches.menu.index.new_category') }}</flux:heading>
                    <flux:button icon="plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createCategory">
                        {{ __('ui.organizations.brands.branches.menu.index.create') }}
                    </flux:button>
                </div>

                <div class="mt-4 grid gap-3">
                    <flux:select wire:model.live="categoryMenuId" :label="__('menu.guest.title')">
                        @forelse ($menuOptions as $option)
                            <flux:select.option wire:key="category-menu-create-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                        @empty
                            <flux:select.option value="">{{ __('ui.organizations.brands.branches.menu.index.create_a_menu_first') }}</flux:select.option>
                        @endforelse
                    </flux:select>

                    <flux:select wire:model="categoryParentId" :label="__('reports.csv.parent_category')">
                        <flux:select.option value="">{{ __('ui.livewire.organizations.brands.branches.areas.top_level') }}</flux:select.option>
                        @foreach ($categoryMenuOptions as $option)
                            <flux:select.option wire:key="category-parent-create-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                        @endforeach
                    </flux:select>





                    <x-menu.translation-fields
                        id-prefix="create-menu-category"
                        model="categoryTranslations"
 base-name-model="categoryName"
 base-description-model="categoryDescription"
                        :language-options="$languageOptions"
                        :name-max="160"
                        :description-max="1000"
                    />

                    <flux:select wire:model="categoryIcon" :label="__('ui.organizations.brands.branches.menu.index.icon')">
                        @foreach ($iconOptions as $value => $label)
                            <flux:select.option wire:key="category-icon-create-{{ $value }}" value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <flux:input wire:model="categorySortOrder" :label="__('ui.departments.dashboard.sort')" type="number" required min="0" max="9999" />
                        <div class="flex items-end">
                            <flux:switch wire:model="categoryIsActive" :label="__('qr.status.active')" />
                        </div>
                    </div>
                </div>
            </form>
</flux:modal>

    <flux:modal name="catalog-create-item" :closable="false" class="w-full max-w-2xl">
        <x-modal-close-button :autofocus="true" />
<form wire:submit="createItem" novalidate class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-wrap items-center justify-between gap-3 pe-6">
                    <flux:heading size="lg">{{ __('ui.organizations.brands.branches.menu.index.new_dish') }}</flux:heading>
                    <flux:button icon="plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createItem">
                        {{ __('ui.organizations.brands.branches.menu.index.create') }}
                    </flux:button>
                </div>

                <div class="mt-4 grid gap-3">
                    <flux:select wire:model.live="itemMenuId" :label="__('menu.guest.title')">
                        @forelse ($menuOptions as $option)
                            <flux:select.option wire:key="item-menu-create-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                        @empty
                            <flux:select.option value="">{{ __('ui.organizations.brands.branches.menu.index.create_a_menu_first') }}</flux:select.option>
                        @endforelse
                    </flux:select>

                    <flux:select wire:model="itemCategoryId" :label="__('ui.organizations.brands.branches.menu.index.category')">
                        @forelse ($itemCategoryOptions as $option)
                            <flux:select.option wire:key="item-category-create-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                        @empty
                            <flux:select.option value="">{{ __('ui.organizations.brands.branches.menu.index.create_an_active_category_first') }}</flux:select.option>
                        @endforelse
                    </flux:select>



                    <flux:select wire:model="itemKitchenDepartmentId" :label="__('reports.csv.kitchen_department')">
                        <flux:select.option value="">{{ __('ui.livewire.organizations.brands.branches.menu.index.default_kitchen') }}</flux:select.option>
                        @foreach ($kitchenDepartmentOptions as $option)
                            <flux:select.option wire:key="item-department-create-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                        @endforeach
                    </flux:select>



                    <x-menu.translation-fields
                        id-prefix="create-menu-item"
                        model="itemTranslations"
 base-name-model="itemName"
 base-description-model="itemDescription"
                        :language-options="$languageOptions"
                        :name-max="180"
                        :description-max="1200"
                    />

                    <div class="grid gap-3 sm:grid-cols-2">
                        @if ($canChangePrices)
                            <flux:input wire:model="itemPrice" :label="__('guest.cart.price')" type="number" required min="0" max="999999.99" step="0.01" />
                        @endif

                        <flux:input wire:model="itemSortOrder" :label="__('ui.departments.dashboard.sort')" type="number" required min="0" max="9999" />
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <flux:input wire:model="itemWeight" :label="__('reports.csv.weight')" type="number" min="0" step="0.01" />
                        <flux:input wire:model="itemVolume" :label="__('reports.csv.volume')" type="number" min="0" step="0.01" />
                        <flux:input wire:model="itemCalories" :label="__('reports.csv.calories')" type="number" min="0" max="999999" />
                    </div>

                    <x-menu.item-label-fields
                        id-prefix="create-menu-item"
                        allergens-model="itemAllergens"
                        dietary-labels-model="itemDietaryLabels"
                        :allergen-options="$allergenOptions"
                        :dietary-label-options="$dietaryLabelOptions"
                    />

                    @if ($canChangeAvailability)
                        <div class="grid gap-3 2xl:grid-cols-2">
                            <flux:switch wire:model="itemIsAvailable" :label="__('menu.guest.available')" />
                            <flux:input wire:model="itemHiddenUntil" :label="__('menu.admin.hidden_until')" type="datetime-local" />
                        </div>
                    @endif
                </div>
            </form>
</flux:modal>

    <flux:modal name="catalog-item-editor" :closable="false" class="w-full max-w-4xl">
        <x-modal-close-button :autofocus="true" />
        @if ($editingItem !== null)
            <div class="mb-5 space-y-1 pe-10">
                <flux:heading size="lg">{{ __('menu.editor.edit_dish') }}</flux:heading>
                <p class="text-sm text-text-muted">{{ $editingItem['name'] }}</p>
            </div>
            <x-menu.item-editor
                :item="$editingItem" :menu-options="$menuOptions" :editing-item-category-options="$editingItemCategoryOptions"
                :active-kitchen-department-options="$activeKitchenDepartmentOptions" :language-options="$languageOptions"
                :can-change-prices="$canChangePrices" :can-change-availability="$canChangeAvailability"
                :allergen-options="$allergenOptions" :dietary-label-options="$dietaryLabelOptions" :item-image-uploads="$pendingItemImageUploads" :image-presentation-context="$imagePresentationContext" :image-presentation-form="$imagePresentationForm"
            />
        @endif
    </flux:modal>
</div>
