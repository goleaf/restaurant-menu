<section data-layout="platform-dashboard" class="flex h-full w-full flex-1 flex-col gap-6">
    <header class="flex flex-col gap-2">
        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Platform workspace') }}</p>
        <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('Platform dashboard') }}</h1>
        <p class="max-w-2xl text-sm text-zinc-600 dark:text-zinc-300">
            {{ __('SaaS-level overview for organizations, brands, branches, and users.') }}
        </p>
    </header>

    <section class="grid gap-4 md:grid-cols-4">
        @foreach ($this->stats as $label => $value)
            <div wire:key="platform-stat-{{ $label }}" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __(str($label)->headline()->toString()) }}</p>
                <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    <section class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-700/60 dark:bg-amber-950/30">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <flux:heading size="lg">{{ __('Local backups') }}</flux:heading>
                <p class="mt-2 max-w-3xl text-sm text-amber-900 dark:text-amber-100">
                    {{ __('SQLite backup contains sensitive data: users, staff access, guest sessions, orders, payments, tokens, and audit records. Store downloaded files outside git and share them carefully.') }}
                </p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
                <flux:button icon="arrow-down-tray" variant="primary" :href="route('superadmin.backups.sqlite.download')">
                    {{ __('Download SQLite') }}
                </flux:button>

                <flux:button icon="archive-box" disabled>
                    {{ __('Media ZIP later') }}
                </flux:button>
            </div>
        </div>
    </section>

    <section class="grid gap-6 xl:grid-cols-2">
        <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                <flux:heading size="lg">{{ __('Organizations') }}</flux:heading>
            </div>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse ($this->organizations as $organization)
                    <div wire:key="platform-organization-{{ $organization->id }}" class="px-4 py-3">
                        <p class="font-medium text-zinc-950 dark:text-white">{{ $organization->name }}</p>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $organization->owner?->email ?? __('No owner') }}</p>
                    </div>
                @empty
                    <div class="px-4 py-6 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No organizations yet.') }}</div>
                @endforelse
            </div>

            @if ($this->organizations->hasPages())
                <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    {{ $this->organizations->links() }}
                </div>
            @endif
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                <flux:heading size="lg">{{ __('Brands') }}</flux:heading>
            </div>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse ($this->brands as $brand)
                    <div wire:key="platform-brand-{{ $brand->id }}" class="px-4 py-3">
                        <p class="font-medium text-zinc-950 dark:text-white">{{ $brand->name }}</p>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $brand->organization?->name ?? __('No organization') }}</p>
                    </div>
                @empty
                    <div class="px-4 py-6 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No brands yet.') }}</div>
                @endforelse
            </div>

            @if ($this->brands->hasPages())
                <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    {{ $this->brands->links() }}
                </div>
            @endif
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                <flux:heading size="lg">{{ __('Branches') }}</flux:heading>
            </div>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse ($this->branches as $branch)
                    <div wire:key="platform-branch-{{ $branch->id }}" class="px-4 py-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium text-zinc-950 dark:text-white">{{ $branch->name }}</p>

                            @if ($branch->is_active)
                                <flux:badge color="green">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge color="zinc">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </div>

                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $branch->organization?->name ?? __('No organization') }} / {{ $branch->brand?->name ?? __('No brand') }}
                        </p>

                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $branch->city }}, {{ $branch->country }}</p>
                    </div>
                @empty
                    <div class="px-4 py-6 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No branches yet.') }}</div>
                @endforelse
            </div>

            @if ($this->branches->hasPages())
                <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    {{ $this->branches->links() }}
                </div>
            @endif
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                <flux:heading size="lg">{{ __('Users') }}</flux:heading>
            </div>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse ($this->users as $user)
                    <div wire:key="platform-user-{{ $user->id }}" class="px-4 py-3">
                        <p class="font-medium text-zinc-950 dark:text-white">{{ $user->name }}</p>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $user->email }}</p>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $user->roles->pluck('name')->join(', ') ?: __('No roles') }}
                        </p>
                    </div>
                @empty
                    <div class="px-4 py-6 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No users yet.') }}</div>
                @endforelse
            </div>

            @if ($this->users->hasPages())
                <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    {{ $this->users->links() }}
                </div>
            @endif
        </div>
    </section>
</section>
