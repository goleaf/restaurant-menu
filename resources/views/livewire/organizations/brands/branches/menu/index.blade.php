<section data-page="branch-menu" class="flex h-full w-full flex-1 flex-col gap-6">
    <header class="flex flex-col gap-3">
        <flux:button icon="arrow-left" :href="route('organizations.brands.branches.index', [$organization, $brand])" wire:navigate>
            {{ __('Branches') }}
        </flux:button>

        <div class="flex flex-col gap-1">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $organization->name }} / {{ $brand->name }} / {{ $branch->name }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('Menu') }}</h1>
        </div>
    </header>

    <div class="grid gap-4 xl:grid-cols-4">
        <form wire:submit="createMenu" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between gap-3">
                <flux:heading size="lg">{{ __('New menu') }}</flux:heading>
                <flux:button icon="plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createMenu">
                    {{ __('Create') }}
                </flux:button>
            </div>

            <div class="mt-4 grid gap-3">
                <flux:input wire:model="menuName" :label="__('Name')" type="text" required maxlength="160" />

                <flux:select wire:model="menuStatus" :label="__('Status')">
                    @foreach ($this->menuStatusOptions as $value => $label)
                        <flux:select.option wire:key="menu-status-create-{{ $value }}" value="{{ $value }}">{{ __($label) }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="menuSortOrder" :label="__('Sort')" type="number" required min="0" max="9999" />
            </div>
        </form>

        <form wire:submit="createCategory" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between gap-3">
                <flux:heading size="lg">{{ __('New category') }}</flux:heading>
                <flux:button icon="plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createCategory">
                    {{ __('Create') }}
                </flux:button>
            </div>

            <div class="mt-4 grid gap-3">
                <flux:select wire:model.live="categoryMenuId" :label="__('Menu')">
                    @forelse ($this->menuOptions() as $option)
                        <flux:select.option wire:key="category-menu-create-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                    @empty
                        <flux:select.option value="">{{ __('Create a menu first') }}</flux:select.option>
                    @endforelse
                </flux:select>

                <flux:select wire:model="categoryParentId" :label="__('Parent category')">
                    <flux:select.option value="">{{ __('Top level') }}</flux:select.option>
                    @foreach ($this->categoryOptionsForMenu($categoryMenuId) as $option)
                        <flux:select.option wire:key="category-parent-create-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="categoryName" :label="__('Name')" type="text" required maxlength="160" />

                <label class="grid gap-1 text-sm">
                    <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('Description') }}</span>
                    <textarea wire:model="categoryDescription" rows="2" maxlength="1000" class="rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-400 focus:outline-hidden focus:ring-2 focus:ring-zinc-200 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-600 dark:focus:ring-zinc-800"></textarea>
                </label>

                <flux:select wire:model="categoryIcon" :label="__('Icon')">
                    @foreach ($this->iconOptions as $value => $label)
                        <flux:select.option wire:key="category-icon-create-{{ $value }}" value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:input wire:model="categorySortOrder" :label="__('Sort')" type="number" required min="0" max="9999" />
                    <div class="flex items-end">
                        <flux:switch wire:model="categoryIsActive" :label="__('Active')" />
                    </div>
                </div>
            </div>
        </form>

        <form wire:submit="createItem" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between gap-3">
                <flux:heading size="lg">{{ __('New dish') }}</flux:heading>
                <flux:button icon="plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createItem">
                    {{ __('Create') }}
                </flux:button>
            </div>

            <div class="mt-4 grid gap-3">
                <flux:select wire:model.live="itemMenuId" :label="__('Menu')">
                    @forelse ($this->menuOptions() as $option)
                        <flux:select.option wire:key="item-menu-create-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                    @empty
                        <flux:select.option value="">{{ __('Create a menu first') }}</flux:select.option>
                    @endforelse
                </flux:select>

                <flux:select wire:model="itemCategoryId" :label="__('Category')">
                    @forelse ($this->categoryOptionsForMenu($itemMenuId, false) as $option)
                        <flux:select.option wire:key="item-category-create-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                    @empty
                        <flux:select.option value="">{{ __('Create an active category first') }}</flux:select.option>
                    @endforelse
                </flux:select>

                <flux:input wire:model="itemName" :label="__('Name')" type="text" required maxlength="180" />

                <label class="grid gap-1 text-sm">
                    <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('Description') }}</span>
                    <textarea wire:model="itemDescription" rows="2" maxlength="1200" class="rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-400 focus:outline-hidden focus:ring-2 focus:ring-zinc-200 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-600 dark:focus:ring-zinc-800"></textarea>
                </label>

                <div class="grid gap-3 sm:grid-cols-2">
                    @if ($canChangePrices)
                        <flux:input wire:model="itemPrice" :label="__('Price')" type="number" required min="0" max="999999.99" step="0.01" />
                    @endif

                    <flux:input wire:model="itemSortOrder" :label="__('Sort')" type="number" required min="0" max="9999" />
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <flux:input wire:model="itemWeight" :label="__('Weight')" type="number" min="0" step="0.01" />
                    <flux:input wire:model="itemVolume" :label="__('Volume')" type="number" min="0" step="0.01" />
                    <flux:input wire:model="itemCalories" :label="__('Calories')" type="number" min="0" max="999999" />
                </div>

                @if ($canChangeAvailability)
                    <flux:switch wire:model="itemIsAvailable" :label="__('Available')" />
                @endif
            </div>
        </form>

        <form wire:submit="createModifierGroup" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between gap-3">
                <flux:heading size="lg">{{ __('New modifier') }}</flux:heading>
                <flux:button icon="plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createModifierGroup">
                    {{ __('Create') }}
                </flux:button>
            </div>

            <div class="mt-4 grid gap-3">
                <flux:input wire:model="modifierGroupName" :label="__('Name')" type="text" required maxlength="160" />

                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:input wire:model="modifierGroupMinSelect" :label="__('Min')" type="number" required min="0" max="50" />
                    <flux:input wire:model="modifierGroupMaxSelect" :label="__('Max')" type="number" required min="0" max="50" />
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:input wire:model="modifierGroupSortOrder" :label="__('Sort')" type="number" required min="0" max="9999" />
                    <div class="flex items-end">
                        <flux:switch wire:model="modifierGroupIsRequired" :label="__('Required')" />
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <flux:heading size="lg">{{ __('Menus in this branch') }}</flux:heading>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @forelse ($this->menus as $menu)
                <div wire:key="menu-{{ $menu->id }}" class="grid gap-4 px-4 py-4">
                    @if ($editingMenuId === $menu->id)
                        <form wire:submit="updateMenu" class="grid gap-3 md:grid-cols-[1fr_180px_120px_auto] md:items-end">
                            <flux:input wire:model="editingMenuName" :label="__('Name')" type="text" required maxlength="160" />

                            <flux:select wire:model="editingMenuStatus" :label="__('Status')">
                                @foreach ($this->menuStatusOptions as $value => $label)
                                    <flux:select.option wire:key="menu-status-edit-{{ $menu->id }}-{{ $value }}" value="{{ $value }}">{{ __($label) }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:input wire:model="editingMenuSortOrder" :label="__('Sort')" type="number" required min="0" max="9999" />

                            <div class="flex flex-wrap gap-2">
                                <flux:button icon="check" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="updateMenu">
                                    {{ __('Save') }}
                                </flux:button>

                                <flux:button icon="x-mark" type="button" wire:click="cancelMenuEditing">
                                    {{ __('Cancel') }}
                                </flux:button>
                            </div>
                        </form>
                    @else
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="truncate text-base font-semibold text-zinc-950 dark:text-white">{{ $menu->name }}</h2>
                                    <flux:badge :color="$menu->status->badgeColor()">{{ __($menu->status->label()) }}</flux:badge>
                                    <flux:badge>{{ __('Sort') }} {{ $menu->sort_order }}</flux:badge>
                                </div>

                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ trans_choice(':count category|:count categories', $menu->categories_count, ['count' => $menu->categories_count]) }}
                                    /
                                    {{ trans_choice(':count dish|:count dishes', $menu->items_count, ['count' => $menu->items_count]) }}
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2 md:justify-end">
                                <flux:button icon="pencil" type="button" wire:click="startEditingMenu({{ $menu->id }})">
                                    {{ __('Edit menu') }}
                                </flux:button>

                                <flux:button icon="trash" type="button" variant="danger" wire:click="deleteMenu({{ $menu->id }})">
                                    {{ __('Delete menu') }}
                                </flux:button>
                            </div>
                        </div>
                    @endif

                    <div class="grid gap-4 lg:grid-cols-[minmax(0,320px)_1fr]">
                        <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950/60">
                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Categories') }}</p>

                            <div class="mt-3 space-y-2">
                                @forelse ($menu->categories as $category)
                                    <div wire:key="menu-category-{{ $category->id }}" class="rounded-md border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
                                        @if ($editingCategoryId === $category->id)
                                            <form wire:submit="updateCategory" class="grid gap-3">
                                                <flux:input wire:model="editingCategoryName" :label="__('Name')" type="text" required maxlength="160" />

                                                <label class="grid gap-1 text-sm">
                                                    <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('Description') }}</span>
                                                    <textarea wire:model="editingCategoryDescription" rows="2" maxlength="1000" class="rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-400 focus:outline-hidden focus:ring-2 focus:ring-zinc-200 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-600 dark:focus:ring-zinc-800"></textarea>
                                                </label>

                                                <div class="grid gap-3 sm:grid-cols-2">
                                                    <flux:select wire:model="editingCategoryIcon" :label="__('Icon')">
                                                        @foreach ($this->iconOptions as $value => $label)
                                                            <flux:select.option wire:key="category-icon-edit-{{ $category->id }}-{{ $value }}" value="{{ $value }}">{{ $label }}</flux:select.option>
                                                        @endforeach
                                                    </flux:select>

                                                    <flux:input wire:model="editingCategorySortOrder" :label="__('Sort')" type="number" required min="0" max="9999" />
                                                </div>

                                                <div class="flex items-center justify-between gap-3">
                                                    <flux:switch wire:model="editingCategoryIsActive" :label="__('Active')" />

                                                    <div class="flex flex-wrap gap-2">
                                                        <flux:button icon="check" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="updateCategory">
                                                            {{ __('Save') }}
                                                        </flux:button>

                                                        <flux:button icon="x-mark" type="button" wire:click="cancelCategoryEditing">
                                                            {{ __('Cancel') }}
                                                        </flux:button>
                                                    </div>
                                                </div>
                                            </form>
                                        @else
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <flux:badge :icon="$category->icon ?? 'bookmark'">{{ $category->name }}</flux:badge>

                                                        @if ($category->is_active)
                                                            <flux:badge color="green">{{ __('Active') }}</flux:badge>
                                                        @else
                                                            <flux:badge color="zinc">{{ __('Inactive') }}</flux:badge>
                                                        @endif
                                                    </div>

                                                    @if ($category->description)
                                                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ $category->description }}</p>
                                                    @endif

                                                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Sort') }} {{ $category->sort_order }}</p>
                                                </div>

                                                <div class="flex flex-wrap gap-2">
                                                    <flux:button icon="pencil" type="button" wire:click="startEditingCategory({{ $category->id }})">
                                                        {{ __('Edit') }}
                                                    </flux:button>

                                                    <flux:button icon="trash" type="button" variant="danger" wire:click="deleteCategory({{ $category->id }})">
                                                        {{ __('Delete') }}
                                                    </flux:button>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No categories yet.') }}</p>
                                @endforelse
                            </div>
                        </div>

                        <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950/60">
                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Dishes') }}</p>

                            <div class="mt-3 space-y-3">
                                @forelse ($menu->items as $item)
                                    <div wire:key="menu-item-{{ $item->id }}" class="rounded-md border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
                                        @if ($editingItemId === $item->id)
                                            <form wire:submit="updateItem" class="grid gap-3">
                                                <div class="grid gap-3 md:grid-cols-2">
                                                    <flux:select wire:model.live="editingItemMenuId" :label="__('Menu')">
                                                        @foreach ($this->menuOptions() as $option)
                                                            <flux:select.option wire:key="item-menu-edit-{{ $item->id }}-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                                                        @endforeach
                                                    </flux:select>

                                                    <flux:select wire:model="editingItemCategoryId" :label="__('Category')">
                                                        @foreach ($this->categoryOptionsForMenu($editingItemMenuId, false) as $option)
                                                            <flux:select.option wire:key="item-category-edit-{{ $item->id }}-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                                                        @endforeach
                                                    </flux:select>
                                                </div>

                                                <flux:input wire:model="editingItemName" :label="__('Name')" type="text" required maxlength="180" />

                                                <label class="grid gap-1 text-sm">
                                                    <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('Description') }}</span>
                                                    <textarea wire:model="editingItemDescription" rows="2" maxlength="1200" class="rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-400 focus:outline-hidden focus:ring-2 focus:ring-zinc-200 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:border-zinc-600 dark:focus:ring-zinc-800"></textarea>
                                                </label>

                                                <div class="grid gap-3 md:grid-cols-4">
                                                    @if ($canChangePrices)
                                                        <flux:input wire:model="editingItemPrice" :label="__('Price')" type="number" required min="0" max="999999.99" step="0.01" />
                                                    @endif

                                                    <flux:input wire:model="editingItemWeight" :label="__('Weight')" type="number" min="0" step="0.01" />
                                                    <flux:input wire:model="editingItemVolume" :label="__('Volume')" type="number" min="0" step="0.01" />
                                                    <flux:input wire:model="editingItemCalories" :label="__('Calories')" type="number" min="0" max="999999" />
                                                    <flux:input wire:model="editingItemSortOrder" :label="__('Sort')" type="number" required min="0" max="9999" />
                                                </div>

                                                <div class="flex items-center justify-between gap-3">
                                                    @if ($canChangeAvailability)
                                                        <flux:switch wire:model="editingItemIsAvailable" :label="__('Available')" />
                                                    @endif

                                                    <div class="flex flex-wrap gap-2">
                                                        <flux:button icon="check" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="updateItem">
                                                            {{ __('Save') }}
                                                        </flux:button>

                                                        <flux:button icon="x-mark" type="button" wire:click="cancelItemEditing">
                                                            {{ __('Cancel') }}
                                                        </flux:button>
                                                    </div>
                                                </div>
                                            </form>
                                        @else
                                            <div class="grid gap-3 md:grid-cols-[64px_1fr_auto] md:items-start">
                                                <div class="flex size-16 items-center justify-center overflow-hidden rounded-md border border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950">
                                                    @if ($item->imageUrl())
                                                        <img src="{{ $item->imageUrl() }}" alt="{{ $item->name }}" class="size-full object-cover">
                                                    @else
                                                        <span class="text-xs font-medium text-zinc-400">{{ __('Photo') }}</span>
                                                    @endif
                                                </div>

                                                <div class="min-w-0">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <h3 class="truncate text-base font-semibold text-zinc-950 dark:text-white">{{ $item->name }}</h3>
                                                        <flux:badge>{{ $item->category?->name ?? __('No category') }}</flux:badge>

                                                        @if ($item->is_available)
                                                            <flux:badge color="green">{{ __('Available') }}</flux:badge>
                                                        @else
                                                            <flux:badge color="zinc">{{ __('Unavailable') }}</flux:badge>
                                                        @endif
                                                    </div>

                                                    @if ($item->description)
                                                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $item->description }}</p>
                                                    @endif

                                                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                                                        {{ __('Price') }}: {{ $item->price }} {{ $branch->currency }} / {{ __('Sort') }} {{ $item->sort_order }}
                                                    </p>

                                                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                                        {{ __('Weight') }}: {{ $item->weight ?? '—' }}
                                                        /
                                                        {{ __('Volume') }}: {{ $item->volume ?? '—' }}
                                                        /
                                                        {{ __('Calories') }}: {{ $item->calories ?? '—' }}
                                                    </p>

                                                    @if ($item->modifierGroups->isNotEmpty())
                                                        <div class="mt-3 flex flex-wrap gap-2">
                                                            @foreach ($item->modifierGroups as $modifierGroup)
                                                                <span wire:key="item-{{ $item->id }}-modifier-{{ $modifierGroup->id }}" class="inline-flex items-center gap-1 rounded-md border border-zinc-200 bg-zinc-50 px-2 py-1 text-xs font-medium text-zinc-700 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-200">
                                                                    {{ $modifierGroup->name }}
                                                                    <button type="button" wire:click="detachModifierGroupFromItem({{ $item->id }}, {{ $modifierGroup->id }})" class="text-zinc-400 hover:text-red-600" aria-label="{{ __('Remove modifier group') }}">
                                                                        ×
                                                                    </button>
                                                                </span>
                                                            @endforeach
                                                        </div>
                                                    @endif

                                                    <form wire:submit="saveItemImage({{ $item->id }})" class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-start">
                                                        <label for="item-photo-{{ $item->id }}" class="sr-only">{{ __('Dish photo') }}</label>
                                                        <input id="item-photo-{{ $item->id }}" wire:model="itemImages.{{ $item->id }}" type="file" accept="image/png,image/jpeg,image/webp" class="block w-full max-w-xs rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-700 file:mr-3 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-1.5 file:text-sm file:font-medium dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-200 dark:file:bg-zinc-800">

                                                        <div class="flex flex-wrap gap-2">
                                                            <flux:button icon="arrow-up-tray" type="submit" wire:loading.attr="disabled" wire:target="itemImages.{{ $item->id }}, saveItemImage({{ $item->id }})">
                                                                {{ __('Upload photo') }}
                                                            </flux:button>

                                                            @if ($item->imageUrl())
                                                                <flux:button icon="trash" type="button" variant="danger" wire:click="removeItemImage({{ $item->id }})" wire:loading.attr="disabled" wire:target="removeItemImage({{ $item->id }})">
                                                                    {{ __('Remove photo') }}
                                                                </flux:button>
                                                            @endif
                                                        </div>

                                                        @error('itemImages.'.$item->id)
                                                            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                                        @enderror
                                                    </form>
                                                </div>

                                                <div class="flex flex-wrap gap-2 md:justify-end">
                                                    @if ($canChangeAvailability)
                                                        @if ($item->is_available)
                                                            <flux:button icon="eye-slash" type="button" wire:click="setItemAvailability({{ $item->id }}, false)">
                                                                {{ __('Disable') }}
                                                            </flux:button>
                                                        @else
                                                            <flux:button icon="eye" type="button" wire:click="setItemAvailability({{ $item->id }}, true)">
                                                                {{ __('Enable') }}
                                                            </flux:button>
                                                        @endif
                                                    @endif

                                                    <flux:button icon="pencil" type="button" wire:click="startEditingItem({{ $item->id }})">
                                                        {{ __('Edit') }}
                                                    </flux:button>

                                                    <flux:button icon="trash" type="button" variant="danger" wire:click="deleteItem({{ $item->id }})">
                                                        {{ __('Delete') }}
                                                    </flux:button>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No dishes yet.') }}</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-4 py-8 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('No menus yet.') }}
                </div>
            @endforelse
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <flux:heading size="lg">{{ __('Modifier groups') }}</flux:heading>
        </div>

        <div class="grid gap-4 border-b border-zinc-200 p-4 dark:border-zinc-800 lg:grid-cols-2">
            <form wire:submit="createModifierOption" class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950/60">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('New option') }}</p>
                    <flux:button icon="plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createModifierOption">
                        {{ __('Create') }}
                    </flux:button>
                </div>

                <div class="mt-3 grid gap-3">
                    <flux:select wire:model="modifierOptionGroupId" :label="__('Modifier group')">
                        @forelse ($this->modifierGroupOptions() as $option)
                            <flux:select.option wire:key="modifier-option-group-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                        @empty
                            <flux:select.option value="">{{ __('Create a modifier group first') }}</flux:select.option>
                        @endforelse
                    </flux:select>

                    <flux:input wire:model="modifierOptionName" :label="__('Name')" type="text" required maxlength="160" />

                    <div class="grid gap-3 sm:grid-cols-2">
                        @if ($canChangePrices)
                            <flux:input wire:model="modifierOptionPriceDelta" :label="__('Price change')" type="number" required min="-999999.99" max="999999.99" step="0.01" />
                        @endif

                        <flux:input wire:model="modifierOptionSortOrder" :label="__('Sort')" type="number" required min="0" max="9999" />
                    </div>

                    @if ($canChangeAvailability)
                        <flux:switch wire:model="modifierOptionIsAvailable" :label="__('Available')" />
                    @endif
                </div>
            </form>

            <form wire:submit="attachModifierGroupToItem" class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950/60">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Assign to dish') }}</p>
                    <flux:button icon="link" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="attachModifierGroupToItem">
                        {{ __('Assign') }}
                    </flux:button>
                </div>

                <div class="mt-3 grid gap-3">
                    <flux:select wire:model.live="modifierItemMenuId" :label="__('Menu')">
                        @forelse ($this->menuOptions() as $option)
                            <flux:select.option wire:key="modifier-item-menu-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                        @empty
                            <flux:select.option value="">{{ __('Create a menu first') }}</flux:select.option>
                        @endforelse
                    </flux:select>

                    <flux:select wire:model="modifierItemId" :label="__('Dish')">
                        @forelse ($this->itemOptionsForMenu($modifierItemMenuId) as $option)
                            <flux:select.option wire:key="modifier-item-dish-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                        @empty
                            <flux:select.option value="">{{ __('Create a dish first') }}</flux:select.option>
                        @endforelse
                    </flux:select>

                    <flux:select wire:model="modifierItemGroupId" :label="__('Modifier group')">
                        @forelse ($this->modifierGroupOptions() as $option)
                            <flux:select.option wire:key="modifier-item-group-{{ $option['value'] }}" value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                        @empty
                            <flux:select.option value="">{{ __('Create a modifier group first') }}</flux:select.option>
                        @endforelse
                    </flux:select>
                </div>
            </form>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @forelse ($this->modifierGroups as $modifierGroup)
                <div wire:key="modifier-group-{{ $modifierGroup->id }}" class="px-4 py-4">
                    @if ($editingModifierGroupId === $modifierGroup->id)
                        <form wire:submit="updateModifierGroup" class="grid gap-3 md:grid-cols-[1fr_100px_100px_120px_auto] md:items-end">
                            <flux:input wire:model="editingModifierGroupName" :label="__('Name')" type="text" required maxlength="160" />
                            <flux:input wire:model="editingModifierGroupMinSelect" :label="__('Min')" type="number" required min="0" max="50" />
                            <flux:input wire:model="editingModifierGroupMaxSelect" :label="__('Max')" type="number" required min="0" max="50" />
                            <flux:input wire:model="editingModifierGroupSortOrder" :label="__('Sort')" type="number" required min="0" max="9999" />

                            <div class="flex flex-wrap items-center gap-2">
                                <flux:switch wire:model="editingModifierGroupIsRequired" :label="__('Required')" />
                                <flux:button icon="check" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="updateModifierGroup">
                                    {{ __('Save') }}
                                </flux:button>
                                <flux:button icon="x-mark" type="button" wire:click="cancelModifierGroupEditing">
                                    {{ __('Cancel') }}
                                </flux:button>
                            </div>
                        </form>
                    @else
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="truncate text-base font-semibold text-zinc-950 dark:text-white">{{ $modifierGroup->name }}</h2>
                                    @if ($modifierGroup->is_required)
                                        <flux:badge color="amber">{{ __('Required') }}</flux:badge>
                                    @else
                                        <flux:badge color="zinc">{{ __('Optional') }}</flux:badge>
                                    @endif
                                    <flux:badge>{{ __('Select') }} {{ $modifierGroup->min_select }}–{{ $modifierGroup->max_select }}</flux:badge>
                                    <flux:badge>{{ trans_choice(':count dish|:count dishes', $modifierGroup->items_count, ['count' => $modifierGroup->items_count]) }}</flux:badge>
                                </div>
                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Sort') }} {{ $modifierGroup->sort_order }}</p>
                            </div>

                            <div class="flex flex-wrap gap-2 md:justify-end">
                                <flux:button icon="pencil" type="button" wire:click="startEditingModifierGroup({{ $modifierGroup->id }})">
                                    {{ __('Edit') }}
                                </flux:button>
                                <flux:button icon="trash" type="button" variant="danger" wire:click="deleteModifierGroup({{ $modifierGroup->id }})">
                                    {{ __('Delete') }}
                                </flux:button>
                            </div>
                        </div>
                    @endif

                    <div class="mt-4 grid gap-2">
                        @forelse ($modifierGroup->options as $modifierOption)
                            <div wire:key="modifier-option-{{ $modifierOption->id }}" class="rounded-md border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-950/60">
                                @if ($editingModifierOptionId === $modifierOption->id)
                                    <form wire:submit="updateModifierOption" class="grid gap-3 md:grid-cols-[1fr_140px_120px_auto] md:items-end">
                                        <flux:input wire:model="editingModifierOptionName" :label="__('Name')" type="text" required maxlength="160" />

                                        @if ($canChangePrices)
                                            <flux:input wire:model="editingModifierOptionPriceDelta" :label="__('Price change')" type="number" required min="-999999.99" max="999999.99" step="0.01" />
                                        @endif

                                        <flux:input wire:model="editingModifierOptionSortOrder" :label="__('Sort')" type="number" required min="0" max="9999" />

                                        <div class="flex flex-wrap items-center gap-2">
                                            @if ($canChangeAvailability)
                                                <flux:switch wire:model="editingModifierOptionIsAvailable" :label="__('Available')" />
                                            @endif
                                            <flux:button icon="check" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="updateModifierOption">
                                                {{ __('Save') }}
                                            </flux:button>
                                            <flux:button icon="x-mark" type="button" wire:click="cancelModifierOptionEditing">
                                                {{ __('Cancel') }}
                                            </flux:button>
                                        </div>
                                    </form>
                                @else
                                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-medium text-zinc-950 dark:text-white">{{ $modifierOption->name }}</span>
                                            <flux:badge>{{ $modifierOption->price_delta }} {{ $branch->currency }}</flux:badge>
                                            @if ($modifierOption->is_available)
                                                <flux:badge color="green">{{ __('Available') }}</flux:badge>
                                            @else
                                                <flux:badge color="zinc">{{ __('Unavailable') }}</flux:badge>
                                            @endif
                                            <flux:badge>{{ __('Sort') }} {{ $modifierOption->sort_order }}</flux:badge>
                                        </div>

                                        <div class="flex flex-wrap gap-2 md:justify-end">
                                            <flux:button icon="pencil" type="button" wire:click="startEditingModifierOption({{ $modifierOption->id }})">
                                                {{ __('Edit') }}
                                            </flux:button>
                                            <flux:button icon="trash" type="button" variant="danger" wire:click="deleteModifierOption({{ $modifierOption->id }})">
                                                {{ __('Delete') }}
                                            </flux:button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No options yet.') }}</p>
                        @endforelse
                    </div>
                </div>
            @empty
                <div class="px-4 py-8 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('No modifier groups yet.') }}
                </div>
            @endforelse
        </div>
    </div>
</section>
