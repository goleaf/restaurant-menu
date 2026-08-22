<x-ui.form-field :for="$fieldId" :label="$label" :description="$description" :name="$name" :error="$error">
    <textarea
        id="{{ $fieldId }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        @if ($placeholder) placeholder="{{ __($placeholder) }}" @endif
        @if ($error !== null || (isset($errors) && $errors->has($name))) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
        {{ $attributes->class([
            'w-full rounded-lg border border-zinc-300 bg-white px-3 py-3 text-base text-zinc-950 shadow-xs transition placeholder:text-zinc-400 focus:border-zinc-500 focus:outline-hidden focus:ring-2 focus:ring-zinc-500/20 disabled:cursor-not-allowed disabled:bg-zinc-100 disabled:text-zinc-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white dark:placeholder:text-zinc-500 dark:focus:border-zinc-400',
            'border-red-500 focus:border-red-500 focus:ring-red-500/20 dark:border-red-500' => $error !== null || (isset($errors) && $errors->has($name)),
        ]) }}
    >{{ $attributes->whereStartsWith('wire:model')->isNotEmpty() ? ($slot->isEmpty() ? $value : trim((string) $slot)) : old($name, $slot->isEmpty() ? $value : trim((string) $slot)) }}</textarea>
</x-ui.form-field>
