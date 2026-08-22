@props(['label' => 'ui.accessibility.close_dialog'])

<div class="absolute end-0 top-0 me-4 mt-4">
    <flux:modal.close>
        <flux:button
            type="button"
            variant="ghost"
            icon="x-mark"
            size="sm"
            :aria-label="__($label)"
            class="text-zinc-400! hover:text-zinc-800! dark:text-zinc-500! dark:hover:text-white!"
        />
    </flux:modal.close>
</div>
