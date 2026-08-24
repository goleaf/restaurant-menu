@props([
    'label' => null,
    'description' => null,
    'for' => null,
    'name' => null,
    'error' => null,
])

<div {{ $attributes->class('grid gap-2') }}>
    @if ($label)
        <label @if ($for) for="{{ $for }}" @endif class="text-sm font-semibold text-text-primary">
            {{ __($label) }}
        </label>
    @endif

    @if ($description)
        <p class="text-pretty text-sm leading-5 text-text-muted">{{ __($description) }}</p>
    @endif

    {{ $slot }}

    @if ($error)
        <p class="text-sm font-medium text-danger">{{ $error }}</p>
    @elseif ($name && isset($errors) && $errors->has($name))
        <p class="text-sm font-medium text-danger">{{ $errors->first($name) }}</p>
    @endif
</div>
