@props([
    'amount' => 0,
    'currency' => null,
    'signed' => false,
    'cents' => false,
    'label' => null,
])

@php
    $displayAmount = match (true) {
        (bool) $cents => App\Support\MoneyFormatter::formatCents((int) $amount, $currency),
        (bool) $signed => App\Support\MoneyFormatter::formatSigned($amount, $currency),
        default => App\Support\MoneyFormatter::format($amount, $currency),
    };
@endphp

<span
    @if ($label) aria-label="{{ __($label) }}" @endif
    @if ($currency) data-currency="{{ $currency }}" @endif
    {{ $attributes->class('whitespace-nowrap font-semibold tabular-nums text-zinc-950 dark:text-white') }}
>
    {{ $displayAmount }}
</span>
