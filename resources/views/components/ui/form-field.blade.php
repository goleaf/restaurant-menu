@props([
    'label' => null,
    'description' => null,
    'for' => null,
    'name' => null,
    'error' => null,
])

<div {{ $attributes->class('grid gap-2') }}>
    @if ($label)
        <label @if ($for) for="{{ $for }}" @endif class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">
            {{ __($label) }}
        </label>
    @endif

    @if ($description)
        <p class="text-sm leading-5 text-zinc-600 dark:text-zinc-300">{{ __($description) }}</p>
    @endif

    {{ $slot }}

    @if ($error)
        <p class="text-sm font-medium text-red-600 dark:text-red-300">{{ $error }}</p>
    @elseif ($name && isset($errors) && $errors->has($name))
        <p class="text-sm font-medium text-red-600 dark:text-red-300">{{ $errors->first($name) }}</p>
    @endif
</div>
