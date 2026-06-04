<?php

use App\Actions\Analytics\BuildBasicAnalyticsDashboardAction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public bool $canViewAnalytics = false;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $analytics = null;

    public function mount(): void
    {
        $this->refreshAnalytics();
    }

    public function refreshAnalytics(): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            $this->canViewAnalytics = false;
            $this->analytics = null;

            return;
        }

        $payload = app(BuildBasicAnalyticsDashboardAction::class)->handle($user);

        $this->canViewAnalytics = (bool) $payload['has_access'];
        $this->analytics = is_array($payload['analytics'] ?? null) ? $payload['analytics'] : null;
    }
};
?>

<div data-layout="restaurant-dashboard" class="flex h-full w-full flex-1 flex-col gap-5">
    <header class="flex flex-col gap-2">
        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Restaurant workspace</p>
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">Restaurant dashboard</h1>
                <p class="mt-1 max-w-2xl text-sm text-zinc-600 dark:text-zinc-300">
                    Operational workspace for branch setup, service points, guest drafts, and staff workflows.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                @if ($canAccessWaiterDashboard ?? false)
                    <flux:button icon="clipboard-document-list" variant="primary" :href="route('restaurant.waiter.dashboard')" wire:navigate>
                        {{ __('Waiter dashboard') }}
                    </flux:button>
                @endif

                @if ($canAccessKitchenDashboard ?? false)
                    <flux:button icon="chef-hat" :href="route('restaurant.kitchen.dashboard')" wire:navigate>
                        {{ __('Kitchen screen') }}
                    </flux:button>
                @endif

                @if ($canAccessBarDashboard ?? false)
                    <flux:button icon="glass-water" :href="route('restaurant.bar.dashboard')" wire:navigate>
                        {{ __('Bar screen') }}
                    </flux:button>
                @endif
            </div>
        </div>
    </header>

    @if ($canViewAnalytics && $analytics !== null)
        <section class="flex flex-col gap-4 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Basic analytics') }}</p>
                    <h2 class="mt-1 text-xl font-semibold text-zinc-950 dark:text-white">
                        {{ __('Today') }} · {{ $analytics['period_label'] }}
                    </h2>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                        {{ __('Branches') }}: {{ $analytics['branch_count'] }}
                    </p>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Cached at') }} {{ $analytics['cached_at'] }}
                    </p>
                    <flux:button icon="arrow-path" size="sm" wire:click="refreshAnalytics">
                        {{ __('Refresh') }}
                    </flux:button>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Orders today') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $analytics['orders_today_count'] }}</p>
                </div>

                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Amount today') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $analytics['orders_today_total'] }}</p>
                </div>

                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Average check') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $analytics['average_check'] }}</p>
                </div>

                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Active tables') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $analytics['active_tables_count'] }}</p>
                </div>

                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Closed sessions') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $analytics['closed_sessions_count'] }}</p>
                </div>

                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Cancelled orders') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $analytics['cancelled_orders_count'] }}</p>
                </div>
            </div>

            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                <h3 class="text-base font-semibold text-zinc-950 dark:text-white">{{ __('Popular dishes') }}</h3>
                <div class="mt-3 divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse ($analytics['popular_items'] as $item)
                        <div wire:key="analytics-popular-item-{{ $loop->index }}" class="flex items-center justify-between gap-4 py-3">
                            <div>
                                <p class="font-medium text-zinc-950 dark:text-white">{{ $item['item_name'] }}</p>
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Quantity') }}: {{ $item['quantity'] }}</p>
                            </div>
                            <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $item['total'] }}</p>
                        </div>
                    @empty
                        <p class="py-3 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No confirmed orders today yet.') }}</p>
                    @endforelse
                </div>
            </div>
        </section>
    @else
        <section class="grid gap-4 md:grid-cols-3">
            @foreach (['Setup', 'Guest flow', 'Waiter workflow'] as $label)
                <div wire:key="restaurant-dashboard-{{ $label }}" class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $label }}</p>
                    <p class="mt-3 text-lg font-semibold text-zinc-950 dark:text-white">Available step by step</p>
                </div>
            @endforeach
        </section>

        <section class="min-h-64 rounded-lg border border-dashed border-zinc-300 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-base font-semibold text-zinc-950 dark:text-white">Current implementation area</h2>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('Basic analytics are available to users with view_reports access. The waiter dashboard shows table flow, and the kitchen and bar screens show department tickets through Livewire polling.') }}
            </p>
        </section>
    @endif
</div>
