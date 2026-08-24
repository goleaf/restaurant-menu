@if ($paginator->hasPages())
    <nav
        class="flex items-center justify-between gap-3 border-t border-border-subtle pt-3"
        role="navigation"
        aria-label="{{ __('pagination.navigation') }}"
    >
        @if ($paginator->onFirstPage())
            <span class="inline-flex min-h-touch items-center rounded-control border border-border-subtle px-3 text-sm text-text-muted opacity-60" aria-disabled="true">
                {{ __('pagination.previous') }}
            </span>
        @else
            @if (method_exists($paginator, 'getCursorName'))
                <button
                    type="button"
                    class="inline-flex min-h-touch items-center rounded-control border border-border-subtle px-3 text-sm font-semibold text-text-primary hover:bg-surface-muted focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-focus"
                    wire:key="cursor-{{ $paginator->getCursorName() }}-{{ $paginator->previousCursor()?->encode() }}"
                    wire:click="setPage('{{ $paginator->previousCursor()?->encode() }}', '{{ $paginator->getCursorName() }}')"
                    wire:loading.attr="disabled"
                >
                    {{ __('pagination.previous') }}
                </button>
            @else
                <button
                    type="button"
                    class="inline-flex min-h-touch items-center rounded-control border border-border-subtle px-3 text-sm font-semibold text-text-primary hover:bg-surface-muted focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-focus"
                    wire:click="previousPage('{{ $paginator->getPageName() }}')"
                    wire:loading.attr="disabled"
                >
                    {{ __('pagination.previous') }}
                </button>
            @endif
        @endif

        @if ($paginator->hasMorePages())
            @if (method_exists($paginator, 'getCursorName'))
                <button
                    type="button"
                    class="inline-flex min-h-touch items-center rounded-control border border-border-subtle px-3 text-sm font-semibold text-text-primary hover:bg-surface-muted focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-focus"
                    wire:key="cursor-{{ $paginator->getCursorName() }}-{{ $paginator->nextCursor()?->encode() }}"
                    wire:click="setPage('{{ $paginator->nextCursor()?->encode() }}', '{{ $paginator->getCursorName() }}')"
                    wire:loading.attr="disabled"
                >
                    {{ __('pagination.next') }}
                </button>
            @else
                <button
                    type="button"
                    class="inline-flex min-h-touch items-center rounded-control border border-border-subtle px-3 text-sm font-semibold text-text-primary hover:bg-surface-muted focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-focus"
                    wire:click="nextPage('{{ $paginator->getPageName() }}')"
                    wire:loading.attr="disabled"
                >
                    {{ __('pagination.next') }}
                </button>
            @endif
        @else
            <span class="inline-flex min-h-touch items-center rounded-control border border-border-subtle px-3 text-sm text-text-muted opacity-60" aria-disabled="true">
                {{ __('pagination.next') }}
            </span>
        @endif
    </nav>
@endif
