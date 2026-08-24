<section
    data-workspace-split
    {{ $attributes->class('overflow-hidden rounded-card border border-border-subtle bg-surface lg:grid lg:grid-cols-[minmax(18rem,0.8fr)_minmax(24rem,1.2fr)]') }}
>
    <div class="min-w-0 bg-surface-muted p-3 sm:p-4 lg:border-e lg:border-border-subtle">
        {{ $queue }}
    </div>

    <div class="hidden min-w-0 bg-surface p-4 lg:block lg:p-5">
        @if ($detail->isEmpty())
            {{ $emptyDetail }}
        @else
            {{ $detail }}
        @endif
    </div>
</section>
