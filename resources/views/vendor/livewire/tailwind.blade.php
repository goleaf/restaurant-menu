@if ($paginator->hasPages())
    <nav
        class="rm-pagination"
        role="navigation"
        aria-label="{{ __('pagination.navigation') }}"
    >
        <p class="text-sm text-text-muted">
            {{ __('pagination.summary', [
                'first' => $paginator->firstItem(),
                'last' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ]) }}
        </p>

        <div class="flex flex-wrap items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="rm-pagination__disabled" aria-disabled="true">
                    {{ __('pagination.previous') }}
                </span>
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

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="inline-flex min-h-touch min-w-touch items-center justify-center px-2 text-sm text-text-muted" aria-hidden="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <span
                                class="rm-pagination__current"
                                aria-current="page"
                                aria-label="{{ __('pagination.current_page', ['page' => $page]) }}"
                            >
                                {{ $page }}
                            </span>
                        @else
                            <button
                                type="button"
                                class="rm-pagination__control rm-pagination__page"
                                wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                aria-label="{{ __('pagination.page', ['page' => $page]) }}"
                            >
                                {{ $page }}
                            </button>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <button
                    type="button"
                    class="rm-pagination__control"
                    wire:click="nextPage('{{ $paginator->getPageName() }}')"
                    wire:loading.attr="disabled"
                >
                    {{ __('pagination.next') }}
                </button>
            @else
                <span class="rm-pagination__disabled" aria-disabled="true">
                    {{ __('pagination.next') }}
                </span>
            @endif
        </div>
    </nav>
@endif
