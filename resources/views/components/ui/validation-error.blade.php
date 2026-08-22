@if ($error)
    <p {{ $attributes->class('text-sm font-medium text-red-600 dark:text-red-300') }}>
        {{ $error }}
    </p>
@elseif ($name)
    @error($name)
        <p {{ $attributes->class('text-sm font-medium text-red-600 dark:text-red-300') }}>
            {{ $message }}
        </p>
    @enderror
@endif
