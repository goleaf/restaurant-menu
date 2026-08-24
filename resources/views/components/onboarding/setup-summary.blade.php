@props(['summary'])

<dl {{ $attributes->class(['grid min-w-0 gap-3 text-sm']) }}>
    <div class="min-w-0">
        <dt class="text-xs text-text-muted">{{ __('ui.livewire.onboarding.restaurantsetup.kompaniia') }}</dt>
        <dd class="mt-0.5 min-w-0 wrap-anywhere font-medium">{{ $summary['organization'] ?? __('ui.onboarding.restaurant_setup.net') }}</dd>
    </div>
    <div class="min-w-0">
        <dt class="text-xs text-text-muted">{{ __('ui.livewire.onboarding.restaurantsetup.restoran') }}</dt>
        <dd class="mt-0.5 min-w-0 wrap-anywhere font-medium">{{ $summary['brand'] ?? __('ui.onboarding.restaurant_setup.net') }}</dd>
    </div>
    <div class="min-w-0">
        <dt class="text-xs text-text-muted">{{ __('ui.onboarding.restaurant_setup.nazvanie_filiala') }}</dt>
        <dd class="mt-0.5 min-w-0 wrap-anywhere font-medium">{{ $summary['branch'] ?? __('ui.onboarding.restaurant_setup.net') }}</dd>
    </div>
    <div class="min-w-0">
        <dt class="text-xs text-text-muted">{{ __('ui.livewire.onboarding.restaurantsetup.zona') }}</dt>
        <dd class="mt-0.5 min-w-0 wrap-anywhere font-medium">{{ $summary['area'] ?? __('ui.onboarding.restaurant_setup.net') }}</dd>
    </div>
    <div class="grid min-w-0 grid-cols-3 gap-2 border-t border-border-subtle pt-3">
        <div class="min-w-0">
            <dt class="wrap-anywhere text-xs text-text-muted">{{ __('ui.livewire.onboarding.restaurantsetup.stoly') }}</dt>
            <dd class="mt-0.5 font-semibold tabular-nums">{{ $summary['service_points'] }}</dd>
        </div>
        <div class="min-w-0">
            <dt class="wrap-anywhere text-xs text-text-muted">{{ __('permissions.groups.qr') }}</dt>
            <dd class="mt-0.5 font-semibold tabular-nums">{{ $summary['qr_codes'] }}</dd>
        </div>
        <div class="min-w-0">
            <dt class="wrap-anywhere text-xs text-text-muted">{{ __('ui.livewire.onboarding.restaurantsetup.meniu') }}</dt>
            <dd class="mt-0.5 min-w-0 wrap-anywhere font-semibold">{{ $summary['menu'] ?? __('ui.onboarding.restaurant_setup.net') }}</dd>
        </div>
    </div>
</dl>
