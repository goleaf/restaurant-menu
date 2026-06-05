@props([
    'tone' => 'muted',
    'icon' => null,
    'dot' => false,
    'size' => 'sm',
    'status' => null,
    'context' => 'default',
    'label' => null,
])

@php
    $statusValue = $status instanceof BackedEnum ? $status->value : $status;
    $statusKey = $statusValue !== null
        ? (string) Illuminate\Support\Str::of((string) $statusValue)->replace(['-', ' ', '.'], '_')->lower()
        : null;
    $contextKey = (string) Illuminate\Support\Str::of((string) $context)->replace(['-', ' ', '.'], '_')->lower();

    $statusTone = match ($statusKey) {
        'active', 'approved', 'available', 'completed', 'confirmed', 'confirmed_by_waiter', 'done', 'free', 'open', 'paid', 'ready', 'served', 'success' => 'success',
        'busy', 'called', 'cooking', 'draft', 'in_progress', 'new', 'pending', 'requested', 'sent', 'waiting', 'waiting_waiter_confirmation' => 'warning',
        'cancelled', 'closed', 'denied', 'disabled', 'expired', 'failed', 'inactive', 'out_of_stock', 'rejected', 'removed', 'unavailable' => 'danger',
        default => $tone,
    };

    $resolvedTone = $statusKey ? $statusTone : $tone;
    $resolvedLabelKey = $label ?? ($statusKey ? 'ui.status.'.$contextKey.'.'.$statusKey : null);
    $resolvedLabel = $resolvedLabelKey ? __($resolvedLabelKey) : null;

    if ($resolvedLabelKey && $resolvedLabel === $resolvedLabelKey && $statusKey) {
        $resolvedLabel = __(Illuminate\Support\Str::headline(str_replace('_', ' ', $statusKey)));
    }

    $resolvedIcon = $icon ?? ($statusKey && ! $dot ? match ($resolvedTone) {
        'success', 'green', 'emerald', 'lime' => 'check-circle',
        'warning', 'amber', 'orange' => 'clock',
        'danger', 'red', 'rose' => 'x-circle',
        'info', 'sky', 'blue' => 'information-circle',
        default => null,
    } : null);

    $toneClasses = match ($resolvedTone) {
        'success', 'green' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-100',
        'emerald' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-100',
        'warning', 'amber' => 'bg-amber-100 text-amber-900 dark:bg-amber-950/60 dark:text-amber-100',
        'danger', 'red', 'rose' => 'bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-100',
        'info', 'sky', 'blue' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-100',
        'orange' => 'bg-orange-100 text-orange-800 dark:bg-orange-950/60 dark:text-orange-100',
        'violet' => 'bg-violet-100 text-violet-800 dark:bg-violet-950/60 dark:text-violet-100',
        'lime' => 'bg-lime-100 text-lime-800 dark:bg-lime-950/60 dark:text-lime-100',
        default => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200',
    };

    $sizeClasses = match ($size) {
        'lg' => 'min-h-8 px-3 py-1 text-sm',
        default => 'min-h-6 px-2 py-0.5 text-xs',
    };
@endphp

<span
    @if ($statusKey) data-status="{{ $statusKey }}" data-status-context="{{ $contextKey }}" @endif
    {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-md font-semibold', $toneClasses, $sizeClasses]) }}
>
    @if ($resolvedIcon)
        <flux:icon :name="$resolvedIcon" variant="micro" class="size-3.5" />
    @elseif ($dot)
        <span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>
    @endif

    {{ $slot->isEmpty() ? $resolvedLabel : $slot }}
</span>
