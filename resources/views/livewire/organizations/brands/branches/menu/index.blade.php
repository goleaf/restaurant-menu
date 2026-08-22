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
        <div data-section="menu-stop-list" class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                <div class="flex flex-col gap-1">
                    <flux:heading size="lg">{{ __('ui.organizations.brands.branches.index.stop_list') }}</flux:heading>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.organizations.brands.branches.menu.index.temporarily_unavailable_dishes') }}</p>
                </div>
            </div>

            <div class="grid gap-4 p-4 lg:grid-cols-2">
                <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950/60">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('ui.organizations.brands.branches.menu.index.currently_out_of_stock') }}</p>
                        <flux:badge color="zinc">{{ count($stopListItems) }}</flux:badge>
                    </div>

                    <div class="mt-3 space-y-3">
                        @forelse ($stopListItems as $stopListItem)
                            <div wire:key="stop-list-item-{{ $stopListItem['id'] }}" class="rounded-md border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
                                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <x-ui.plain-text :text="$stopListItem['name']" class="block text-base font-semibold text-zinc-950 dark:text-white" :preserve-lines="false" />
                                            <flux:badge color="zinc">{{ __('menu.guest.out_of_stock') }}</flux:badge>
                                            <flux:badge>{{ $stopListItem['price'] }}</flux:badge>
                                        </div>

                                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $stopListItem['menu_name'] }}
                                            /
                                            {{ $stopListItem['category_name'] }}
                                            /
                                            {{ $stopListItem['department_name'] }}
                                        </p>

                                        @if ($stopListItem['updated_at'])
                                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('ui.departments.dashboard.updated') }} {{ $stopListItem['updated_at'] }}</p>
                                        @endif
                                    </div>

                                    <flux:button icon="eye" type="button" wire:click="setItemAvailability({{ $stopListItem['id'] }}, true)" wire:loading.attr="disabled" wire:target="setItemAvailability({{ $stopListItem['id'] }}, true)">
                                        {{ __('ui.organizations.brands.branches.menu.index.return_to_menu') }}
                                    </flux:button>
                                </div>
                            </div>
                        @empty
                            <p class="rounded-md border border-dashed border-zinc-300 bg-white px-3 py-4 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">
                                {{ __('menu.empty.no_stop_list_items') }}
                            </p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950/60">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('ui.organizations.brands.branches.menu.index.available_dishes') }}</p>
                        <flux:badge color="green">{{ count($availableItems) }}</flux:badge>
                    </div>

                    <div class="mt-3 space-y-3">
                        @forelse ($availableItems as $availableItem)
                            <div wire:key="available-stop-list-item-{{ $availableItem['id'] }}" class="rounded-md border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
                                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <x-ui.plain-text :text="$availableItem['name']" class="block text-base font-semibold text-zinc-950 dark:text-white" :preserve-lines="false" />
                                            <flux:badge color="green">{{ __('menu.guest.available') }}</flux:badge>
                                            <flux:badge>{{ $availableItem['price'] }}</flux:badge>
                                        </div>

                                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $availableItem['menu_name'] }}
                                            /
                                            {{ $availableItem['category_name'] }}
                                            /
                                            {{ $availableItem['department_name'] }}
                                        </p>
                                    </div>

                                    <x-dangerous-action-confirmation
                                        name="stop-list-available-item-{{ $availableItem['id'] }}"
                                        action="delete_or_deactivate_menu_item"
                                        confirm-action="setItemAvailability({{ $availableItem['id'] }}, false)"
                                        submit-target="setItemAvailability({{ $availableItem['id'] }}, false)"
                                        confirm-label="ui.actions.confirm"
                                        loading-label="ui.actions.saving"
                                    >
                                        <x-slot:trigger>
                                            <flux:button icon="eye-slash" type="button">
                                                {{ __('ui.organizations.brands.branches.menu.index.add_to_stop_list') }}
                                            </flux:button>
                                        </x-slot:trigger>
                                    </x-dangerous-action-confirmation>
                                </div>
                            </div>
                        @empty
                            <p class="rounded-md border border-dashed border-zinc-300 bg-white px-3 py-4 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">
                                {{ __('menu.empty.no_available_items') }}
                            </p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($canManageMenu)
    <div class="grid gap-4 xl:grid-cols-5">
        <form wire:submit="createMenu" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between gap-3">
                <flux:heading size="lg">{{ __('ui.organizations.brands.branches.menu.index.new_menu') }}</flux:heading>
                <flux:button icon="plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createMenu">
                    {{ __('ui.organizations.brands.branches.menu.index.create') }}
                </flux:button>
            </div>

            <div class="mt-4 grid gap-3">
                <flux:input wire:model="menuName" :label="__('reports.csv.name')" type="text" required maxlength="160" />

                <flux:select wire:model="menuStatus" :label="__('guest.table.status')">
                    @foreach ($menuStatusOptions as $value => $label)
                        <flux:select.option wire:key="menu-status-create-{{ $value }}" value="{{ $value }}">{{ __($label) }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="menuSortOrder" :label="__('ui.departments.dashboard.sort')" type="number" required min="0" max="9999" />
            </div>
        </form>

        <form wire:submit="createCategory" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between gap-3">
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

                <flux:input wire:model="categoryName" :label="__('reports.csv.name')" type="text" required maxlength="160" />

                <label class="grid gap-1 text-sm">
                    <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('menu.item_detail.description') }}</span>
                    <textarea wire:model="categoryDescription" rows="2" maxlength="1000" class="rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-400 focus:outline-hidden focus:ring-2 focus:ring-zinc-200 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-600 dark:focus:ring-zinc-800"></textarea>
                </label>

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

        <form wire:submit="createItem" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between gap-3">
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

                <flux:input wire:model="itemName" :label="__('reports.csv.name')" type="text" required maxlength="180" />

                <flux:select wire:model="itemKitchenDepartmentId" :label="__('reports.csv.kitchen_department')">
                    <flux:select.option value="">{{ __('ui.livewire.organizations.brands.branches.menu.index.default_kitchen') }}</flux:select.option>
                    @foreach ($kitchenDepartmentOptions as $option)
                        <flux:select.option wire:key="item-department-create-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>

                <label class="grid gap-1 text-sm">
                    <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('menu.item_detail.description') }}</span>
                    <textarea wire:model="itemDescription" rows="2" maxlength="1200" class="rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-400 focus:outline-hidden focus:ring-2 focus:ring-zinc-200 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-600 dark:focus:ring-zinc-800"></textarea>
                </label>

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

                @if ($canChangeAvailability)
                    <flux:switch wire:model="itemIsAvailable" :label="__('menu.guest.available')" />
                @endif
            </div>
        </form>

        <form wire:submit="createKitchenDepartment" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between gap-3">
                <flux:heading size="lg">{{ __('reports.csv.kitchen_department') }}</flux:heading>
                <flux:button icon="plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createKitchenDepartment">
                    {{ __('ui.organizations.brands.branches.menu.index.create') }}
                </flux:button>
            </div>

            <div class="mt-4 grid gap-3">
                <flux:input wire:model="departmentName" :label="__('reports.csv.name')" type="text" required maxlength="120" />

                <flux:select wire:model="departmentType" :label="__('reports.csv.type')">
                    @foreach ($kitchenDepartmentTypeOptions as $value => $label)
                        <flux:select.option wire:key="department-type-create-{{ $value }}" value="{{ $value }}">{{ __($label) }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:input wire:model="departmentSortOrder" :label="__('ui.departments.dashboard.sort')" type="number" required min="0" max="9999" />
                    <div class="flex items-end">
                        <flux:switch wire:model="departmentIsActive" :label="__('qr.status.active')" />
                    </div>
                </div>
            </div>
        </form>

        <form wire:submit="createModifierGroup" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between gap-3">
                <flux:heading size="lg">{{ __('ui.organizations.brands.branches.menu.index.new_modifier') }}</flux:heading>
                <flux:button icon="plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createModifierGroup">
                    {{ __('ui.organizations.brands.branches.menu.index.create') }}
                </flux:button>
            </div>

            <div class="mt-4 grid gap-3">
                <flux:input wire:model="modifierGroupName" :label="__('reports.csv.name')" type="text" required maxlength="160" />

                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:input wire:model="modifierGroupMinSelect" :label="__('ui.organizations.brands.branches.menu.index.min')" type="number" required min="0" max="50" />
                    <flux:input wire:model="modifierGroupMaxSelect" :label="__('ui.organizations.brands.branches.menu.index.max')" type="number" required min="0" max="50" />
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:input wire:model="modifierGroupSortOrder" :label="__('ui.departments.dashboard.sort')" type="number" required min="0" max="9999" />
                    <div class="flex items-end">
                        <flux:switch wire:model="modifierGroupIsRequired" :label="__('guest.cart.required')" />
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <flux:heading size="lg">{{ __('ui.organizations.brands.branches.menu.index.menus_in_this_branch') }}</flux:heading>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @forelse ($menuRows as $menu)
                <div wire:key="menu-{{ $menu['id'] }}" class="grid gap-4 px-4 py-4">
                    @if ($editingMenuId === $menu['id'])
                        <form wire:submit="updateMenu" class="grid gap-3 md:grid-cols-[1fr_180px_120px_auto] md:items-end">
                            <flux:input wire:model="editingMenuName" :label="__('reports.csv.name')" type="text" required maxlength="160" />

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

                                <flux:button icon="trash" type="button" variant="danger" wire:click="deleteMenu({{ $menu['id'] }})">
                                    {{ __('ui.organizations.brands.branches.menu.index.delete_menu') }}
                                </flux:button>
                            </div>
                        </div>
                    @endif

                    <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950/60">
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
                                    <div class="flex flex-wrap items-center gap-2 text-sm">
                                        <flux:badge>{{ $schedule['day_label'] }}</flux:badge>
                                        <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $schedule['time_range'] }}</span>
                                    </div>

                                    <flux:button icon="trash" type="button" variant="danger" wire:click="deleteMenuSchedule({{ $schedule['id'] }})" wire:loading.attr="disabled" wire:target="deleteMenuSchedule({{ $schedule['id'] }})">
                                        {{ __('ui.actions.delete') }}
                                    </flux:button>
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
                    </div>

                    <div class="grid gap-4 lg:grid-cols-[minmax(0,320px)_1fr]">
                        <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950/60">
                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('menu.guest.categories') }}</p>

                            <div class="mt-3 space-y-2">
                                @forelse ($menu['categories'] as $category)
                                    <div wire:key="menu-category-{{ $category['id'] }}" class="rounded-md border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
                                        @if ($editingCategoryId === $category['id'])
                                            <form wire:submit="updateCategory" class="grid gap-3">
                                                <flux:input wire:model="editingCategoryName" :label="__('reports.csv.name')" type="text" required maxlength="160" />

                                                <label class="grid gap-1 text-sm">
                                                    <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('menu.item_detail.description') }}</span>
                                                    <textarea wire:model="editingCategoryDescription" rows="2" maxlength="1000" class="rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-400 focus:outline-hidden focus:ring-2 focus:ring-zinc-200 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-600 dark:focus:ring-zinc-800"></textarea>
                                                </label>

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
                                                            <flux:badge color="zinc">{{ __('staff.statuses.suspended') }}</flux:badge>
                                                        @endif
                                                    </div>

                                                    @if ($category['description'])
                                                        <x-ui.plain-text :text="$category['description']" class="mt-2 block text-sm leading-5 text-zinc-500 dark:text-zinc-400" />
                                                    @endif

                                                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('ui.departments.dashboard.sort') }} {{ $category['sort_order'] }}</p>
                                                </div>

                                                <div class="flex flex-wrap gap-2">
                                                    <flux:button icon="pencil" type="button" wire:click="startEditingCategory({{ $category['id'] }})">
                                                        {{ __('guest.cart.edit_item') }}
                                                    </flux:button>

                                                    <flux:button icon="trash" type="button" variant="danger" wire:click="deleteCategory({{ $category['id'] }})">
                                                        {{ __('ui.actions.delete') }}
                                                    </flux:button>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('menu.empty.no_categories') }}</p>
                                @endforelse
                            </div>
                        </div>

                        <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950/60">
                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('ui.organizations.brands.branches.menu.index.dishes') }}</p>

                            <div class="mt-3 space-y-3">
                                @forelse ($menu['items'] as $item)
                                    <div wire:key="menu-item-{{ $item['id'] }}" class="rounded-md border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
                                        @if ($editingItemId === $item['id'])
                                            <form wire:submit="updateItem" class="grid gap-3">
                                                <div class="grid gap-3 md:grid-cols-2">
                                                    <flux:select wire:model.live="editingItemMenuId" :label="__('menu.guest.title')">
                                                        @foreach ($menuOptions as $option)
                                                            <flux:select.option wire:key="item-menu-edit-{{ $item['id'] }}-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                                                        @endforeach
                                                    </flux:select>

                                                    <flux:select wire:model="editingItemCategoryId" :label="__('ui.organizations.brands.branches.menu.index.category')">
                                                        @foreach ($editingItemCategoryOptions as $option)
                                                            <flux:select.option wire:key="item-category-edit-{{ $item['id'] }}-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                                                        @endforeach
                                                    </flux:select>
                                                </div>

                                                <flux:input wire:model="editingItemName" :label="__('reports.csv.name')" type="text" required maxlength="180" />

                                                <flux:select wire:model="editingItemKitchenDepartmentId" :label="__('reports.csv.kitchen_department')">
                                                    <flux:select.option value="">{{ __('ui.livewire.organizations.brands.branches.menu.index.default_kitchen') }}</flux:select.option>
                                                    @foreach ($activeKitchenDepartmentOptions as $option)
                                                        <flux:select.option wire:key="item-department-edit-{{ $item['id'] }}-{{ $option['value'] }}" value="{{ $option['value'] }}">
                                                            {{ $option['label'] }}{{ $option['is_active'] ? '' : ' - '.__('staff.statuses.suspended') }}
                                                        </flux:select.option>
                                                    @endforeach
                                                </flux:select>

                                                <label class="grid gap-1 text-sm">
                                                    <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('menu.item_detail.description') }}</span>
                                                    <textarea wire:model="editingItemDescription" rows="2" maxlength="1200" class="rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-400 focus:outline-hidden focus:ring-2 focus:ring-zinc-200 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-600 dark:focus:ring-zinc-800"></textarea>
                                                </label>

                                                <div class="grid gap-3 md:grid-cols-4">
                                                    @if ($canChangePrices)
                                                        <flux:input wire:model="editingItemPrice" :label="__('guest.cart.price')" type="number" required min="0" max="999999.99" step="0.01" />
                                                    @endif

                                                    <flux:input wire:model="editingItemWeight" :label="__('reports.csv.weight')" type="number" min="0" step="0.01" />
                                                    <flux:input wire:model="editingItemVolume" :label="__('reports.csv.volume')" type="number" min="0" step="0.01" />
                                                    <flux:input wire:model="editingItemCalories" :label="__('reports.csv.calories')" type="number" min="0" max="999999" />
                                                    <flux:input wire:model="editingItemSortOrder" :label="__('ui.departments.dashboard.sort')" type="number" required min="0" max="9999" />
                                                </div>

                                                <div class="flex items-center justify-between gap-3">
                                                    @if ($canChangeAvailability)
                                                        <flux:switch wire:model="editingItemIsAvailable" :label="__('menu.guest.available')" />
                                                    @endif

                                                    <div class="flex flex-wrap gap-2">
                                                        <flux:button icon="check" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="updateItem">
                                                            {{ __('ui.actions.save') }}
                                                        </flux:button>

                                                        <flux:button icon="x-mark" type="button" wire:click="cancelItemEditing">
                                                            {{ __('ui.actions.cancel') }}
                                                        </flux:button>
                                                    </div>
                                                </div>
                                            </form>
                                        @else
                                            <div class="grid gap-3 md:grid-cols-[64px_1fr_auto] md:items-start">
                                                <div class="flex size-16 items-center justify-center overflow-hidden rounded-md border border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950">
                                                    @if ($item['has_image'])
                                                        <img src="{{ $item['image_url'] }}" alt="{{ $item['name'] }}" class="size-full object-cover">
                                                    @else
                                                        <span class="text-xs font-medium text-zinc-400">{{ __('uploads.labels.image') }}</span>
                                                    @endif
                                                </div>

                                                <div class="min-w-0">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <x-ui.plain-text :text="$item['name']" class="block text-base font-semibold text-zinc-950 dark:text-white" :preserve-lines="false" />
                                                        <flux:badge>{{ $item['category_name'] }}</flux:badge>
                                                        @if ($item['has_department'])
                                                            <flux:badge :color="$item['department_color']">{{ $item['department_name'] }}</flux:badge>
                                                        @else
                                                            <flux:badge color="zinc">{{ __('ui.livewire.organizations.brands.branches.menu.index.default_kitchen') }}</flux:badge>
                                                        @endif

                                                        @if ($item['is_available'])
                                                            <flux:badge color="green">{{ __('menu.guest.available') }}</flux:badge>
                                                        @else
                                                            <flux:badge color="zinc">{{ __('menu.guest.unavailable') }}</flux:badge>
                                                        @endif
                                                    </div>

                                                    @if ($item['description'])
                                                        <x-ui.plain-text :text="$item['description']" class="mt-1 block text-sm leading-5 text-zinc-500 dark:text-zinc-400" />
                                                    @endif

                                                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                                                        {{ __('guest.cart.price') }}: {{ $item['formatted_price'] }} / {{ __('ui.departments.dashboard.sort') }} {{ $item['sort_order'] }}
                                                    </p>

                                                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                                        {{ __('reports.csv.weight') }}: {{ $item['weight'] }}
                                                        /
                                                        {{ __('reports.csv.volume') }}: {{ $item['volume'] }}
                                                        /
                                                        {{ __('reports.csv.calories') }}: {{ $item['calories'] }}
                                                    </p>

                                                    @if ($item['modifier_groups'] !== [])
                                                        <div class="mt-3 flex flex-wrap gap-2">
                                                            @foreach ($item['modifier_groups'] as $modifierGroup)
                                                                <span wire:key="item-{{ $item['id'] }}-modifier-{{ $modifierGroup['id'] }}" class="inline-flex items-center gap-1 rounded-md border border-zinc-200 bg-zinc-50 px-2 py-1 text-xs font-medium text-zinc-700 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-200">
                                                                    {{ $modifierGroup['name'] }}
                                                                    <button type="button" wire:click="detachModifierGroupFromItem({{ $item['id'] }}, {{ $modifierGroup['id'] }})" class="text-zinc-400 hover:text-red-600" aria-label="{{ __('ui.organizations.brands.branches.menu.index.remove_modifier_group') }}">
                                                                        ×
                                                                    </button>
                                                                </span>
                                                            @endforeach
                                                        </div>
                                                    @endif

                                                    <form wire:submit="saveItemImage({{ $item['id'] }})" class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-start">
                                                        <label for="item-photo-{{ $item['id'] }}" class="sr-only">{{ __('uploads.labels.image') }}</label>
                                                        <x-ui.image-upload-input id="item-photo-{{ $item['id'] }}" wire:model="itemImages.{{ $item['id'] }}" :aria-label="__('uploads.actions.choose_file').' '.__('uploads.labels.image')" class="max-w-xs" />

                                                        <div class="flex flex-wrap gap-2">
                                                            <flux:button icon="arrow-up-tray" type="submit" wire:loading.attr="disabled" wire:target="itemImages.{{ $item['id'] }}, saveItemImage({{ $item['id'] }})">
                                                                {{ $item['has_image'] ? __('uploads.actions.replace') : __('uploads.actions.upload') }}
                                                            </flux:button>

                                                            @if ($item['has_image'])
                                                                <x-dangerous-action-confirmation
                                                                    name="remove-menu-item-photo-{{ $item['id'] }}"
                                                                    action="delete_media_file"
                                                                    confirm-action="removeItemImage({{ $item['id'] }})"
                                                                    submit-target="removeItemImage({{ $item['id'] }})"
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

                                                        @error('itemImages.'.$item['id'])
                                                            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                                        @enderror
                                                    </form>
                                                </div>

                                                <div class="flex flex-wrap gap-2 md:justify-end">
                                                    @if ($canChangeAvailability)
                                                        @if ($item['is_available'])
                                                            <x-dangerous-action-confirmation
                                                                name="disable-menu-item-{{ $item['id'] }}"
                                                                action="delete_or_deactivate_menu_item"
                                                                confirm-action="setItemAvailability({{ $item['id'] }}, false)"
                                                                submit-target="setItemAvailability({{ $item['id'] }}, false)"
                                                                confirm-label="ui.actions.confirm"
                                                                loading-label="ui.actions.saving"
                                                            >
                                                                <x-slot:trigger>
                                                                    <flux:button icon="eye-slash" type="button">
                                                                        {{ __('ui.actions.disable') }}
                                                                    </flux:button>
                                                                </x-slot:trigger>
                                                            </x-dangerous-action-confirmation>
                                                        @else
                                                            <flux:button icon="eye" type="button" wire:click="setItemAvailability({{ $item['id'] }}, true)">
                                                                {{ __('ui.organizations.brands.branches.menu.index.enable') }}
                                                            </flux:button>
                                                        @endif
                                                    @endif

                                                    <flux:button icon="pencil" type="button" wire:click="startEditingItem({{ $item['id'] }})">
                                                        {{ __('guest.cart.edit_item') }}
                                                    </flux:button>

                                                    <x-dangerous-action-confirmation
                                                        name="delete-menu-item-{{ $item['id'] }}"
                                                        action="delete_or_deactivate_menu_item"
                                                        confirm-action="deleteItem({{ $item['id'] }})"
                                                        submit-target="deleteItem({{ $item['id'] }})"
                                                        confirm-label="ui.actions.confirm"
                                                        loading-label="ui.actions.deleting"
                                                    >
                                                        <x-slot:trigger>
                                                            <flux:button icon="trash" type="button" variant="danger">
                                                                {{ __('ui.actions.delete') }}
                                                            </flux:button>
                                                        </x-slot:trigger>
                                                    </x-dangerous-action-confirmation>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
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

    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <flux:heading size="lg">{{ __('ui.organizations.brands.branches.menu.index.kitchen_departments') }}</flux:heading>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @forelse ($kitchenDepartmentRows as $department)
                <div wire:key="kitchen-department-{{ $department['id'] }}" class="px-4 py-4">
                    @if ($editingDepartmentId === $department['id'])
                        <form wire:submit="updateKitchenDepartment" class="grid gap-3 md:grid-cols-[1fr_160px_120px_auto] md:items-end">
                            <flux:input wire:model="editingDepartmentName" :label="__('reports.csv.name')" type="text" required maxlength="120" />

                            <flux:select wire:model="editingDepartmentType" :label="__('reports.csv.type')">
                                @foreach ($kitchenDepartmentTypeOptions as $value => $label)
                                    <flux:select.option wire:key="department-type-edit-{{ $department['id'] }}-{{ $value }}" value="{{ $value }}">{{ __($label) }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:input wire:model="editingDepartmentSortOrder" :label="__('ui.departments.dashboard.sort')" type="number" required min="0" max="9999" />

                            <div class="flex flex-wrap items-center gap-2">
                                <flux:switch wire:model="editingDepartmentIsActive" :label="__('qr.status.active')" />
                                <flux:button icon="check" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="updateKitchenDepartment">
                                    {{ __('ui.actions.save') }}
                                </flux:button>
                                <flux:button icon="x-mark" type="button" wire:click="cancelKitchenDepartmentEditing">
                                    {{ __('ui.actions.cancel') }}
                                </flux:button>
                            </div>
                        </form>
                    @else
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="truncate text-base font-semibold text-zinc-950 dark:text-white">{{ $department['name'] }}</h2>
                                    <flux:badge :color="$department['type_color']">{{ $department['localized_type'] }}</flux:badge>
                                    @if ($department['is_active'])
                                        <flux:badge color="green">{{ __('qr.status.active') }}</flux:badge>
                                    @else
                                        <flux:badge color="zinc">{{ __('staff.statuses.suspended') }}</flux:badge>
                                    @endif
                                    <flux:badge>{{ trans_choice('ui.organizations.brands.branches.menu.index.dish_dishes', $department['menu_items_count'], ['count' => $department['menu_items_count']]) }}</flux:badge>
                                </div>
                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('ui.departments.dashboard.sort') }} {{ $department['sort_order'] }}</p>
                            </div>

                            <div class="flex flex-wrap gap-2 md:justify-end">
                                @if ($department['is_active'])
                                    <flux:button icon="eye-slash" type="button" wire:click="setKitchenDepartmentActive({{ $department['id'] }}, false)">
                                        {{ __('ui.actions.disable') }}
                                    </flux:button>
                                @else
                                    <flux:button icon="eye" type="button" wire:click="setKitchenDepartmentActive({{ $department['id'] }}, true)">
                                        {{ __('ui.organizations.brands.branches.menu.index.enable') }}
                                    </flux:button>
                                @endif

                                <flux:button icon="pencil" type="button" wire:click="startEditingKitchenDepartment({{ $department['id'] }})">
                                    {{ __('guest.cart.edit_item') }}
                                </flux:button>

                                <flux:button icon="trash" type="button" variant="danger" wire:click="deleteKitchenDepartment({{ $department['id'] }})">
                                    {{ __('ui.actions.delete') }}
                                </flux:button>
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <div class="px-4 py-8 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('departments.empty.no_departments') }}
                </div>
            @endforelse
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <flux:heading size="lg">{{ __('ui.organizations.brands.branches.menu.index.modifier_groups') }}</flux:heading>
        </div>

        <div class="grid gap-4 border-b border-zinc-200 p-4 dark:border-zinc-800 lg:grid-cols-2">
            <form wire:submit="createModifierOption" class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950/60">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('ui.organizations.brands.branches.menu.index.new_option') }}</p>
                    <flux:button icon="plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createModifierOption">
                        {{ __('ui.organizations.brands.branches.menu.index.create') }}
                    </flux:button>
                </div>

                <div class="mt-3 grid gap-3">
                    <flux:select wire:model="modifierOptionGroupId" :label="__('ui.organizations.brands.branches.menu.index.modifier_group')">
                        @forelse ($modifierGroupOptions as $option)
                            <flux:select.option wire:key="modifier-option-group-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                        @empty
                            <flux:select.option value="">{{ __('ui.organizations.brands.branches.menu.index.create_a_modifier_group_first') }}</flux:select.option>
                        @endforelse
                    </flux:select>

                    <flux:input wire:model="modifierOptionName" :label="__('reports.csv.name')" type="text" required maxlength="160" />

                    <div class="grid gap-3 sm:grid-cols-2">
                        @if ($canChangePrices)
                            <flux:input wire:model="modifierOptionPriceDelta" :label="__('ui.organizations.brands.branches.menu.index.price_change')" type="number" required min="-999999.99" max="999999.99" step="0.01" />
                        @endif

                        <flux:input wire:model="modifierOptionSortOrder" :label="__('ui.departments.dashboard.sort')" type="number" required min="0" max="9999" />
                    </div>

                    @if ($canChangeAvailability)
                        <flux:switch wire:model="modifierOptionIsAvailable" :label="__('menu.guest.available')" />
                    @endif
                </div>
            </form>

            <form wire:submit="attachModifierGroupToItem" class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950/60">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('ui.organizations.brands.branches.menu.index.assign_to_dish') }}</p>
                    <flux:button icon="link" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="attachModifierGroupToItem">
                        {{ __('ui.organizations.brands.branches.menu.index.assign') }}
                    </flux:button>
                </div>

                <div class="mt-3 grid gap-3">
                    <flux:select wire:model.live="modifierItemMenuId" :label="__('menu.guest.title')">
                        @forelse ($menuOptions as $option)
                            <flux:select.option wire:key="modifier-item-menu-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                        @empty
                            <flux:select.option value="">{{ __('ui.organizations.brands.branches.menu.index.create_a_menu_first') }}</flux:select.option>
                        @endforelse
                    </flux:select>

                    <flux:select wire:model="modifierItemId" :label="__('ui.actions.analytics.buildbasicanalyticsdashboardaction.dish')">
                        @forelse ($modifierItemOptions as $option)
                            <flux:select.option wire:key="modifier-item-dish-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                        @empty
                            <flux:select.option value="">{{ __('ui.organizations.brands.branches.menu.index.create_a_dish_first') }}</flux:select.option>
                        @endforelse
                    </flux:select>

                    <flux:select wire:model="modifierItemGroupId" :label="__('ui.organizations.brands.branches.menu.index.modifier_group')">
                        @forelse ($modifierGroupOptions as $option)
                            <flux:select.option wire:key="modifier-item-group-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                        @empty
                            <flux:select.option value="">{{ __('ui.organizations.brands.branches.menu.index.create_a_modifier_group_first') }}</flux:select.option>
                        @endforelse
                    </flux:select>
                </div>
            </form>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @forelse ($modifierGroupRows as $modifierGroup)
                <div wire:key="modifier-group-{{ $modifierGroup['id'] }}" class="px-4 py-4">
                    @if ($editingModifierGroupId === $modifierGroup['id'])
                        <form wire:submit="updateModifierGroup" class="grid gap-3 md:grid-cols-[1fr_100px_100px_120px_auto] md:items-end">
                            <flux:input wire:model="editingModifierGroupName" :label="__('reports.csv.name')" type="text" required maxlength="160" />
                            <flux:input wire:model="editingModifierGroupMinSelect" :label="__('ui.organizations.brands.branches.menu.index.min')" type="number" required min="0" max="50" />
                            <flux:input wire:model="editingModifierGroupMaxSelect" :label="__('ui.organizations.brands.branches.menu.index.max')" type="number" required min="0" max="50" />
                            <flux:input wire:model="editingModifierGroupSortOrder" :label="__('ui.departments.dashboard.sort')" type="number" required min="0" max="9999" />

                            <div class="flex flex-wrap items-center gap-2">
                                <flux:switch wire:model="editingModifierGroupIsRequired" :label="__('guest.cart.required')" />
                                <flux:button icon="check" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="updateModifierGroup">
                                    {{ __('ui.actions.save') }}
                                </flux:button>
                                <flux:button icon="x-mark" type="button" wire:click="cancelModifierGroupEditing">
                                    {{ __('ui.actions.cancel') }}
                                </flux:button>
                            </div>
                        </form>
                    @else
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="truncate text-base font-semibold text-zinc-950 dark:text-white">{{ $modifierGroup['name'] }}</h2>
                                    @if ($modifierGroup['is_required'])
                                        <flux:badge color="amber">{{ __('guest.cart.required') }}</flux:badge>
                                    @else
                                        <flux:badge color="zinc">{{ __('guest.cart.optional') }}</flux:badge>
                                    @endif
                                    <flux:badge>{{ __('ui.organizations.brands.branches.menu.index.select') }} {{ $modifierGroup['min_select'] }}–{{ $modifierGroup['max_select'] }}</flux:badge>
                                    <flux:badge>{{ trans_choice('ui.organizations.brands.branches.menu.index.dish_dishes', $modifierGroup['items_count'], ['count' => $modifierGroup['items_count']]) }}</flux:badge>
                                </div>
                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('ui.departments.dashboard.sort') }} {{ $modifierGroup['sort_order'] }}</p>
                            </div>

                            <div class="flex flex-wrap gap-2 md:justify-end">
                                <flux:button icon="pencil" type="button" wire:click="startEditingModifierGroup({{ $modifierGroup['id'] }})">
                                    {{ __('guest.cart.edit_item') }}
                                </flux:button>
                                <flux:button icon="trash" type="button" variant="danger" wire:click="deleteModifierGroup({{ $modifierGroup['id'] }})">
                                    {{ __('ui.actions.delete') }}
                                </flux:button>
                            </div>
                        </div>
                    @endif

                    <div class="mt-4 grid gap-2">
                        @forelse ($modifierGroup['options'] as $modifierOption)
                            <div wire:key="modifier-option-{{ $modifierOption['id'] }}" class="rounded-md border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-950/60">
                                @if ($editingModifierOptionId === $modifierOption['id'])
                                    <form wire:submit="updateModifierOption" class="grid gap-3 md:grid-cols-[1fr_140px_120px_auto] md:items-end">
                                        <flux:input wire:model="editingModifierOptionName" :label="__('reports.csv.name')" type="text" required maxlength="160" />

                                        @if ($canChangePrices)
                                            <flux:input wire:model="editingModifierOptionPriceDelta" :label="__('ui.organizations.brands.branches.menu.index.price_change')" type="number" required min="-999999.99" max="999999.99" step="0.01" />
                                        @endif

                                        <flux:input wire:model="editingModifierOptionSortOrder" :label="__('ui.departments.dashboard.sort')" type="number" required min="0" max="9999" />

                                        <div class="flex flex-wrap items-center gap-2">
                                            @if ($canChangeAvailability)
                                                <flux:switch wire:model="editingModifierOptionIsAvailable" :label="__('menu.guest.available')" />
                                            @endif
                                            <flux:button icon="check" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="updateModifierOption">
                                                {{ __('ui.actions.save') }}
                                            </flux:button>
                                            <flux:button icon="x-mark" type="button" wire:click="cancelModifierOptionEditing">
                                                {{ __('ui.actions.cancel') }}
                                            </flux:button>
                                        </div>
                                    </form>
                                @else
                                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-medium text-zinc-950 dark:text-white">{{ $modifierOption['name'] }}</span>
                                            <flux:badge>{{ $modifierOption['formatted_price_delta'] }}</flux:badge>
                                            @if ($modifierOption['is_available'])
                                                <flux:badge color="green">{{ __('menu.guest.available') }}</flux:badge>
                                            @else
                                                <flux:badge color="zinc">{{ __('menu.guest.unavailable') }}</flux:badge>
                                            @endif
                                            <flux:badge>{{ __('ui.departments.dashboard.sort') }} {{ $modifierOption['sort_order'] }}</flux:badge>
                                        </div>

                                        <div class="flex flex-wrap gap-2 md:justify-end">
                                            <flux:button icon="pencil" type="button" wire:click="startEditingModifierOption({{ $modifierOption['id'] }})">
                                                {{ __('guest.cart.edit_item') }}
                                            </flux:button>
                                            <flux:button icon="trash" type="button" variant="danger" wire:click="deleteModifierOption({{ $modifierOption['id'] }})">
                                                {{ __('ui.actions.delete') }}
                                            </flux:button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('menu.empty.no_options') }}</p>
                        @endforelse
                    </div>
                </div>
            @empty
                <div class="px-4 py-8 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('menu.empty.no_modifier_groups') }}
                </div>
            @endforelse
        </div>
    </div>
    @endif
</section>
