@if ($error)
    <p {{ $attributes->class('text-sm font-medium leading-5 text-danger') }}>
        {{ $error }}
    </p>
@elseif ($name)
    @error($name)
        <p {{ $attributes->class('text-sm font-medium leading-5 text-danger') }}>
            {{ $message }}
        </p>
    @enderror
@endif
