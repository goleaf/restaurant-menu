<section
    data-component="guest-order-statuses"
    wire:poll.visible.{{ $pollingIntervalSeconds }}s="refreshOrderStatuses"
    class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
>
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-xs font-medium uppercase text-emerald-700 dark:text-emerald-300">{{ __('Статус') }}</p>
            <h2 class="mt-1 text-lg font-semibold leading-tight text-zinc-950 dark:text-white">{{ __('Что с заказом') }}</h2>
        </div>

        @if ($draftStatusLabel)
            <span class="rounded-md bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                {{ $draftStatusLabel }}
            </span>
        @endif
    </div>

    @if ($draftStatusValue === 'rejected')
        <p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-800 dark:bg-red-950/40 dark:text-red-100">
            {{ __('Официант отклонил черновик.') }}
            @if ($rejectionReason)
                <span class="block pt-1 font-normal">{{ __('Причина') }}: {{ $rejectionReason }}</span>
            @endif
        </p>
    @elseif ($serviceStatusValue !== '')
        <p @class([
            'mt-4 rounded-lg px-3 py-2 text-sm font-medium',
            'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100' => $serviceStatusTone === 'emerald',
            'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-100' => $serviceStatusTone === 'amber',
            'bg-sky-50 text-sky-800 dark:bg-sky-950/40 dark:text-sky-100' => $serviceStatusTone === 'sky',
            'bg-zinc-50 text-zinc-700 dark:bg-zinc-950/40 dark:text-zinc-100' => $serviceStatusTone === 'zinc',
        ])>
            {{ __('Статус заказа') }}: {{ $serviceStatusLabel }}

            @if ($serviceStatusValue === 'accepted' && $orderStatusValue === 'sent_to_kitchen_bar')
                <span class="block pt-1 font-normal">{{ __('Заказ принят. Кухня и бар получили позиции.') }}</span>
            @endif
        </p>
    @elseif ($draftStatusValue === 'converted_to_order')
        <p class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
            {{ __('Официант подтвердил заказ. Изменения сейчас недоступны.') }}
        </p>
    @elseif ($draftStatusValue && $draftStatusValue !== 'draft')
        <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
            {{ __('Черновик отправлен официанту. Изменения сейчас недоступны.') }}
        </p>
    @else
        <p class="mt-4 rounded-lg bg-zinc-50 px-3 py-2 text-sm text-zinc-600 dark:bg-zinc-950 dark:text-zinc-300">
            {{ __('Можно выбирать позиции. Перед кухней или баром заказ подтвердит официант.') }}
        </p>
    @endif

    @if ($tableSessionStatusValue === 'payment_requested')
        <p class="mt-3 rounded-lg bg-sky-50 px-3 py-2 text-sm font-medium text-sky-800 dark:bg-sky-950/40 dark:text-sky-100">
            {{ __('Счёт уже запрошен.') }}
        </p>
    @elseif (in_array($tableSessionStatusValue, ['paid', 'closed', 'cancelled'], true))
        <p class="mt-3 rounded-lg bg-zinc-50 px-3 py-2 text-sm font-medium text-zinc-700 dark:bg-zinc-950/60 dark:text-zinc-100">
            {{ __('Эта посадка завершена.') }}
        </p>
    @endif
</section>
