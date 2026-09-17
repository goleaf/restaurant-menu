<p class="mt-3 text-sm text-text-muted">{{ __('staff.workspace.area_help') }}</p>
                <p class="mt-2 font-medium">{{ $coverageLabel }}</p>
                <form wire:submit="previewAreaAssignments" novalidate class="mt-4 space-y-4">
                    <flux:input wire:model.live.debounce.300ms="assignmentForm.search" name="assignmentForm.search" :label="__('staff.workspace.area_search')" type="search" maxlength="120" />
                    @forelse ($areaEditor['unavailable'] as $area)
                        <label wire:key="unavailable-area-{{ $area['id'] }}" class="flex min-h-touch items-center gap-3 rounded-control border border-warning-border p-3">
                            <input type="checkbox" wire:model="assignmentForm.areaIds" value="{{ $area['id'] }}" class="size-5 shrink-0" />
                            <span>{{ $area['label'] }} · {{ __('staff.workspace.unavailable_area') }}</span>
                        </label>
                    @empty
                    @endforelse
                    <div class="grid gap-4 lg:grid-cols-2">
                        @forelse ($areaEditor['groups'] as $group)
                            <fieldset class="min-w-0 rounded-control border border-border-subtle p-3">
                                <legend class="px-1 text-sm font-semibold">{{ $group['label'] }}</legend>
                                @forelse ($group['areas'] as $area)
                                    <label wire:key="area-{{ $area['id'] }}" class="flex min-h-touch items-center gap-3 py-2">
                                        <input type="checkbox" wire:model="assignmentForm.areaIds" value="{{ $area['id'] }}" class="size-5 shrink-0" />
                                        <span class="text-sm">{{ $area['name'] }}</span>
                                    </label>
                                @empty
                                @endforelse
                            </fieldset>
                        @empty
                            <p class="text-sm text-text-muted">{{ __('staff.workspace.no_results') }}</p>
                        @endforelse
                    </div>
                    <div>{{ $areaEditor['paginator']->links() }}</div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <section aria-label="{{ __('staff.workspace.current_areas') }}" class="min-w-0">
                            <h3 class="text-sm font-semibold">{{ __('staff.workspace.current_areas') }}</h3>
                            <ul class="mt-2 space-y-1 text-sm text-text-muted">
                                @forelse ($areaEditor['current'] as $area)
                                    <li wire:key="current-area-{{ $area['id'] }}" class="break-words">{{ $area['label'] }} @if (!$area['available']) · {{ __('staff.workspace.unavailable_area') }} @endif</li>
                                @empty
                                    <li>{{ __('staff.workspace.coverage_all') }}</li>
                                @endforelse
                            </ul>
                        </section>
                        <section aria-label="{{ __('staff.workspace.selected_areas') }}" class="min-w-0">
                            <h3 class="text-sm font-semibold">{{ __('staff.workspace.selected_areas') }}</h3>
                            <ul class="mt-2 space-y-1 text-sm text-text-muted">
                                @forelse ($areaEditor['selected'] as $area)
                                    <li wire:key="selected-area-{{ $area['id'] }}" class="break-words">{{ $area['label'] }}</li>
                                @empty
                                    <li>{{ __('staff.workspace.coverage_all') }}</li>
                                @endforelse
                            </ul>
                        </section>
                    </div>
                    @if ($previewFingerprint !== '')
                        <div class="grid gap-4 border-t border-border-subtle pt-4 sm:grid-cols-2">
                            <section class="min-w-0">
                                <h3 class="text-sm font-semibold">{{ __('staff.workspace.added_areas') }} ({{ $areasAdded }})</h3>
                                <ul class="mt-2 space-y-1 text-sm">
                                    @forelse ($areaEditor['added'] as $area)
                                        <li wire:key="added-area-{{ $area['id'] }}" class="break-words">{{ $area['label'] }}</li>
                                    @empty
                                        <li class="text-text-muted">{{ __('staff.workspace.no_area_changes') }}</li>
                                    @endforelse
                                </ul>
                            </section>
                            <section class="min-w-0">
                                <h3 class="text-sm font-semibold">{{ __('staff.workspace.removed_areas') }} ({{ $areasRemoved }})</h3>
                                <ul class="mt-2 space-y-1 text-sm">
                                    @forelse ($areaEditor['removed'] as $area)
                                        <li wire:key="removed-area-{{ $area['id'] }}" class="break-words">{{ $area['label'] }}</li>
                                    @empty
                                        <li class="text-text-muted">{{ __('staff.workspace.no_area_changes') }}</li>
                                    @endforelse
                                </ul>
                            </section>
                        </div>
                    @endif
                    <div class="flex flex-wrap gap-2">
                        <flux:button type="submit" wire:loading.attr="disabled" wire:target="previewAreaAssignments" x-bind:disabled="!online" class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2">{{ __('staff.workspace.preview_change') }}</flux:button>
                        @if ($previewFingerprint !== '')
                            <flux:button variant="primary" wire:click="saveAreaAssignments" wire:loading.attr="disabled" wire:target="saveAreaAssignments" x-bind:disabled="!online" class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2">{{ __('staff.save_zones') }}</flux:button>
                        @endif
                        <flux:button x-on:click="requestNavigation(() => $wire.refreshAssignments(false), false)" wire:loading.attr="disabled" x-bind:disabled="!online" class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2">{{ __('staff.workspace.refresh_assignments') }}</flux:button>
                        <flux:button wire:click="refreshAssignments(true)" wire:loading.attr="disabled" x-bind:disabled="!online" class="h-auto! min-h-touch whitespace-normal! rounded-control! font-semibold! py-2">{{ __('staff.workspace.reapply_assignments') }}</flux:button>
                    </div>
                </form>
