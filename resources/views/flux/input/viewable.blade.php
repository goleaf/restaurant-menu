@blaze(fold: true, memo: true)

@props([
    'iconVariant' => 'mini',
    'size' => null,
])

<flux:button
    {{ $attributes->merge([
        'variant' => 'subtle',
        'class' => '-me-1',
        'square' => true,
        'size' => null,
    ]) }}
    :size="$size === 'sm' || $size === 'xs' ? 'xs' : 'sm'"
    x-data="fluxInputViewable"
    x-on:click="toggle()"
    x-bind:data-viewable-open="open"
    :aria-label="__('ui.accessibility.toggle_password_visibility')"
>
    <flux:icon.eye-slash :variant="$iconVariant" class="hidden [[data-viewable-open]>&]:block" />
    <flux:icon.eye :variant="$iconVariant" class="block [[data-viewable-open]>&]:hidden" />
</flux:button>
