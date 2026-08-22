@props([
    'label' => 'ui.accessibility.close_dialog',
    'autofocus' => false,
])

<div class="absolute end-0 top-0 me-4 mt-4">
    <flux:modal.close>
        <flux:button
            type="button"
            variant="ghost"
            icon="x-mark"
            size="sm"
            :aria-label="__($label)"
            :autofocus="$autofocus"
            class="text-zinc-600! hover:text-zinc-950! dark:text-zinc-400! dark:hover:text-white!"
        />
    </flux:modal.close>
</div>
