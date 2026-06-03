<section data-page="branch-service-points" class="flex h-full w-full flex-1 flex-col gap-6">
    <header class="flex flex-col gap-3">
        <flux:button icon="arrow-left" :href="route('organizations.brands.branches.index', [$organization, $brand])" wire:navigate>
            {{ __('Branches') }}
        </flux:button>

        <div class="flex flex-col gap-1">
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $organization->name }} / {{ $brand->name }} / {{ $branch->name }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('Service points') }}</h1>
        </div>
    </header>

    <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex flex-wrap gap-2">
            @foreach ($this->quickCreateOptions as $option)
                <flux:button
                    wire:key="service-point-preset-{{ $option['type'] }}"
                    :icon="$option['icon']"
                    type="button"
                    wire:click="prepareCreate('{{ $option['type'] }}')"
                >
                    {{ $option['label'] }}
                </flux:button>
            @endforeach
        </div>

        <form wire:submit="create" class="mt-4 grid gap-4 md:grid-cols-2">
            <flux:input wire:model="name" :label="__('Name')" type="text" required maxlength="160" />
            <flux:input wire:model="displayNumber" :label="__('Number')" type="text" maxlength="80" />

            <flux:select wire:model="type" :label="__('Type')">
                @foreach ($this->servicePointTypeOptions as $value => $label)
                    <flux:select.option wire:key="service-point-type-{{ $value }}" value="{{ $value }}">{{ __($label) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="areaNodeId" :label="__('Zone')">
                @foreach ($this->areaOptions as $option)
                    <flux:select.option wire:key="service-point-area-create-{{ $option['value'] === '' ? 'none' : $option['value'] }}" value="{{ $option['value'] }}">
                        {{ $option['label'] }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="icon" :label="__('Icon')">
                @foreach ($this->iconOptions as $value => $label)
                    <flux:select.option wire:key="service-point-icon-{{ $value }}" value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="capacity" :label="__('Capacity')" type="number" required min="1" max="999" />

            <div class="flex items-end justify-between gap-4 md:col-span-2">
                <flux:switch wire:model="isActive" :label="__('Active')" />

                <flux:button icon="plus" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="create">
                    {{ __('Create') }}
                </flux:button>
            </div>
        </form>
    </div>

    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <flux:heading size="lg">{{ __('Service points in this branch') }}</flux:heading>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @forelse ($this->servicePoints as $servicePoint)
                <div wire:key="service-point-{{ $servicePoint->id }}" class="grid gap-4 px-4 py-4 md:grid-cols-[1fr_auto] md:items-center">
                    @if ($editingServicePointId === $servicePoint->id)
                        <form wire:submit="update" class="grid gap-3 md:col-span-2 md:grid-cols-2">
                            <flux:input wire:model="editingName" :label="__('Name')" type="text" required maxlength="160" />
                            <flux:input wire:model="editingDisplayNumber" :label="__('Number')" type="text" maxlength="80" />

                            <flux:select wire:model="editingType" :label="__('Type')">
                                @foreach ($this->servicePointTypeOptions as $value => $label)
                                    <flux:select.option wire:key="editing-service-point-type-{{ $servicePoint->id }}-{{ $value }}" value="{{ $value }}">{{ __($label) }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="editingAreaNodeId" :label="__('Zone')">
                                @foreach ($this->areaOptions as $option)
                                    <flux:select.option wire:key="editing-service-point-area-{{ $servicePoint->id }}-{{ $option['value'] === '' ? 'none' : $option['value'] }}" value="{{ $option['value'] }}">
                                        {{ $option['label'] }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="editingIcon" :label="__('Icon')">
                                @foreach ($this->iconOptions as $value => $label)
                                    <flux:select.option wire:key="editing-service-point-icon-{{ $servicePoint->id }}-{{ $value }}" value="{{ $value }}">{{ $label }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:input wire:model="editingCapacity" :label="__('Capacity')" type="number" required min="1" max="999" />

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
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="truncate text-base font-semibold text-zinc-950 dark:text-white">{{ $servicePoint->name }}</h2>

                                <flux:badge :icon="$servicePoint->icon ?? 'bookmark'">{{ __($servicePoint->type->label()) }}</flux:badge>

                                @if ($servicePoint->is_active)
                                    <flux:badge color="green">{{ __('Active') }}</flux:badge>
                                @else
                                    <flux:badge color="zinc">{{ __('Inactive') }}</flux:badge>
                                @endif
                            </div>

                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Number') }}: {{ $servicePoint->display_number ?: __('Not set') }}
                            </p>

                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Zone') }}: {{ $servicePoint->areaNode?->name ?? __('No zone') }} / {{ __('Capacity') }}: {{ $servicePoint->capacity }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2 md:justify-end">
                            @if ($servicePoint->is_active)
                                <flux:button icon="eye-slash" type="button" wire:click="disable({{ $servicePoint->id }})">
                                    {{ __('Disable') }}
                                </flux:button>
                            @else
                                <flux:button icon="eye" type="button" wire:click="enable({{ $servicePoint->id }})">
                                    {{ __('Enable') }}
                                </flux:button>
                            @endif

                            <flux:button icon="pencil" type="button" wire:click="startEditing({{ $servicePoint->id }})">
                                {{ __('Edit') }}
                            </flux:button>
                        </div>
                    @endif
                </div>
            @empty
                <div class="px-4 py-8 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('No service points yet.') }}
                </div>
            @endforelse
        </div>
    </div>
</section>
