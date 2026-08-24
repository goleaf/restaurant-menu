<section wire:poll.visible.30s="refreshHistory" class="rounded-card border border-border-subtle bg-surface p-4" aria-labelledby="table-session-history-heading">
    <div>
        <h2 id="table-session-history-heading" class="text-base font-semibold text-text-primary">{{ __('ui.waiter.session_history.title') }}</h2>
        <p class="mt-1 text-sm text-text-muted">{{ __('ui.waiter.session_history.description') }}</p>
    </div>

    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($sessions as $tableSession)
            <article wire:key="table-session-history-{{ $tableSession['id'] }}" class="rounded-lg border border-border-subtle bg-surface-muted p-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold text-text-primary">{{ $tableSession['service_point_name'] }}</p>
                        <p class="mt-1 text-sm text-text-muted">
                            {{ $tableSession['branch_name'] }}
                            @if ($tableSession['display_number'])
                                · {{ __('qr.labels.number') }} {{ $tableSession['display_number'] }}
                            @endif
                        </p>
                    </div>
                    <x-ui.status-badge>{{ __($tableSession['status_key']) }}</x-ui.status-badge>
                </div>

                <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <dt class="text-text-muted">{{ __('guest.table.guests') }}</dt>
                        <dd class="font-medium text-text-primary">{{ $tableSession['guest_count'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">{{ __('ui.waiter.session_history.orders') }}</dt>
                        <dd class="font-medium text-text-primary">{{ $tableSession['order_count'] }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-text-muted">{{ __('ui.waiter.session_history.closed_at') }}</dt>
                        <dd class="font-medium text-text-primary">{{ $tableSession['ended_at'] ?? __('ui.departments.dashboard.time_not_set') }}</dd>
                    </div>
                </dl>

                <flux:button class="mt-3 w-full" size="sm" icon="clock" :href="$tableSession['detail_url']" wire:navigate>
                    {{ __('ui.waiter.session_history.open') }}
                </flux:button>
            </article>
        @empty
            <x-ui.state-panel class="md:col-span-2 xl:col-span-3" kind="empty" title="ui.waiter.session_history.empty" />
        @endforelse
    </div>
</section>
