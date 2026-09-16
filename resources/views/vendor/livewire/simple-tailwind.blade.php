@if ($paginator->hasPages())
    <nav
        class="rm-pagination rm-pagination--simple"
        role="navigation"
        aria-label="{{ __('pagination.navigation') }}"
    >
        @if ($paginator->onFirstPage())
            <span class="rm-pagination__disabled" aria-disabled="true">
                {{ __('pagination.previous') }}
            </span>
        @else
            @if (method_exists($paginator, 'getCursorName'))
                <button
                    type="button"
                    class="rm-pagination__control"
                    wire:key="cursor-{{ $paginator->getCursorName() }}-{{ $paginator->previousCursor()?->encode() }}"
                    wire:click="setPage('{{ $paginator->previousCursor()?->encode() }}', '{{ $paginator->getCursorName() }}')"
                    wire:loading.attr="disabled"
                >
                    {{ __('pagination.previous') }}
                </button>
            @else
                <button
                    type="button"
                    class="rm-pagination__control"
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
                    class="rm-pagination__control"
                    wire:key="cursor-{{ $paginator->getCursorName() }}-{{ $paginator->nextCursor()?->encode() }}"
                    wire:click="setPage('{{ $paginator->nextCursor()?->encode() }}', '{{ $paginator->getCursorName() }}')"
                    wire:loading.attr="disabled"
                >
                    {{ __('pagination.next') }}
                </button>
            @else
                <button
                    type="button"
                    class="rm-pagination__control"
                    wire:click="nextPage('{{ $paginator->getPageName() }}')"
                    wire:loading.attr="disabled"
                >
                    {{ __('pagination.next') }}
                </button>
            @endif
        @else
            <span class="rm-pagination__disabled" aria-disabled="true">
                {{ __('pagination.next') }}
            </span>
        @endif
    </nav>
@endif
