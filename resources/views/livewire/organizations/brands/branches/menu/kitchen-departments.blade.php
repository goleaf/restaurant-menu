<div class="grid gap-4">
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
                <div class="flex items-end"><flux:switch wire:model="departmentIsActive" :label="__('qr.status.active')" /></div>
            </div>
        </div>
    </form>

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
</div>
