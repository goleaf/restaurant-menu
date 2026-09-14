@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="{{ __('layout.app_name') }}" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-control bg-accent text-accent-foreground">
            <x-app-logo-icon class="size-5" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="{{ __('layout.app_name') }}" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-control bg-accent text-accent-foreground">
            <x-app-logo-icon class="size-5" />
        </x-slot>
    </flux:brand>
@endif
