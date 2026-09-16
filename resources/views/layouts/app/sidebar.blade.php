<!DOCTYPE html>
<html lang="{{ __('meta.document_language') }}">
    <head>
        @include('partials.head')
        @stack('page-scripts')
    </head>
    <body
        class="min-h-screen overflow-x-clip bg-canvas text-text-primary antialiased"
        x-data="workspaceNavigation"
        x-on:keydown.window="handleShortcut($event)"
        data-workspace-navigation
    >
        <a href="#main-content" class="skip-link">{{ __('ui.accessibility.skip_to_content') }}</a>

        <flux:sidebar sticky collapsible class="border-e border-border-subtle bg-surface-muted">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" class="min-h-touch" href="{{ route('dashboard') }}" wire:navigate />
            </flux:sidebar.header>

            <flux:sidebar.nav :aria-label="__('navigation.workspaces')">
                <p class="px-3 py-2 text-sm font-medium text-text-muted in-data-flux-sidebar-collapsed-desktop:hidden">{{ __('navigation.workspaces') }}</p>
                @foreach ($navigationItems as $item)
                    @if ($item['group'] === 'workspace')
                        @if ($serviceNavigation !== [] && $item['key'] === $serviceNavigation[0]['key'])
                            <p class="px-3 py-2 text-sm font-medium text-text-muted in-data-flux-sidebar-collapsed-desktop:hidden">{{ __('workspace.service') }}</p>
                        @endif
                        <flux:sidebar.item
                            class="workspace-nav-item min-h-touch"
                            :icon="$item['icon']"
                            :href="$item['href']"
                            :current="$item['current']"
                            :aria-current="$item['current'] ? 'page' : null"
                            :aria-label="$item['label']"
                            :tooltip="$item['label']"
                            :data-navigation-key="$item['key']"
                            wire:navigate
                        >{{ $item['label'] }}</flux:sidebar.item>
                    @endif
                @endforeach
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav :aria-label="__('workspace.administration')">
                @foreach ($navigationItems as $item)
                    @if ($item['group'] === 'administration')
                        <flux:sidebar.item
                            class="workspace-nav-item min-h-touch"
                            :icon="$item['icon']"
                            :href="$item['href']"
                            :current="$item['current']"
                            :aria-current="$item['current'] ? 'page' : null"
                            :aria-label="$item['label']"
                            :tooltip="$item['label']"
                            :data-navigation-key="$item['key']"
                            wire:navigate
                        >{{ $item['label'] }}</flux:sidebar.item>
                    @endif
                @endforeach
            </flux:sidebar.nav>
        </flux:sidebar>

        <flux:header class="sticky top-0 z-navigation flex! min-w-0 flex-wrap gap-2 border-b border-border-subtle bg-surface-raised px-3! py-2! sm:px-6!">
            <flux:sidebar.toggle
                class="min-h-touch min-w-touch"
                icon="bars-2"
                :aria-label="__('navigation.toggle_sidebar')"
                :tooltip="__('navigation.toggle_sidebar')"
            />
            @if ($workspace !== null)
                <livewire:workspace.restaurant-switcher :branch-id="$workspace->branchId" :destination="$workspace->destination" :mode="$workspace->mode" :key="'workspace-'.$workspace->actorId.'-'.$workspace->mode.'-'.$workspace->branchId.'-'.$workspace->destination" />
            @endif
            <flux:button
                variant="ghost"
                icon="magnifying-glass"
                class="min-h-touch min-w-touch"
                :aria-label="__('navigation.search')"
                x-on:click="openSearch()"
                data-navigation-search-trigger
            >
                <span class="hidden sm:inline">{{ __('navigation.search') }}</span>
            </flux:button>
            <flux:spacer />
            <livewire:notifications.unread-count />
            @if ($authenticatedUser !== null)
                <x-account-menu
                    :name="$authenticatedUser['name']"
                    :email="$authenticatedUser['email']"
                    :initials="$authenticatedUser['initials']"
                />
            @endif
        </flux:header>

        <p x-show="blocked" x-cloak role="status" class="px-4 py-2">{{ __('workspace.operation_pending') }}</p>
        @if ($workspaceFallback)
            <flux:callout role="status">{{ __('workspace.section_unavailable') }}</flux:callout>
        @endif
        @if ($hallNavigation !== [])
            <nav class="flex flex-wrap gap-2 px-4 py-2" aria-label="{{ __('workspace.halls') }}">
                @foreach ($hallNavigation as $item)
                    <flux:button :href="$item['href']" :aria-current="$item['current'] ? 'page' : null" :variant="$item['current'] ? 'primary' : 'ghost'" wire:navigate>{{ $item['label'] }}</flux:button>
                @endforeach
            </nav>
        @endif
        {{ $slot }}

        <flux:modal name="workspace-navigation" :closable="false" class="w-full min-w-0! max-w-lg space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <flux:heading id="workspace-navigation-heading" level="2" size="lg" x-bind="dialogLabel">{{ __('navigation.search') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('navigation.search_description') }}</flux:text>
                </div>
                <flux:modal.close>
                    <flux:button variant="ghost" icon="x-mark" class="min-h-touch min-w-touch" :aria-label="__('ui.accessibility.close_dialog')" />
                </flux:modal.close>
            </div>
            <flux:input
                data-workspace-search-input
                x-model="query"
                type="search"
                icon="magnifying-glass"
                class:input="min-h-touch"
                :label="__('navigation.search_placeholder')"
                autocomplete="off"
                autofocus
                x-on:keydown.escape.stop.prevent="$flux.modal('workspace-navigation').close()"
                x-on:keydown="handleSearchKeydown($event)"
            />
            <nav aria-label="{{ __('navigation.workspaces') }}" class="max-h-[60dvh] overflow-y-auto" x-on:keydown="handleResultsKeydown($event)">
                <ul class="space-y-1">
                    @foreach ($navigationItems as $item)
                        <li x-show="matches($el.dataset.searchLabel)" data-search-label="{{ $item['search'] }}">
                            <flux:button
                                :href="$item['href']"
                                :icon="$item['icon']"
                                :aria-current="$item['current'] ? 'page' : null"
                                :data-navigation-search-key="$item['key']"
                                variant="ghost"
                                class="h-auto! min-h-touch w-full justify-start! whitespace-normal! py-2 text-start"
                                wire:navigate
                            >{{ $item['label'] }}</flux:button>
                        </li>
                    @endforeach
                </ul>
                <flux:text x-show="!hasMatches" x-cloak role="status" class="py-4">{{ __('navigation.search_empty') }}</flux:text>
            </nav>
        </flux:modal>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @livewireScriptConfig
        @fluxScripts
    </body>
</html>
