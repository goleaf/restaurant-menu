<span
    @if ($label) aria-label="{{ __($label) }}" @endif
    @if ($currency) data-currency="{{ $currency }}" @endif
    {{ $attributes->class('whitespace-nowrap font-semibold tabular-nums text-zinc-950 dark:text-white') }}
>
    {{ $displayAmount }}
</span>
