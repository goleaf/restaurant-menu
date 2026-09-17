<section class="rm-availability__editor" data-availability-editor tabindex="-1" aria-labelledby="weekly-title" x-show="!locallyDiscarded">
    <flux:heading id="weekly-title" size="lg">{{ $editor === 'menu' ? __('availability.menu_schedule') : __('availability.branch_schedule') }}</flux:heading>
    @if ($editorMenuName && $editor === 'menu') <flux:heading>{{ $editorMenuName }}</flux:heading> @endif
    <form wire:submit="previewSchedule" novalidate class="rm-availability__section">
        <flux:select wire:model="weekly.mode" :label="__('availability.schedule_mode')">
            <flux:select.option value="unrestricted">{{ __('availability.schedule.unrestricted') }}</flux:select.option>
            <flux:select.option value="weekly">{{ __('availability.schedule.weekly') }}</flux:select.option>
            <flux:select.option value="closed">{{ __('availability.schedule.closed') }}</flux:select.option>
        </flux:select>
        <flux:text>{{ __('availability.schedule_modes_description') }}</flux:text>
        <flux:error name="weekly.openingHours" />
        @forelse ($weeklyDays as $day)
            <flux:card wire:key="availability-weekday-{{ $day['index'] }}" class="rm-availability__section">
                <flux:heading>{{ $day['label'] }}</flux:heading>
                <flux:checkbox wire:model="weekly.openingHours.{{ $day['index'] }}.is_closed" :label="__('availability.closed_day')" />
                @forelse ($day['intervals'] as $intervalIndex => $interval)
                    <div wire:key="availability-interval-{{ $day['index'] }}-{{ $intervalIndex }}" class="rm-availability__filters">
                        <flux:time-picker :placeholder="__('availability.choose_time')" wire:model="weekly.openingHours.{{ $day['index'] }}.intervals.{{ $intervalIndex }}.opens_at" :label="__('availability.opens')" :locale="$locale" time-format="24-hour" />
                        <flux:time-picker :placeholder="__('availability.choose_time')" wire:model="weekly.openingHours.{{ $day['index'] }}.intervals.{{ $intervalIndex }}.closes_at" :label="__('availability.closes')" :locale="$locale" time-format="24-hour" />
                        <flux:button type="button" wire:click="removeInterval({{ $day['index'] }}, {{ $intervalIndex }})" wire:offline.attr="disabled">{{ __('availability.remove_interval') }}</flux:button>
                    </div>
                @empty
                    <flux:text>{{ __('availability.no_intervals') }}</flux:text>
                @endforelse
                <flux:error name="weekly.openingHours.{{ $day['index'] }}.intervals" />
                @if ($day['can_add'])
                    <flux:button type="button" wire:click="addInterval({{ $day['index'] }})" icon="plus" wire:offline.attr="disabled">{{ __('availability.add_interval') }}</flux:button>
                @endif
            </flux:card>
        @empty
        @endforelse
        <flux:card class="rm-availability__section">
            <flux:heading>{{ __('availability.copy_days') }}</flux:heading>
            <flux:select wire:model="weekly.copyFrom" :label="__('availability.copy_from')">
                @forelse ($weeklyDays as $day)
                    <flux:select.option :value="$day['index']">{{ $day['label'] }}</flux:select.option>
                @empty
                @endforelse
            </flux:select>
            <flux:checkbox.group wire:model="weekly.copyTo" :label="__('availability.copy_to')">
                @forelse ($weeklyDays as $day)
                    <flux:checkbox :value="$day['index']" :label="$day['label']" />
                @empty
                @endforelse
            </flux:checkbox.group>
            <flux:error name="weekly.copyTo.*" />
            <flux:button type="button" wire:click="previewDayCopy" wire:offline.attr="disabled">{{ __('availability.preview_copy') }}</flux:button>
            @if ($dayCopyPreview)
                <div class="rm-availability__preview">
                    <flux:text>{{ __('availability.copy_replaces') }}</flux:text>
                    @forelse ($dayCopyPreview['rows'] as $row)
                        <flux:heading>{{ $row['label'] }}</flux:heading>
                        <div class="rm-availability__comparison">
                            <div><flux:heading>{{ __('availability.before') }}</flux:heading>@include('livewire.organizations.brands.branches.availability.day-summary', ['day' => $row['before']])</div>
                            <div><flux:heading>{{ __('availability.after') }}</flux:heading>@include('livewire.organizations.brands.branches.availability.day-summary', ['day' => $row['after']])</div>
                        </div>
                    @empty
                    @endforelse
                    <flux:button type="button" wire:click="copyDays" wire:offline.attr="disabled">{{ __('availability.copy_to_draft') }}</flux:button>
                </div>
            @endif
        </flux:card>
        <flux:text>{{ __('availability.overnight_description') }}</flux:text>
        <div class="rm-availability__actions">
            <flux:button type="submit" variant="primary" wire:offline.attr="disabled" wire:loading.attr="disabled">{{ __('availability.preview') }}</flux:button>
            <flux:button type="button" x-on:click="cancelDraft">{{ __('availability.cancel_draft') }}</flux:button>
        </div>
    </form>
    @if ($preview)
        @include('livewire.organizations.brands.branches.availability.preview', ['applyAction' => 'applySchedule'])
    @endif
</section>
