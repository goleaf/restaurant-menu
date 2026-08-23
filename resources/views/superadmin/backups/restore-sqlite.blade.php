<x-layouts::app :title="__('ui.superadmin.backup_restore.title')">
    <section class="mx-auto flex w-full max-w-3xl flex-col gap-6">
        <header class="flex flex-col gap-2">
            <p class="text-sm font-medium text-red-700 dark:text-red-300">{{ __('ui.superadmin.backup_restore.critical_operation') }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('ui.superadmin.backup_restore.title') }}</h1>
            <p class="max-w-2xl text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                {{ __('ui.superadmin.backup_restore.description') }}
            </p>
        </header>

        <x-ui.alert
            tone="danger"
            heading="ui.superadmin.backup_restore.warning_title"
        >
            {{ __('ui.superadmin.backup_restore.warning') }}
        </x-ui.alert>

        <form
            method="POST"
            action="{{ route('superadmin.backups.sqlite.restore.store') }}"
            enctype="multipart/form-data"
            class="grid gap-5 rounded-card border border-border-subtle bg-surface p-5 shadow-card sm:p-6"
        >
            @csrf

            <label for="sqlite-backup-file" class="grid gap-2 text-sm">
                <span class="font-semibold text-text-primary">{{ __('ui.superadmin.backup_restore.choose_file') }}</span>
                <span class="text-text-muted">
                    {{ __('ui.superadmin.backup_restore.file_help', ['size' => $maximumSizeMegabytes]) }}
                </span>
                <input
                    id="sqlite-backup-file"
                    name="backup"
                    type="file"
                    accept=".sqlite,.sqlite3,.db,application/vnd.sqlite3"
                    required
                    aria-describedby="sqlite-backup-file-help sqlite-backup-file-error"
                    class="min-h-11 rounded-control border border-border-subtle bg-surface px-3 py-2 text-sm text-text-primary file:me-3 file:rounded-control file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:font-semibold file:text-brand-800 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 dark:file:bg-brand-950 dark:file:text-brand-200"
                >
                <span id="sqlite-backup-file-help" class="text-xs text-text-muted">
                    {{ __('ui.superadmin.backup_restore.schema_help') }}
                </span>
            </label>

            @error('backup')
                <p id="sqlite-backup-file-error" role="alert" class="text-sm font-medium text-danger">
                    {{ $message }}
                </p>
            @enderror

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <flux:button :href="route('superadmin.dashboard')" icon="arrow-left" wire:navigate>
                    {{ __('ui.actions.cancel') }}
                </flux:button>
                <flux:button type="submit" variant="danger" icon="arrow-path">
                    {{ __('ui.superadmin.backup_restore.submit') }}
                </flux:button>
            </div>
        </form>
    </section>
</x-layouts::app>
