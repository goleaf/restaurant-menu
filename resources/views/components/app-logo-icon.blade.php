@props([
    'decorative' => true,
    'label' => null,
])

<svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24"
    data-product-mark="service-pass"
    @if ($decorative) aria-hidden="true" focusable="false" @else role="img" aria-label="{{ $label ?? __('layout.app_name') }}" @endif
    {{ $attributes }}
>
    <path d="M6 6 12 12 18 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
    <rect x="3" y="3" width="6" height="6" rx="2" fill="currentColor" />
    <rect x="9" y="9" width="6" height="6" rx="2" fill="currentColor" />
    <rect x="15" y="15" width="6" height="6" rx="2" fill="currentColor" />
</svg>
