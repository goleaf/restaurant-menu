{{-- Credit: Lucide (https://lucide.dev) --}}

@props([
    'variant' => 'outline',
])

<svg
    {{ $attributes->class([
        'shrink-0',
        '[:where(&)]:size-6' => $variant === 'outline',
        '[:where(&)]:size-5' => $variant === 'mini',
        '[:where(&)]:size-4' => $variant === 'micro',
    ]) }}
    data-flux-icon
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="{{ match ($variant) { 'outline' => 2, 'mini' => 2.25, 'micro' => 2.5 } }}"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    data-slot="icon"
>
    <rect width="7" height="7" x="3" y="3" rx="1" />
    <rect width="7" height="7" x="14" y="3" rx="1" />
    <rect width="7" height="7" x="14" y="14" rx="1" />
    <rect width="7" height="7" x="3" y="14" rx="1" />
</svg>
