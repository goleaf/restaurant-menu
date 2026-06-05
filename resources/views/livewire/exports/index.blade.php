<div data-layout="data-exports" class="flex h-full w-full flex-1 flex-col gap-5">
    <header class="flex flex-col gap-2">
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('layout.restaurant_workspace') }}</p>
                <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('reports.exports.title') }}</h1>
                <p class="mt-1 max-w-2xl text-sm text-zinc-600 dark:text-zinc-300">
                    {{ __('reports.exports.description') }}
                </p>
            </div>

            <flux:button icon="layout-grid" :href="route('restaurant.dashboard')" wire:navigate>
                {{ __('navigation.restaurant_dashboard') }}
            </flux:button>
        </div>
    </header>

    <section class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-700/60 dark:bg-amber-950/30">
        <p class="text-sm font-medium text-amber-950 dark:text-amber-100">{{ __('reports.exports.csv_only') }}</p>
        <p class="mt-1 text-sm text-amber-900 dark:text-amber-100">
            {{ __('reports.exports.warning') }}
        </p>
    </section>

    <section class="grid gap-4 xl:grid-cols-2">
        @forelse ($exports['branches'] as $branch)
            <article wire:key="export-branch-{{ $branch['id'] }}" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-col gap-1">
                    <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">{{ $branch['name'] }}</h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ $branch['organization_name'] }} / {{ $branch['brand_name'] }}
                    </p>
                    @if ($branch['location'] !== '')
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $branch['location'] }}</p>
                    @endif
                </div>

                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    @foreach ($exports['export_types'] as $type)
                        <flux:button
                            wire:key="export-branch-{{ $branch['id'] }}-{{ $type['value'] }}"
                            icon="arrow-down-tray"
                            :href="$branch['downloads'][$type['value']]"
                        >
                            {{ __('reports.actions.export_type_csv', ['type' => $type['label']]) }}
                        </flux:button>
                    @endforeach
                </div>
            </article>
        @empty
            <div class="rounded-lg border border-zinc-200 bg-white p-6 text-sm text-zinc-500 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400">
                {{ __('reports.empty.no_data') }}
            </div>
        @endforelse
    </section>
</div>
