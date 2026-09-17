<section class="rm-availability" data-page="availability-workspace" x-data="availabilityWorkspace">
    @pushOnce('page-scripts', 'availability-styles')
        @vite('resources/scss/availability.scss')
    @endPushOnce
    <x-ui.page-header :title="__('availability.title')" :description="$contextDescription" />
    <flux:callout icon="clock" :heading="$timezoneLabel" :text="__('availability.existing_orders_unchanged')" />
    <details class="rm-availability__section">
        <summary>{{ __('availability.evaluate_title') }}</summary>
        <form wire:submit="evaluate" novalidate class="rm-availability__filters">
            <flux:select wire:model="evaluation.mode" :label="__('availability.evaluate_mode')">
                <flux:select.option value="current">{{ __('availability.evaluate.current') }}</flux:select.option>
                <flux:select.option value="future">{{ __('availability.evaluate.future') }}</flux:select.option>
            </flux:select>
            <flux:date-picker :placeholder="__('availability.choose_date')" wire:model="evaluation.date" :label="__('availability.exception_date')" :locale="$locale" />
            <flux:time-picker :placeholder="__('availability.choose_time')" wire:model="evaluation.time" :label="__('availability.until_time')" :locale="$locale" time-format="24-hour" />
            <flux:button type="submit" wire:offline.attr="disabled">{{ __('availability.evaluate') }}</flux:button>
        </form>
        <flux:text>{{ __('availability.evaluate_description') }}</flux:text>
    </details>
    @if ($evaluationAt)
        <flux:callout :heading="__('availability.future_preview')" :text="$evaluationLabel" />
    @endif
    @if ($errors->any())
        <flux:callout variant="danger" :heading="__('availability.validation_title')" role="alert" tabindex="-1">
            @forelse ($errors->all() as $message)<p>{{ $message }}</p>@empty @endforelse
        </flux:callout>
    @endif
    <nav class="rm-availability__nav" aria-label="{{ __('availability.sections') }}">
        @forelse ($sections as $link)
            <flux:button :href="$link['url']" :variant="$section === $link['key'] ? 'primary' : 'ghost'" :aria-current="$section === $link['key'] ? 'page' : null" :data-availability-section="$link['key']" :data-menu-section="$link['key']" x-on:click="navigateSection($event, $event.currentTarget.dataset.availabilitySection)">{{ $link['label'] }}</flux:button>
        @empty
        @endforelse
    </nav>
    <flux:callout wire:offline variant="warning" :heading="__('availability.offline')" :text="__('availability.offline_description')" />
    <div data-menu-workspace-content data-availability-content>
        @if ($section === 'now')
            @include('livewire.organizations.brands.branches.availability.now')
        @elseif ($section === 'schedules')
            @include('livewire.organizations.brands.branches.availability.schedules')
        @elseif ($section === 'stoplist')
            @include('livewire.organizations.brands.branches.availability.stoplist')
        @endif
    </div>
    <flux:modal name="availability-unsaved" class="md:w-96">
        <flux:heading>{{ __('availability.unsaved_title') }}</flux:heading>
        <flux:text>{{ __('availability.unsaved_description') }}</flux:text>
        <div class="rm-availability__actions">
            <flux:button x-on:click="cancelNavigation">{{ __('availability.stay') }}</flux:button>
            <flux:button variant="danger" x-on:click="discardAndNavigate">{{ __('availability.discard_leave') }}</flux:button>
        </div>
    </flux:modal>
</section>
