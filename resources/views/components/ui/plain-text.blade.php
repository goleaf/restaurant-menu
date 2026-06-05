@props([
    'text' => null,
    'placeholder' => null,
    'preserveLines' => true,
])

@php
    $value = is_scalar($text) || $text instanceof \Stringable ? trim((string) $text) : '';
    $fallback = is_scalar($placeholder) || $placeholder instanceof \Stringable ? trim((string) $placeholder) : '';
@endphp

@if ($value !== '')
    <span {{ $attributes->class([
        'break-words',
        'whitespace-pre-line' => $preserveLines,
    ]) }}>{{ $value }}</span>
@elseif ($fallback !== '')
    <span {{ $attributes->class([
        'break-words',
        'whitespace-pre-line' => $preserveLines,
    ]) }}>{{ $fallback }}</span>
@endif
