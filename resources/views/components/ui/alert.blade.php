<div {{ $attributes->class(['rounded-lg border p-3 text-sm leading-5', $toneClasses]) }} role="status">
    <div class="flex gap-2">
        <flux:icon :name="$resolvedIcon" variant="mini" class="mt-0.5 size-4 shrink-0" />

        <div class="min-w-0 flex-1">
            @if ($heading)
                <p class="font-semibold">{{ __($heading) }}</p>
            @endif

            <div @class(['font-medium' => ! $heading])>
                {{ $slot }}
            </div>
        </div>

        @isset($actions)
            <div class="flex shrink-0 flex-wrap gap-2">
                {{ $actions }}
            </div>
        @endisset
    </div>
</div>
