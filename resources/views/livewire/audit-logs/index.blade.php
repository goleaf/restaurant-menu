@php($payload = $this->payload)

<div data-layout="audit-log" class="flex h-full w-full flex-1 flex-col gap-5">
    <header class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Restaurant workspace') }}</p>
            <h1 class="mt-1 text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('Audit log') }}</h1>
            <p class="mt-1 max-w-2xl text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('Important staff, menu, QR, payment, session, and order changes.') }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <flux:button icon="layout-grid" :href="route('restaurant.dashboard')" wire:navigate>
                {{ __('Dashboard') }}
            </flux:button>
            <flux:button icon="arrow-path" wire:click="refreshAuditLog">
                {{ __('Refresh') }}
            </flux:button>
        </div>
    </header>

    <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex flex-col gap-1">
            <h2 class="text-base font-semibold text-zinc-950 dark:text-white">{{ __('Recent audit events') }}</h2>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Showing the latest events for accessible branches') }}: {{ $payload['branch_count'] }}
            </p>
        </div>

        <div class="mt-5 overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-800">
            @forelse ($payload['logs'] as $log)
                <article
                    wire:key="audit-log-{{ $log['id'] }}"
                    class="grid gap-3 border-b border-zinc-200 p-4 last:border-b-0 dark:border-zinc-800 lg:grid-cols-[12rem_minmax(0,1fr)_14rem]"
                >
                    <div>
                        <p class="text-sm font-medium text-zinc-950 dark:text-white">{{ $log['created_at'] }}</p>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $log['actor'] }}</p>
                    </div>

                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:badge color="zinc">{{ __($log['action_label']) }}</flux:badge>
                            <span class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $log['entity_type'] }} #{{ $log['entity_id'] ?? '—' }}
                            </span>
                        </div>

                        <dl class="mt-3 grid gap-2 text-sm md:grid-cols-2">
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('Before') }}</dt>
                                <dd class="mt-1 break-words text-zinc-700 dark:text-zinc-200">{{ $log['old_summary'] }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ __('After') }}</dt>
                                <dd class="mt-1 break-words text-zinc-700 dark:text-zinc-200">{{ $log['new_summary'] }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="text-sm text-zinc-500 dark:text-zinc-400">
                        <p>{{ $log['organization_name'] ?? __('Organization unavailable') }}</p>
                        <p class="mt-1">{{ $log['branch_name'] ?? __('Organization-level event') }}</p>
                    </div>
                </article>
            @empty
                <div class="p-6 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('No audit events yet.') }}
                </div>
            @endforelse
        </div>

        @if ($payload['logs']->hasPages())
            <div class="mt-4">
                {{ $payload['logs']->links() }}
            </div>
        @endif
    </section>
</div>
