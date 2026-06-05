@props([
    'label' => null,
    'size' => 'lg',
    'icon' => null,
    'iconTrailing' => null,
    'fullWidth' => false,
])

<x-ui.button
    variant="primary"
    :size="$size"
    :icon="$icon"
    :icon-trailing="$iconTrailing"
    :full-width="$fullWidth"
    {{ $attributes }}
>
    {{ $label ? __($label) : $slot }}
</x-ui.button>
