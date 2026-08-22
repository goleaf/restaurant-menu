<div data-layout="restaurant-dashboard" class="flex h-full w-full flex-1 flex-col gap-5">
    <header class="flex flex-col gap-2">
        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('layout.restaurant_workspace') }}</p>
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('navigation.restaurant_dashboard') }}</h1>
                <p class="mt-1 max-w-2xl text-sm text-zinc-600 dark:text-zinc-300">
                    {{ __('ui.headers.restaurant_dashboard.description') }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                @if ($canAccessWaiterDashboard)
                    <flux:button icon="clipboard-document-list" variant="primary" :href="route('restaurant.waiter.dashboard')" wire:navigate>
                        {{ __('navigation.waiter') }}
                    </flux:button>
                @endif

                @if ($canAccessKitchenDashboard)
                    <flux:button icon="fire" :href="route('restaurant.kitchen.dashboard')" wire:navigate>
                        {{ __('navigation.kitchen') }}
                    </flux:button>
                @endif

                @if ($canAccessBarDashboard)
                    <flux:button icon="beaker" :href="route('restaurant.bar.dashboard')" wire:navigate>
                        {{ __('navigation.bar') }}
                    </flux:button>
                @endif

                @if ($canAccessAuditLog)
                    <flux:button icon="shield-check" :href="route('restaurant.audit-log.index')" wire:navigate>
                        {{ __('navigation.audit_log') }}
                    </flux:button>
                @endif

                @if ($canAccessDataExports)
                    <flux:button icon="arrow-down-tray" :href="route('restaurant.exports.index')" wire:navigate>
                        {{ __('navigation.exports') }}
                    </flux:button>
                @endif
            </div>
        </div>
    </header>

    @if ($canAccessRestaurantDashboard && $dashboard !== null)
        <section id="reports" class="flex flex-col gap-4 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('reports.title') }}</p>
                    <h2 class="mt-1 text-xl font-semibold text-zinc-950 dark:text-white">
                        {{ __('reports.filters.today') }} · {{ $dashboard['period_label'] }}
                    </h2>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                        {{ __('reports.filters.branch') }}: {{ $dashboard['branch_count'] }}
                    </p>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('reports.cached_at') }} {{ $dashboard['cached_at'] }}
                    </p>
                    <flux:button icon="arrow-path" size="sm" wire:click="refreshDashboard" wire:loading.attr="disabled" wire:target="refreshDashboard">
                        {{ __('ui.audit_logs.index.refresh') }}
                    </flux:button>
                </div>
            </div>

            <x-ui.metric-strip :items="[
                ['label' => 'reports.active_tables', 'value' => $dashboard['metrics']['active_tables_count']],
                ['label' => 'reports.new_orders_to_waiter', 'value' => $dashboard['metrics']['new_orders_to_waiter_count'], 'tone' => $dashboard['metrics']['new_orders_to_waiter_count'] > 0 ? 'danger' : 'neutral'],
                ['label' => 'reports.cooking_orders', 'value' => $dashboard['metrics']['cooking_orders_count'], 'tone' => $dashboard['metrics']['cooking_orders_count'] > 0 ? 'warning' : 'neutral'],
                ['label' => 'reports.ready_positions', 'value' => $dashboard['metrics']['ready_positions_count'], 'tone' => $dashboard['metrics']['ready_positions_count'] > 0 ? 'success' : 'neutral'],
                ['label' => 'reports.revenue.net_total', 'value' => $dashboard['metrics']['orders_today_total'] ?? '—', 'description' => $dashboard['can_view_reports'] ? null : 'reports.access_required'],
            ]" />

            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                    <h3 class="text-base font-semibold text-zinc-950 dark:text-white">{{ __('reports.popular_items.title') }}</h3>
                    <div class="mt-3 divide-y divide-zinc-200 dark:divide-zinc-800">
                        @if ($dashboard['can_view_reports'])
                            @forelse ($dashboard['popular_items'] as $item)
                                <div wire:key="dashboard-popular-item-{{ $item['item_name'] }}" class="flex items-center justify-between gap-4 py-3">
                                    <div>
                                        <p class="font-medium text-zinc-950 dark:text-white">{{ $item['item_name'] }}</p>
                                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('reports.popular_items.quantity_sold') }}: {{ $item['quantity'] }}</p>
                                    </div>
                                    <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $item['total'] }}</p>
                                </div>
                            @empty
                                <p class="py-3 text-sm text-zinc-500 dark:text-zinc-400">{{ __('reports.empty.no_data') }}</p>
                            @endforelse
                        @else
                            <p class="py-3 text-sm text-zinc-500 dark:text-zinc-400">{{ __('reports.access_required_popular_items') }}</p>
                        @endif
                    </div>
                </div>

                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                    <h3 class="text-base font-semibold text-zinc-950 dark:text-white">{{ __('reports.quick_actions.title') }}</h3>
                    <div class="mt-3 grid gap-2">
                        @foreach ($dashboard['quick_actions'] as $action)
                            @if ($action['is_available'] && $action['href'] !== null)
                                <a
                                    wire:key="dashboard-action-{{ $action['label'] }}"
                                    data-quick-action-link
                                    href="{{ $action['href'] }}"
                                    class="group flex min-h-touch items-center gap-3 rounded-control border border-zinc-200 p-3 transition hover:border-brand-300 hover:bg-brand-50 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-focus dark:border-zinc-800 dark:hover:border-brand-700 dark:hover:bg-brand-950"
                                    wire:navigate
                                >
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-control bg-zinc-100 text-zinc-600 group-hover:bg-brand-100 group-hover:text-brand-800 dark:bg-zinc-800 dark:text-zinc-300 dark:group-hover:bg-brand-900 dark:group-hover:text-brand-100" aria-hidden="true">
                                        <flux:icon :name="$action['icon']" class="size-4" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-medium text-zinc-950 dark:text-white">{{ __($action['label']) }}</span>
                                        <span class="block text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ __($action['description']) }}</span>
                                    </span>
                                    <flux:icon name="chevron-right" class="size-4 shrink-0 text-zinc-400 transition-transform group-hover:translate-x-0.5 motion-reduce:transition-none rtl:rotate-180" aria-hidden="true" />
                                </a>
                            @else
                                <div wire:key="dashboard-action-{{ $action['label'] }}" class="flex items-center justify-between gap-3 rounded-control border border-zinc-200 p-3 opacity-70 dark:border-zinc-800">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-zinc-950 dark:text-white">{{ __($action['label']) }}</p>
                                        <p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ __($action['description']) }}</p>
                                    </div>
                                    <span class="inline-flex min-h-8 items-center rounded-control border border-zinc-200 px-3 text-xs font-medium text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
                                        {{ __('ui.pages.restaurant.dashboard.no_access') }}
                                    </span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    @else
        <section class="grid gap-4 md:grid-cols-3">
            @foreach ($emptyStateFeatureKeys as $featureKey)
                <div wire:key="restaurant-dashboard-{{ $featureKey }}" class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __($featureKey) }}</p>
                    <p class="mt-3 text-lg font-semibold text-zinc-950 dark:text-white">{{ __('ui.pages.restaurant.dashboard.available_step_by_step') }}</p>
                </div>
            @endforeach
        </section>

        <section class="min-h-64 rounded-lg border border-dashed border-zinc-300 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-base font-semibold text-zinc-950 dark:text-white">{{ __('ui.pages.restaurant.dashboard.current_implementation_area') }}</h2>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('ui.pages.restaurant.dashboard.restaurant_dashboard_access_appears_when_the') }}
            </p>
        </section>
    @endif
</div>
