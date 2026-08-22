<div class="grid gap-1">
    <input
        type="file"
        accept="{{ $acceptedMimeTypes }}"
        aria-label="{{ $ariaLabel }}"
        {{ $attributes->class('block w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm file:mr-3 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-zinc-900 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100 dark:file:bg-zinc-800 dark:file:text-zinc-100') }}
    >
    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $helpText }}</p>
</div>
