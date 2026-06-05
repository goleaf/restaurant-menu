<?php

use App\Actions\Dashboard\BuildRestaurantDashboardAction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public bool $canAccessRestaurantDashboard = false;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $dashboard = null;

    public function mount(): void
    {
        $this->refreshDashboard();
    }

    public function refreshDashboard(): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            $this->canAccessRestaurantDashboard = false;
            $this->dashboard = null;

            return;
        }

        $payload = app(BuildRestaurantDashboardAction::class)->handle($user);

        $this->canAccessRestaurantDashboard = (bool) $payload['has_access'];
        $this->dashboard = is_array($payload['dashboard'] ?? null) ? $payload['dashboard'] : null;
    }
};
?>

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
                @if ($canAccessWaiterDashboard ?? false)
                    <flux:button icon="clipboard-document-list" variant="primary" :href="route('restaurant.waiter.dashboard')" wire:navigate>
                        {{ __('navigation.waiter') }}
                    </flux:button>
                @endif

                @if ($canAccessKitchenDashboard ?? false)
                    <flux:button icon="fire" :href="route('restaurant.kitchen.dashboard')" wire:navigate>
                        {{ __('navigation.kitchen') }}
                    </flux:button>
                @endif

                @if ($canAccessBarDashboard ?? false)
                    <flux:button icon="beaker" :href="route('restaurant.bar.dashboard')" wire:navigate>
                        {{ __('navigation.bar') }}
                    </flux:button>
                @endif

                @if ($canAccessAuditLog ?? false)
                    <flux:button icon="shield-check" :href="route('restaurant.audit-log.index')" wire:navigate>
                        {{ __('navigation.audit_log') }}
                    </flux:button>
                @endif

                @if ($canAccessDataExports ?? false)
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
                    <flux:button icon="arrow-path" size="sm" wire:click="refreshDashboard">
                        {{ __('Refresh') }}
                    </flux:button>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('reports.active_tables') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $dashboard['metrics']['active_tables_count'] }}</p>
                </div>

                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('reports.new_orders_to_waiter') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $dashboard['metrics']['new_orders_to_waiter_count'] }}</p>
                </div>

                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('reports.cooking_orders') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $dashboard['metrics']['cooking_orders_count'] }}</p>
                </div>

                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('reports.ready_positions') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $dashboard['metrics']['ready_positions_count'] }}</p>
                </div>

                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('reports.revenue.net_total') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">
                        {{ $dashboard['metrics']['orders_today_total'] ?? '—' }}
                    </p>
                    @unless ($dashboard['can_view_reports'])
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('reports.access_required') }}</p>
                    @endunless
                </div>
            </div>

            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                    <h3 class="text-base font-semibold text-zinc-950 dark:text-white">{{ __('reports.popular_items.title') }}</h3>
                    <div class="mt-3 divide-y divide-zinc-200 dark:divide-zinc-800">
                        @if ($dashboard['can_view_reports'])
                            @forelse ($dashboard['popular_items'] as $item)
                                <div wire:key="dashboard-popular-item-{{ $loop->index }}" class="flex items-center justify-between gap-4 py-3">
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
                            <div wire:key="dashboard-action-{{ $action['label'] }}" class="flex items-center justify-between gap-3 rounded-md border border-zinc-200 p-3 dark:border-zinc-800">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-zinc-950 dark:text-white">{{ __($action['label']) }}</p>
                                    <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ __($action['description']) }}</p>
                                </div>

                                @if ($action['is_available'] && $action['href'] !== null)
                                    <flux:button :icon="$action['icon']" size="sm" :href="$action['href']" wire:navigate>
                                        {{ __('Open') }}
                                    </flux:button>
                                @else
                                    <button type="button" disabled class="inline-flex h-8 items-center rounded-md border border-zinc-200 px-3 text-xs font-medium text-zinc-400 dark:border-zinc-800 dark:text-zinc-500">
                                        {{ __('No access') }}
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    @else
        <section class="grid gap-4 md:grid-cols-3">
            @foreach (['Setup', 'Guest flow', 'Waiter workflow'] as $label)
                <div wire:key="restaurant-dashboard-{{ $label }}" class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __($label) }}</p>
                    <p class="mt-3 text-lg font-semibold text-zinc-950 dark:text-white">{{ __('Available step by step') }}</p>
                </div>
            @endforeach
        </section>

        <section class="min-h-64 rounded-lg border border-dashed border-zinc-300 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-base font-semibold text-zinc-950 dark:text-white">{{ __('Current implementation area') }}</h2>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('Restaurant dashboard access appears when the user has branch-level operational or reporting access.') }}
            </p>
        </section>
    @endif
</div>
