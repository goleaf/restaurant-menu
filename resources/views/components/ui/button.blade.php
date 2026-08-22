@if ($attributes->has('href'))
    <a {{ $attributes->class([$baseClasses, $variantClasses, $sizeClasses, $widthClasses]) }}>
        @if ($icon)
            <flux:icon :name="$icon" variant="micro" class="size-4" />
        @endif

        {{ $slot }}

        @if ($iconTrailing)
            <flux:icon :name="$iconTrailing" variant="micro" class="size-4" />
        @endif
    </a>
@else
    <button {{ $attributes->merge(['type' => 'button'])->class([$baseClasses, $variantClasses, $sizeClasses, $widthClasses]) }}>
        @if ($icon)
            <flux:icon :name="$icon" variant="micro" class="size-4" />
        @endif

        {{ $slot }}

        @if ($iconTrailing)
            <flux:icon :name="$iconTrailing" variant="micro" class="size-4" />
        @endif
    </button>
@endif
