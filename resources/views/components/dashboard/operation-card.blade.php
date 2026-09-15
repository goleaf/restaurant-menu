@props(['item'])

<article {{ $attributes->class('flex min-w-0 flex-col rounded-control border border-border-subtle bg-surface p-4') }} data-operation="{{ $item['key'] }}">
    <div class="flex min-w-0 items-start justify-between gap-3">
        <h3 class="min-w-0 text-sm font-semibold leading-6 text-text-primary">{{ $item['label'] }}</h3>
        <span class="shrink-0 text-2xl font-semibold tabular-nums text-text-primary">{{ $item['value'] ?? '—' }}</span>
    </div>
    <p class="mt-1 flex-1 text-sm leading-6 text-text-muted">{{ $item['description'] }}</p>
    @if ($item['href'] !== null && $item['is_available'])
        <a href="{{ $item['href'] }}" wire:navigate class="mt-3 flex min-h-touch min-w-0 items-center justify-between gap-3 rounded-control border border-border-subtle px-3 py-2 text-sm font-semibold text-accent hover:border-border-strong hover:bg-surface-selected focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2" aria-label="{{ __('dashboard.control.open_operation', ['operation' => $item['label']]) }}">
            <span>{{ __('dashboard.control.open_queue') }}</span>
            <flux:icon name="arrow-right" class="size-4 shrink-0 rtl:rotate-180" aria-hidden="true" />
        </a>
    @elseif (($item['requires_branch'] ?? false) && $item['is_available'])
        <x-ui.button x-on:click="openBranchPicker()" class="mt-3 justify-start whitespace-normal text-start">{{ __('dashboard.control.choose_branch') }}</x-ui.button>
    @else
        <p class="mt-3 flex min-h-touch items-center gap-2 text-xs text-text-muted"><flux:icon name="lock-closed" class="size-4 shrink-0" aria-hidden="true" />{{ __('dashboard.control.no_access') }}</p>
    @endif
</article>
