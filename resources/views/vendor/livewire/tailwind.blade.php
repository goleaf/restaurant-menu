@if ($paginator->hasPages())
    <nav
        class="flex flex-col gap-3 border-t border-border-subtle pt-3 sm:flex-row sm:items-center sm:justify-between"
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
                <span class="inline-flex min-h-touch items-center rounded-control border border-border-subtle px-3 text-sm text-text-muted opacity-60" aria-disabled="true">
                    {{ __('pagination.previous') }}
                </span>
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

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="inline-flex min-h-touch min-w-touch items-center justify-center px-2 text-sm text-text-muted" aria-hidden="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <span
                                class="inline-flex min-h-touch min-w-touch items-center justify-center rounded-control bg-brand-700 px-2 text-sm font-semibold text-white"
                                aria-current="page"
                                aria-label="{{ __('pagination.current_page', ['page' => $page]) }}"
                            >
                                {{ $page }}
                            </span>
                        @else
                            <button
                                type="button"
                                class="inline-flex min-h-touch min-w-touch items-center justify-center rounded-control border border-border-subtle px-2 text-sm font-semibold text-text-primary hover:bg-surface-muted focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-focus"
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
                    class="inline-flex min-h-touch items-center rounded-control border border-border-subtle px-3 text-sm font-semibold text-text-primary hover:bg-surface-muted focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-focus"
                    wire:click="nextPage('{{ $paginator->getPageName() }}')"
                    wire:loading.attr="disabled"
                >
                    {{ __('pagination.next') }}
                </button>
            @else
                <span class="inline-flex min-h-touch items-center rounded-control border border-border-subtle px-3 text-sm text-text-muted opacity-60" aria-disabled="true">
                    {{ __('pagination.next') }}
                </span>
            @endif
        </div>
    </nav>
@endif
