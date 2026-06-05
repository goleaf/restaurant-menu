@props([
    'name',
    'label' => null,
    'description' => null,
    'placeholder' => null,
    'type' => 'text',
    'value' => null,
    'id' => null,
    'error' => null,
])

@php
    $fieldId = $id ?? 'field-'.Illuminate\Support\Str::of($name)->replace(['[', ']', '.'], '-')->trim('-');
    $errorId = $fieldId.'-error';
    $hasError = $error !== null || (isset($errors) && $errors->has($name));
    $isLivewireBound = $attributes->whereStartsWith('wire:model')->isNotEmpty();
    $inputValue = old($name, $value);
@endphp

<x-ui.form-field :for="$fieldId" :label="$label" :description="$description" :name="$name" :error="$error">
    <input
        id="{{ $fieldId }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($placeholder) placeholder="{{ __($placeholder) }}" @endif
        @if (! $isLivewireBound && $inputValue !== null) value="{{ $inputValue }}" @endif
        @if ($hasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
        {{ $attributes->class([
            'min-h-12 w-full rounded-lg border border-zinc-300 bg-white px-3 text-base text-zinc-950 shadow-xs transition placeholder:text-zinc-400 focus:border-zinc-500 focus:outline-hidden focus:ring-2 focus:ring-zinc-500/20 disabled:cursor-not-allowed disabled:bg-zinc-100 disabled:text-zinc-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white dark:placeholder:text-zinc-500 dark:focus:border-zinc-400',
            'border-red-500 focus:border-red-500 focus:ring-red-500/20 dark:border-red-500' => $hasError,
        ]) }}
    >
</x-ui.form-field>
