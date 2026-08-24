<x-ui.form-field :for="$fieldId" :label="$label" :description="$description" :name="$name" :error="$error">
    <select
        id="{{ $fieldId }}"
        name="{{ $name }}"
        @if ($error !== null || (isset($errors) && $errors->has($name))) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
        {{ $attributes->class([
            'min-h-12 w-full rounded-control border border-border-subtle bg-surface px-3 text-base text-text-primary shadow-xs transition-[background-color,border-color,color,box-shadow] duration-state ease-product focus:border-strong focus:outline-hidden focus:ring-2 focus:ring-focus/20 disabled:cursor-not-allowed disabled:bg-surface-muted disabled:text-text-muted motion-reduce:transition-none',
            'border-danger focus:border-danger focus:ring-danger/20' => $error !== null || (isset($errors) && $errors->has($name)),
        ]) }}
    >
        @if ($placeholder)
            <option value="">{{ __($placeholder) }}</option>
        @endif

        @foreach ($resolvedOptions as $option)
            <option value="{{ $option['value'] }}" @selected((string) old($name, $selected) === (string) $option['value'])>
                {{ __($option['label']) }}
            </option>
        @endforeach

        {{ $slot }}
    </select>
</x-ui.form-field>
