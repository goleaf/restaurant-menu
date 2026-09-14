<section
    data-workspace-split
    {{ $attributes->class('min-w-0 overflow-hidden rounded-card border border-border-subtle bg-surface lg:grid lg:grid-cols-[minmax(17rem,0.72fr)_minmax(25rem,1.28fr)]') }}
>
    <div class="min-w-0 bg-surface-muted p-2.5 sm:p-3 lg:border-e lg:border-border-subtle">
        {{ $queue }}
    </div>

    <div class="hidden min-w-0 bg-surface p-4 lg:block">
        @if ($detail->isEmpty())
            {{ $emptyDetail }}
        @else
            {{ $detail }}
        @endif
    </div>
</section>
