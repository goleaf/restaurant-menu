<aside class="rounded-lg border border-border-subtle bg-surface p-4">
    @if ($paymentFeedbackMessage)
        <p class="rounded-md bg-success-surface px-3 py-2 text-sm font-medium text-success">
            {{ $paymentFeedbackMessage }}
        </p>
    @endif

    @error('table_session')
        <p class="rounded-md bg-danger-surface px-3 py-2 text-sm font-medium text-danger">{{ $message }}</p>
    @enderror

    @if (data_get($payment, 'session.close_blocked_by_workflow'))
        <p class="rounded-md bg-warning-surface px-3 py-2 text-sm font-medium text-warning">
            {{ __('orders.errors.table_has_unfinished_work') }}
        </p>
    @endif

    @if (data_get($payment, 'session.can_close'))
        <div id="close-table">
            <h2 class="text-base font-semibold text-text-primary">{{ __('payments.close_session') }}</h2>

            <p @class([
                'mt-3 rounded-md px-3 py-2 text-sm',
                'bg-warning-surface text-warning' => data_get($payment, 'session.close_requires_warning'),
                'bg-surface-muted text-text-muted' => ! data_get($payment, 'session.close_requires_warning'),
            ])>
                {{ __('payments.close_session_warning') }}
            </p>

            <x-dangerous-action-confirmation
                name="close-table-session"
                :action="data_get($payment, 'session.close_requires_warning') ? 'close_table_with_unpaid_amount' : null"
                confirm-action="closeTableSession"
                submit-target="closeTableSession"
                confirm-label="ui.actions.i_understand"
                loading-label="ui.actions.closing"
                :confirmation-model="data_get($payment, 'session.close_requires_warning') ? 'closeTableConfirmation' : null"
                confirmation-text="CLOSE"
                confirmation-help="ui.confirmations.close_unpaid_session.confirmation_help"
            >
                <x-slot:trigger>
                    <flux:button icon="check" type="button" class="mt-3 w-full">
                        {{ __('payments.close_session') }}
                    </flux:button>
                </x-slot:trigger>
            </x-dangerous-action-confirmation>
        </div>
    @endif

    @if (data_get($payment, 'can_view'))
        <div @class(['border-border-subtle' => data_get($payment, 'session.can_close'), 'mt-5 border-t pt-4' => data_get($payment, 'session.can_close')])>
            <h2 class="text-base font-semibold text-text-primary">{{ __('payments.title') }}</h2>

            @error('manual_payment')
                <p class="mt-3 rounded-md bg-danger-surface px-3 py-2 text-sm font-medium text-danger">{{ $message }}</p>
            @enderror

            <p class="mt-4 text-xs font-medium uppercase text-text-muted">{{ __('payments.summary') }}</p>

            <dl class="mt-2 grid gap-3 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-text-muted">{{ __('payments.table_total') }}</dt>
                    <dd class="font-semibold text-text-primary">{{ data_get($payment, 'confirmed_total') }}</dd>
                </div>

                @if (data_get($payment, 'service_charge_enabled'))
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-text-muted">
                            {{ __('payments.service_charge') }} · {{ data_get($payment, 'service_charge_percent') }}%
                        </dt>
                        <dd class="font-semibold text-text-primary">{{ data_get($payment, 'service_charge_total') }}</dd>
                    </div>
                @endif

                <div class="flex items-center justify-between gap-3">
                    <dt class="text-text-muted">{{ __('payments.total_paid') }}</dt>
                    <dd class="font-semibold text-text-primary">{{ data_get($payment, 'paid_total') }}</dd>
                </div>

                @if (data_get($payment, 'tips_enabled'))
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-text-muted">{{ __('payments.tips_recorded') }}</dt>
                        <dd class="font-semibold text-text-primary">{{ data_get($payment, 'tips_paid_total') }}</dd>
                    </div>
                @endif

                <div class="flex items-center justify-between gap-3">
                    <dt class="text-text-muted">{{ __('payments.remaining') }}</dt>
                    <dd class="font-semibold text-text-primary">{{ data_get($payment, 'remaining_total') }}</dd>
                </div>
            </dl>

            @if (data_get($payment, 'unpaid_guests_count', 0) > 0)
                <div class="mt-3 rounded-md bg-warning-surface px-3 py-2 text-sm text-warning">
                    <p class="font-medium">{{ __('payments.unpaid') }}: {{ data_get($payment, 'unpaid_guests_count') }}</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach (data_get($payment, 'unpaid_guests', []) as $unpaidGuest)
                            <flux:badge wire:key="unpaid-guest-{{ $unpaidGuest['guest_id'] }}" color="amber">
                                <x-ui.plain-text :text="$unpaidGuest['guest_name']" class="inline" :preserve-lines="false" />
                                · {{ $unpaidGuest['remaining'] }}
                            </flux:badge>
                        @endforeach
                    </div>
                </div>
            @elseif (data_get($payment, 'is_fully_paid'))
                <p class="mt-3 rounded-md bg-success-surface px-3 py-2 text-sm font-medium text-success">
                    {{ __('payments.fully_paid') }}
                </p>
            @elseif ((int) data_get($payment, 'paid_total_cents', 0) > 0)
                <p class="mt-3 rounded-md bg-information-surface px-3 py-2 text-sm font-medium text-information">
                    {{ __('payments.partially_paid') }}
                </p>
            @endif

            @if (data_get($payment, 'has_open_draft'))
                <p class="mt-3 rounded-md bg-warning-surface px-3 py-2 text-sm text-warning">
                    {{ __('payments.errors.open_draft') }}
                </p>
            @elseif (! data_get($payment, 'has_payable_total'))
                <p class="mt-3 rounded-md bg-surface-muted px-3 py-2 text-sm text-text-muted">
                    {{ __('payments.errors.no_confirmed_orders') }}
                </p>
            @endif

            @if (data_get($payment, 'can_manage'))
                <div class="mt-4 space-y-3">
                    <flux:select wire:model="paymentMethod" :label="__('payments.forms.method')">
                        @foreach (data_get($payment, 'payment_methods', []) as $paymentMethodOption)
                            <flux:select.option wire:key="payment-method-{{ $paymentMethodOption['value'] }}" value="{{ $paymentMethodOption['value'] }}">
                                {{ __($paymentMethodOption['label']) }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:textarea label="{{ __('payments.forms.note') }}" id="waiter-payment-note" name="paymentNote" wire:model="paymentNote" rows="2" maxlength="500" class="min-h-touch"></flux:textarea>

                    @if (data_get($payment, 'tips_enabled'))
                        <flux:input wire:model="tipsAmount" :label="__('payments.forms.amount')" type="number" min="0" step="0.01" />

                        @error('tipsAmount')
                            <p class="text-sm font-medium text-danger">{{ $message }}</p>
                        @enderror
                    @endif

                    @if (data_get($payment, 'can_record_table_payment'))
                        <x-dangerous-action-confirmation
                            name="record-table-payment"
                            action="payment_correction"
                            confirm-action="recordTablePayment"
                            submit-target="recordTablePayment"
                            confirm-label="ui.actions.confirm"
                            loading-label="ui.actions.saving"
                        >
                            <x-slot:trigger>
                                <flux:button icon="banknotes" variant="primary" type="button" class="w-full">
                                    {{ __('payments.pay_whole_table') }} · {{ data_get($payment, 'remaining_total') }}
                                </flux:button>
                            </x-slot:trigger>
                        </x-dangerous-action-confirmation>
                    @endif
                </div>
            @endif

            <div class="mt-4 space-y-2">
                <p class="text-xs font-medium uppercase text-text-muted">{{ __('payments.forms.guest') }}</p>

                @forelse (data_get($payment, 'guest_balances', []) as $guestBalance)
                    <article wire:key="payment-guest-{{ $guestBalance['guest_id'] }}" class="rounded-md border border-border-subtle p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <x-ui.plain-text :text="$guestBalance['guest_name']" class="block font-medium text-text-primary" :preserve-lines="false" />
                                <p class="mt-1 text-xs text-text-muted">
                                    {{ __('payments.guest_total') }}: {{ $guestBalance['due'] }}
                                    · {{ __('payments.guest_paid') }}: {{ $guestBalance['paid'] }}
                                </p>
                            </div>

                            <flux:badge :color="$guestBalance['is_paid'] ? 'lime' : 'zinc'">
                                {{ $guestBalance['is_paid'] ? __('payments.paid') : __('payments.guest_remaining').': '.$guestBalance['remaining'] }}
                            </flux:badge>
                        </div>

                        @if ($guestBalance['covered_by_table_payment'])
                            <p class="mt-2 text-xs text-success">{{ __('payments.covered_by_table_payment') }}</p>
                        @endif

                        @if ($guestBalance['can_record_payment'])
                            <x-dangerous-action-confirmation
                                name="record-guest-payment-{{ $guestBalance['guest_id'] }}"
                                action="payment_correction"
                                confirm-action="recordGuestPayment({{ $guestBalance['guest_id'] }})"
                                submit-target="recordGuestPayment({{ $guestBalance['guest_id'] }})"
                                confirm-label="ui.actions.confirm"
                                loading-label="ui.actions.saving"
                            >
                                <x-slot:trigger>
                                    <flux:button size="sm" icon="banknotes" type="button" class="mt-3 w-full">
                                        {{ __('payments.pay_guest') }} · {{ $guestBalance['remaining'] }}
                                    </flux:button>
                                </x-slot:trigger>
                            </x-dangerous-action-confirmation>
                        @endif
                    </article>
                @empty
                    <p class="rounded-md bg-surface-muted px-3 py-2 text-sm text-text-muted">{{ __('payments.no_guest_totals') }}</p>
                @endforelse
            </div>

            @if (data_get($payment, 'payments'))
                <div class="mt-4 space-y-2">
                    <p class="text-xs font-medium uppercase text-text-muted">{{ __('payments.payment_history') }}</p>

                    @foreach (data_get($payment, 'payments', []) as $recordedPayment)
                        <div wire:key="manual-payment-{{ $recordedPayment['id'] }}" class="rounded-md bg-surface-muted px-3 py-2 text-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-medium text-text-primary">{{ $recordedPayment['amount'] }} · {{ __($recordedPayment['method_label']) }}</p>
                                    <p class="mt-1 text-xs text-text-muted">
                                        {{ __($recordedPayment['scope_label']) }}
                                        @if ($recordedPayment['guest_name'])
                                            · <x-ui.plain-text :text="$recordedPayment['guest_name']" class="inline" :preserve-lines="false" />
                                        @endif
                                    </p>
                                </div>
                                <p class="shrink-0 text-xs text-text-muted">{{ $recordedPayment['paid_at'] }}</p>
                            </div>

                            @if ($recordedPayment['recorded_by_name'] || $recordedPayment['note'])
                                <p class="mt-1 text-xs text-text-muted">
                                    @if ($recordedPayment['recorded_by_name'])
                                        {{ __('payments.recorded_by') }}: {{ $recordedPayment['recorded_by_name'] }}
                                    @endif
                                    @if ($recordedPayment['note'])
                                        · <x-ui.plain-text :text="$recordedPayment['note']" class="inline" />
                                    @endif
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</aside>
