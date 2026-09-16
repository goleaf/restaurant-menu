<section
    data-workspace-split
    {{ $attributes->class('rm-workspace') }}
>
    <div class="rm-workspace__queue">
        {{ $queue }}
    </div>

    <div class="rm-workspace__detail">
        @if ($detail->isEmpty())
            {{ $emptyDetail }}
        @else
            {{ $detail }}
        @endif
    </div>
</section>
