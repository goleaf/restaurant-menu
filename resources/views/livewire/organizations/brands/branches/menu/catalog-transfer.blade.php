<div class="grid min-w-0 gap-5" data-section="catalog-transfer" x-data x-on:catalog-transfer-feedback.window="$nextTick(() => $refs.feedback?.focus())">
    <header class="grid gap-1">
        <flux:heading size="lg">{{ __('menu.csv.title') }}</flux:heading>
        <p class="max-w-3xl text-sm text-text-muted">{{ __('menu.csv.intro') }}</p>
    </header>

    <div wire:offline role="status" class="rounded-control border border-warning-border bg-warning-surface p-3 text-sm text-warning-foreground">{{ __('menu.csv.offline') }}</div>

    <section class="grid min-w-0 gap-4 rounded-card border border-border bg-surface p-4 sm:p-5">
        <div class="grid min-w-0 gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
            <flux:select wire:model.live="form.menuId" :label="__('menu.guest.title')" wire:loading.attr="disabled">
                <flux:select.option value="">{{ __('menu.csv.choose_menu') }}</flux:select.option>
                @forelse ($menus as $menu)
                    <flux:select.option value="{{ $menu['id'] }}">{{ $menu['name'] }}</flux:select.option>
                @empty
                @endforelse
            </flux:select>
            <flux:button wire:click="downloadSample" icon="document-arrow-down" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('menu.csv.sample') }}</flux:button>
        </div>
        <p class="text-sm text-text-muted">{{ __('menu.csv.rules') }}</p>
        <details class="min-w-0 rounded-control border border-border p-3">
            <summary class="touch-target cursor-pointer text-sm font-medium text-text">{{ __('menu.csv.category_reference') }}</summary>
            <p class="mt-2 text-xs text-text-muted">{{ __('menu.csv.reference_help') }}</p>
            <dl class="mt-3 grid gap-x-5 gap-y-2 sm:grid-cols-2">
                @forelse ($categories as $category)
                    <div class="flex min-w-0 gap-3 text-sm"><dt class="shrink-0 font-mono tabular-nums text-text-muted">{{ $category['id'] }}</dt><dd class="break-words text-text">{{ $category['name'] }}</dd></div>
                @empty
                    <p class="text-sm text-text-muted">{{ __('menu.csv.no_categories') }}</p>
                @endforelse
            </dl>
        </details>
    </section>

    <div x-ref="feedback" tabindex="-1" class="grid gap-3 focus:outline-none">
        @error('form.file')
            <p role="alert" class="rounded-control border border-danger-border bg-danger-surface p-3 text-sm text-danger-foreground">{{ $message }}</p>
        @enderror
        @if ($success !== '')
            <p role="status" class="rounded-control border border-success-border bg-success-surface p-3 text-sm text-success-foreground">{{ $success }}</p>
        @endif
        @if ($rowErrors !== [])
            <section role="alert" class="grid gap-2 rounded-card border border-danger-border bg-danger-surface p-4 text-danger-foreground">
                <h3 class="font-semibold">{{ __('menu.csv.fix_rows') }}</h3>
                <ul class="max-h-72 space-y-2 overflow-y-auto text-sm">
                    @forelse ($rowErrors as $error)
                        <li>{{ __('menu.csv.row_error', ['row' => $error['line'], 'message' => $error['message']]) }}</li>
                    @empty
                    @endforelse
                </ul>
            </section>
        @endif
    </div>

    <form wire:submit="previewImport" novalidate class="grid gap-4 rounded-card border border-border bg-surface p-4 sm:p-5"
        x-data="{ uploading: false, progress: 0 }"
        x-on:change="$dispatch('menu-workspace-dirty', { key: 'catalog-transfer', dirty: ($event.target.files?.length ?? 0) > 0 })"
        x-on:livewire-upload-start="uploading = true; progress = 0"
        x-on:livewire-upload-finish="uploading = false"
        x-on:livewire-upload-error="uploading = false"
        x-on:livewire-upload-progress="progress = $event.detail.progress">
        <flux:heading>{{ __('menu.csv.import_title') }}</flux:heading>
        <flux:input type="file" wire:model="form.file" accept=".csv,text/csv,text/plain" :label="__('menu.csv.file')" :description="__('menu.csv.file_help')" />
        <progress x-cloak x-show="uploading" :value="progress" max="100" class="h-2 w-full accent-accent" aria-label="{{ __('uploads.editor.uploading') }}"></progress>
        <div class="flex flex-wrap gap-2">
            <flux:button type="submit" variant="primary" icon="eye" wire:loading.attr="disabled" wire:offline.attr="disabled" x-bind:disabled="uploading">{{ __('menu.csv.preview') }}</flux:button>
            <flux:button type="button" wire:click="discardImport" wire:loading.attr="disabled" x-bind:disabled="uploading">{{ __('menu.csv.discard') }}</flux:button>
        </div>
    </form>

    @if ($previewRows !== [])
        <section class="grid min-w-0 gap-4 rounded-card border border-border bg-surface p-4 sm:p-5" aria-label="{{ __('menu.csv.preview_title') }}">
            <div class="grid gap-1">
                <flux:heading>{{ __('menu.csv.preview_title') }}</flux:heading>
                <p class="text-sm text-text-muted">{{ __('menu.csv.summary', ['create' => $createCount, 'update' => $updateCount]) }}</p>
            </div>
            <ul class="max-h-96 divide-y divide-border overflow-y-auto rounded-control border border-border">
                @forelse ($previewRows as $row)
                    <li wire:key="csv-row-{{ $row['line'] }}" class="grid min-w-0 gap-1 p-3 sm:grid-cols-[5rem_minmax(0,1fr)_auto] sm:items-center sm:gap-3">
                        <span class="text-xs font-medium text-text-muted">{{ __($row['operation']) }}</span>
                        <details class="min-w-0">
                            <summary class="touch-target cursor-pointer break-words text-sm font-medium text-text">{{ $row['name'] }}</summary>
                            <p class="mt-2 text-xs text-text-muted">{{ __('menu.csv.fields.category_id') }}: {{ $row['category_id'] }}</p>
                            <dl class="mt-3 grid min-w-0 gap-3">
                                @forelse ($row['translations'] as $locale => $translation)
                                    <div class="min-w-0">
                                        <dt class="text-xs font-semibold text-text-muted">{{ $locale }}</dt>
                                        <dd class="break-words text-sm text-text">{{ $translation['name'] }}</dd>
                                        <dd class="whitespace-pre-line break-words text-sm text-text-muted">{{ $translation['description'] }}</dd>
                                    </div>
                                @empty
                                @endforelse
                            </dl>
                        </details>
                        <span class="font-mono text-sm tabular-nums text-text">{{ $row['price'] }}</span>
                    </li>
                @empty
                @endforelse
            </ul>
            <p class="text-xs text-text-muted">{{ __('menu.csv.apply_help') }}</p>
            <flux:button type="button" wire:click="applyImport" variant="primary" icon="check" :disabled="! $canApply" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('menu.csv.apply') }}</flux:button>
        </section>
    @endif

    <section class="grid gap-3 rounded-card border border-border bg-surface-muted p-4 sm:p-5">
        <flux:heading>{{ __('menu.csv.export_title') }}</flux:heading>
        <p class="text-sm text-text-muted">{{ __('menu.csv.export_help') }}</p>
        <div class="flex flex-wrap gap-2">
            <flux:button wire:click="exportCatalog" icon="arrow-down-tray" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('menu.csv.export_first') }}</flux:button>
            @if ($exportHasMore)
                <flux:button wire:click="exportCatalog(true)" icon="arrow-right" wire:loading.attr="disabled" wire:offline.attr="disabled">{{ __('menu.csv.export_next') }}</flux:button>
            @endif
        </div>
        @if ($exportSummary !== '')
            <p role="status" class="text-sm text-text-muted">{{ $exportSummary }}</p>
        @endif
    </section>
</div>
