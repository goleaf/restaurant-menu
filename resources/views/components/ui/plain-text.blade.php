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
