@props([
    'summary' => null,
])

<div {{ $attributes->class('rm-mobile-actions') }}>
    @if ($summary)
        <p class="rm-mobile-actions__summary">{{ __($summary) }}</p>
    @endif

    <div class="rm-mobile-actions__content">
        {{ $slot }}
    </div>
</div>
