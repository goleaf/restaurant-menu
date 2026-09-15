<flux:button
    :variant="$fluxVariant"
    :icon="$icon"
    :icon:trailing="$iconTrailing"
    {{ $attributes->class(['h-auto! min-w-0 whitespace-normal! rounded-control! font-semibold! leading-tight text-pretty', $variantClasses, $sizeClasses, $widthClasses]) }}
>
    {{ $slot }}
</flux:button>
