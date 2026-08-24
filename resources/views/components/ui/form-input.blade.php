<x-ui.form-field :for="$fieldId" :label="$label" :description="$description" :name="$name" :error="$error">
    <input
        id="{{ $fieldId }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($placeholder) placeholder="{{ __($placeholder) }}" @endif
        @if ($attributes->whereStartsWith('wire:model')->isEmpty() && old($name, $value) !== null) value="{{ old($name, $value) }}" @endif
        @if ($error !== null || (isset($errors) && $errors->has($name))) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
        {{ $attributes->class([
            'min-h-12 w-full rounded-control border border-border-subtle bg-surface px-3 text-base text-text-primary shadow-xs transition-[background-color,border-color,color,box-shadow] duration-state ease-product placeholder:text-text-muted focus:border-strong focus:outline-hidden focus:ring-2 focus:ring-focus/20 disabled:cursor-not-allowed disabled:bg-surface-muted disabled:text-text-muted motion-reduce:transition-none',
            'border-danger focus:border-danger focus:ring-danger/20' => $error !== null || (isset($errors) && $errors->has($name)),
        ]) }}
    >
</x-ui.form-field>
